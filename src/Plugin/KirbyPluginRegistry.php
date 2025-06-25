<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing\Plugin;

use Kirby\Cms\App;

/**
 * Plugin registry implementation using Kirby's plugin system.
 */
class KirbyPluginRegistry implements PluginRegistryInterface
{
    public function getPluginVersion(string $packageName): string|null
    {
        // Map package name to Kirby plugin name and remove the vendor prefix
        $kirbyPluginName = str_replace('/kirby-', '/', $packageName);

        return App::instance()->plugin($kirbyPluginName)?->version();
    }
}
