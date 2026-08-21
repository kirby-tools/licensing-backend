<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\LicenseStatus;
use Kirby\Cms\Api;
use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\LogicException;
use Kirby\Http\Route;
use Kirby\Toolkit\I18n;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(LicensePanel::class)]
final class LicensePanelTest extends LicenseTestCase
{
    private const PACKAGE_NAME = 'johannschopplich/test-plugin';

    protected function setUp(): void
    {
        $this->appWithTranslations(LicensePanel::translations());
    }

    private function appWithTranslations(array $translations): App
    {
        return $this->appWithLicenseRoots(['translations' => $translations]);
    }

    private function appWithActivationRequest(string $licenseKey): App
    {
        return $this->appWithLicenseRoots([
            'translations' => LicensePanel::translations(),
            'request' => [
                'query' => ['email' => 'test@example.com', 'licenseKey' => $licenseKey]
            ]
        ]);
    }

    private function registerActivatedPlugin(string $licenseKey): void
    {
        App::plugin(name: self::PACKAGE_NAME, extends: [], info: ['version' => '1.0.0'], version: '1.0.0');

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            self::PACKAGE_NAME => [
                'licenseKey' => $licenseKey,
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => '1.0.0'
            ]
        ]));
    }

    public static function activationHandlers(): array
    {
        return [
            'api route action' => [
                LicensePanel::api(self::PACKAGE_NAME)[0]['action'],
                fn (): Api => App::instance()->api()
            ],
            'dialog submit handler' => [
                array_column(LicensePanel::dialogs(self::PACKAGE_NAME, 'Test Plugin'), 'submit')[0],
                fn (): Route => new Route('', 'POST', fn () => null)
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

    public static function panelLocalesWithCountryCode(): array
    {
        return [
            'european spanish' => ['es_ES', 'Activar ahora'],
            'latin american spanish' => ['es_419', 'Activar ahora'],
            'european portuguese' => ['pt_PT', 'Ativar agora']
        ];
    }

    #[Test]
    #[DataProvider('activationHandlers')]
    public function activation_handler_throws_an_invalid_argument_exception_when_kirby_rebinds_the_scope(Closure $handler, Closure $scope): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Kirby binds these handlers to the matched route or to `Api`, never to `LicensePanel`.
        $handler->call($scope());
    }

    #[Test]
    #[DataProvider('dialogFields')]
    public function dialogs_label_their_fields_when_the_translation_cache_lost_the_plugin_keys(
        string $dialogId,
        string $field,
        string $property,
        string $expected
    ): void {
        I18n::$locale = fn (): string => 'de';
        I18n::$translations = ['de' => ['error.page.undefined' => 'Die Seite kann nicht gefunden werden']];

        $load = LicensePanel::dialogs(self::PACKAGE_NAME, 'Test Plugin')[$dialogId]['load'];

        $dialog = $load->call(new Route('', 'GET', $load));

        $this->assertSame($expected, $dialog['props']['fields'][$field][$property]);
    }

    #[Test]
    #[DataProvider('activationHandlers')]
    public function activation_handler_translates_the_failure_when_the_translation_cache_lost_the_plugin_keys(
        Closure $handler,
        Closure $scope
    ): void {
        $licenseKey = 'KT1-ABC123-DEF456';

        $this->appWithActivationRequest($licenseKey);

        $this->registerActivatedPlugin($licenseKey);

        I18n::$locale = fn (): string => 'de';
        I18n::$translations = ['de' => ['error.page.undefined' => 'Die Seite kann nicht gefunden werden']];

        $this->expectExceptionMessage('Lizenz bereits aktiviert');
        $handler->call($scope());
    }

    #[Test]
    #[DataProvider('panelLocalesWithCountryCode')]
    public function status_label_translates_into_a_panel_locale_with_a_country_code(
        string $locale,
        string $expected
    ): void {
        I18n::$locale = fn (): string => $locale;

        $this->assertSame($expected, LicensePanel::statusLabel(LicenseStatus::Inactive));
    }

    #[Test]
    public function dialogs_label_their_fields_when_the_fallback_locale_lost_the_plugin_keys(): void
    {
        $translations = LicensePanel::translations();
        unset($translations['de']['kirby-tools.license.activate.email']);
        $this->appWithTranslations($translations);

        I18n::$locale = fn (): string => 'de';
        I18n::$translations = ['en' => ['error.page.undefined' => 'The page cannot be found']];

        $load = LicensePanel::dialogs(self::PACKAGE_NAME, 'Test Plugin')['johannschopplich-test-plugin/activate']['load'];
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

    #[Test]
    public function activate_dialog_warns_when_the_license_file_cannot_be_read(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, '{ this is not json');

        $load = LicensePanel::dialogs(self::PACKAGE_NAME, 'Test Plugin')['johannschopplich-test-plugin/activate']['load'];
        $dialog = $load->call(new Route('', 'GET', $load));

        $this->assertSame('negative', $dialog['props']['fields']['info']['theme']);
        $this->assertStringContainsString('could not be read', $dialog['props']['fields']['info']['text']);
    }

    #[Test]
    #[DataProvider('activationHandlers')]
    public function activation_handler_keeps_the_cause_when_it_translates_a_failure(
        Closure $handler,
        Closure $scope
    ): void {
        $licenseKey = 'KT1-ABC123-DEF456';

        $this->appWithActivationRequest($licenseKey);
        $this->registerActivatedPlugin($licenseKey);

        try {
            $handler->call($scope());
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(LogicException::class, $e->getPrevious());
            $this->assertSame(self::PACKAGE_NAME, $e->getDetails()['package']);

            return;
        }

        $this->fail('The handler did not report the failure.');
    }
}
