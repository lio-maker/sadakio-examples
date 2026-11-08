<?php
/**
 * Removes the plugin's settings, cached balances and pending retries.
 * Order notes and order meta stay: they are the shop's order history.
 *
 * @package SadakioSadakat
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sadakio_sadakat_settings' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_sadakio_sadakat_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_sadakio_sadakat_' ) . '%'
	)
);

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sadakio_sadakat_retry_order', array(), 'sadakio-sadakat' );
	as_unschedule_all_actions( 'sadakio_sadakat_retry_cancel', array(), 'sadakio-sadakat' );
}
