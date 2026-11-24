<?php

/**
 * Plugin Name:       SC Image Optimizer
 * Plugin URI:        https://screencandy.co.uk
 * Description:       Site-agnostic image pipeline — add your own image sizes from a settings screen, write uploads as AVIF/WebP automatically, cap oversized originals, and get a capped srcset helper for lightboxes. No build step.
 * Version:           0.2.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Neil Sayers
 * Author URI:        https://screencandy.co.uk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sc-image-optimizer
 */

namespace SCIO;

if (! defined('ABSPATH')) {
    exit;
}

define('SCIO_VERSION', '0.2.0');
define('SCIO_FILE', __FILE__);
define('SCIO_PATH', \plugin_dir_path(__FILE__));
define('SCIO_URL', \plugin_dir_url(__FILE__));

/**
 * Minimal PSR-4-style autoloader — same shape as the other SC plugins,
 * sharing no code with them at runtime.
 */
\spl_autoload_register(function (string $class): void {
    $prefix = __NAMESPACE__.'\\';

    if (! \str_starts_with($class, $prefix)) {
        return;
    }

    $path = SCIO_PATH.'src/'.\str_replace('\\', '/', \substr($class, \strlen($prefix))).'.php';

    if (\is_file($path)) {
        require $path;
    }
});

require_once SCIO_PATH.'src/helpers.php';

/*
 * Not on WordPress.org, so updates come from this plugin's GitHub
 * Releases (Plugin Update Checker, vendored in lib/ — no build step),
 * published by .github/workflows/release.yml whenever the Version
 * above changes.
 */
require_once SCIO_PATH.'lib/plugin-update-checker/plugin-update-checker.php';

\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker('https://github.com/neilsayers/sc-image-optimizer/', SCIO_FILE, 'sc-image-optimizer')
    ->getVcsApi()
    ->enableReleaseAssets();

Plugin::instance()->boot();
