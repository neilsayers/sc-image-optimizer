<?php

namespace SCIO;

use SCIO\Settings\Settings;

/**
 * The runtime behaviour: register the custom sizes, write uploads in the
 * chosen format, apply the quality, and cap oversized originals.
 */
final class ImagePipeline
{
    public function __construct(private Settings $settings) {}

    public function register(): void
    {
        \add_action('after_setup_theme', [$this, 'registerSizes'], 20);
        \add_filter('image_size_names_choose', [$this, 'offerSizesInMediaPicker']);
        \add_filter('big_image_size_threshold', [$this, 'threshold']);
        \add_filter('image_editor_output_format', [$this, 'outputFormat']);
        \add_filter('wp_editor_set_quality', [$this, 'quality'], 10, 2);
    }

    public function registerSizes(): void
    {
        foreach ((array) $this->settings->get('custom_sizes') as $size) {
            \add_image_size($size['name'], (int) $size['width'], (int) $size['height'], (bool) $size['crop']);
        }
    }

    public function offerSizesInMediaPicker(array $sizes): array
    {
        foreach ((array) $this->settings->get('custom_sizes') as $size) {
            $sizes[$size['name']] = \ucwords(\str_replace(['-', '_'], ' ', $size['name']));
        }

        return $sizes;
    }

    public function threshold(): int|false
    {
        $value = (int) $this->settings->get('big_image_threshold');

        return $value > 0 ? $value : false;
    }

    public function outputFormat(array $formats): array
    {
        $target = self::mimeFor((string) $this->settings->get('output_format'));

        // Only convert if this server can actually write the format.
        if (! $target || ! self::serverSupports($target)) {
            return $formats;
        }

        $formats['image/jpeg'] = $target;
        $formats['image/png'] = $target;

        return $formats;
    }

    public function quality(int $quality, string $mime): int
    {
        if (\in_array($mime, ['image/avif', 'image/webp'], true) && $mime === self::mimeFor((string) $this->settings->get('output_format'))) {
            return (int) $this->settings->get('quality');
        }

        return $quality;
    }

    public static function mimeFor(string $format): ?string
    {
        return ['avif' => 'image/avif', 'webp' => 'image/webp'][$format] ?? null;
    }

    public static function serverSupports(string $mime): bool
    {
        return \wp_image_editor_supports(['mime_type' => $mime]);
    }
}
