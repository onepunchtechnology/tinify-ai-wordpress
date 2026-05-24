=== tinify.ai — AI Image Optimization ===
Contributors: tinifyai
Tags: image optimization, compress images, webp, seo, alt text
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-only
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically optimize images with the full tinify.ai pipeline: upscale, resize, compress, and AI-generated alt text.

== Description ==

tinify.ai optimizes every image in your WordPress Media Library using a four-step AI pipeline:

* **Upscale** — Enhance resolution with AI (Real-ESRGAN)
* **Resize** — Fit to your configured max dimensions
* **Compress** — Smart compression via TinyPNG
* **Tag** — AI-generated SEO alt text written back to your attachment

Works automatically on new uploads. Bulk optimize your existing library from Media → Bulk Optimize.

== External Services ==

This plugin sends image data to api.tinify.ai for processing. By using this plugin, you agree to the tinify.ai Terms of Service (https://tinify.ai/terms) and Privacy Policy (https://tinify.ai/privacy). An API key from tinify.ai is required.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/tinify-ai`
2. Activate the plugin
3. Go to Settings → tinify.ai
4. Follow the on-screen instructions to connect your tinify.ai account

== Changelog ==

= 1.0.0 =
* Initial release
