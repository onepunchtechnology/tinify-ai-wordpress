<?php
declare(strict_types=1);
namespace TinifyAI;

class MediaLibrary
{
	public function __construct(
		private readonly MetaManager $meta,
		private readonly Scheduler $scheduler,
		private readonly Settings $settings,
	) {}

	public function addStatusColumn( array $columns ): array {
		$columns['tinify_ai'] = esc_html__('tinify.ai', 'tinify-ai');
		return $columns;
	}

	public function renderStatusColumn( string $column, int $attachmentId ): void {
		if ($column !== 'tinify_ai') {
			return;
		}

		$status = $this->meta->getStatus($attachmentId);
		switch ($status) {
			case 'completed':
				$pct = get_post_meta($attachmentId, '_tinify_savings_pct', true);
				/* translators: %s: percentage of file size reduction */
				echo '<span class="tinify-status tinify-done">'
					. esc_html(sprintf(__('✓ %s%% smaller', 'tinify-ai'), round( (float) $pct, 1))) // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
					. '</span>';
				break;
			case 'processing':
			case 'pending':
				echo '<span class="tinify-status tinify-processing">'
					. esc_html__('⟳ Processing…', 'tinify-ai') . '</span>';
				break;
			case 'failed':
				printf(
					'<span class="tinify-status tinify-failed">%s</span> <button class="button button-small tinify-optimize-single" data-id="%d">%s</button>',
					esc_html__('✕ Failed', 'tinify-ai'),
					absint($attachmentId),
					esc_html__('Retry', 'tinify-ai')
				);
				break;
			default:
				printf(
					'<span class="tinify-status tinify-none">— </span><button class="button button-small tinify-optimize-single" data-id="%d">%s</button>',
					absint($attachmentId),
					esc_html__('Optimize', 'tinify-ai')
				);
		}
	}

	public function renderAttachmentPanel(): void {
		global $post;
		if ( ! $post || $post->post_type !== 'attachment') {
			return;
		}
		$id         = $post->ID;
		$status     = $this->meta->getStatus($id);
		$origSize   = get_post_meta($id, '_tinify_original_size', true);
		$procSize   = get_post_meta($id, '_tinify_processed_size', true);
		$backupPath = get_post_meta($id, '_tinify_orig_backup', true);
		?>
		<div class="misc-pub-section tinify-panel">
			<strong><?php esc_html_e('tinify.ai', 'tinify-ai'); ?></strong><br>
			<?php if ($status === 'completed') : ?>
				<?php esc_html_e('Status: Optimized ✓', 'tinify-ai'); ?><br>
				<?php if ($origSize && $procSize) : ?>
					<?php
					/* translators: 1: original file size, 2: optimized file size */
					printf(esc_html__('%1$s → %2$s', 'tinify-ai'),
						esc_html(size_format( (int) $origSize)),
						esc_html(size_format( (int) $procSize)));
					?>
						<br>
				<?php endif; ?>
				<button class="button button-small tinify-optimize-single" data-id="<?php echo absint($id); ?>">
					<?php esc_html_e('Re-optimize', 'tinify-ai'); ?>
				</button>
				<?php if ($backupPath && file_exists($backupPath)) : ?>
				<br>
				<button class="button button-small tinify-restore-original" data-id="<?php echo absint($id); ?>" style="margin-top:4px;">
					<?php esc_html_e('Restore Original', 'tinify-ai'); ?>
				</button>
				<?php endif; ?>
			<?php elseif (in_array($status, [ 'processing', 'pending' ], true)) : ?>
				<?php esc_html_e('Status: Processing…', 'tinify-ai'); ?>
			<?php else : ?>
				<button class="button button-small tinify-optimize-single" data-id="<?php echo absint($id); ?>">
					<?php esc_html_e('Optimize', 'tinify-ai'); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handleAjaxOptimizeSingle(): void {
		check_ajax_referer('tinify_ajax', 'nonce');
		if ( ! current_user_can('upload_files')) {
			wp_die(-1);
		}

		$attachmentId = absint($_POST['attachment_id'] ?? 0);
		if ( ! $attachmentId) {
			wp_send_json_error('Invalid attachment ID');
		}

		$this->meta->setStatus($attachmentId, 'pending');
		$this->scheduler->queue($attachmentId);

		wp_send_json_success([ 'status' => 'queued' ]);
	}

	public function handleAjaxRestoreOriginal(): void {
		check_ajax_referer('tinify_ajax', 'nonce');
		if ( ! current_user_can('upload_files')) {
			wp_die(-1);
		}

		$attachmentId = absint($_POST['attachment_id'] ?? 0);
		if ( ! $attachmentId) {
			wp_send_json_error('Invalid attachment ID');
			return;
		}

		$backupPath = get_post_meta($attachmentId, '_tinify_orig_backup', true);
		$destPath   = get_attached_file($attachmentId);

		if ( ! $backupPath || ! $destPath || ! file_exists($backupPath)) {
			wp_send_json_error('Backup not found');
			return;
		}

		// Path guard: backup must be within uploads dir
		$uploadsDir   = wp_upload_dir()['basedir'];
		$uploads_real = realpath($uploadsDir);
		$uploadsReal  = false !== $uploads_real ? $uploads_real : $uploadsDir;
		$backup_real  = realpath($backupPath);
		$backupReal   = false !== $backup_real ? $backup_real : $backupPath;
		if ( ! str_starts_with($backupReal . DIRECTORY_SEPARATOR, $uploadsReal . DIRECTORY_SEPARATOR)) {
			wp_send_json_error('Invalid backup path');
			return;
		}

		if ( ! copy($backupPath, $destPath)) {
			wp_send_json_error('Failed to restore backup');
			return;
		}

		$this->meta->clearOptimizationData($attachmentId);
		wp_delete_file($backupPath);

		$metadata = wp_generate_attachment_metadata($attachmentId, $destPath);
		wp_update_attachment_metadata($attachmentId, $metadata);

		wp_send_json_success([ 'status' => 'restored' ]);
	}
}
