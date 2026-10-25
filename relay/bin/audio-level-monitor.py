#!/usr/bin/env python3
"""Publish level messages from the relay's existing GStreamer pipeline."""
import json
import os
import re
import sys
import time

OUTPUT_PATH = "/run/bcast-control/audio-level.json"
LEVEL_RE = re.compile(r"\blevel,")
ARRAY_RE = re.compile(r"\b(rms|peak)=\([^)]*\)\s*<([^>]*)>")
NUMBER_RE = re.compile(r"[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?")


def write_level(rms, peak):
    tmp_path = OUTPUT_PATH + ".tmp"
    payload = {
        "rms": rms,
        "peak": peak,
        "active": True,
        "updated": time.time(),
    }
    with open(tmp_path, "w") as f:
        json.dump(payload, f)
    os.replace(tmp_path, OUTPUT_PATH)


def parse_level(line):
    if not LEVEL_RE.search(line):
        return None

    values = {}
    for name, array in ARRAY_RE.findall(line):
        values[name] = [float(value) for value in NUMBER_RE.findall(array)]

    if "rms" not in values or "peak" not in values:
        return None
    return values["rms"], values["peak"]


def main():
    for line in sys.stdin:
        parsed = parse_level(line)
        if parsed is None:
            print(line, end="", file=sys.stderr, flush=True)
        else:
            write_level(parsed[0], parsed[1])


if __name__ == "__main__":
    main()
