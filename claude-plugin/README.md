# Sadakio Developer Docs

Sadakio is loyalty infrastructure for cafés and small businesses in Türkiye: guest registration, digital cards, stamps and points, QR codes and menus, visit events, webhooks and retention metrics over a REST API. This plugin helps you build on it from Claude.

## What it adds

- **Connector `sadakio-docs`**: Sadakio's remote MCP server at `https://api.sadakio.com/mcp`, with four read-only tools:
  - `search_docs`: full-text search over the developer documentation (English and Turkish)
  - `get_doc`: one documentation page as markdown, including the solution recipes
  - `list_endpoints`: every endpoint of the Platform API or the Business API
  - `get_endpoint`: the exact OpenAPI contract of one endpoint
- **Skill `build-on-sadakio`**: a guided workflow that picks the right API, points you to developer sign-up, finds the matching recipe, fetches the exact contract of every endpoint and writes working code.

## What it runs, sends and fetches

- The only network destination is `https://api.sadakio.com/mcp`, operated by Sadakio. Claude sends it your tool call arguments (a search query, a page name, an API name, an HTTP method and path). Nothing else is sent.
- No authentication, no API keys, no cookies. The server returns only what is already public at [sadakio.com/docs](https://sadakio.com/docs/) and in the published OpenAPI files. It has no access to any business or guest data and cannot change anything.
- The plugin runs no local code, hooks or scripts.

To make real API calls from your own code you need your own key: developers sign up at [app.sadakio.com/gelistirici/kayit](https://app.sadakio.com/gelistirici/kayit), café owners create one in the Sadakio panel under Ayarlar → API. Never paste a key into a chat.

## Example prompts

- How do I register a guest and give a stamp with the Sadakio Platform API?
- Show me the exact contract of the Sadakio visit event endpoint.
- Build me a branded loyalty app for my client's café on Sadakio.

## Support and privacy

- Documentation: https://sadakio.com/en/docs/
- Support: biz@sadakio.com, https://sadakio.com/support
- Privacy policy: https://sadakio.com/privacy

## License

MIT, see [LICENSE](LICENSE).
