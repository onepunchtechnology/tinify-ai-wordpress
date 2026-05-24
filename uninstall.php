<?php
if ( ! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Delete all plugin options
$options = [
	'tinify_api_key',
	'tinify_auto_optimize',
	'tinify_seo_alt_text',
	'tinify_optimize_thumbnails',
	'tinify_pipeline_settings',
];
foreach ($options as $option) {
	delete_option($option);
}
delete_transient('tinify_account_cache');
delete_transient('tinify_credits_reset_at');
delete_transient('tinify_bulk_status_cache');

// Delete .tinify-orig backup files before removing the meta that points to them
global $wpdb;
$backup_paths = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_tinify_orig_backup'"
);
foreach ($backup_paths as $backup_path) {
	if ($backup_path && file_exists($backup_path)) {
		wp_delete_file($backup_path);
	}
}

// Delete all attachment post meta
$meta_keys = [
	'_tinify_status',
	'_tinify_job_id',
	'_tinify_original_size',
	'_tinify_processed_size',
	'_tinify_savings_pct',
	'_tinify_optimized_at',
	'_tinify_error',
	'_tinify_orig_backup',
];
foreach ($meta_keys as $key) {
	delete_post_meta_by_key($key);
}

// Cancel all pending ActionScheduler actions
if (function_exists('as_unschedule_all_actions')) {
	as_unschedule_all_actions('tinify_ai/process_attachment', [], 'tinify_ai');
}
