<?php
/** Remove stored traffic when the plugin is deleted in WordPress. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$activity_table = $wpdb->prefix . 'utm_activity_log';
$wpdb->query( "DROP TABLE IF EXISTS {$activity_table}" );
$table = $wpdb->prefix . 'utm_traffic_recorder';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$click_table = $wpdb->prefix . 'utm_traffic_wa_clicks';
$wpdb->query( "DROP TABLE IF EXISTS {$click_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_clear_scheduled_hook( 'utr_daily_cleanup' );
wp_clear_scheduled_hook( 'utr_sheets_sync' );
wp_clear_scheduled_hook( 'utr_webhook_sync' );
delete_option( 'utr_webhook_config' );
delete_option( 'utr_webhook_cursor' );
delete_option( 'utr_webhook_state' );
delete_option( 'utr_webhook_lock' );
delete_site_transient( 'utr_github_release' );
delete_option( 'utr_sheets_config' );
delete_option( 'utr_sheets_cursor' );
delete_option( 'utr_sheets_state' );
delete_option( 'utr_sheets_lock' );
delete_option( 'utr_whatsapp_settings' );
delete_option( 'utr_loaded_version' );
delete_option( 'utr_wa_accounts' );
delete_option( 'utr_wa_schema_version' );
delete_option( 'utr_wa_next_account_id' );
delete_option( 'utr_hide_utm_url' );
delete_option( 'utr_schema_version' );
delete_option( 'utr_recorded_utm_fields' );
delete_option( 'utr_wa_source_mode' );
delete_option( 'utr_track_external_wa_links' );
