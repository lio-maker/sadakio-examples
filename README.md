# Sadakio examples

Three small, complete things you can run today against your own business's data.

| | What it is | Stack |
|---|---|---|
| [`retention-dashboard`](retention-dashboard) | A one-file web dashboard: who came back this month, and the ten most frequent guests | Node, `sadakio` |
| [`guest-sync-csv`](guest-sync-csv) | An incremental guest export to CSV you can open in Sheets or Excel | Python, `sadakio` |
| [`mcp-in-cursor`](mcp-in-cursor) | Connecting an AI assistant to your data in about two minutes | No code |

All three are read-only, because the Public API v1 is read-only. All three need
one key, created in the Sadakio panel under **Ayarlar → API**.

None of them invents a number. Where the API declines to estimate money without
an average ticket, the examples decline too — a figure a dashboard makes up is a
figure somebody repeats in a meeting.

## What you need

| | Status |
|---|---|
| `mcp-in-cursor` | **Works now.** `sadakio-mcp` is published on [npm](https://www.npmjs.com/package/sadakio-mcp) and [PyPI](https://pypi.org/project/sadakio-mcp/) |
| `retention-dashboard`, `guest-sync-csv` | **Work now.** The `sadakio` client is published on [npm](https://www.npmjs.com/package/sadakio) and [PyPI](https://pypi.org/project/sadakio/) |

All three run today.

## Install the client first

```bash
npm install sadakio     # Node 18+
pip install sadakio     # Python 3.10+
```

The MCP server needs no install at all:

```bash
npx sadakio-mcp         # Node 18+
uvx sadakio-mcp         # Python 3.10+
```

## Where the docs are

- Developer quickstart: https://sadakio.com/gelistirici
- OpenAPI contract: https://api.sadakio.com/api/v1/openapi.yaml
- Machine-readable summary: https://sadakio.com/llms.txt

Questions, or a key for a business you do not own yet: biz@sadakio.com

MIT licensed. These examples are deliberately small enough to read in full
before you run them.
