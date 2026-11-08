<?php
/**
 * Thin client for the Sadakio Public API v1 web-loyalty endpoints.
 *
 * Every call returns a Sadakio_Sadakat_Response and never throws, so a
 * Sadakio outage can never surface as a fatal error in a shop request.
 *
 * Endpoints used in 0.1.0:
 *   POST /web/order         (write scope)
 *   POST /web/order/cancel  (write scope)
 *   GET  /web/balance       (read scope)
 *
 * POST /web/redeem (checkout discount) is intentionally not wired yet. When
 * it is, it is one more method here calling request( 'POST', '/web/redeem',
 * array( 'redeem_id' => ..., 'phone' => ..., 'kind' => ..., 'amount' => ... ) ).
 *
 * @package SadakioSadakat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalised outcome of one API call.
 */
class Sadakio_Sadakat_Response {

	/** @var bool True on a 2xx answer. */
	public $ok = false;

	/** @var int HTTP status, 0 when the request never reached the server. */
	public $status = 0;

	/** @var array The response "data" object on success. */
	public $data = array();

	/** @var bool True when the server answered a replay ("idempotent": true). */
	public $idempotent = false;

	/** @var string Machine error code ("network_error", "not_found", ...). */
	public $error_code = '';

	/** @var string Human-readable error message. */
	public $error_message = '';

	/**
	 * Worth retrying later: the network failed, the server erred (5xx) or
	 * rate-limited us (429). Other 4xx answers will not change on retry.
	 *
	 * @return bool
	 */
	public function is_retryable() {
		if ( $this->ok ) {
			return false;
		}
		return 0 === $this->status || 429 === $this->status || $this->status >= 500;
	}

	/**
	 * One-line description for order notes and admin notices.
	 *
	 * @return string
	 */
	public function describe_error() {
		if ( 0 === $this->status ) {
			/* translators: %s: transport error message */
			return sprintf( __( 'Sunucuya ulaşılamadı (%s)', 'sadakio-sadakat' ), $this->error_message );
		}
		/* translators: 1: HTTP status code, 2: error code, 3: error message */
		return sprintf( __( 'HTTP %1$d %2$s: %3$s', 'sadakio-sadakat' ), $this->status, $this->error_code, $this->error_message );
	}
}

/**
 * HTTP client.
 */
class Sadakio_Sadakat_API {

	const DEFAULT_BASE_URL = 'https://app.sadakio.com/api/v1';
	const TIMEOUT          = 5;

	/** @var string */
	private $api_key;

	/** @var string */
	private $base_url;

	/**
	 * @param string $api_key  Bearer key from the Sadakio panel.
	 * @param string $base_url API root without trailing slash.
	 */
	public function __construct( $api_key, $base_url = self::DEFAULT_BASE_URL ) {
		$this->api_key  = (string) $api_key;
		$this->base_url = untrailingslashit( $base_url ? $base_url : self::DEFAULT_BASE_URL );
	}

	/**
	 * Client built from the saved settings.
	 *
	 * @return self
	 */
	public static function from_settings() {
		return new self( Sadakio_Sadakat_Settings::get_api_key(), Sadakio_Sadakat_Settings::get_base_url() );
	}

	/**
	 * Record one paid order.
	 *
	 * @param array $payload order_id, phone, amount, name, currency.
	 * @return Sadakio_Sadakat_Response
	 */
	public function order( array $payload ) {
		return $this->request( 'POST', '/web/order', $payload );
	}

	/**
	 * Reverse what an order earned.
	 *
	 * @param string $order_id The id sent with order().
	 * @return Sadakio_Sadakat_Response
	 */
	public function cancel_order( $order_id ) {
		return $this->request( 'POST', '/web/order/cancel', array( 'order_id' => (string) $order_id ) );
	}

	/**
	 * Current balance of the guest with this phone.
	 *
	 * @param string $phone Phone as the shopper typed it.
	 * @return Sadakio_Sadakat_Response
	 */
	public function balance( $phone ) {
		return $this->request( 'GET', '/web/balance', null, array( 'phone' => (string) $phone ) );
	}

	/**
	 * Perform one request.
	 *
	 * @param string     $method GET or POST.
	 * @param string     $path   Path under the base URL, with leading slash.
	 * @param array|null $body   JSON body for POST.
	 * @param array      $query  Query parameters.
	 * @return Sadakio_Sadakat_Response
	 */
	public function request( $method, $path, $body = null, array $query = array() ) {
		$res = new Sadakio_Sadakat_Response();

		if ( '' === $this->api_key ) {
			$res->error_code    = 'not_configured';
			$res->error_message = __( 'API anahtarı girilmemiş.', 'sadakio-sadakat' );
			return $res;
		}

		$url = $this->base_url . $path;
		if ( $query ) {
			// http_build_query encodes "+" in phone numbers as %2B.
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$args = array(
			'method'      => $method,
			'timeout'     => self::TIMEOUT,
			'redirection' => 0,
			// No site URL in the user agent: the plugin sends nothing it does not need.
			'user-agent'  => 'SadakioSadakat/' . SADAKIO_SADAKAT_VERSION,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$raw = wp_remote_request( $url, $args );

		if ( is_wp_error( $raw ) ) {
			$res->error_code    = 'network_error';
			$res->error_message = $raw->get_error_message();
			return $res;
		}

		$res->status = (int) wp_remote_retrieve_response_code( $raw );
		$json        = json_decode( (string) wp_remote_retrieve_body( $raw ), true );
		$json        = is_array( $json ) ? $json : array();

		if ( $res->status >= 200 && $res->status < 300 ) {
			$res->ok         = true;
			$res->data       = isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : array();
			$res->idempotent = ! empty( $json['idempotent'] );
			return $res;
		}

		if ( isset( $json['error'] ) && is_array( $json['error'] ) ) {
			$res->error_code    = isset( $json['error']['code'] ) ? (string) $json['error']['code'] : 'error';
			$res->error_message = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : '';
		} else {
			$res->error_code    = 'http_error';
			$res->error_message = (string) wp_remote_retrieve_response_message( $raw );
		}
		return $res;
	}
}
