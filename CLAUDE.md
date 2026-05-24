# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer install          # install PHP deps (ActionScheduler + dev tools)
composer test             # run PHPUnit with --testdox
composer lint             # PHPCS against WordPress standard
./vendor/bin/phpcbf --standard=phpcs.xml.dist src/   # auto-fix style
./vendor/bin/phpunit --filter TestClassName           # run one test class
```

PHP 8.1+ and Composer are required. There is no JS build step — `assets/` are plain JS/CSS.

## Architecture

### Entry point and wiring

`tinify-ai.php` bootstraps on `plugins_loaded`. It requires Composer's autoloader and the ActionScheduler library, defines `TINIFY_AI_FILE`, then calls `Plugin::init()`.

`Plugin::init()` is the single wiring point — it constructs all classes and registers every WordPress hook. Nothing registers hooks outside of `Plugin`.

### Processing pipeline

An image attachment flows through these stages, always in a background ActionScheduler job:

```
Upload          → ApiClient::upload()         returns temp_file_id
Process         → ApiClient::process()        returns job_id
Poll            → ApiClient::pollJob()        blocks until status=completed (max 5 min)
Download        → ApiClient::downloadProcessedFile()
Atomic swap     → Replacer::swap()            copy+rename, keeps .tinify-orig backup
Save results    → MetaManager::saveResults()  writes 5 meta fields + status=completed
```

`Processor::run(int $attachmentId)` owns the pipeline. Upload retries 3 times before failing. A 429 from `process()` throws `InsufficientCreditsException`, which pauses all pending jobs and reschedules them for 5 minutes after the credits reset date (stored in a transient for the admin notice).

### Scheduling

`Scheduler` wraps ActionScheduler. Hook: `tinify_ai/process_attachment`, group: `tinify_ai`.

`Scheduler::queueOnUpload()` is hooked to `wp_generate_attachment_metadata` (a filter that must return `$metadata` unchanged). It contains a re-entrant guard: if the attachment status is already `completed`, `processing`, or `pending` it returns early. This prevents an infinite loop because `Replacer::swap()` calls `wp_generate_attachment_metadata()` to regenerate thumbnails after replacing the file.

### Status lifecycle

All status is stored in `_tinify_*` attachment post meta, managed exclusively by `MetaManager`:

```
(none) → pending → processing → completed
                              → failed
                              → paused   (credits exhausted; auto-resumes)
```

Key meta keys: `_tinify_status`, `_tinify_job_id`, `_tinify_original_size`, `_tinify_processed_size`, `_tinify_savings_pct`, `_tinify_optimized_at`, `_tinify_error`, `_tinify_orig_backup`.

Alt text goes to `_wp_attachment_image_alt` (the standard WP key) when SEO alt text is enabled.

### Settings and API key encryption

`Settings` stores the API key encrypted with AES-256-CBC using `AUTH_KEY . SECURE_AUTH_KEY` as key material. `getApiKey()` decrypts on read. The settings page shows a masked placeholder when a key is saved so the real key is never echoed into HTML.

Pipeline options (output format, resize dimensions, resize behavior) are stored as a single serialized array under `tinify_pipeline_settings`.

### Admin UI

- **Settings page** (`/wp-admin/options-general.php?page=tinify-ai`): API key, auto-optimize toggle, SEO alt text toggle. Renders account tier + credits from a transient cache.
- **Bulk Optimize page** (`/wp-admin/upload.php?page=tinify-ai-bulk`): queues all unoptimized attachments via AJAX, polls status every 3 s, shows a progress bar. Status counts are cached in a 2 s transient to rate-limit DB hits.
- **Media Library column**: "tinify.ai" column shows savings %, processing state, or Optimize/Retry button per image.
- **Attachment edit panel**: `attachment_submitbox_misc_actions` hook renders a panel with size stats and Re-optimize button.

All AJAX handlers (`tinify_optimize_single`, `tinify_bulk_queue`, `tinify_bulk_status`) verify nonce `tinify_ajax` and capability `upload_files`.

### Security

- **Path traversal guard** in `Replacer::swap()`: resolves `realpath()` on the destination directory and asserts it is inside the WP uploads dir before writing.
- **MIME validation** in `Replacer::swap()`: calls `wp_check_filetype_and_ext()` on the downloaded tmp file against an allowlist of 6 image MIME types; deletes the tmp file and throws on failure.
- **SQL**: `BulkOptimizer::getUnoptimizedAttachmentIds()` uses only hardcoded string literals in `$statuses` (no user input); suppressed with `phpcs:disable/enable`.

## Testing

Tests use PHPUnit 10 + Brain\Monkey 2.6 (backed by Mockery) to mock WordPress functions without a real WP install. `patchwork.json` lists the PHP core functions (`file_exists`, `copy`, `rename`, etc.) that Patchwork allows mocking.

`tests/bootstrap.php` defines the WP constants needed by the classes under test (`DAY_IN_SECONDS`, `AUTH_KEY`, etc.).

The test suite is unit-only — no integration tests against a real database or WP install.

## Code standards

`phpcs.xml.dist` runs `WordPress-Core` + `WordPress-Extra` with a narrow exclusion list (PSR-4 class naming, short array syntax, brace/parenthesis style, doc comment requirements). All `phpcs:ignore` comments explain why the suppression is safe.
