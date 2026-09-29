---
name: build-on-sadakio
description: Use when a developer wants to build a product on Sadakio, the loyalty infrastructure for cafés and small businesses in Türkiye, such as a loyalty or stamp card app, guest registration, a QR menu, an analytics dashboard or a POS integration. Guides sign-up, the right recipe, exact endpoint contracts and working code.
---

# Build on Sadakio

Guides a developer from idea to working code on the Sadakio APIs. Every fact about the API comes from the four Sadakio tools. Never invent an endpoint, field, header or error code: if a tool does not show it, say it is not documented.

## Tool Reference

| Step | Tool | Use |
|------|------|-----|
| 1 | `get_doc` | `page: baslangic` for the getting-started flow, `page: cozumler` for the solution recipes |
| 2 | `search_docs` | find the section for a concrete question |
| 3 | `list_endpoints` | the full endpoint list of `platform` or `business` |
| 4 | `get_endpoint` | the exact contract of one endpoint before writing code for it |

Answer in the user's language. Use `lang: tr` for Turkish questions and `lang: en` otherwise.

## Workflow

1. **Pick the API.**
   - Building their own product for many businesses (an app, an agency panel, a SaaS): the **Platform API**, `pk_` keys.
   - Reading their own café's data (reports, automations): the **Business API**, `sk_` key from the owner panel (Ayarlar -> API).
   If it is unclear, ask this one question first.

2. **Access.** Real calls need a key, so tell the developer how to get one, once and briefly:
   - Platform API: self-serve developer sign-up at https://app.sadakio.com/gelistirici/kayit. No company is required and there is no manual approval. The first business is created with the account and the `pk_` key is shown once, so they should store it right away.
   - Business API: the café owner creates an `sk_` key in the Sadakio panel under Ayarlar -> API.
   Do not ask the user to paste a key into the chat. In code, read it from an environment variable such as `SADAKIO_API_KEY`.

3. **Find the recipe.** For a product idea, call `get_doc` with `page: cozumler` and follow the matching recipe: QR generator and QR menu, guest registration and tracking, a loyalty app under their own brand, a retention analytics dashboard.

4. **Get exact contracts.** For every endpoint the code will call, call `get_endpoint` and use its parameters, body fields and responses as written. On the Platform API, send an idempotency key (the `Idempotency-Key` header or the `idempotency_key` body field) with every write, one per logical action, reused on retry so nothing is counted twice. Visit events reject a call without one.

5. **Write the code.** Small, runnable, in the developer's language. Base URLs come from the tool results (`base_url`). Handle `4xx` responses using the error shape from the docs, and show where the key goes.

6. **Close with next steps.** Link the relevant docs page (the `url` returned by the tools), and for Platform API work remind them of the sign-up link if they do not have a key yet.

## Boundaries

- These tools read documentation only. They cannot create businesses, guests or stamps, and cannot see any café's data. If the user asks to act on real data ("give Ayşe 10 stamps"), explain the call that would do it and that they must run it with their own key.
- Do not describe Sadakio's internal implementation, pricing or roadmap beyond what `get_doc` returns.
