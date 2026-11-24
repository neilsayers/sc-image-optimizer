<?php

namespace SCIO;

/**
 * Regenerates every image's sizes (the equivalent of
 * `wp media regenerate --yes`) in small AJAX batches from the settings
 * screen, so it works on hosts with no WP-CLI and never hits a
 * time limit. Files made obsolete by the regeneration (e.g. the old
 * JPEG sizes once AVIF is on) are deleted; the original upload and any
 * edited-image backups are never touched.
 */
final class Regenerator
{
    public const ACTION = 'scio_regenerate';

    private const BATCH = 3;

    public function register(): void
    {
        \add_action('wp_ajax_'.self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        \check_ajax_referer(self::ACTION, 'nonce');

        if (! \current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Not allowed.'], 403);
        }

        require_once ABSPATH.'wp-admin/includes/image.php';

        $offset = \max(0, (int) ($_POST['offset'] ?? 0));

        $query = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'orderby' => 'ID',
            'order' => 'ASC',
            'posts_per_page' => self::BATCH,
            'offset' => $offset,
            'fields' => 'ids',
            'no_found_rows' => false,
        ]);

        $errors = [];

        foreach ($query->posts as $id) {
            $error = $this->regenerate((int) $id);

            if ($error) {
                $errors[] = \get_the_title($id).': '.$error;
            }
        }

        $processed = $offset + \count($query->posts);

        \wp_send_json_success([
            'processed' => $processed,
            'total' => (int) $query->found_posts,
            'done' => \count($query->posts) < self::BATCH || $processed >= $query->found_posts,
            'errors' => $errors,
        ]);
    }

    /**
     * @return string|null An error message, or null on success.
     */
    private function regenerate(int $id): ?string
    {
        if (! \wp_attachment_is_image($id)) {
            return null;
        }

        $source = \wp_get_original_image_path($id) ?: \get_attached_file($id);

        if (! $source || ! \file_exists($source)) {
            return 'original file missing';
        }

        $old = \wp_get_attachment_metadata($id) ?: [];
        $new = \wp_generate_attachment_metadata($id, $source);

        if (\is_wp_error($new)) {
            return $new->get_error_message();
        }

        if (empty($new)) {
            return 'could not generate sizes';
        }

        \wp_update_attachment_metadata($id, $new);
        $this->deleteObsolete($id, $old, $new, $source);

        return null;
    }

    private function deleteObsolete(int $id, array $old, array $new, string $original): void
    {
        $keep = \array_flip(\array_merge($this->files($new), [\wp_basename($original)]));

        foreach ((array) \get_post_meta($id, '_wp_attachment_backup_sizes', true) as $backup) {
            if (! empty($backup['file'])) {
                $keep[$backup['file']] = true;
            }
        }

        $dir = \dirname($original);

        $stale = $this->files($old);

        // Also sweep leftovers WordPress no longer tracks (e.g. JPEG sizes
        // orphaned by an earlier regenerate that didn't clean up): files
        // named <original>-<w>x<h>.<ext> or <original>-scaled.<ext>.
        $stem = \preg_quote(\pathinfo($original, PATHINFO_FILENAME), '/');

        foreach ((array) \glob($dir.'/*') as $path) {
            if (\preg_match('/^'.$stem.'-(\d+x\d+|scaled)\.(jpe?g|png|gif|webp|avif)$/i', \basename($path))) {
                $stale[] = \basename($path);
            }
        }

        foreach (\array_unique($stale) as $file) {
            if (! isset($keep[$file])) {
                \wp_delete_file($dir.'/'.$file);
            }
        }
    }

    /**
     * Every file name (not path) an attachment's metadata references.
     *
     * @return string[]
     */
    private function files(array $meta): array
    {
        $files = [];

        if (! empty($meta['file'])) {
            $files[] = \wp_basename($meta['file']);
        }

        foreach ((array) ($meta['sizes'] ?? []) as $size) {
            if (! empty($size['file'])) {
                $files[] = $size['file'];
            }
        }

        return $files;
    }
}
