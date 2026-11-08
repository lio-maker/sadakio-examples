<?php
/**
 * Order sync: paid orders → POST /web/order, cancelled / fully refunded
 * orders → POST /web/order/cancel.
 *
 * Rules:
 *  - Never break checkout: every entry point catches Throwable and the HTTP
 *    timeout is 5 seconds.
 *  - Sent once: a meta flag on the order; the API is idempotent per order_id
 *    as a second line of defence.
 *  - Network errors, 5xx and 429 are retried through Action Scheduler
 *    (bundled with WooCommerce) up to MAX_RETRIES times; other 4xx answers
 *    are final and written to the order notes.
 *
 * @package SadakioSadakat
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce order hooks.
 */
class Sadakio_Sadakat_Orders {

	const META_SENT            = '_sadakio_order_sent';
	const META_ATTEMPTS        = '_sadakio_order_attempts';
	const META_FAILED          = '_sadakio_order_failed';
	const META_SKIPPED         = '_sadakio_order_skipped';
	const META_CANCEL_SENT     = '_sadakio_cancel_sent';
	const META_CANCEL_ATTEMPTS = '_sadakio_cancel_attempts';

	const HOOK_RETRY_ORDER  = 'sadakio_sadakat_retry_order';
	const HOOK_RETRY_CANCEL = 'sadakio_sadakat_retry_cancel';
	const AS_GROUP          = 'sadakio-sadakat';

	const MAX_RETRIES = 3;

	/** Delay before retry 1, 2, 3 in seconds. */
	const RETRY_DELAYS = array( 300, 1800, 7200 );

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_paid' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_paid' ), 20, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'on_cancelled' ), 20, 2 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'on_cancelled' ), 20, 2 );

		add_action( self::HOOK_RETRY_ORDER, array( __CLASS__, 'retry_order' ), 10, 1 );
		add_action( self::HOOK_RETRY_CANCEL, array( __CLASS__, 'retry_cancel' ), 10, 1 );

		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'order_actions' ), 10, 1 );
		add_action( 'woocommerce_order_action_sadakio_sadakat_resend', array( __CLASS__, 'manual_resend' ), 10, 1 );
	}

	/* ─── Entry points (each one swallows every error) ─────────────────── */

	/**
	 * Order became processing or completed.
	 *
	 * @param int           $order_id Order id.
	 * @param WC_Order|null $order    Order object (WC 3.0+ passes it).
	 */
	public static function on_paid( $order_id, $order = null ) {
		self::guard( array( __CLASS__, 'send_order' ), self::resolve( $order_id, $order ), false );
	}

	/**
	 * Order became cancelled or refunded (WooCommerce sets "refunded" only on a full refund).
	 *
	 * @param int           $order_id Order id.
	 * @param WC_Order|null $order    Order object.
	 */
	public static function on_cancelled( $order_id, $order = null ) {
		self::guard( array( __CLASS__, 'send_cancel' ), self::resolve( $order_id, $order ) );
	}

	/**
	 * Action Scheduler retry of POST /web/order.
	 *
	 * @param int $order_id Order id.
	 */
	public static function retry_order( $order_id ) {
		self::guard( array( __CLASS__, 'send_order' ), self::resolve( $order_id ), false );
	}

	/**
	 * Action Scheduler retry of POST /web/order/cancel.
	 *
	 * @param int $order_id Order id.
	 */
	public static function retry_cancel( $order_id ) {
		self::guard( array( __CLASS__, 'send_cancel' ), self::resolve( $order_id ) );
	}

	/**
	 * "Send to Sadakio again" in the order actions box.
	 *
	 * @param array $actions Order actions.
	 * @return array
	 */
	public static function order_actions( $actions ) {
		if ( Sadakio_Sadakat_Settings::is_configured() ) {
			$actions['sadakio_sadakat_resend'] = __( 'Sadakio\'ya yeniden gönder', 'sadakio-sadakat' );
		}
		return $actions;
	}

	/**
	 * Handler for the order action. Clears a final failure and sends again;
	 * the API answers a replay without awarding twice.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function manual_resend( $order ) {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( Sadakio_Sadakat_Settings::CAPABILITY ) ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$order->delete_meta_data( self::META_FAILED );
		$order->delete_meta_data( self::META_SKIPPED );
		$order->update_meta_data( self::META_ATTEMPTS, 0 );
		$order->save();
		self::guard( array( __CLASS__, 'send_order' ), $order, true );
	}

	/* ─── Work ──────────────────────────────────────────────────────────── */

	/**
	 * POST /web/order for one order.
	 *
	 * @param WC_Order $order Order.
	 * @param bool     $force Send even when already marked as sent (manual action).
	 */
	public static function send_order( WC_Order $order, $force = false ) {
		if ( ! Sadakio_Sadakat_Settings::is_configured() ) {
			return;
		}
		if ( ! $force && ( $order->get_meta( self::META_SENT ) || $order->get_meta( self::META_FAILED ) || $order->get_meta( self::META_SKIPPED ) ) ) {
			return;
		}
		// A retry that fires after the order was cancelled must not award.
		if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
			return;
		}

		if ( 'TRY' !== strtoupper( (string) $order->get_currency() ) ) {
			$order->update_meta_data( self::META_SKIPPED, 'currency' );
			$order->save();
			/* translators: %s: order currency code */
			$order->add_order_note( sprintf( __( 'Sadakio: sipariş para birimi %s olduğu için gönderilmedi; yalnızca TRY siparişleri işlenir.', 'sadakio-sadakat' ), $order->get_currency() ) );
			return;
		}

		$phone = trim( (string) $order->get_billing_phone() );
		if ( '' === $phone ) {
			$order->update_meta_data( self::META_SKIPPED, 'phone' );
			$order->save();
			$order->add_order_note( __( 'Sadakio: fatura telefonu boş olduğu için sipariş gönderilmedi.', 'sadakio-sadakat' ) );
			return;
		}

		$attempts = (int) $order->get_meta( self::META_ATTEMPTS ) + 1;
		$order->update_meta_data( self::META_ATTEMPTS, $attempts );
		$order->save();

		$payload = array(
			'order_id' => (string) $order->get_id(),
			'phone'    => $phone,
			'amount'   => number_format( (float) $order->get_total(), 2, '.', '' ),
			'currency' => 'TRY',
		);
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( '' !== $name ) {
			$payload['name'] = $name;
		}

		$res = Sadakio_Sadakat_API::from_settings()->order( $payload );

		if ( $res->ok ) {
			$order->update_meta_data( self::META_SENT, time() );
			$order->delete_meta_data( self::META_FAILED );
			$order->save();
			self::unschedule( self::HOOK_RETRY_ORDER, $order->get_id() );
			Sadakio_Sadakat_Balance_Box::forget( $phone );
			$order->add_order_note( self::earned_note( $res ) );
			return;
		}

		self::handle_failure( $order, $res, $attempts, self::HOOK_RETRY_ORDER, self::META_FAILED, __( 'sipariş gönderilemedi', 'sadakio-sadakat' ) );
	}

	/**
	 * POST /web/order/cancel for one order.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function send_cancel( WC_Order $order ) {
		if ( ! Sadakio_Sadakat_Settings::is_configured() ) {
			return;
		}
		if ( $order->get_meta( self::META_CANCEL_SENT ) ) {
			return;
		}
		if ( ! $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
			return;
		}

		// No more order retries for a cancelled order.
		self::unschedule( self::HOOK_RETRY_ORDER, $order->get_id() );

		// Never attempted: Sadakio has nothing to reverse. An attempt that
		// timed out may still have been recorded, so that case does cancel.
		if ( ! $order->get_meta( self::META_SENT ) && (int) $order->get_meta( self::META_ATTEMPTS ) < 1 ) {
			return;
		}

		$attempts = (int) $order->get_meta( self::META_CANCEL_ATTEMPTS ) + 1;
		$order->update_meta_data( self::META_CANCEL_ATTEMPTS, $attempts );
		$order->save();

		$res = Sadakio_Sadakat_API::from_settings()->cancel_order( (string) $order->get_id() );

		if ( $res->ok || 404 === $res->status ) {
			$order->update_meta_data( self::META_CANCEL_SENT, $res->ok ? time() : 'not_found' );
			$order->save();
			self::unschedule( self::HOOK_RETRY_CANCEL, $order->get_id() );
			Sadakio_Sadakat_Balance_Box::forget( (string) $order->get_billing_phone() );

			if ( 404 === $res->status ) {
				$note = __( 'Sadakio: bu sipariş Sadakio\'da kayıtlı değil; geri alınacak bir kazanım yok.', 'sadakio-sadakat' );
			} elseif ( $res->idempotent ) {
				$note = __( 'Sadakio: sipariş daha önce iptal edilmiş; değişiklik yapılmadı.', 'sadakio-sadakat' );
			} else {
				$reversed = isset( $res->data['reversed'] ) ? (int) $res->data['reversed'] : 0;
				/* translators: %d: number of reversed loyalty transactions */
				$note = sprintf( __( 'Sadakio: sipariş iptal edildi, %d kazanım geri alındı.', 'sadakio-sadakat' ), $reversed );
			}
			$order->add_order_note( $note );
			return;
		}

		self::handle_failure( $order, $res, $attempts, self::HOOK_RETRY_CANCEL, '', __( 'iptal gönderilemedi', 'sadakio-sadakat' ) );
	}

	/* ─── Helpers ───────────────────────────────────────────────────────── */

	/**
	 * Schedule a retry or record a final failure, with an order note.
	 *
	 * @param WC_Order                 $order       Order.
	 * @param Sadakio_Sadakat_Response $res         Failed response.
	 * @param int                      $attempts    Attempts so far, including this one.
	 * @param string                   $hook        Retry hook.
	 * @param string                   $failed_meta Meta key that marks a final failure ('' for none).
	 * @param string                   $what        Short label for the note.
	 */
	private static function handle_failure( WC_Order $order, Sadakio_Sadakat_Response $res, $attempts, $hook, $failed_meta, $what ) {
		$retries_done = $attempts - 1;

		if ( $res->is_retryable() && $retries_done < self::MAX_RETRIES && function_exists( 'as_schedule_single_action' ) ) {
			if ( ! self::is_scheduled( $hook, $order->get_id() ) ) {
				$delays = self::RETRY_DELAYS;
				$delay  = isset( $delays[ $retries_done ] ) ? $delays[ $retries_done ] : end( $delays );
				as_schedule_single_action( time() + $delay, $hook, array( (int) $order->get_id() ), self::AS_GROUP );
			}
			/* translators: 1: what failed, 2: error description, 3: retry number, 4: max retries */
			$order->add_order_note( sprintf( __( 'Sadakio: %1$s. %2$s. Yeniden deneme %3$d/%4$d planlandı.', 'sadakio-sadakat' ), $what, $res->describe_error(), $retries_done + 1, self::MAX_RETRIES ) );
			return;
		}

		if ( '' !== $failed_meta ) {
			$order->update_meta_data( $failed_meta, $res->error_code ? $res->error_code : 'error' );
			$order->save();
		}
		/* translators: 1: what failed, 2: error description */
		$order->add_order_note( sprintf( __( 'Sadakio: %1$s. %2$s. Siparişi daha sonra "Sadakio\'ya yeniden gönder" işlemiyle tekrar gönderebilirsiniz.', 'sadakio-sadakat' ), $what, $res->describe_error() ) );
		self::log( 'warning', sprintf( 'Order %d: %s', $order->get_id(), $res->describe_error() ) );
	}

	/**
	 * Order note for a successful POST /web/order, e.g. "Sadakio: 25 puan kazandı."
	 *
	 * @param Sadakio_Sadakat_Response $res Response.
	 * @return string
	 */
	private static function earned_note( Sadakio_Sadakat_Response $res ) {
		if ( $res->idempotent ) {
			return __( 'Sadakio: sipariş daha önce kaydedilmiş; tekrar kazanım verilmedi.', 'sadakio-sadakat' );
		}
		$e     = isset( $res->data['earned'] ) && is_array( $res->data['earned'] ) ? $res->data['earned'] : array();
		$parts = array();
		if ( ! empty( $e['stamps'] ) ) {
			/* translators: %d: stamps earned */
			$parts[] = sprintf( __( '%d damga', 'sadakio-sadakat' ), (int) $e['stamps'] );
		}
		if ( ! empty( $e['points'] ) ) {
			/* translators: %d: points earned */
			$parts[] = sprintf( __( '%d puan', 'sadakio-sadakat' ), (int) $e['points'] );
		}
		if ( isset( $e['cashback'] ) && (float) $e['cashback'] > 0 ) {
			/* translators: %s: cashback amount, e.g. 12.50 */
			$parts[] = sprintf( __( '%s TL cashback', 'sadakio-sadakat' ), (string) $e['cashback'] );
		}
		if ( ! empty( $e['reward'] ) ) {
			/* translators: %s: reward label */
			$parts[] = sprintf( __( 'ödül: %s', 'sadakio-sadakat' ), (string) $e['reward'] );
		}
		if ( ! $parts ) {
			return __( 'Sadakio: sipariş kaydedildi; bu siparişle yeni kazanım olmadı.', 'sadakio-sadakat' );
		}
		/* translators: %s: comma-separated list, e.g. "25 puan" */
		return sprintf( __( 'Sadakio: %s kazandı.', 'sadakio-sadakat' ), implode( ', ', $parts ) );
	}

	/**
	 * Run a callback, swallowing any error so the shop request survives.
	 *
	 * @param callable      $cb    Callback taking ( WC_Order, ...$args ).
	 * @param WC_Order|null $order Order or null.
	 * @param mixed         ...$args Extra args.
	 */
	private static function guard( $cb, $order, ...$args ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		try {
			call_user_func( $cb, $order, ...$args );
		} catch ( \Throwable $e ) {
			self::log( 'error', sprintf( 'Order %d: %s', $order->get_id(), $e->getMessage() ) );
		}
	}

	/**
	 * @param int           $order_id Order id.
	 * @param WC_Order|null $order    Order passed by the hook, if any.
	 * @return WC_Order|null
	 */
	private static function resolve( $order_id, $order = null ) {
		if ( $order instanceof WC_Order ) {
			return $order;
		}
		$o = wc_get_order( $order_id );
		return $o instanceof WC_Order ? $o : null;
	}

	/**
	 * @param string $hook     Hook.
	 * @param int    $order_id Order id.
	 * @return bool
	 */
	private static function is_scheduled( $hook, $order_id ) {
		// Pending only: as_next_scheduled_action() also counts the action that
		// is running right now, which would stop a retry from scheduling the next one.
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}
		$ids = as_get_scheduled_actions(
			array(
				'hook'     => $hook,
				'args'     => array( (int) $order_id ),
				'group'    => self::AS_GROUP,
				'status'   => 'pending',
				'per_page' => 1,
			),
			'ids'
		);
		return ! empty( $ids );
	}

	/**
	 * @param string $hook     Hook.
	 * @param int    $order_id Order id.
	 */
	private static function unschedule( $hook, $order_id ) {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, array( (int) $order_id ), self::AS_GROUP );
		}
	}

	/**
	 * WooCommerce logger (WooCommerce → Durum → Günlükler, source sadakio-sadakat).
	 * Never receives the API key.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 */
	private static function log( $level, $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => 'sadakio-sadakat' ) );
		}
	}
}
