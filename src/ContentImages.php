<?php

namespace SCIO;

/**
 * Brings images already saved in post content up to date.
 *
 * The block editor stores the image URL (and a srcset) in post_content
 * at the moment the image is inserted. Once an upload has been
 * converted to AVIF/WebP — or regenerated with different sizes — those
 * saved URLs point at files that are no longer the attachment's current
 * ones. WordPress then can't match them to the attachment metadata, so
 * it adds no srcset, and visitors get the heavy original.
 *
 * This runs per <img> inside wp_filter_content_tags() (so it covers
 * classic content, blocks and widgets alike): if an <img> carries a
 * wp-image-{ID} class and its src is not one of the attachment's
 * current files, swap in the current file of the same size, then let
 * WordPress build a fresh srcset/sizes from the metadata. Nothing in
 * the database is edited — it's applied at render time, so it also
 * heals itself after any later regenerate.
 *
 * Disable with: add_filter('scio_upgrade_content_images', '__return_false');
 */
final class ContentImages
{
    public function register(): void
    {
        \add_filter('wp_content_img_tag', [$this, 'upgrade'], 10, 3);
    }

    public function upgrade(string $tag, string $context, int $attachmentId): string
    {
        if (! \apply_filters('scio_upgrade_content_images', true, $attachmentId)) {
            return $tag;
        }

        if (! \preg_match('/\ssrc=(["\'])([^"\']+)\1/i', $tag, $src)) {
            return $tag;
        }

        $current = $this->currentUrl($attachmentId, $src[2]);

        if (! $current) {
            return $tag;
        }

        // Drop the stale srcset/sizes (they reference the old files),
        // swap the src, then have core rebuild them from the metadata.
        $tag = \preg_replace('/\s(?:srcset|sizes)=(["\']).*?\1/is', '', $tag);
        $tag = \str_replace($src[0], ' src='.$src[1].$current.$src[1], $tag);

        // Core already ran its width/height + srcset passes against the
        // stale src (and found nothing), so repeat them now it's current.
        $tag = \wp_img_tag_add_width_and_height_attr($tag, $context, $attachmentId);

        return \wp_img_tag_add_srcset_and_sizes_attr($tag, $context, $attachmentId);
    }

    /**
     * The attachment's current file for the size the saved $src was
     * asking for, or null when $src is already current / not ours.
     */
    private function currentUrl(int $id, string $src): ?string
    {
        $meta = \wp_get_attachment_metadata($id);
        $full = \wp_get_attachment_url($id);

        if (! \is_array($meta) || empty($meta['file']) || ! $full) {
            return null;
        }

        $dir = \trailingslashit(\dirname($full));

        // Only touch images from this attachment's own upload folder.
        if (\strtok($src, '?') && \trailingslashit(\dirname(\strtok($src, '?'))) !== $dir) {
            return null;
        }

        $file = \basename(\strtok($src, '?'));
        $current = [\basename($meta['file'])];

        foreach ((array) ($meta['sizes'] ?? []) as $size) {
            $current[] = $size['file'];
        }

        if (\in_array($file, $current, true)) {
            return null;
        }

        // A saved size suffix (-768x436) says which size was wanted.
        if (\preg_match('/-(\d+)x(\d+)\.[a-z0-9]+$/i', $file, $m)) {
            $want = (int) $m[1];
            $best = null;

            foreach ((array) ($meta['sizes'] ?? []) as $size) {
                if ((int) $size['width'] === $want) {
                    return $dir.$size['file'];
                }

                // Otherwise the smallest generated size that's still wide enough.
                if ((int) $size['width'] > $want && (! $best || $size['width'] < $best['width'])) {
                    $best = $size;
                }
            }

            if ($best) {
                return $dir.$best['file'];
            }
        }

        return $full;
    }
}
