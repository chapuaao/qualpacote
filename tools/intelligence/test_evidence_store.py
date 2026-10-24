import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
import zipfile

spec = importlib.util.spec_from_file_location("intelligence_evidence", Path(__file__).with_name("evidence.py"))
ev = importlib.util.module_from_spec(spec)
spec.loader.exec_module(ev)


class FakeGitHub:
    def __init__(self, private=True):
        self.private = private
        self.refs = {"main": "base"}
        self.commits = {"base": {"tree": {"sha": "base-tree"}}}
        self.trees = {"base-tree": {}}
        self.blobs = {}
        self.counter = 0
        self.conflict_once = False

    def request(self, method, path, payload=None, missing=False):
        import hashlib
        import base64
        prefix = "/repos/example/project"
        route = path[len(prefix):]
        if method == "GET" and route == "":
            return {"private": self.private, "default_branch": "main"}
        if method == "GET" and route.startswith("/git/ref/heads/"):
            branch = route[len("/git/ref/heads/"):]
            return {"object": {"sha": self.refs[branch]}} if branch in self.refs else None
        if method == "GET" and route.startswith("/git/commits/"):
            return self.commits[route.split("/")[-1]]
        if method == "GET" and route.startswith("/git/trees/"):
            key = route.split("/")[-1].split("?")[0]
            key = self.commits.get(key, {"tree": {"sha": key}})["tree"]["sha"]
            return {"truncated": False, "tree": [{"path": k, **v} for k, v in self.trees[key].items()]}
        self.counter += 1
        key = str(self.counter)
        if method == "POST" and route == "/git/blobs":
            body = base64.b64decode(payload["content"])
            sha = hashlib.sha1(b"blob " + str(len(body)).encode() + b"\0" + body).hexdigest()
            self.blobs[sha] = body
            return {"sha": sha}
        if method == "POST" and route == "/git/trees":
            entries = dict(self.trees[payload["base_tree"]])
            entries.update({e["path"]: {k: v for k, v in e.items() if k != "path"} for e in payload["tree"]})
            self.trees[key] = entries
            return {"sha": key}
        if method == "POST" and route == "/git/commits":
            self.commits[key] = {"tree": {"sha": payload["tree"]}}
            return {"sha": key}
        if method == "POST" and route == "/git/refs":
            if self.conflict_once:
                self.conflict_once = False
                self.refs["intelligence/evidence"] = "base"
                raise RuntimeError("Concurrent creation")
            self.refs[payload["ref"][len("refs/heads/"):]] = payload["sha"]
            return {}
        if method == "PATCH" and route.startswith("/git/refs/heads/"):
            if payload["force"]:
                raise AssertionError("Force push forbidden")
            self.refs[route[len("/git/refs/heads/"):]] = payload["sha"]
            return {}
        raise AssertionError((method, path))


class EvidenceTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.config = {"schema": "ProjectIntelligence/1", "repository": "example/project", "store": "archive",
                       "archive_branch": "intelligence/evidence", "sensitivity": "uso_interno"}
        (self.root / "report.txt").write_text("original observation\n")

    def capture(self, run="run-1", status="success"):
        return ev.capture(self.root, self.config, [str(self.root / "report.txt")], run, status, "a" * 40)

    def test_roundtrip_exact_original_bytes_and_idempotence(self):
        record = self.capture()
        self.assertEqual(record, self.capture())
        data = ev.verify(self.root / "archive")
        self.assertEqual(len(data), 1)
        with zipfile.ZipFile(self.root / "archive" / data[0]["payload"]["path"]) as z:
            self.assertEqual(z.read("repository/report.txt"), b"original observation\n")

    def test_same_run_cannot_overwrite_changed_results(self):
        self.capture()
        (self.root / "report.txt").write_text("changed")
        with self.assertRaisesRegex(ValueError, "Append-only"):
            self.capture()

    def test_tampered_payload_rejected(self):
        self.capture()
        record = ev.verify(self.root / "archive")[0]
        (self.root / "archive" / record["payload"]["path"]).write_bytes(b"corrupt")
        with self.assertRaises(ValueError):
            ev.verify(self.root / "archive")

    def test_tampered_record_rejected(self):
        path = self.root / "archive" / self.capture()
        path.write_text(path.read_text().replace('"success"', '"failure"'))
        with self.assertRaisesRegex(ValueError, "record digest"):
            ev.verify(self.root / "archive")

    def test_missing_inputs_fail_without_false_receipt(self):
        with self.assertRaisesRegex(ValueError, "missing"):
            ev.capture(self.root, self.config, [str(self.root / "missing")], "run-1", "success", "a" * 40)

    def test_secret_blocks_publication_without_altering_original(self):
        secret = "ghp_" + "A" * 40
        (self.root / "report.txt").write_text(secret)
        with self.assertRaisesRegex(ValueError, "Credential"):
            self.capture()
        self.assertEqual((self.root / "report.txt").read_text(), secret)

    def test_symlink_input_rejected(self):
        (self.root / "link.txt").symlink_to(self.root / "report.txt")
        with self.assertRaisesRegex(ValueError, "Symlink"):
            ev.capture(self.root, self.config, [str(self.root / "link.txt")], "run-1", "success", "a" * 40)

    def test_zip_traversal_and_duplicates_rejected(self):
        for names in (["../escape"], ["same", "same"]):
            raw = io.BytesIO()
            with zipfile.ZipFile(raw, "w") as z:
                for name in names:
                    z.writestr(name, "value")
            with self.assertRaises(ValueError):
                ev.inspect_zip(raw.getvalue())

    def test_local_failed_gate_is_preserved_and_exit_remains_failed(self):
        import sys
        self.config["profiles"] = {"portable": [[sys.executable, "-c", "print('failed observation'); raise SystemExit(7)"]]}
        record, code = ev.run_profile(self.root, self.config, "portable", "failed-run", "a" * 40)
        self.assertEqual(code, 1)
        self.assertEqual(ev.verify(self.root / "archive")[0]["status"], "failure")
        self.assertTrue((self.root / "archive" / record).exists())

    def test_publication_confirmed_and_repeat_is_noop(self):
        self.capture()
        api = FakeGitHub()
        receipt = ev.publish(self.root, self.config, api)
        self.assertTrue(receipt["verified"])
        self.assertTrue(receipt["changed"])
        self.assertFalse(ev.publish(self.root, self.config, api)["changed"])
        self.assertEqual(api.refs["main"], "base")

    def test_concurrent_branch_creation_retries_without_force(self):
        self.capture()
        api = FakeGitHub()
        api.conflict_once = True
        self.assertTrue(ev.publish(self.root, self.config, api)["verified"])

    def test_remote_immutable_conflict_rejected(self):
        path = self.capture()
        api = FakeGitHub()
        ev.publish(self.root, self.config, api)
        tree = api.commits[api.refs["intelligence/evidence"]]["tree"]["sha"]
        api.trees[tree]["archive/" + path]["sha"] = "corrupted"
        with self.assertRaisesRegex(ValueError, "Append-only remote"):
            ev.publish(self.root, self.config, api)

    def test_internal_evidence_cannot_go_to_public_repository(self):
        self.capture()
        with self.assertRaisesRegex(ValueError, "public repository"):
            ev.publish(self.root, self.config, FakeGitHub(private=False))

    def test_wrong_project_rejected(self):
        self.capture()
        self.config["repository"] = "other/project"
        with self.assertRaisesRegex(ValueError, "Cross-project"):
            ev.publish(self.root, self.config, FakeGitHub())

    def packed_record(self):
        record_path = self.root / "archive" / self.capture()
        record = json.loads(record_path.read_text())
        raw = ev.payload_bytes(self.root / "archive", record["payload"])
        output = io.BytesIO()
        with zipfile.ZipFile(output, "w", zipfile.ZIP_STORED) as z:
            z.writestr("original.zip", raw)
        body = output.getvalue()
        pack = {"path": "packs/test.zip", "sha256": ev.digest(body), "bytes": len(body), "entry": "original.zip"}
        ev.immutable_write(self.root / "archive" / pack["path"], body)
        record["payload"]["pack"] = pack
        record.pop("record_sha256")
        record["record_sha256"] = ev.digest(ev.canonical(record))
        record_path.write_bytes(ev.canonical(record))
        return pack

    def test_packed_original_roundtrip_and_tamper_detection(self):
        pack = self.packed_record()
        self.assertEqual(len(ev.verify(self.root / "archive")), 1)
        (self.root / "archive" / pack["path"]).write_bytes(b"tampered")
        with self.assertRaisesRegex(ValueError, "pack"):
            ev.verify(self.root / "archive")

    def test_packed_publication_keeps_original_bytes_and_idempotence(self):
        self.packed_record()
        api = FakeGitHub()
        self.assertTrue(ev.publish(self.root, self.config, api)["verified"])
        self.assertFalse(ev.publish(self.root, self.config, api)["changed"])

    def test_dirty_worktree_is_explicit_in_provenance(self):
        import subprocess
        for command in (["git", "init", "-q"], ["git", "config", "user.name", "Test"],
                        ["git", "config", "user.email", "test@invalid.local"], ["git", "add", "report.txt"],
                        ["git", "commit", "-qm", "original"]):
            subprocess.run(command, cwd=self.root, check=True, capture_output=True)
        head = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=self.root, text=True).strip()
        (self.root / "report.txt").write_text("changed candidate")
        ev.capture(self.root, self.config, [str(self.root / "report.txt")], "candidate", "success", head)
        record = ev.verify(self.root / "archive")[0]
        self.assertTrue(record["source"]["working_tree_dirty"])
        self.assertEqual(record["source"]["commit_role"], "checkout_head")
        self.assertTrue(record["source"]["tracked_patch_sha256"])


if __name__ == "__main__":
    unittest.main()
