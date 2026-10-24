#!/usr/bin/env python3
"""Capture configured CI outputs, explicitly record missing outputs, then publish."""
import argparse
import glob
import json
import os
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
import evidence


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--name", required=True)
    p.add_argument("--status", required=True, choices=["success", "failure", "cancelled", "unknown"])
    p.add_argument("--publish", action="store_true")
    args = p.parse_args()
    root = Path.cwd().resolve()
    config = evidence.load_config(root / ".intelligence.json")
    patterns = config.get("ci_inputs", {}).get(args.name)
    if not patterns:
        raise ValueError("Unknown CI evidence group")
    found, missing = [], []
    for pattern in patterns:
        paths = glob.glob(os.path.expandvars(pattern))
        if paths:
            found.extend(paths)
        else:
            missing.append(pattern)
    run_id = "-".join([os.environ["GITHUB_RUN_ID"], os.environ.get("GITHUB_RUN_ATTEMPT", "1"), args.name])
    context = Path(os.environ["RUNNER_TEMP"]) / "intelligence-context" / (run_id + ".json")
    context.parent.mkdir(parents=True, exist_ok=True)
    context.write_bytes(evidence.canonical({"schema": "CIEvidenceContext/1", "status": args.status,
                       "missing_inputs": missing, "outputs_found": len(found), "group": args.name}))
    record = evidence.capture(root, config, found + [str(context)], run_id, args.status)
    receipt = evidence.publish(root, config) if args.publish else {"published": False, "record": record}
    summary = os.environ.get("GITHUB_STEP_SUMMARY")
    if summary:
        with open(summary, "a", encoding="utf-8") as f:
            f.write("\nEvidence: `" + record + "`\n\n" + json.dumps(receipt) + "\n")
    print(json.dumps(receipt))


if __name__ == "__main__":
    main()
