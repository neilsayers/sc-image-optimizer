<?php

namespace SCIO;

use SCIO\Admin\SettingsPage;
use SCIO\Settings\Settings;

final class Plugin
{
    private static ?self $instance = null;

    private Settings $settings;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public function boot(): void
    {
        $this->settings = new Settings;

        (new ImagePipeline($this->settings))->register();

        if (\is_admin()) {
            (new SettingsPage($this->settings))->register();
        }
    }
}
