# Guest sync to CSV

Exports your guest base to a CSV you can open in Google Sheets or Excel, and
keeps it up to date.

```bash
pip install -r requirements.txt
export SADAKIO_API_KEY=sk_your_key
python sync_guests.py                 # writes guests.csv
python sync_guests.py                 # again: only what changed
python sync_guests.py --full          # ignore the saved timestamp
```

## Incremental without losing anyone

The second run asks only for guests who **visited or were created** since the
last run, which is what `updated_since` means — a brand-new guest who has not
visited yet is still returned, so a sync cannot miss them.

Two details worth stealing:

**Rows are keyed by id and merged, not appended.** An incremental sync returns a
guest again every time they visit. An append-only writer grows a second row per
return visit, which is precisely backwards for a retention report.

**The timestamp is taken before the read, never after.** A guest who visits
while the script is running is then picked up by the next run instead of falling
into the gap between the two.

The state file holds one timestamp and nothing else. Guest data lives only in
the CSV you asked for, and phone numbers are masked before they ever leave
Sadakio.
