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
        $this->app = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ],
            'translations' => LicensePanel::translations()
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists(self::LICENSE_FILE)) {
            unlink(self::LICENSE_FILE);
        }

        App::destroy();
    }

    public static function activationHandlers(): array
    {
        $packageName = 'johannschopplich/test-plugin';

        return [
            'api route action' => [
                LicensePanel::api($packageName)[0]['action']
            ],
            'dialog submit handler' => [
                array_column(LicensePanel::dialogs($packageName, 'Test Plugin'), 'submit')[0]
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
    public function activation_handler_throws_when_bound_to_the_kirby_api_scope(Closure $handler): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Kirby runs these handlers under its own `Api` scope, not the handler's own class.
        $handler->call($this->app->api());
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
    public function activation_handler_reports_a_failure_in_the_locale_I18n_cached_without_plugin_keys(): void
    {
        $packageName = 'johannschopplich/test-plugin';
        $licenseKey = 'KT1-ABC123-DEF456';

        $this->app = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ],
            'translations' => LicensePanel::translations(),
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

        $submit = array_column(LicensePanel::dialogs($packageName, 'Test Plugin'), 'submit')[0];

        $this->expectExceptionMessage('Lizenz bereits aktiviert');
        $submit->call(new Route('', 'POST', $submit));
    }

    #[Test]
    public function status_label_translates_into_a_panel_locale_with_a_country_code(): void
    {
        I18n::$locale = fn (): string => 'es_ES';

        $this->assertSame('Activar ahora', LicensePanel::statusLabel(LicenseStatus::Inactive));
    }
}
