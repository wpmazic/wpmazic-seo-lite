<?php
/**
 * Uninstall cleanup for WPMazic SEO Lite.
 *
 * @package WPMazic_SEO_Lite
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin data for a single site.
 *
 * @return void
 */
function wpmazic_seo_lite_uninstall_site_data() {
	global $wpdb;

	delete_option( 'wpmazic_settings' );
	delete_option( 'wpmazic_db_version' );
	delete_option( 'wpmazic_show_migration_wizard' );
	delete_option( 'wpmazic_migration_wizard_completed' );
	delete_option( 'wpmazic_robots_txt' );
	delete_option( 'wpmazic_llms_txt' );
	delete_option( 'wpmazic_last_search_ping' );

	$postmeta_like = $wpdb->esc_like( '_wpmazic_' ) . '%';

	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_wpmazic\_%'
		   OR option_name LIKE '_transient_timeout_wpmazic\_%'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	);

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmazic_seo_redirects" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmazic_seo_404" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmazic_seo_links" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmazic_seo_indexnow" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmazic_redirects" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmazic_404" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmazic_links" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmazic_indexnow" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$postmeta_like
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s, %s)",
			'wpmazic_author_job_title',
			'wpmazic_author_expertise',
			'wpmazic_author_sameas'
		)
	);
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		wpmazic_seo_lite_uninstall_site_data();
		restore_current_blog();
	}
} else {
	wpmazic_seo_lite_uninstall_site_data();
}
