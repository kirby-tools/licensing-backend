<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\LicenseRepository;
use JohannSchopplich\Licensing\LicenseStatus;
use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Http\Route;
use Kirby\Toolkit\I18n;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicensePanel::class)]
final class LicensePanelTest extends TestCase
{
    private const LICENSE_FILE = __DIR__ . '/' . LicenseRepository::LICENSE_FILE;

    private App $app;

    protected function setUp(): void
    {
        $this->app = $this->bootApp();
    }

    protected function tearDown(): void
    {
        if (file_exists(self::LICENSE_FILE)) {
            unlink(self::LICENSE_FILE);
        }

        App::destroy();
    }

    private function bootApp(array $props = []): App
    {
        return $this->app = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ],
            'translations' => LicensePanel::translations(),
            ...$props
        ]);
    }

    public static function activationHandlers(): array
    {
        $packageName = 'johannschopplich/test-plugin';

        return [
            'api route action' => [
                LicensePanel::api($packageName)[0]['action'],
                fn (App $app): object => $app->api()
            ],
            'dialog submit handler' => [
                array_column(LicensePanel::dialogs($packageName, 'Test Plugin'), 'submit')[0],
                fn (App $app): object => new Route('', 'POST', fn () => null)
            ]
        ];
    }

    public static function dialogFields(): array
    {
        return [
            'activate dialog' => ['johannschopplich-test-plugin/activate', 'email', 'label', 'E-Mail'],
            'license dialog' => ['johannschopplich-test-plugin/license', 'info', 'text', 'Keine Lizenz gefunden.']
        ];
    }

    #[Test]
    #[DataProvider('activationHandlers')]
    public function activation_handler_throws_when_bound_to_kirbys_own_scope(Closure $handler, Closure $scope): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Kirby runs these handlers under its own scope, not the handler's own class.
        $handler->call($scope($this->app));
    }

    #[Test]
    #[DataProvider('dialogFields')]
    public function dialogs_label_their_fields_in_the_locale_I18n_cached_without_plugin_keys(
        string $dialogId,
        string $field,
        string $property,
        string $expected
    ): void {
        I18n::$locale = fn (): string => 'de';
        I18n::$translations = ['de' => ['error.page.undefined' => 'Die Seite kann nicht gefunden werden']];

        $load = LicensePanel::dialogs('johannschopplich/test-plugin', 'Test Plugin')[$dialogId]['load'];

        // Kirby runs dialog handlers bound to the matched route, not to `LicensePanel`.
        $dialog = $load->call(new Route('', 'GET', $load));

        $this->assertSame($expected, $dialog['props']['fields'][$field][$property]);
    }

    #[Test]
    #[DataProvider('activationHandlers')]
    public function activation_handler_reports_a_failure_in_the_locale_I18n_cached_without_plugin_keys(
        Closure $handler,
        Closure $scope
    ): void {
        $packageName = 'johannschopplich/test-plugin';
        $licenseKey = 'KT1-ABC123-DEF456';

        $this->bootApp([
            'request' => [
                'query' => ['email' => 'test@example.com', 'licenseKey' => $licenseKey]
            ]
        ]);

        App::plugin(name: $packageName, extends: [], info: ['version' => '1.0.0'], version: '1.0.0');
        file_put_contents(self::LICENSE_FILE, json_encode([
            $packageName => [
                'licenseKey' => $licenseKey,
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => '1.0.0'
            ]
        ]));

        I18n::$locale = fn (): string => 'de';
        I18n::$translations = ['de' => ['error.page.undefined' => 'Die Seite kann nicht gefunden werden']];

        $this->expectExceptionMessage('Lizenz bereits aktiviert');
        $handler->call($scope($this->app));
    }

    #[Test]
    public function status_label_translates_into_a_panel_locale_with_a_country_code(): void
    {
        I18n::$locale = fn (): string => 'es_ES';

        $this->assertSame('Activar ahora', LicensePanel::statusLabel(LicenseStatus::Inactive));
    }

    #[Test]
    public function dialogs_label_their_fields_from_the_fallback_locale_I18n_cached_without_plugin_keys(): void
    {
        $translations = LicensePanel::translations();
        unset($translations['de']['kirby-tools.license.activate.email']);
        $this->bootApp(['translations' => $translations]);

        I18n::$locale = fn (): string => 'de';
        I18n::$translations = ['en' => ['error.page.undefined' => 'The page cannot be found']];

        $load = LicensePanel::dialogs('johannschopplich/test-plugin', 'Test Plugin')['johannschopplich-test-plugin/activate']['load'];
        $dialog = $load->call(new Route('', 'GET', $load));

        $this->assertSame('Email', $dialog['props']['fields']['email']['label']);
    }

    #[Test]
    public function repair_translation_cache_keeps_locales_it_did_not_probe(): void
    {
        I18n::$locale = fn (): string => 'de';
        I18n::$translations = [
            'de' => ['error.page.undefined' => 'Die Seite kann nicht gefunden werden'],
            'fr' => ['some.other.plugin.key' => 'Registered at boot']
        ];

        LicensePanel::repairTranslationCache();

        $this->assertSame('Registered at boot', I18n::$translations['fr']['some.other.plugin.key']);
    }

    #[Test]
    public function repair_translation_cache_probes_every_locale_it_is_called_for(): void
    {
        I18n::$locale = fn (): string => 'de';
        I18n::$translations = [
            'de' => ['error.page.undefined' => 'Die Seite kann nicht gefunden werden'],
            'fr' => ['error.page.undefined' => 'La page est introuvable']
        ];

        LicensePanel::repairTranslationCache();

        I18n::$locale = fn (): string => 'fr';

        $this->assertSame('Sous licence', LicensePanel::statusLabel(LicenseStatus::Active));
    }
}
