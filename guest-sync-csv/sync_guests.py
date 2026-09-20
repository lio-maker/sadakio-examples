#!/usr/bin/env python3
"""Sync the guest base to a CSV you can open in Sheets or Excel.

Incremental by default: it remembers when it last ran and asks only for guests
who have visited OR been created since then, so the second run is small. The
state file holds a timestamp and nothing else — no guest data on disk beyond the
CSV you asked for.

    export SADAKIO_API_KEY=sk_...
    python sync_guests.py                 # incremental, writes guests.csv
    python sync_guests.py --full          # ignore the saved timestamp
    python sync_guests.py --out misafirler.csv

Phones arrive masked and are written masked. That is not a setting.
"""

from __future__ import annotations

import argparse
import csv
import json
import os
import pathlib
import sys
from datetime import datetime, timezone

from sadakio import Sadakio, SadakioError

FIELDS = [
    "id",
    "name",
    "masked_phone",
    "email",
    "joined_at",
    "visits_count",
    "last_visit",
    "has_birth_date",
    "opt_in",
    "lang",
]


def load_state(path: pathlib.Path) -> str | None:
    try:
        return json.loads(path.read_text(encoding="utf-8")).get("synced_at")
    except (OSError, ValueError):
        return None


def save_state(path: pathlib.Path, moment: str) -> None:
    path.write_text(json.dumps({"synced_at": moment}, indent=2), encoding="utf-8")


def merge(existing: dict[int, dict], incoming: list[dict]) -> dict[int, dict]:
    """Rows are keyed by id so a re-run updates a guest instead of duplicating.

    An incremental sync returns a guest again every time they visit, so an
    append-only writer would grow a second row for every return visit — which is
    the opposite of what a retention report needs.
    """
    for row in incoming:
        existing[row["id"]] = row
    return existing


def read_existing(path: pathlib.Path) -> dict[int, dict]:
    if not path.exists():
        return {}
    with path.open(newline="", encoding="utf-8") as fh:
        return {int(r["id"]): r for r in csv.DictReader(fh) if r.get("id")}


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--out", default="guests.csv", type=pathlib.Path)
    ap.add_argument("--state", default=".sadakio-sync.json", type=pathlib.Path)
    ap.add_argument("--full", action="store_true", help="ignore the saved timestamp and read everything")
    args = ap.parse_args()

    key = os.environ.get("SADAKIO_API_KEY")
    if not key:
        print("Set SADAKIO_API_KEY first. Create a key in the Sadakio panel, Ayarlar → API.", file=sys.stderr)
        return 1

    since = None if args.full else load_state(args.state)
    # Taken before the read, never after: a guest who visits while this runs must
    # be picked up by the NEXT sync rather than falling into the gap between.
    started = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

    rows = read_existing(args.out)
    before = len(rows)

    try:
        with Sadakio(api_key=key) as sadakio:
            fetched = list(sadakio.iter_guests(updated_since=since, limit=200))
    except SadakioError as err:
        # The message already carries the step that fixes it.
        print(f"{err.code}: {err}", file=sys.stderr)
        return 1

    rows = merge(rows, fetched)

    with args.out.open("w", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(fh, fieldnames=FIELDS, extrasaction="ignore")
        writer.writeheader()
        for guest in sorted(rows.values(), key=lambda r: int(r["id"])):
            writer.writerow(guest)

    save_state(args.state, started)
    scope = "full" if since is None else f"changed since {since}"
    print(f"{len(fetched)} guests read ({scope}), {len(rows)} rows in {args.out} (was {before})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
