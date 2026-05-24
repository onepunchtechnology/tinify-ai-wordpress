<?php
declare(strict_types=1);
namespace TinifyAI;

class Replacer
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'image/avif', 'image/svg+xml',
    ];

    public function __construct(private readonly MetaManager $meta) {}

    public function swap(int $attachmentId, string $processedTmpPath): void
    {
        $destPath   = get_attached_file($attachmentId);
        $uploadsDir = wp_upload_dir()['basedir'];

        // Path traversal guard
        $destDir     = realpath(dirname($destPath)) ?: dirname($destPath);
        $uploadsReal = realpath($uploadsDir) ?: $uploadsDir;
        if (!str_starts_with($destDir . DIRECTORY_SEPARATOR, $uploadsReal . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Path traversal detected: destination is outside uploads directory');
        }

        // MIME validation — check the downloaded file, not the tmp path extension
        $mimeCheck = wp_check_filetype_and_ext($processedTmpPath, basename($destPath));
        if (empty($mimeCheck['type']) || !in_array($mimeCheck['type'], self::ALLOWED_MIME_TYPES, true)) {
            @unlink($processedTmpPath);
            throw new \RuntimeException('MIME validation failed: processed file is not an allowed image type');
        }

        // Create .tinify-orig backup before overwriting
        $backupPath = $destPath . '.tinify-orig';
        if (!file_exists($backupPath)) {
            copy($destPath, $backupPath);
            $this->meta->setOrigBackup($attachmentId, $backupPath);
        }

        // Atomic swap via copy+rename (rename is atomic on POSIX same-filesystem)
        $stagingPath = $destPath . '.new';
        if (!copy($processedTmpPath, $stagingPath)) {
            throw new \RuntimeException('Failed to stage processed file at ' . $stagingPath);
        }
        if (!rename($stagingPath, $destPath)) {
            @unlink($stagingPath);
            throw new \RuntimeException('Failed to atomically replace original file');
        }
        @unlink($processedTmpPath);

        // Regenerate WP thumbnails; the Scheduler::queueOnUpload re-entrant guard
        // prevents re-queuing this attachment since status is already 'completed'.
        $metadata = wp_generate_attachment_metadata($attachmentId, $destPath);
        wp_update_attachment_metadata($attachmentId, $metadata);
    }
}
