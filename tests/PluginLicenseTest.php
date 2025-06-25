<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Licenses;
use JohannSchopplich\Licensing\PluginLicense;
use Kirby\Cms\App;
use Kirby\Plugin\License as KirbyLicense;
use Kirby\Plugin\LicenseStatus as KirbyLicenseStatus;
use Kirby\Plugin\Plugin;
use PHPUnit\Framework\TestCase;

class PluginLicenseTest extends TestCase
{
    public const LICENSE_FILE_PATH = __DIR__ . '/' . Licenses::LICENSE_FILE;

    private App $kirby;
    private Plugin $plugin;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__
            ]
        ]);

        $this->plugin = $this->createMock(Plugin::class);
        $this->plugin->method('version')->willReturn('1.0.0');
    }

    protected function tearDown(): void
    {
        if (file_exists(static::LICENSE_FILE_PATH)) {
            unlink(static::LICENSE_FILE_PATH);
        }

        App::destroy();
    }

    public function testPluginLicenseExtendsKirbyLicense(): void
    {
        $pluginLicense = new PluginLicense($this->plugin, 'test/package');

        $this->assertInstanceOf(KirbyLicense::class, $pluginLicense);
        $this->assertInstanceOf(PluginLicense::class, $pluginLicense);
    }

    public function testPluginLicenseConstants(): void
    {
        $this->assertEquals('Kirby Tools License', PluginLicense::LICENSE_NAME);
        $this->assertEquals('https://kirby.tools/license', PluginLicense::LICENSE_URL);
    }

    public function testMapToKirbyStatusActive(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('mapToKirbyStatus');
        $method->setAccessible(true);

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, 'active');

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals('active', $status->value());
        $this->assertEquals('Licensed', $status->label());
        $this->assertEquals('check', $status->icon());
        $this->assertEquals('positive', $status->theme());
    }

    public function testMapToKirbyStatusInactive(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('mapToKirbyStatus');
        $method->setAccessible(true);

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, 'inactive');

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals('missing', $status->value());
        $this->assertEquals('Please buy a license', $status->label());
        $this->assertEquals('key', $status->icon());
        $this->assertEquals('love', $status->theme());
    }

    public function testMapToKirbyStatusInvalid(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('mapToKirbyStatus');
        $method->setAccessible(true);

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, 'invalid');

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals('invalid', $status->value());
        $this->assertEquals('Invalid license', $status->label());
        $this->assertEquals('alert', $status->icon());
        $this->assertEquals('negative', $status->theme());
    }

    public function testMapToKirbyStatusIncompatible(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('mapToKirbyStatus');
        $method->setAccessible(true);

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, 'incompatible');

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals('incompatible', $status->value());
        $this->assertEquals('Incompatible license', $status->label());
        $this->assertEquals('alert', $status->icon());
        $this->assertEquals('negative', $status->theme());
    }

    public function testMapToKirbyStatusUpgradeable(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('mapToKirbyStatus');
        $method->setAccessible(true);

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, 'upgradeable');

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals('upgradeable', $status->value());
        $this->assertEquals('License upgrade available', $status->label());
        $this->assertEquals('refresh', $status->icon());
        $this->assertEquals('notice', $status->theme());
    }

    public function testMapToKirbyStatusUnknown(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('mapToKirbyStatus');
        $method->setAccessible(true);

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, 'unknown_status');

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals('unknown', $status->value());
        $this->assertEquals('Unknown license status', $status->label());
        $this->assertEquals('question', $status->icon());
        $this->assertEquals('passive', $status->theme());
    }

    public function testConstructorInitializesCorrectly(): void
    {
        $pluginLicense = new PluginLicense($this->plugin, 'test/package');

        $reflection = new ReflectionClass($pluginLicense);
        $packageNameProperty = $reflection->getProperty('packageName');
        $packageNameProperty->setAccessible(true);

        $this->assertEquals('test/package', $packageNameProperty->getValue($pluginLicense));
    }

    public function testConstructorWithActiveLicense(): void
    {
        file_put_contents(static::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => '1.0.0',
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]));

        $this->kirby->extend([
            'plugins' => [
                'test/package' => $this->plugin
            ]
        ]);

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');

        $this->assertInstanceOf(PluginLicense::class, $pluginLicense);
    }
}
