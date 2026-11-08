<?php
/**
 * Settings page under WooCommerce → Sadakio.
 *
 * Plain admin-post forms with nonces and a manage_woocommerce check; no
 * JavaScript, so the API key never reaches the browser. After saving, the
 * key is only ever shown as its last four characters.
 *
 * @package SadakioSadakat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage and admin screen.
 */
class Sadakio_Sadakat_Settings {

	const OPTION     = 'sadakio_sadakat_settings';
	const PAGE       = 'sadakio-sadakat';
	const CAPABILITY = 'manage_woocommerce';
	const TEST_PHONE = '+900000000000';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 60 );
		add_action( 'admin_post_sadakio_sadakat_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_sadakio_sadakat_test', array( __CLASS__, 'handle_test' ) );
	}

	/**
	 * Raw stored settings.
	 *
	 * @return array
	 */
	private static function all() {
		$opt = get_option( self::OPTION, array() );
		return is_array( $opt ) ? $opt : array();
	}

	/**
	 * @return string
	 */
	public static function get_api_key() {
		$opt = self::all();
		return isset( $opt['api_key'] ) ? (string) $opt['api_key'] : '';
	}

	/**
	 * @return string
	 */
	public static function get_base_url() {
		$opt = self::all();
		$url = isset( $opt['base_url'] ) ? (string) $opt['base_url'] : '';
		return '' !== $url ? $url : Sadakio_Sadakat_API::DEFAULT_BASE_URL;
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::get_api_key();
	}

	/**
	 * The key as it may be shown: dots plus the last four characters.
	 *
	 * @return string
	 */
	public static function masked_key() {
		$key = self::get_api_key();
		if ( '' === $key ) {
			return '';
		}
		return '••••' . substr( $key, -4 );
	}

	/**
	 * Submenu entry.
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Sadakio Sadakat', 'sadakio-sadakat' ),
			__( 'Sadakio', 'sadakio-sadakat' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Settings URL, optionally with a notice flag.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	private static function page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Save handler (admin-post).
	 */
	public static function handle_save() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'sadakio-sadakat' ), 403 );
		}
		check_admin_referer( 'sadakio_sadakat_save' );

		$opt = self::all();

		// An empty key field means "keep the current key"; the clear box removes it.
		$new_key = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		if ( ! empty( $_POST['clear_key'] ) ) {
			$opt['api_key'] = '';
		} elseif ( '' !== $new_key ) {
			$opt['api_key'] = $new_key;
		}

		$base   = isset( $_POST['base_url'] ) ? trim( esc_url_raw( wp_unslash( $_POST['base_url'] ), array( 'https', 'http' ) ) ) : '';
		$notice = 'saved';
		if ( '' === $base ) {
			$opt['base_url'] = Sadakio_Sadakat_API::DEFAULT_BASE_URL;
		} else {
			$opt['base_url'] = untrailingslashit( $base );
			if ( 0 !== strpos( $opt['base_url'], 'https://' ) ) {
				$notice = 'saved_http';
			}
		}

		update_option( self::OPTION, $opt, false );
		Sadakio_Sadakat_Balance_Box::flush_cache();

		wp_safe_redirect( self::page_url( array( 'sadakio_notice' => $notice ) ) );
		exit;
	}

	/**
	 * "Test connection" handler (admin-post). Calls GET /web/balance with a
	 * dummy phone; any 2xx means the key and base URL work.
	 */
	public static function handle_test() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'sadakio-sadakat' ), 403 );
		}
		check_admin_referer( 'sadakio_sadakat_test' );

		$res = Sadakio_Sadakat_API::from_settings()->balance( self::TEST_PHONE );

		set_transient(
			'sadakio_sadakat_test_' . get_current_user_id(),
			array(
				'ok'      => $res->ok,
				'message' => $res->ok ? '' : ( 'not_configured' === $res->error_code ? $res->error_message : $res->describe_error() ),
			),
			120
		);

		wp_safe_redirect( self::page_url( array( 'sadakio_notice' => 'tested' ) ) );
		exit;
	}

	/**
	 * Notices after a redirect.
	 */
	private static function render_notices() {
		// Display-only flag set by our own redirect; nothing is changed based on it.
		$notice = isset( $_GET['sadakio_notice'] ) ? sanitize_key( wp_unslash( $_GET['sadakio_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'saved' === $notice || 'saved_http' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ayarlar kaydedildi.', 'sadakio-sadakat' ) . '</p></div>';
		}
		if ( 'saved_http' === $notice ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'API adresi https ile başlamıyor. Canlı mağazada yalnızca https kullanın; http yalnızca yerel test içindir.', 'sadakio-sadakat' ) . '</p></div>';
		}
		if ( 'tested' === $notice ) {
			$key    = 'sadakio_sadakat_test_' . get_current_user_id();
			$result = get_transient( $key );
			delete_transient( $key );
			if ( is_array( $result ) && ! empty( $result['ok'] ) ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Bağlantı başarılı: Sadakio API anahtarınızı kabul etti.', 'sadakio-sadakat' ) . '</p></div>';
			} elseif ( is_array( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Bağlantı kurulamadı:', 'sadakio-sadakat' ) . ' ' . esc_html( (string) $result['message'] ) . '</p></div>';
			}
		}
	}

	/**
	 * The settings screen.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$masked = self::masked_key();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sadakio Sadakat', 'sadakio-sadakat' ); ?></h1>
			<?php self::render_notices(); ?>
			<p><?php esc_html_e( 'Ödenen WooCommerce siparişleri, müşterinin fatura telefonuyla Sadakio sadakat programınıza işlenir. İptal edilen veya tamamen iade edilen siparişlerin kazanımları geri alınır.', 'sadakio-sadakat' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sadakio_sadakat_save" />
				<?php wp_nonce_field( 'sadakio_sadakat_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sadakio-api-key"><?php esc_html_e( 'API anahtarı', 'sadakio-sadakat' ); ?></label></th>
						<td>
							<input type="password" id="sadakio-api-key" name="api_key" class="regular-text" value="" autocomplete="off"
								placeholder="<?php echo esc_attr( $masked ? $masked : __( 'Anahtarınızı yapıştırın', 'sadakio-sadakat' ) ); ?>" />
							<?php if ( $masked ) : ?>
								<p class="description">
									<?php
									/* translators: %s: last four characters of the saved key, e.g. ••••ab12 */
									echo esc_html( sprintf( __( 'Kayıtlı anahtar: %s. Değiştirmek için yenisini yazın; boş bırakırsanız mevcut anahtar korunur.', 'sadakio-sadakat' ), $masked ) );
									?>
								</p>
								<p><label><input type="checkbox" name="clear_key" value="1" /> <?php esc_html_e( 'Kayıtlı anahtarı sil', 'sadakio-sadakat' ); ?></label></p>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Anahtarı Sadakio panelinde Ayarlar → API bölümünden oluşturabilirsiniz. Siparişlerin işlenebilmesi için anahtarın yazma (write) izni olmalıdır.', 'sadakio-sadakat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sadakio-base-url"><?php esc_html_e( 'API adresi', 'sadakio-sadakat' ); ?></label></th>
						<td>
							<input type="url" id="sadakio-base-url" name="base_url" class="regular-text" value="<?php echo esc_attr( self::get_base_url() ); ?>" />
							<p class="description">
								<?php
								/* translators: %s: default API base URL */
								echo esc_html( sprintf( __( 'Varsayılan: %s. Sadakio aksini söylemedikçe değiştirmeyin.', 'sadakio-sadakat' ), Sadakio_Sadakat_API::DEFAULT_BASE_URL ) );
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Kaydet', 'sadakio-sadakat' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Bağlantı testi', 'sadakio-sadakat' ); ?></h2>
			<p><?php esc_html_e( 'Kayıtlı anahtar ve adresle Sadakio\'ya örnek bir bakiye sorgusu gönderir. Hiçbir müşteri kaydı oluşturulmaz.', 'sadakio-sadakat' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sadakio_sadakat_test" />
				<?php wp_nonce_field( 'sadakio_sadakat_test' ); ?>
				<?php submit_button( __( 'Bağlantıyı test et', 'sadakio-sadakat' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
