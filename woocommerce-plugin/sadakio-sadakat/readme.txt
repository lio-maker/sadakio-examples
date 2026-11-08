=== Sadakio Sadakat ===
Contributors: sadakio
Tags: loyalty, woocommerce, sadakat, puan, rewards
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce mağazanızı Sadakio sadakat programına bağlar. Connects your WooCommerce shop to your Sadakio loyalty program.

== Description ==

**Türkçe**

Sadakio Sadakat, WooCommerce mağazanızdaki ödenmiş siparişleri Sadakio sadakat programınıza işleyen ücretsiz bir eklentidir. Müşterileriniz kafenizde veya mağazanızda kullandıkları aynı sadakat kartıyla internetten verdikleri siparişlerden de damga, puan veya cashback kazanır.

* Sipariş "İşleniyor" veya "Tamamlandı" durumuna geçtiğinde, müşterinin fatura telefonu ve sipariş tutarı Sadakio'ya gönderilir.
* Sipariş iptal edildiğinde veya tamamen iade edildiğinde, o siparişin kazandırdıkları geri alınır.
* Her sonuç sipariş notlarına yazılır (örneğin "Sadakio: 25 puan kazandı.").
* Sadakio'ya ulaşılamazsa ödeme akışı etkilenmez; gönderim Action Scheduler ile üç kez yeniden denenir.
* Teşekkür sayfasında ve Hesabım panosunda müşteriye güncel sadakat bakiyesi gösterilir.
* Yalnızca TRY siparişleri işlenir.

Kurulum için bir Sadakio işletme hesabına ve Sadakio panelinde oluşturulmuş bir API anahtarına ihtiyacınız vardır.

**English**

Sadakio Sadakat is a free plugin that records paid WooCommerce orders in your Sadakio loyalty program, so guests earn stamps, points or cashback online on the same loyalty card they use at your café or shop.

* When an order becomes Processing or Completed, the billing phone and order total are sent to Sadakio.
* When an order is cancelled or fully refunded, what it earned is reversed.
* Every outcome is written to the order notes.
* If Sadakio cannot be reached, checkout is not affected; the call is retried up to three times with Action Scheduler.
* The thank-you page and the My Account dashboard show the guest's current loyalty balance.
* Only TRY orders are processed.

You need a Sadakio business account and an API key created in the Sadakio panel.

== External service ==

This plugin talks to the Sadakio API (default `https://app.sadakio.com/api/v1`), operated by Sadakio.

* When an order is paid: the WooCommerce order id, billing phone, billing first and last name and order total are sent.
* When an order is cancelled or fully refunded: the WooCommerce order id is sent.
* On the thank-you page and the My Account dashboard: the billing phone is sent to read the balance.
* When you press "Bağlantıyı test et": a placeholder phone number is sent.

Nothing else is sent, and the plugin loads no external scripts, styles or tracking.

* Privacy policy: https://sadakio.com/privacy

== Installation ==

1. Upload the `sadakio-sadakat` folder to `/wp-content/plugins/`, or install the zip from Plugins → Add New → Upload.
2. Activate the plugin. WooCommerce must be active.
3. Go to WooCommerce → Sadakio, paste your API key (it needs the write scope) and save.
4. Press "Bağlantıyı test et" to check the connection.

== Frequently Asked Questions ==

= Bir sipariş iki kez puan kazandırabilir mi? / Can an order earn twice? =

Hayır. Eklenti her siparişi bir kez gönderir ve Sadakio aynı sipariş numarasını ikinci kez işlemez.
No. The plugin sends each order once, and Sadakio ignores a repeated order id.

= Sadakio'ya ulaşılamazsa ne olur? / What if Sadakio is down? =

Ödeme normal şekilde tamamlanır. Gönderim 5 dakika, 30 dakika ve 2 saat sonra yeniden denenir. Sonuç sipariş notlarında görünür ve sipariş eylemlerinden "Sadakio'ya yeniden gönder" ile elle tekrar gönderilebilir.
Checkout completes normally. The call is retried after 5 minutes, 30 minutes and 2 hours. The outcome is in the order notes, and you can resend from the order actions.

= Kısmi iadeler? / Partial refunds? =

Bu sürümde yalnızca tam iade (sipariş durumu "İade edildi") kazanımı geri alır.
In this version only a full refund (order status Refunded) reverses the earn.

= HPOS destekleniyor mu? / Is HPOS supported? =

Evet. Yes, the plugin declares compatibility with WooCommerce High-Performance Order Storage.

== Changelog ==

= 0.1.0 =
* İlk sürüm: sipariş gönderimi, iptal/iade geri alma, bakiye kutusu, bağlantı testi.
* First release: order sync, cancel/refund reversal, balance box, connection test.
