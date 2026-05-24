# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin that integrates Tinify AI image optimization into WordPress. This repository is newly initialized — update this file with architecture details once the plugin structure is established.

## WordPress Plugin Development

### File Structure Convention
Follow standard WordPress plugin structure:
- Main plugin file at root: `tinify-ai-wordpress.php` (contains plugin header)
- `includes/` — core PHP classes and business logic
- `admin/` — WordPress admin UI (settings pages, meta boxes)
- `public/` — frontend assets (JS, CSS served to visitors)
- `languages/` — `.pot`/`.po` files for i18n

### Local Development
Use a local WordPress environment (LocalWP, DDEV, or Docker). After setup, symlink or copy this plugin into `wp-content/plugins/tinify-ai-wordpress/`.

Enable WordPress debug mode in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Testing
WordPress plugin testing uses PHPUnit with the WordPress test suite:
```bash
# Install test suite (first time)
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> localhost latest

# Run all tests
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/test-specific-file.php
```

### Linting / Code Standards
WordPress coding standards via PHP_CodeSniffer:
```bash
# Install
composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs

# Lint
./vendor/bin/phpcs --standard=WordPress .

# Auto-fix
./vendor/bin/phpcbf --standard=WordPress .
```

## Code Conventions

- Hook naming: `tinify_ai_` prefix on all custom hooks (actions and filters)
- Option names: `tinify_ai_` prefix (stored via `get_option`/`update_option`)
- Namespace: `TinifyAI\` for PHP classes
- Escape all output with `esc_html()`, `esc_attr()`, `esc_url()` — never echo raw values
- Nonce-verify all form submissions and AJAX handlers
- Sanitize all input with WordPress sanitize functions before storing
- Use `wp_remote_get`/`wp_remote_post` for HTTP requests to Tinify AI API (not curl directly)

## Tinify AI API Integration

The plugin communicates with the Tinify AI service. Store the API key in WordPress options (not hardcoded). Use `wp_remote_post` with proper error handling via `is_wp_error()`.

## Build / Assets

(Update when build toolchain is chosen — e.g., `npm run build` for webpack/Vite, `composer install` for PHP deps.)
