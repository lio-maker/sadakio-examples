# Your AI assistant, reading your own data, in two minutes

No code. Cursor, Claude Code and Claude Desktop all take the same block.

## 1. Get a key

In the Sadakio panel: **Ayarlar → API → yeni anahtar**. The raw key is shown
once, at creation — copy it then.

The key is scoped to one business and carries the `read` scope. It cannot name
another business's row, phone numbers arrive masked, and nothing it exposes can
change anything.

## 2. Paste the block

<!-- mcp-config-block -->
```json
{
  "mcpServers": {
    "sadakio": {
      "command": "npx",
      "args": ["-y", "sadakio-mcp"],
      "env": { "SADAKIO_API_KEY": "sk_SIZIN_ANAHTARINIZ" }
    }
  }
}
```
<!-- /mcp-config-block -->

| Client | File |
|---|---|
| Cursor | `.cursor/mcp.json` in the project, or the global one in Settings → MCP |
| Claude Code | `.mcp.json` in the project root |
| Claude Desktop | `claude_desktop_config.json` |

On a Python stack, swap the two lines for `"command": "uvx"` and
`"args": ["sadakio-mcp"]`. Same server, same four tools — pick whichever your
machine already has.

## 3. Ask it something

Restart the client so it picks the server up, then try:

- «Bu ay kaç misafirim geri döndü»
- «En sık gelen on misafirimi listele»
- «Son iki haftada hiç gelmeyen ama daha önce düzenli gelen misafirler kimler»

The assistant sees four tools — `list_guests`, `get_guest`, `list_visits`,
`get_retention` — and works out which to call.

## When it does not work

**Nothing appears in the client.** Restart it fully — most clients read the
config once at startup.

**«no API key configured» on every call.** The server started but the `env`
block did not reach it. Check the key is in the `env` object above, not in
`args`.

**`npx` is not found.** Node 18 or newer is needed for `npx sadakio-mcp`, and
Python 3.10 or newer for `uvx sadakio-mcp`.

**Everything answers «not found».** The key belongs to a different business than
the one you are asking about. A foreign id answers 404 rather than revealing
that it exists, so a mismatched key looks like an empty account.

## What it will not do

It will not write. The Public API v1 is read-only, every tool is annotated
read-only, and there is no scope you can add that changes that today. Write
tools arrive with write endpoints, and each will name the guard it goes through.
