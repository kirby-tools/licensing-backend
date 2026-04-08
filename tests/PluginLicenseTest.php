<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\LicenseRepository;
use JohannSchopplich\Licensing\PluginLicense;
use Kirby\Cms\App;
use Kirby\Plugin\Plugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PluginLicense::class)]
final class PluginLicenseTest extends TestCase
{
    private const PACKAGE_NAME = 'test/kirby-package';
    private const LICENSE_FILE_PATH = __DIR__ . '/' . LicenseRepository::LICENSE_FILE;

    private Plugin $plugin;

    protected function setUp(): void
    {
        new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ],
            'translations' => LicensePanel::translations()
        ]);

        $this->plugin = $this->createMock(Plugin::class);
        $this->plugin->method('version')->willReturn('1.0.0');
    }

    protected function tearDown(): void
    {
        if (file_exists(self::LICENSE_FILE_PATH)) {
            unlink(self::LICENSE_FILE_PATH);
        }

        App::destroy();
    }

    #[Test]
    public function constants(): void
    {
        $this->assertSame('Kirby Tools Plugin License', PluginLicense::LICENSE_NAME);
        $this->assertSame('https://kirby.tools/license', PluginLicense::LICENSE_URL);
    }

    #[Test]
    public function constructor_active(): void
    {
        $this->registerPlugin('1.0.0');
        $this->writeLicenseFile([
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'pluginVersion' => '1.0.0',
        ]);

        $status = $this->createLicense()->status()->toArray();

        $this->assertSame('active', $status['value']);
        $this->assertSame('check', $status['icon']);
        $this->assertSame('positive', $status['theme']);
    }

    #[Test]
    public function constructor_inactive(): void
    {
        $status = $this->createLicense()->status()->toArray();

        $this->assertSame('missing', $status['value']);
        $this->assertSame('key', $status['icon']);
        $this->assertSame('love', $status['theme']);
    }

    #[Test]
    public function constructor_invalid(): void
    {
        $this->writeLicenseFile([
            'licenseKey' => 'INVALID-KEY',
            'licenseCompatibility' => '^1.0.0',
            'pluginVersion' => '1.0.0',
        ]);

        $status = $this->createLicense()->status()->toArray();

        $this->assertSame('invalid', $status['value']);
        $this->assertSame('alert', $status['icon']);
        $this->assertSame('negative', $status['theme']);
    }

    #[Test]
    public function constructor_incompatible(): void
    {
        $this->registerPlugin('1.0.0');
        $this->writeLicenseFile([
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^2.0.0',
            'pluginVersion' => '1.0.0',
        ]);

        $status = $this->createLicense()->status()->toArray();

        $this->assertSame('incompatible', $status['value']);
        $this->assertSame('alert', $status['icon']);
        $this->assertSame('negative', $status['theme']);
    }

    #[Test]
    public function constructor_upgradeable(): void
    {
        $this->registerPlugin('2.0.0');
        $this->writeLicenseFile([
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'pluginVersion' => '2.0.0',
        ]);

        $status = $this->createLicense()->status()->toArray();

        $this->assertSame('upgradeable', $status['value']);
        $this->assertSame('refresh', $status['icon']);
        $this->assertSame('notice', $status['theme']);
    }

    private function createLicense(): PluginLicense
    {
        return new PluginLicense($this->plugin, self::PACKAGE_NAME);
    }

    private function registerPlugin(string $version): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => $version],
            version: $version
        );
    }

    private function writeLicenseFile(array $data): void
    {
        file_put_contents(
            self::LICENSE_FILE_PATH,
            json_encode([self::PACKAGE_NAME => $data])
        );
    }
}
