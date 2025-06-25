<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing\Plugin;

interface PluginRegistryInterface
{
    public function getPluginVersion(string $packageName): string|null;
}
