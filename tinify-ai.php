<?php
/**
 * Plugin Name:       tinify.ai — AI Image Optimization
 * Plugin URI:        https://tinify.ai
 * Description:       Automatically optimize images using the full tinify.ai pipeline: upscale, resize, compress, and AI-generated alt text.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            tinify.ai
 * Author URI:        https://tinify.ai
 * License:           GPL-2.0-only
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tinify-ai
 *
 * == External Services ==
 * This plugin sends image data to api.tinify.ai for processing.
 * See https://tinify.ai/privacy for the privacy policy.
 */

declare(strict_types=1);

if ( ! defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Boot ActionScheduler
require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

define('TINIFY_AI_FILE', __FILE__);

add_action('plugins_loaded', static function (): void {
	( new \TinifyAI\Plugin() )->init();
});
