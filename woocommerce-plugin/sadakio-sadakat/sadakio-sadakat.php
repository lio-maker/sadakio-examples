<?php
/**
 * Plugin Name:          Sadakio Sadakat
 * Plugin URI:           https://github.com/lio-maker/sadakio-examples/tree/main/woocommerce-plugin/sadakio-sadakat
 * Description:          WooCommerce siparişlerinizi Sadakio sadakat programınıza bağlar: ödenen siparişler puan/damga kazandırır, iptal ve iadeler geri alınır, müşteriler bakiyesini görür.
 * Version:              0.1.0
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      9.4
 * Author:               Sadakio
 * Author URI:           https://sadakio.com
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          sadakio-sadakat
 * Domain Path:          /languages
 *
 * @package SadakioSadakat
 */

defined( 'ABSPATH' ) || exit;

define( 'SADAKIO_SADAKAT_VERSION', '0.1.0' );
define( 'SADAKIO_SADAKAT_FILE', __FILE__ );
define( 'SADAKIO_SADAKAT_DIR', plugin_dir_path( __FILE__ ) );

require_once SADAKIO_SADAKAT_DIR . 'includes/class-sadakio-sadakat-settings.php';
require_once SADAKIO_SADAKAT_DIR . 'includes/class-sadakio-sadakat-api.php';
require_once SADAKIO_SADAKAT_DIR . 'includes/class-sadakio-sadakat-orders.php';
require_once SADAKIO_SADAKAT_DIR . 'includes/class-sadakio-sadakat-balance-box.php';

/*
 * HPOS: every order read/write goes through WC_Order getters and
 * update_meta_data(), never post meta, so the plugin works with both the
 * custom order tables and the legacy posts storage.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SADAKIO_SADAKAT_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', 'sadakio_sadakat_boot', 20 );

/**
 * Wire everything once WooCommerce is known to be loaded.
 */
function sadakio_sadakat_boot() {
	load_plugin_textdomain( 'sadakio-sadakat', false, dirname( plugin_basename( SADAKIO_SADAKAT_FILE ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'sadakio_sadakat_missing_wc_notice' );
		return;
	}

	Sadakio_Sadakat_Settings::init();
	Sadakio_Sadakat_Orders::init();
	Sadakio_Sadakat_Balance_Box::init();
}

/**
 * Admin notice shown when WooCommerce is not active.
 */
function sadakio_sadakat_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'Sadakio Sadakat çalışmak için WooCommerce eklentisine ihtiyaç duyar. Lütfen WooCommerce\'i etkinleştirin.', 'sadakio-sadakat' )
		. '</p></div>';
}

/**
 * Settings link on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function sadakio_sadakat_action_links( $links ) {
	$url = admin_url( 'admin.php?page=' . Sadakio_Sadakat_Settings::PAGE );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Ayarlar', 'sadakio-sadakat' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'sadakio_sadakat_action_links' );
