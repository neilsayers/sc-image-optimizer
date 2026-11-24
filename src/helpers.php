<?php

/**
 * Template helper: a srcset string for an attachment, built from the
 * generated sizes and capped so high-DPR screens never pull anything
 * bigger than $maxEdge px (longest side). Use it for lightbox images:
 *
 *   data-lightbox-srcset="{{ scio_srcset($id) }}"
 *
 * Built from attachment metadata rather than wp_calculate_image_srcset(),
 * which can't cap on the longest side (a tall portrait image passes a
 * width-only cap while being far taller than intended).
 *
 * @param  int  $id  Attachment ID.
 * @param  int  $maxEdge  Longest-side cap in px. 0 = use the "lightbox" size's width, else 1500.
 * @param  int  $minWidth  Skip tiny sizes (thumbnails) — they're useless for a lightbox.
 */
function scio_srcset(int $id, int $maxEdge = 0, int $minWidth = 300): string
{
    if ($maxEdge <= 0) {
        $sizes = \wp_get_additional_image_sizes();
        $maxEdge = (int) ($sizes['lightbox']['width'] ?? 1500);
    }

    $meta = \wp_get_attachment_metadata($id);
    $url = \wp_get_attachment_url($id);

    if (empty($meta['sizes']) || ! $url) {
        return '';
    }

    $base = \trailingslashit(\dirname($url));
    $parts = [];

    foreach ($meta['sizes'] as $size) {
        if (\max($size['width'], $size['height']) <= $maxEdge && $size['width'] >= $minWidth) {
            $parts[$base.$size['file']] = $base.$size['file'].' '.$size['width'].'w';
        }
    }

    return \implode(', ', $parts);
}
