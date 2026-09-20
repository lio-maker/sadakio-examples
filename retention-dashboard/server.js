/**
 * A retention dashboard in one file.
 *
 * It answers the question a café owner actually asks — "did the people I served
 * last month come back, and how many of them" — using only what the API really
 * returns. Nothing here estimates money unless you give it a real average
 * ticket, because a number a dashboard invents is a number someone will repeat
 * in a meeting.
 *
 *   SADAKIO_API_KEY=sk_... node server.js
 *   open http://localhost:3000
 */
import { createServer } from 'node:http';
import { Sadakio, SadakioError } from 'sadakio';

const PORT = Number(process.env.PORT || 3000);
const apiKey = process.env.SADAKIO_API_KEY;

if (!apiKey) {
  console.error('Set SADAKIO_API_KEY first. Create a key in the Sadakio panel, Ayarlar → API.');
  process.exit(1);
}

const sadakio = new Sadakio({ apiKey });

/** Everything the page shows, fetched once per request. */
async function gather() {
  const [week, month] = await Promise.all([
    sadakio.stats.retention({ period: 'week' }),
    sadakio.stats.retention({
      period: 'month',
      // only pass avg_ticket if you genuinely know it; without it the API
      // returns counts and no money, which is the honest default
      avg_ticket: process.env.SADAKIO_AVG_TICKET || undefined,
    }),
  ]);

  // The guest base, walked in full. iterate() handles the cursor, so an
  // incremental sync here would be one extra parameter rather than a loop.
  const guests = [];
  for await (const g of sadakio.guests.iterate({ limit: 200 })) guests.push(g);

  const withVisits = guests.filter((g) => (g.visits_count || 0) > 0);
  const returning = guests.filter((g) => (g.visits_count || 0) > 1);
  const optedIn = guests.filter((g) => g.opt_in);

  return {
    week: week.data,
    month: month.data,
    totals: {
      guests: guests.length,
      withVisits: withVisits.length,
      returning: returning.length,
      optedIn: optedIn.length,
    },
    top: [...guests].sort((a, b) => (b.visits_count || 0) - (a.visits_count || 0)).slice(0, 10),
  };
}

const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const n = (v) => (v == null ? '—' : Number(v).toLocaleString('tr-TR'));

function page(d) {
  const share = d.month.active_guests ? Math.round((d.month.returned_guests / d.month.active_guests) * 100) : null;
  return `<!doctype html><meta charset="utf-8"><title>Sadakio retention</title>
<style>
  :root{--ink:#1b2440;--line:#e6e6ea;--orange:#dc5b2c;--muted:#545a66}
  body{font:16px/1.5 system-ui,sans-serif;color:var(--ink);margin:0;padding:40px 20px;background:#faf9f7}
  main{max-width:900px;margin:0 auto}
  h1{font-size:24px;margin:0 0 4px} .sub{color:var(--muted);margin:0 0 28px}
  .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px}
  .card b{display:block;font-size:28px;line-height:1.2} .card span{color:var(--muted);font-size:13.5px}
  table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  th,td{text-align:left;padding:10px 14px;border-bottom:1px solid var(--line);font-size:14px}
  th{background:#f4f4f6;font-weight:600} tr:last-child td{border-bottom:none}
  .hl{color:var(--orange)} footer{color:var(--muted);font-size:13px;margin-top:24px}
</style>
<main>
  <h1>Geri dönen misafirler</h1>
  <p class="sub">${esc(d.month.since ?? '')} → ${esc(d.month.until ?? '')}</p>
  <div class="cards">
    <div class="card"><b>${n(d.month.active_guests)}</b><span>Bu ay gelen misafir</span></div>
    <div class="card"><b class="hl">${n(d.month.returned_guests)}</b><span>Geri dönen misafir${share == null ? '' : ` · %${share}`}</span></div>
    <div class="card"><b>${n(d.month.visits)}</b><span>Bu ay ziyaret</span></div>
    <div class="card"><b>${n(d.week.returned_guests)}</b><span>Son 7 günde geri dönen</span></div>
  </div>
  ${d.month.estimated_returned_value == null ? `
  <p class="sub">Para tahmini yok. <code>SADAKIO_AVG_TICKET</code> verirseniz hesaplanır — vermezseniz uydurulmaz.</p>`
    : `<div class="cards"><div class="card"><b class="hl">₺${n(Math.round(d.month.estimated_returned_value))}</b><span>Geri dönen misafirlerin tahmini cirosu</span></div></div>`}
  <table>
    <tr><th>Misafir</th><th>Telefon</th><th>Ziyaret</th><th>Son ziyaret</th></tr>
    ${d.top.map((g) => `<tr><td>${esc(g.name)}</td><td>${esc(g.masked_phone)}</td><td>${n(g.visits_count)}</td><td>${esc((g.last_visit || '').slice(0, 10))}</td></tr>`).join('')}
  </table>
  <footer>${n(d.totals.guests)} misafir · ${n(d.totals.returning)} birden fazla kez geldi · ${n(d.totals.optedIn)} iletişim izni verdi.
  Telefonlar KVKK gereği maskeli gelir.</footer>
</main>`;
}

createServer(async (req, res) => {
  if (req.url !== '/') { res.writeHead(404).end('Not found'); return; }
  try {
    const data = await gather();
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }).end(page(data));
  } catch (err) {
    // The SDK's message already carries the step that fixes it, so show it
    // rather than a generic 500 that sends the reader to the logs.
    const msg = err instanceof SadakioError ? `${err.code}: ${err.message}` : String(err);
    res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' }).end(msg);
  }
}).listen(PORT, () => console.log(`http://localhost:${PORT}`));
