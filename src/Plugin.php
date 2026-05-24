<?php
declare(strict_types=1);
namespace TinifyAI;

class Plugin
{
    private Settings     $settings;
    private MetaManager  $meta;
    private Scheduler    $scheduler;
    private ApiClient    $api;
    private Processor    $processor;
    private Replacer     $replacer;

    public function init(): void
    {
        $this->settings  = new Settings();
        $this->meta      = new MetaManager();
        $this->scheduler = new Scheduler($this->meta);
        $apiKey          = $this->settings->getApiKey();
        $this->api       = new ApiClient($apiKey ?? '');
        $this->replacer  = new Replacer($this->meta);
        $this->processor = new Processor($this->api, $this->meta, $this->replacer, $this->scheduler, $this->settings);

        // Settings
        add_action('admin_init',  [$this->settings, 'register']);
        add_action('admin_menu',  [$this, 'registerAdminPages']);

        // Upload hook
        add_filter('wp_generate_attachment_metadata', [$this->scheduler, 'queueOnUpload'], 10, 2);

        // ActionScheduler action handler
        add_action(Scheduler::ACTION_HOOK, [$this->processor, 'run']);

        // Media Library
        $mediaLibrary = new MediaLibrary($this->meta, $this->scheduler, $this->settings);
        add_filter('manage_media_columns',              [$mediaLibrary, 'addStatusColumn']);
        add_action('manage_media_custom_column',        [$mediaLibrary, 'renderStatusColumn'], 10, 2);
        add_action('attachment_submitbox_misc_actions', [$mediaLibrary, 'renderAttachmentPanel']);
        add_action('wp_ajax_tinify_optimize_single',   [$mediaLibrary, 'handleAjaxOptimizeSingle']);

        // Bulk optimizer AJAX
        $bulkOptimizer = new BulkOptimizer($this->meta, $this->scheduler, $this->settings, $this->api);
        add_action('wp_ajax_tinify_bulk_queue',  [$bulkOptimizer, 'handleAjaxBulkQueue']);
        add_action('wp_ajax_tinify_bulk_status', [$bulkOptimizer, 'handleAjaxBulkStatus']);

        // Admin notices
        add_action('admin_notices', [$this, 'showCreditsExhaustedNotice']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerAdminPages(): void
    {
        add_options_page(
            esc_html__('tinify.ai', 'tinify-ai'),
            esc_html__('tinify.ai', 'tinify-ai'),
            'manage_options',
            'tinify-ai',
            [$this->settings, 'renderPage']
        );
        add_media_page(
            esc_html__('Bulk Optimize — tinify.ai', 'tinify-ai'),
            esc_html__('Bulk Optimize', 'tinify-ai'),
            'upload_files',
            'tinify-ai-bulk',
            [new BulkOptimizer($this->meta, $this->scheduler, $this->settings, $this->api), 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['upload.php', 'settings_page_tinify-ai', 'media_page_tinify-ai-bulk'], true)) return;
        wp_enqueue_script('tinify-ai-admin', plugin_dir_url(TINIFY_AI_FILE) . 'assets/admin.js', ['jquery'], '1.0.0', true);
        wp_enqueue_style('tinify-ai-admin',  plugin_dir_url(TINIFY_AI_FILE) . 'assets/admin.css', [], '1.0.0');
        wp_localize_script('tinify-ai-admin', 'tinifyAi', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tinify_ajax'),
        ]);
    }

    public function showCreditsExhaustedNotice(): void
    {
        $resetAt = get_transient('tinify_credits_reset_at');
        if (!$resetAt) return;
        $date = (new \DateTimeImmutable($resetAt))->format(get_option('date_format'));
        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            sprintf(
                esc_html__('tinify.ai: You\'ve run out of credits. Paused jobs will resume automatically on %1$s. %2$s', 'tinify-ai'),
                esc_html($date),
                '<a href="https://tinify.ai/pricing" target="_blank">' . esc_html__('Upgrade Plan', 'tinify-ai') . ' ↗</a>'
            )
        );
    }
}
