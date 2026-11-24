<?php

namespace SCIO\Admin;

use SCIO\ImagePipeline;
use SCIO\Regenerator;
use SCIO\Settings\Settings;

final class SettingsPage
{
    private const SLUG = 'sc-image-optimizer';

    public function __construct(private Settings $settings) {}

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'addPage']);
        \add_action('admin_init', fn () => \register_setting(self::SLUG, Settings::OPTION_KEY, ['sanitize_callback' => [Settings::class, 'sanitize']]));
        \add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function addPage(): void
    {
        \add_menu_page('SC Image Optimizer', 'SC Image Optimizer', 'manage_options', self::SLUG, [$this, 'render'], 'dashicons-format-image', 94);
        \add_submenu_page(self::SLUG, 'SC Image Optimizer', 'Settings', 'manage_options', self::SLUG, [$this, 'render']);
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_'.self::SLUG) {
            return;
        }

        \wp_enqueue_style('scio-admin', SCIO_URL.'assets/css/admin.css', [], SCIO_VERSION);
        \wp_enqueue_script('scio-admin', SCIO_URL.'assets/js/settings.js', [], SCIO_VERSION, true);
    }

    /**
     * Every size WordPress currently knows about, tagged by origin.
     *
     * @return array<int, array{name:string,width:int,height:int,crop:string,source:string}>
     */
    private function builtInSizes(): array
    {
        $custom = \array_column((array) $this->settings->get('custom_sizes'), 'name');
        $rows = [];

        foreach (['thumbnail', 'medium', 'medium_large', 'large'] as $name) {
            $rows[] = [
                'name' => $name,
                'width' => (int) \get_option("{$name}_size_w"),
                'height' => (int) \get_option("{$name}_size_h"),
                'crop' => $name === 'thumbnail' && \get_option('thumbnail_crop') ? 'Hard crop' : 'Soft (scaled)',
                'source' => 'WordPress',
            ];
        }

        foreach (\wp_get_additional_image_sizes() as $name => $size) {
            if (\in_array($name, $custom, true)) {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'width' => (int) $size['width'],
                'height' => (int) $size['height'],
                'crop' => $size['crop'] ? 'Hard crop' : 'Soft (scaled)',
                'source' => \in_array($name, ['1536x1536', '2048x2048'], true) ? 'WordPress' : 'Theme / plugin',
            ];
        }

        return $rows;
    }

    private function dim(int $value): string
    {
        return $value > 0 ? $value.' px' : '— (auto)';
    }

    public function render(): void
    {
        $customs = (array) $this->settings->get('custom_sizes');
        $format = (string) $this->settings->get('output_format');
        $key = Settings::OPTION_KEY;
        ?>
        <div class="wrap scio">
            <h1>SC Image Optimizer</h1>
            <?php \settings_errors(); ?>
            <form method="post" action="options.php">
                <?php \settings_fields(self::SLUG); ?>

                <h2>Image sizes</h2>
                <p class="description">WordPress generates every size below for each upload. The built-in and theme sizes are read-only here (WordPress's own sizes are under <a href="<?php echo \esc_url(\admin_url('options-media.php')); ?>">Settings → Media</a>).</p>

                <table class="widefat striped scio-table">
                    <thead>
                        <tr><th>Name</th><th>Width</th><th>Height</th><th>Crop</th><th>Source</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->builtInSizes() as $row) { ?>
                            <tr>
                                <td><code><?php echo \esc_html($row['name']); ?></code></td>
                                <td><?php echo \esc_html($this->dim($row['width'])); ?></td>
                                <td><?php echo \esc_html($this->dim($row['height'])); ?></td>
                                <td><?php echo \esc_html($row['crop']); ?></td>
                                <td><?php echo \esc_html($row['source']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <h3>Your sizes</h3>
                <p class="description">Use lowercase letters, numbers, dashes and underscores. Set a height of 0 to scale by width only. Names can't reuse a WordPress size.</p>

                <table class="widefat scio-table scio-repeater" data-scio-repeater>
                    <thead>
                        <tr><th>Name</th><th>Max width (px)</th><th>Max height (px)</th><th>Hard crop</th><th></th></tr>
                    </thead>
                    <tbody data-scio-rows>
                        <?php foreach ($customs as $i => $size) { ?>
                            <?php $this->row((string) $i, $size, $key); ?>
                        <?php } ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" data-scio-add>+ Add size</button></p>

                <template data-scio-template>
                    <?php $this->row('__INDEX__', ['name' => '', 'width' => 0, 'height' => 0, 'crop' => false], $key); ?>
                </template>

                <h2>Format &amp; quality</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="scio-format">Convert uploads to</label></th>
                        <td>
                            <select id="scio-format" name="<?php echo \esc_attr($key); ?>[output_format]">
                                <?php foreach (Settings::FORMATS as $value => $label) { ?>
                                    <option value="<?php echo \esc_attr($value); ?>" <?php \selected($format, $value); ?>><?php echo \esc_html($label); ?></option>
                                <?php } ?>
                            </select>
                            <p class="description">
                                Server support:
                                AVIF <?php echo ImagePipeline::serverSupports('image/avif') ? '✅' : '❌ not available'; ?> ·
                                WebP <?php echo ImagePipeline::serverSupports('image/webp') ? '✅' : '❌ not available'; ?>.
                                If the chosen format isn't supported, uploads stay as JPEG/PNG. The original upload is always kept as the master copy.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scio-quality">Quality</label></th>
                        <td>
                            <input type="number" id="scio-quality" class="small-text" min="30" max="100" name="<?php echo \esc_attr($key); ?>[quality]" value="<?php echo \esc_attr((string) $this->settings->get('quality')); ?>">
                            <p class="description">For AVIF/WebP. 75 suits most work; raise it for fine-detail artwork.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scio-threshold">Cap original images at</label></th>
                        <td>
                            <input type="number" id="scio-threshold" class="small-text" min="0" name="<?php echo \esc_attr($key); ?>[big_image_threshold]" value="<?php echo \esc_attr((string) $this->settings->get('big_image_threshold')); ?>"> px (longest side)
                            <p class="description">Larger uploads are scaled down to this. 0 disables the cap.</p>
                        </td>
                    </tr>
                </table>

                <?php \submit_button(); ?>
            </form>

            <h2>Regenerate existing images</h2>
            <p>New uploads use the settings straight away. To rebuild every image already in the library with the current sizes and format, regenerate them (save any changes above first). Old files that are no longer needed are removed; your original uploads are never touched.</p>
            <p>
                <button type="button" class="button button-secondary" data-scio-regenerate
                    data-url="<?php echo \esc_url(\admin_url('admin-ajax.php')); ?>"
                    data-action="<?php echo \esc_attr(Regenerator::ACTION); ?>"
                    data-nonce="<?php echo \esc_attr(\wp_create_nonce(Regenerator::ACTION)); ?>">Regenerate all images</button>
            </p>
            <div class="scio-progress" data-scio-progress hidden>
                <div class="scio-progress__bar"><span data-scio-bar></span></div>
                <p data-scio-status aria-live="polite"></p>
            </div>
            <p class="description">Or from the command line: <code>wp media regenerate --yes</code></p>
            <h2>Using it in a theme</h2>
            <p>Use <code>wp_get_attachment_image_url($id, 'lightbox')</code> for the image, and <code>scio_srcset($id)</code> for a lightbox <code>srcset</code> capped at that size.</p>
        </div>
        <?php
    }

    private function row(string $index, array $size, string $key): void
    {
        $base = \esc_attr($key).'[custom_sizes]['.\esc_attr($index).']';
        ?>
        <tr data-scio-row>
            <td><input type="text" class="regular-text" name="<?php echo $base; ?>[name]" value="<?php echo \esc_attr($size['name']); ?>" placeholder="e.g. lightbox" pattern="[a-z0-9_\-]+"></td>
            <td><input type="number" class="small-text" min="0" name="<?php echo $base; ?>[width]" value="<?php echo \esc_attr((string) $size['width']); ?>"></td>
            <td><input type="number" class="small-text" min="0" name="<?php echo $base; ?>[height]" value="<?php echo \esc_attr((string) $size['height']); ?>"></td>
            <td><input type="checkbox" name="<?php echo $base; ?>[crop]" value="1" <?php \checked(! empty($size['crop'])); ?>></td>
            <td><button type="button" class="button-link button-link-delete" data-scio-remove>Remove</button></td>
        </tr>
        <?php
    }
}
