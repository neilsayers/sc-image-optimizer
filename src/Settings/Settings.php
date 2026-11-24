<?php

namespace SCIO\Settings;

/**
 * One options row: custom image sizes, output format, quality, and the
 * original-image cap. The first time the plugin runs, the custom sizes
 * are seeded with a 1500px "lightbox" size so the common portfolio
 * case works out of the box.
 */
final class Settings
{
    public const OPTION_KEY = 'scio_settings';

    /** Sizes WordPress itself registers — custom sizes can't reuse these names. */
    public const RESERVED = ['thumbnail', 'medium', 'medium_large', 'large', 'full', '1536x1536', '2048x2048', 'post-thumbnail'];

    public const FORMATS = [
        'avif' => 'AVIF (smallest files, best for artwork)',
        'webp' => 'WebP (wider legacy support)',
        'none' => 'Leave JPEG/PNG as uploaded',
    ];

    private const DEFAULTS = [
        'custom_sizes' => [
            ['name' => 'lightbox', 'width' => 1500, 'height' => 1500, 'crop' => false],
        ],
        'output_format' => 'avif',
        'quality' => 75,
        'big_image_threshold' => 2560,
    ];

    private array $values;

    public function __construct()
    {
        $stored = \get_option(self::OPTION_KEY, null);

        $this->values = \is_array($stored) ? \array_merge(self::DEFAULTS, $stored) : self::DEFAULTS;
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * register_setting sanitize callback.
     */
    public static function sanitize(mixed $input): array
    {
        $input = \is_array($input) ? $input : [];
        $sizes = [];
        $seen = [];

        foreach ((array) ($input['custom_sizes'] ?? []) as $row) {
            $name = \sanitize_key($row['name'] ?? '');
            $width = \absint($row['width'] ?? 0);
            $height = \absint($row['height'] ?? 0);

            if ($name === '' || \in_array($name, self::RESERVED, true) || isset($seen[$name]) || ($width === 0 && $height === 0)) {
                continue;
            }

            $seen[$name] = true;
            $sizes[] = ['name' => $name, 'width' => $width, 'height' => $height, 'crop' => ! empty($row['crop'])];
        }

        $format = $input['output_format'] ?? 'avif';

        return [
            'custom_sizes' => $sizes,
            'output_format' => \array_key_exists($format, self::FORMATS) ? $format : 'avif',
            'quality' => \max(30, \min(100, \absint($input['quality'] ?? 75))),
            'big_image_threshold' => \absint($input['big_image_threshold'] ?? 2560),
        ];
    }
}
