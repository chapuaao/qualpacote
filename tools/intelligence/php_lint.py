#!/usr/bin/env python3
"""Lint real project PHP files without starting the app or requiring MySQL."""
from pathlib import Path
import subprocess
import sys

root = Path(__file__).resolve().parents[2]
files = [p for p in sorted(root.rglob("*.php")) if not any(x in ("vendor", ".git", ".intelligence") for x in p.parts)]
if not files:
    raise SystemExit("No PHP source files found; empty lint cannot certify the project")
for path in files:
    try:
        result = subprocess.run(["php", "-l", str(path)], cwd=root, check=False)
    except FileNotFoundError:
        print("PHP 8.2+ CLI unavailable; PHP validation remains pending", file=sys.stderr)
        raise SystemExit(127)
    if result.returncode:
        raise SystemExit(result.returncode)
print("PHP lint passed for", len(files), "files")
