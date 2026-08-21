<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicenseRepository;
use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

abstract class LicenseTestCase extends TestCase
{
    protected const LICENSE_FILE_PATH = __DIR__ . '/' . LicenseRepository::LICENSE_FILE;

    protected function setUp(): void
    {
        $this->appWithLicenseRoots();
    }

    protected function tearDown(): void
    {
        if (file_exists(self::LICENSE_FILE_PATH)) {
            unlink(self::LICENSE_FILE_PATH);
        }

        App::destroy();
    }

    protected function appWithLicenseRoots(array $props = []): App
    {
        return new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ],
            ...$props
        ]);
    }
}
