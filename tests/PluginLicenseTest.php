<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\PluginLicense;
use Kirby\Cms\App;
use Kirby\Plugin\Plugin;
use Kirby\Toolkit\I18n;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(PluginLicense::class)]
final class PluginLicenseTest extends LicenseTestCase
{
    private const PACKAGE_NAME = 'test/kirby-package';

    private Plugin $plugin;

    protected function setUp(): void
    {
        $this->appWithLicenseRoots(['translations' => LicensePanel::translations()]);

        $this->plugin = $this->createMock(Plugin::class);
        $this->plugin->method('version')->willReturn('1.0.0');
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

    #[Test]
    public function exposes_the_license_name_and_url(): void
    {
        $this->assertSame('Kirby Tools Plugin License', PluginLicense::LICENSE_NAME);
        $this->assertSame('https://kirby.tools/license', PluginLicense::LICENSE_URL);
    }

    #[Test]
    public function shows_an_active_badge_for_a_valid_compatible_license(): void
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
    public function shows_a_missing_badge_without_a_stored_license(): void
    {
        $status = $this->createLicense()->status()->toArray();

        $this->assertSame('missing', $status['value']);
        $this->assertSame('key', $status['icon']);
        $this->assertSame('love', $status['theme']);
    }

    #[Test]
    public function shows_an_invalid_badge_for_a_malformed_key(): void
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
    public function shows_an_incompatible_badge_when_the_license_does_not_cover_the_plugin_version(): void
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
    public function shows_an_upgradeable_badge_when_the_plugin_outgrew_the_license(): void
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

    #[Test]
    public function translates_the_status_label_when_the_translation_cache_lost_the_plugin_keys(): void
    {
        I18n::$locale = fn (): string => 'de';
        I18n::$translations = ['de' => ['error.page.undefined' => 'Die Seite kann nicht gefunden werden']];

        $status = $this->createLicense()->status()->toArray();

        $this->assertSame('Jetzt aktivieren', $status['label']);
    }
}
