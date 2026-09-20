# Retention dashboard

Answers the question an owner actually asks: **did the people I served last
month come back, and how many of them.**

```bash
npm install
SADAKIO_API_KEY=sk_your_key npm start
# open http://localhost:3000
```

One file, `server.js`, about 120 lines. It shows guests seen this month,
how many of those had been customers before, visits, the last seven days, and
the ten most frequent guests.

## Money is opt-in, on purpose

By default there is no currency figure anywhere, because the API does not
estimate one without a real average ticket and neither should a dashboard. If
you know yours:

```bash
SADAKIO_AVG_TICKET=180 SADAKIO_API_KEY=sk_... npm start
```

Then the estimate appears, labelled as an estimate.

## What to copy from it

The pagination. `sadakio.guests.iterate()` walks every page for you and stops on
a null cursor — not on an empty page, which is how a hand-written loop quietly
truncates a sync. Swapping this to an incremental refresh is one parameter,
`updated_since`, not a rewrite.

Phones arrive masked and are rendered masked. That is not a setting.
