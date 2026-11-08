# WooCommerce plugin: Sadakio Sadakat

[`sadakio-sadakat/`](sadakio-sadakat) is a WordPress plugin that connects a
WooCommerce shop to a Sadakio loyalty program. Paid orders earn on the guest's
card (found by billing phone), cancelled or fully refunded orders reverse what
they earned, and the thank-you page and My Account dashboard show the balance.

| | |
|---|---|
| Requires | PHP 7.4+, WordPress 6.0+, WooCommerce 7.0+ |
| Order storage | HPOS and legacy posts storage both supported |
| Dependencies | none (no Composer, no external assets) |
| License | GPLv2 or later (the plugin only; the rest of this repo is MIT) |

The web-loyalty endpoints it uses are enabled per business. Ask
biz@sadakio.com to switch them on for yours before you install it.

## What it calls

| When | Request |
|---|---|
| Order becomes Processing or Completed | `POST /web/order` `{order_id, phone, amount, name, currency: "TRY"}` |
| Order becomes Cancelled or Refunded | `POST /web/order/cancel` `{order_id}` |
| Thank-you page, My Account dashboard | `GET /web/balance?phone=...` (cached 5 minutes) |
| "Bağlantıyı test et" button | `GET /web/balance?phone=+900000000000` |

All with `Authorization: Bearer <key>`. The key needs the write scope.

Each order is sent once (order meta flag) and the API is idempotent per
`order_id`. Network errors, 5xx and 429 are retried through Action Scheduler
after 5 minutes, 30 minutes and 2 hours; other errors are final and go to the
order notes, from where the order can be resent with the
"Sadakio'ya yeniden gönder" order action. Checkout never waits more than the
5 second HTTP timeout and never fails because of Sadakio.

## Install

Zip the `sadakio-sadakat` folder and upload it in Plugins → Add New → Upload,
then open WooCommerce → Sadakio and paste your key.

```bash
cd woocommerce-plugin && zip -r sadakio-sadakat.zip sadakio-sadakat
```

## Layout

```
sadakio-sadakat/
  sadakio-sadakat.php                         bootstrap, HPOS declaration
  includes/class-sadakio-sadakat-api.php      HTTP client (redeem goes here later)
  includes/class-sadakio-sadakat-settings.php WooCommerce → Sadakio screen
  includes/class-sadakio-sadakat-orders.php   order hooks, retries, notes
  includes/class-sadakio-sadakat-balance-box.php  thank-you / My Account box
  uninstall.php                               removes settings and cache
  readme.txt                                  wordpress.org format
  languages/sadakio-sadakat.pot
```
