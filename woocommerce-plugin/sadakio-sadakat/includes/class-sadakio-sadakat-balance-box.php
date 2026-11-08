<?php
/**
 * A small "your loyalty balance" box on the thank-you page and the My
 * Account dashboard. Balance comes from GET /web/balance by billing phone,
 * cached for five minutes; on any error the box is simply not shown.
 *
 * @package SadakioSadakat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end balance box.
 */
class Sadakio_Sadakat_Balance_Box {

	const CACHE_PREFIX = 'sadakio_sadakat_bal_';
	const CACHE_TTL    = 300;
	const ERROR_TTL    = 60;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'on_thankyou' ), 20, 1 );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'on_account_dashboard' ), 20 );
	}

	/**
	 * Thank-you page. WooCommerce already restricts this page to the
	 * order's buyer (order key in the URL).
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_thankyou( $order_id ) {
		try {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order || 'TRY' !== strtoupper( (string) $order->get_currency() ) ) {
				return;
			}
			self::render( (string) $order->get_billing_phone() );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/**
	 * My Account dashboard for the logged-in customer.
	 */
	public static function on_account_dashboard() {
		try {
			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return;
			}
			$customer = new WC_Customer( $user_id );
			self::render( (string) $customer->get_billing_phone() );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/**
	 * Balance for a phone, from cache or the API. Null on any error.
	 *
	 * @param string $phone Phone.
	 * @return array|null
	 */
	public static function get_balance( $phone ) {
		$phone = trim( $phone );
		if ( '' === $phone || ! Sadakio_Sadakat_Settings::is_configured() ) {
			return null;
		}
		$key    = self::cache_key( $phone );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return empty( $cached['err'] ) ? $cached : null;
		}

		$res = Sadakio_Sadakat_API::from_settings()->balance( $phone );
		if ( ! $res->ok ) {
			// Remember the failure briefly so a down API is not hit on every page view.
			set_transient( $key, array( 'err' => 1 ), self::ERROR_TTL );
			return null;
		}
		set_transient( $key, $res->data, self::CACHE_TTL );
		return $res->data;
	}

	/**
	 * Drop the cached balance for one phone (after an order or a cancel).
	 *
	 * @param string $phone Phone.
	 */
	public static function forget( $phone ) {
		$phone = trim( (string) $phone );
		if ( '' !== $phone ) {
			delete_transient( self::cache_key( $phone ) );
		}
	}

	/**
	 * Drop every cached balance (after settings change, on uninstall).
	 */
	public static function flush_cache() {
		global $wpdb;
		$like_value   = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like_value, $like_timeout ) );
	}

	/**
	 * @param string $phone Phone.
	 * @return string
	 */
	private static function cache_key( $phone ) {
		return self::CACHE_PREFIX . md5( Sadakio_Sadakat_Settings::get_base_url() . '|' . $phone );
	}

	/**
	 * Print the box, or nothing.
	 *
	 * @param string $phone Billing phone.
	 */
	private static function render( $phone ) {
		$b = self::get_balance( $phone );
		if ( ! is_array( $b ) || empty( $b['card_token'] ) ) {
			return; // Unknown guest or an error: show nothing.
		}

		$rows = array();
		if ( ! empty( $b['stamps'] ) ) {
			$rows[] = array( __( 'Damga', 'sadakio-sadakat' ), (string) (int) $b['stamps'] );
		}
		if ( ! empty( $b['points'] ) ) {
			$rows[] = array( __( 'Puan', 'sadakio-sadakat' ), (string) (int) $b['points'] );
		}
		if ( isset( $b['cashback'] ) && (float) $b['cashback'] > 0 ) {
			$rows[] = array( __( 'Cashback', 'sadakio-sadakat' ), (string) $b['cashback'] . ' TL' );
		}
		if ( ! empty( $b['rewards'] ) ) {
			$rows[] = array( __( 'Bekleyen ödül', 'sadakio-sadakat' ), (string) (int) $b['rewards'] );
		}
		if ( ! $rows ) {
			return;
		}
		?>
		<section class="sadakio-sadakat-balance" style="margin:1.5em 0;padding:1em 1.25em;border:1px solid rgba(0,0,0,.12);border-radius:6px;">
			<h2 style="margin-top:0;font-size:1.15em;"><?php esc_html_e( 'Sadakat bakiyeniz', 'sadakio-sadakat' ); ?></h2>
			<dl style="display:grid;grid-template-columns:auto 1fr;gap:.25em 1em;margin:0;">
				<?php foreach ( $rows as $row ) : ?>
					<dt><?php echo esc_html( $row[0] ); ?></dt>
					<dd style="margin:0;font-weight:600;"><?php echo esc_html( $row[1] ); ?></dd>
				<?php endforeach; ?>
			</dl>
			<?php if ( ! empty( $b['phone'] ) ) : ?>
				<p style="margin:.75em 0 0;font-size:.9em;opacity:.75;">
					<?php
					/* translators: %s: masked phone number */
					echo esc_html( sprintf( __( 'Kartınız %s numarasına bağlıdır.', 'sadakio-sadakat' ), (string) $b['phone'] ) );
					?>
				</p>
			<?php endif; ?>
		</section>
		<?php
	}
}
