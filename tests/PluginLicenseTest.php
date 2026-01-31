<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\LicenseRepository;
use JohannSchopplich\Licensing\LicenseStatus;
use JohannSchopplich\Licensing\PluginLicense;
use Kirby\Cms\App;
use Kirby\Plugin\License as KirbyLicense;
use Kirby\Plugin\LicenseStatus as KirbyLicenseStatus;
use Kirby\Plugin\Plugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PluginLicense::class)]
final class PluginLicenseTest extends TestCase
{
    public const LICENSE_FILE_PATH = __DIR__ . '/' . LicenseRepository::LICENSE_FILE;

    private App $kirby;
    private Plugin $plugin;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__
            ],
            'translations' => LicensePanel::translations()
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

    #[Test]
    public function plugin_license_extends_kirby_license(): void
    {
        $pluginLicense = new PluginLicense($this->plugin, 'test/package');

        $this->assertInstanceOf(KirbyLicense::class, $pluginLicense);
        $this->assertInstanceOf(PluginLicense::class, $pluginLicense);
    }

    #[Test]
    public function plugin_license_constants(): void
    {
        $this->assertEquals('Kirby Tools Plugin License', PluginLicense::LICENSE_NAME);
        $this->assertEquals('https://kirby.tools/license', PluginLicense::LICENSE_URL);
    }

    #[Test]
    public function map_to_kirby_status_active(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('toKirbyStatus');

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, LicenseStatus::Active);

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals(LicenseStatus::Active->value, $status->value());
        $this->assertEquals('Licensed', $status->label());
        $this->assertEquals('check', $status->icon());
        $this->assertEquals('positive', $status->theme());
    }

    #[Test]
    public function map_to_kirby_status_inactive(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('toKirbyStatus');

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, LicenseStatus::Inactive);

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals('missing', $status->value());
        $this->assertEquals('Activate now', $status->label());
        $this->assertEquals('key', $status->icon());
        $this->assertEquals('love', $status->theme());
    }

    #[Test]
    public function map_to_kirby_status_invalid(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('toKirbyStatus');

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, LicenseStatus::Invalid);

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals(LicenseStatus::Invalid->value, $status->value());
        $this->assertEquals('Invalid license', $status->label());
        $this->assertEquals('alert', $status->icon());
        $this->assertEquals('negative', $status->theme());
    }

    #[Test]
    public function map_to_kirby_status_incompatible(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('toKirbyStatus');

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, LicenseStatus::Incompatible);

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals(LicenseStatus::Incompatible->value, $status->value());
        $this->assertEquals('Incompatible license version', $status->label());
        $this->assertEquals('alert', $status->icon());
        $this->assertEquals('negative', $status->theme());
    }

    #[Test]
    public function map_to_kirby_status_upgradeable(): void
    {
        $reflection = new ReflectionClass(PluginLicense::class);
        $method = $reflection->getMethod('toKirbyStatus');

        $pluginLicense = new PluginLicense($this->plugin, 'test/package');
        $status = $method->invoke($pluginLicense, LicenseStatus::Upgradeable);

        $this->assertInstanceOf(KirbyLicenseStatus::class, $status);
        $this->assertEquals(LicenseStatus::Upgradeable->value, $status->value());
        $this->assertEquals('License upgrade available', $status->label());
        $this->assertEquals('refresh', $status->icon());
        $this->assertEquals('notice', $status->theme());
    }

    #[Test]
    public function constructor_initializes_correctly(): void
    {
        $pluginLicense = new PluginLicense($this->plugin, 'test/package');

        $reflection = new ReflectionClass($pluginLicense);
        $packageNameProperty = $reflection->getProperty('packageName');

        $this->assertEquals('test/package', $packageNameProperty->getValue($pluginLicense));
    }

    #[Test]
    public function constructor_with_active_license(): void
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
