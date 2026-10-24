#!/usr/bin/env python3
"""Preserve execution evidence with ordinary Git objects, independently of Actions artifacts.

Python standard library only. Capture and verify work offline. Publication is an
explicit operation, restricted to an append-only branch of the source repository.
"""
from __future__ import annotations

import argparse
import base64
import glob
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys
import tempfile
import urllib.error
import urllib.request
import zipfile

SCHEMA = "IntelligenceEvidence/1"
REPO = re.compile(r"^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$")
IDENT = re.compile(r"^[A-Za-z0-9][A-Za-z0-9_.-]{0,159}$")
SHA = re.compile(r"^[a-f0-9]{40}$")
SECRET = re.compile(rb"(?:-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,})")
MAX_FILE = 32 * 1024 * 1024
MAX_PAYLOAD = 256 * 1024 * 1024


def canonical(value):
    return (json.dumps(value, sort_keys=True, ensure_ascii=False, indent=2) + "\n").encode("utf-8")


def digest(data):
    return hashlib.sha256(data).hexdigest()


def safe_path(value):
    p = PurePosixPath(value)
    if not value or "\\" in value or p.is_absolute() or any(x in ("", ".", "..") for x in value.split("/")):
        raise ValueError("Unsafe relative path")
    return p


def confined(root, relative):
    p = root.joinpath(*safe_path(relative).parts)
    if p.is_symlink() or any(x.is_symlink() for x in p.parents if x != root.parent):
        raise ValueError("Symlinks are not evidence inputs")
    p.resolve().relative_to(root.resolve())
    return p


def immutable_write(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.exists():
        if path.is_symlink() or path.read_bytes() != data:
            raise ValueError("Append-only conflict: " + str(path))
        return
    with path.open("xb") as f:
        f.write(data)


def inspect_zip(data):
    members, seen, total = [], set(), 0
    with zipfile.ZipFile(io.BytesIO(data)) as z:
        for item in z.infolist():
            if item.is_dir():
                continue
            safe_path(item.filename)
            if item.filename in seen or (item.external_attr >> 16) & 0o170000 == 0o120000:
                raise ValueError("Duplicate or symlink ZIP member")
            seen.add(item.filename)
            total += item.file_size
            if item.file_size > MAX_FILE or total > MAX_PAYLOAD:
                raise ValueError("Evidence exceeds reviewed size limits; retain original locally")
            body = z.read(item)  # also checks the ZIP CRC
            if SECRET.search(body):
                raise ValueError("Credential detected; original retained locally, publication blocked")
            members.append({"path": item.filename, "bytes": len(body), "sha256": digest(body)})
    if not members:
        raise ValueError("Empty evidence payload")
    return sorted(members, key=lambda m: m["path"])


def save_record(store, record, payload):
    members = inspect_zip(payload)
    payload_hash = digest(payload)
    object_path = f"objects/sha256/{payload_hash[:2]}/{payload_hash}.zip"
    record = dict(record, schema=SCHEMA, payload={"path": object_path, "sha256": payload_hash,
                  "bytes": len(payload), "members": members})
    record["record_sha256"] = digest(canonical(record))
    repo = record["source"]["repository"]
    key = record["id"]
    if not REPO.fullmatch(repo) or not IDENT.fullmatch(key):
        raise ValueError("Invalid repository or evidence ID")
    record_path = f"records/{repo}/{key}.json"
    immutable_write(confined(store, object_path), payload)
    immutable_write(confined(store, record_path), canonical(record))
    return record_path


def verify(store):
    records = []
    for p in sorted((store / "records").rglob("*.json")):
        if p.is_symlink():
            raise ValueError("Symlink record")
        data = json.loads(p.read_text(encoding="utf-8"))
        claimed = data.pop("record_sha256", None)
        if data.get("schema") != SCHEMA or claimed != digest(canonical(data)):
            raise ValueError("Invalid record digest: " + str(p))
        meta = data["payload"]
        raw = payload_bytes(store, meta)
        if len(raw) != meta["bytes"] or digest(raw) != meta["sha256"] or inspect_zip(raw) != meta["members"]:
            raise ValueError("Invalid payload: " + str(p))
        data["record_sha256"] = claimed
        records.append(data)
    if not records:
        raise ValueError("No verified evidence records")
    return records


def payload_bytes(store, meta):
    """Read an original ZIP directly or from a byte-preserving storage pack."""
    pack = meta.get("pack")
    if not pack:
        return confined(store, meta["path"]).read_bytes()
    body = confined(store, pack["path"]).read_bytes()
    if len(body) != pack["bytes"] or digest(body) != pack["sha256"]:
        raise ValueError("Invalid evidence pack")
    safe_path(pack["entry"])
    with zipfile.ZipFile(io.BytesIO(body)) as z:
        names = z.namelist()
        if len(names) != len(set(names)):
            raise ValueError("Duplicate pack entries")
        item = z.getinfo(pack["entry"])
        if item.file_size > MAX_PAYLOAD:
            raise ValueError("Packed payload too large")
        return z.read(item)


def load_config(path):
    data = json.loads(path.read_text(encoding="utf-8"))
    if data.get("schema") != "ProjectIntelligence/1" or not REPO.fullmatch(data.get("repository", "")):
        raise ValueError("Invalid project intelligence configuration")
    safe_path(data["store"])
    branch = data.get("archive_branch", "intelligence/evidence")
    if not re.fullmatch(r"intelligence/[A-Za-z0-9_/-]+", branch) or ".." in branch:
        raise ValueError("Archive branch must be under intelligence/")
    engine_hash = data.get("engine", {}).get("sha256")
    if engine_hash and digest(Path(__file__).read_bytes()) != engine_hash:
        raise ValueError("Evidence engine differs from the reviewed configuration hash")
    return data


def capture(root, config, inputs, run_id, status, commit=None):
    if not IDENT.fullmatch(run_id):
        raise ValueError("Invalid run ID")
    commit = commit or os.environ.get("GITHUB_SHA") or subprocess.check_output(
        ["git", "-C", str(root), "rev-parse", "HEAD"], text=True).strip()
    if not SHA.fullmatch(commit):
        raise ValueError("Full source commit SHA required")
    temp = Path(os.environ.get("RUNNER_TEMP", str(root / ".intelligence"))).resolve()
    bodies = {}
    for pattern in inputs:
        expanded = Path(os.path.expandvars(pattern))
        matches = glob.glob(str(expanded if expanded.is_absolute() else root / expanded), recursive=True)
        if not matches:
            raise ValueError("Evidence input missing: " + pattern)
        for value in matches:
            entry = Path(value)
            if not entry.is_absolute():
                entry = root / entry
            paths = sorted(entry.rglob("*")) if entry.is_dir() else [entry]
            for path in paths:
                if path.is_symlink() or any(p.is_symlink() for p in path.parents):
                    raise ValueError("Symlink evidence input")
                if not path.is_file():
                    continue
                resolved = path.resolve()
                try:
                    rel = resolved.relative_to(root)
                    label = "repository/" + rel.as_posix()
                except ValueError:
                    label = "runner/" + resolved.relative_to(temp).as_posix()
                if any(p in (".git", "node_modules", "secrets") for p in resolved.parts) or path.name.startswith(".env") or "/public/dist/" in resolved.as_posix():
                    raise ValueError("Forbidden evidence input")
                body = path.read_bytes()
                if len(body) > MAX_FILE or sum(map(len, bodies.values())) + len(body) > MAX_PAYLOAD:
                    raise ValueError("Evidence too large; keep original and choose durable external storage")
                if label in bodies and bodies[label] != body:
                    raise ValueError("Evidence label collision")
                bodies[label] = body
    if not bodies:
        raise ValueError("No evidence inputs")
    output = io.BytesIO()
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as z:
        for label, body in sorted(bodies.items()):
            item = zipfile.ZipInfo(label, (1980, 1, 1, 0, 0, 0))
            item.compress_type = zipfile.ZIP_DEFLATED
            z.writestr(item, body)
    record = {"id": run_id, "kind": "execution", "status": status,
              "sensitivity": config.get("sensitivity", "uso_interno"),
              "source": {"repository": config["repository"], "commit": commit,
                         "run_id": os.environ.get("GITHUB_RUN_ID"), "attempt": os.environ.get("GITHUB_RUN_ATTEMPT"),
                         "job": os.environ.get("GITHUB_JOB"), "event": os.environ.get("GITHUB_EVENT_NAME", "local")},
              "interpretation": "Historical execution evidence; preservation does not certify current software."}
    head = subprocess.run(["git", "-C", str(root), "rev-parse", "HEAD"], capture_output=True, text=True)
    state = subprocess.run(["git", "-C", str(root), "status", "--porcelain"], capture_output=True, text=True)
    diff = subprocess.run(["git", "-C", str(root), "diff", "--binary", "HEAD"], capture_output=True)
    record["source"].update(
        commit_role="checkout_head" if head.returncode == 0 and head.stdout.strip() == commit else "declared_reference",
        checkout_head=head.stdout.strip() if head.returncode == 0 else None,
        working_tree_dirty=bool(state.stdout.strip()) if state.returncode == 0 else None,
        tracked_patch_sha256=digest(diff.stdout) if diff.returncode == 0 else None,
        engine_sha256=digest(Path(__file__).read_bytes()),
        configuration_sha256=digest(canonical(config)),
    )
    untracked = subprocess.run(["git", "-C", str(root), "ls-files", "--others", "--exclude-standard", "-z"], capture_output=True)
    untracked_hashes = []
    if untracked.returncode == 0:
        for raw_path in untracked.stdout.decode("utf-8").split("\0"):
            if not raw_path:
                continue
            path = root / raw_path
            if path.is_file() and not path.is_symlink():
                untracked_hashes.append({"path": raw_path, "sha256": digest(path.read_bytes())})
    record["source"]["untracked_sources_sha256"] = digest(canonical(untracked_hashes)) if untracked.returncode == 0 else None
    return save_record(root / config["store"], record, output.getvalue())


class GitHub:
    def __init__(self, token):
        if not token:
            raise ValueError("GITHUB_TOKEN or GH_TOKEN required only for publication")
        self.token = token

    def request(self, method, path, payload=None, missing=False):
        req = urllib.request.Request("https://api.github.com" + path,
            data=canonical(payload) if payload is not None else None, method=method,
            headers={"Authorization": "Bearer " + self.token, "Accept": "application/vnd.github+json",
                     "Content-Type": "application/json", "User-Agent": "project-intelligence/1"})
        try:
            with urllib.request.urlopen(req, timeout=60) as response:
                return json.load(response)
        except urllib.error.HTTPError as exc:
            if missing and exc.code == 404:
                return None
            raise RuntimeError(f"GitHub {method} failed with HTTP {exc.code}") from None


def publish(root, config, api=None):
    store = root / config["store"]
    records = verify(store)
    repo = config["repository"]
    if any(r["source"]["repository"] != repo for r in records):
        raise ValueError("Cross-project publication requires a separate reviewed migration")
    api = api or GitHub(os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN"))
    prefix = "/repos/" + repo
    info = api.request("GET", prefix)
    if not info.get("private") and any(r.get("sensitivity") != "publica" for r in records):
        raise ValueError("Internal evidence cannot be published to a public repository")
    branch = config.get("archive_branch", "intelligence/evidence")
    if branch == info["default_branch"]:
        raise ValueError("Evidence publication cannot modify the default branch")
    files = {}
    for r in records:
        rel = f"records/{repo}/{r['id']}.json"
        files[config["store"] + "/" + rel] = confined(store, rel).read_bytes()
        rel = r["payload"].get("pack", {}).get("path", r["payload"]["path"])
        files[config["store"] + "/" + rel] = confined(store, rel).read_bytes()
    for attempt in range(3):
        ref = api.request("GET", prefix + "/git/ref/heads/" + branch, missing=True)
        exists = ref is not None
        head = ref["object"]["sha"] if exists else api.request("GET", prefix + "/git/ref/heads/" + info["default_branch"])["object"]["sha"]
        commit = api.request("GET", prefix + "/git/commits/" + head)
        tree = api.request("GET", prefix + "/git/trees/" + commit["tree"]["sha"] + "?recursive=1")
        if tree.get("truncated"):
            raise ValueError("Remote tree truncated; no publication attempted")
        remote = {f["path"]: f for f in tree["tree"]}
        entries = []
        for path, body in sorted(files.items()):
            expected = hashlib.sha1(b"blob " + str(len(body)).encode() + b"\0" + body).hexdigest()
            if path in remote:
                if remote[path]["sha"] != expected or remote[path].get("mode") != "100644":
                    raise ValueError("Append-only remote conflict: " + path)
                continue
            blob = api.request("POST", prefix + "/git/blobs", {"content": base64.b64encode(body).decode(), "encoding": "base64"})
            if blob["sha"] != expected:
                raise ValueError("Remote blob verification failed")
            entries.append({"path": path, "mode": "100644", "type": "blob", "sha": expected})
        if not entries:
            return {"verified": True, "repository": repo, "branch": branch, "commit": head, "records": len(records), "changed": False}
        new_tree = api.request("POST", prefix + "/git/trees", {"base_tree": commit["tree"]["sha"], "tree": entries})
        new_commit = api.request("POST", prefix + "/git/commits", {"message": "intelligence: preserve execution evidence [skip ci]", "tree": new_tree["sha"], "parents": [head]})
        try:
            if exists:
                api.request("PATCH", prefix + "/git/refs/heads/" + branch, {"sha": new_commit["sha"], "force": False})
            else:
                api.request("POST", prefix + "/git/refs", {"ref": "refs/heads/" + branch, "sha": new_commit["sha"]})
        except RuntimeError:
            if attempt == 2:
                raise
            continue
        observed = api.request("GET", prefix + "/git/ref/heads/" + branch)["object"]["sha"]
        observed_tree = api.request("GET", prefix + "/git/trees/" + observed + "?recursive=1")
        confirmed = {f["path"]: f["sha"] for f in observed_tree["tree"]}
        if observed_tree.get("truncated") or any(confirmed.get(e["path"]) != e["sha"] for e in entries):
            raise ValueError("Published evidence was not independently confirmed")
        return {"verified": True, "repository": repo, "branch": branch, "commit": observed, "records": len(records), "changed": True}
    raise RuntimeError("Concurrent publication did not converge")


def run_profile(root, config, name, run_id, commit=None):
    commands = config.get("profiles", {}).get(name)
    if not isinstance(commands, list) or not commands:
        raise ValueError("Unknown or empty gate profile")
    output = root / ".intelligence" / "runs" / run_id
    if output.exists():
        raise ValueError("Run ID already exists; use a new execution ID")
    output.mkdir(parents=True)
    results = []
    for index, command in enumerate(commands):
        if not isinstance(command, list) or not command or not all(isinstance(v, str) for v in command):
            raise ValueError("Gate command must be an argv list")
        command = [sys.executable if value == "{python}" else value for value in command]
        log = output / f"gate-{index + 1:02d}.txt"
        with log.open("wb") as stream:
            try:
                result = subprocess.run(command, cwd=root, stdout=stream, stderr=subprocess.STDOUT,
                                        timeout=config.get("gate_timeout_seconds", 300), check=False)
                code = result.returncode
            except (OSError, subprocess.TimeoutExpired) as exc:
                stream.write((type(exc).__name__ + "\n").encode())
                code = 124 if isinstance(exc, subprocess.TimeoutExpired) else 127
        results.append({"command": command, "exit_code": code, "log": log.name})
    failed = any(r["exit_code"] != 0 for r in results)
    (output / "gates.json").write_bytes(canonical({"schema": "LocalGates/1", "profile": name, "results": results}))
    record = capture(root, config, [str(output)], run_id, "failure" if failed else "success", commit)
    return record, 1 if failed else 0


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path.cwd())
    parser.add_argument("--config", default=".intelligence.json")
    subs = parser.add_subparsers(dest="operation", required=True)
    cap = subs.add_parser("capture")
    cap.add_argument("--input", action="append", required=True)
    cap.add_argument("--run-id", required=True)
    cap.add_argument("--status", choices=["success", "failure", "cancelled", "unknown"], required=True)
    cap.add_argument("--commit")
    subs.add_parser("verify")
    subs.add_parser("publish")
    run = subs.add_parser("run")
    run.add_argument("--profile", default="portable")
    run.add_argument("--run-id", required=True)
    run.add_argument("--commit")
    args = parser.parse_args(argv)
    try:
        root = args.root.resolve()
        config = load_config(root / args.config)
        code = 0
        if args.operation == "capture":
            result = {"record": capture(root, config, args.input, args.run_id, args.status, args.commit), "published": False}
        elif args.operation == "verify":
            result = {"verified": True, "records": len(verify(root / config["store"]))}
        elif args.operation == "publish":
            result = publish(root, config)
        else:
            if not IDENT.fullmatch(args.run_id):
                raise ValueError("Invalid run ID")
            record, code = run_profile(root, config, args.profile, args.run_id, args.commit)
            result = {"record": record, "gate_exit_code": code, "published": False}
        print(json.dumps(result, ensure_ascii=False))
        return code
    except (ValueError, OSError, RuntimeError, zipfile.BadZipFile, KeyError) as exc:
        print("Evidence error: " + str(exc), file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
