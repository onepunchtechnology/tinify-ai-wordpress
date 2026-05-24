<?php
declare(strict_types=1);
namespace TinifyAI;

class BulkOptimizer
{
    public function __construct(
        private readonly MetaManager $meta,
        private readonly Scheduler   $scheduler,
        private readonly Settings    $settings,
        private readonly ApiClient   $api,
    ) {}

    public function renderPage(): void
    {
        $counts  = $this->getStatusCounts();
        $apiKey  = $this->settings->getApiKey();
        $account = $apiKey ? get_transient('tinify_account_cache') : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bulk Optimize — tinify.ai', 'tinify-ai'); ?></h1>

            <?php if ($account): ?>
            <p><?php printf(
                esc_html__('%s plan · %s credits remaining', 'tinify-ai'),
                esc_html(ucfirst($account['tier'])),
                esc_html(number_format($account['credits_remaining']))
            ); ?></p>
            <?php endif; ?>

            <div id="tinify-progress-bar" style="display:none; margin: 1em 0;">
                <progress id="tinify-progress" max="<?php echo esc_attr($counts['total']); ?>"
                          value="<?php echo esc_attr($counts['completed']); ?>" style="width:100%;height:20px;"></progress>
                <p id="tinify-progress-text"></p>
            </div>

            <p>
                <strong><?php printf(esc_html__('✓ Optimized: %d', 'tinify-ai'), $counts['completed']); ?></strong>
                &nbsp;|&nbsp;
                <?php printf(esc_html__('Pending: %d', 'tinify-ai'), $counts['pending'] + $counts['processing']); ?>
                &nbsp;|&nbsp;
                <?php printf(esc_html__('Failed: %d', 'tinify-ai'), $counts['failed']); ?>
            </p>

            <?php
            $remaining = $counts['pending'] + $counts['processing'];
            $estimated = $remaining * 7; // 7 credits per full pipeline
            if ($account && $estimated > $account['credits_remaining']): ?>
            <div class="notice notice-warning inline">
                <p><?php printf(
                    esc_html__('This batch needs ~%1$d credits but you only have %2$d. %3$s', 'tinify-ai'),
                    $estimated,
                    $account['credits_remaining'],
                    '<a href="https://tinify.ai/pricing" target="_blank">' . esc_html__('Upgrade', 'tinify-ai') . '</a>'
                ); ?></p>
            </div>
            <?php endif; ?>

            <button id="tinify-bulk-start" class="button button-primary">
                <?php printf(esc_html__('Optimize %d images', 'tinify-ai'), max(0, $counts['total'] - $counts['completed'])); ?>
            </button>
            <?php if ($counts['failed'] > 0): ?>
            <button id="tinify-bulk-retry" class="button" style="margin-left:8px;">
                <?php printf(esc_html__('Retry %d failed', 'tinify-ai'), $counts['failed']); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handleAjaxBulkQueue(): void
    {
        check_ajax_referer('tinify_ajax', 'nonce');
        if (!current_user_can('upload_files')) wp_die(-1);

        $retryFailed = (bool) ($_POST['retry_failed'] ?? false);
        $ids         = $this->getUnoptimizedAttachmentIds($retryFailed);

        foreach (array_chunk($ids, 50) as $chunk) {
            foreach ($chunk as $id) {
                $this->meta->setStatus($id, 'pending');
                $this->scheduler->queue($id);
            }
        }

        wp_send_json_success(['queued' => count($ids)]);
    }

    public function handleAjaxBulkStatus(): void
    {
        check_ajax_referer('tinify_ajax', 'nonce');
        if (!current_user_can('upload_files')) wp_die(-1);

        // Rate-limit: cache status counts for 2 seconds
        $cached = get_transient('tinify_bulk_status_cache');
        if ($cached !== false) {
            wp_send_json_success($cached);
            return;
        }

        $counts = $this->getStatusCounts();
        set_transient('tinify_bulk_status_cache', $counts, 2);
        wp_send_json_success($counts);
    }

    private function getStatusCounts(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_value AS status, COUNT(*) AS cnt
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = %s
                 GROUP BY meta_value",
                '_tinify_status'
            ),
            ARRAY_A
        );

        $counts = ['completed' => 0, 'processing' => 0, 'pending' => 0, 'failed' => 0, 'paused' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $status = $row['status'];
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row['cnt'];
            }
        }

        // total = all image attachments regardless of status
        $counts['total'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND post_status = 'inherit'"
        );

        return $counts;
    }

    private function getUnoptimizedAttachmentIds(bool $includeFailed): array
    {
        global $wpdb;
        $statuses = $includeFailed ? ["'pending'", "'failed'"] : ["'pending'"];

        return array_map('intval', $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tinify_status'
             WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND p.post_status = 'inherit'
             AND (pm.meta_value IS NULL OR pm.meta_value IN (" . implode(',', $statuses) . "))"
        ));
    }
}
