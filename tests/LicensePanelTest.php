<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicensePanel;
use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicensePanel::class)]
final class LicensePanelTest extends TestCase
{
    private App $kirby;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    #[DataProvider('activationHandlers')]
    public function activation_handler_reports_failure_when_bound_to_kirby_api_scope(Closure $handler): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Kirby runs these handlers under its own `Api` scope, not the handler's own class
        $handler->call($this->kirby->api());
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
}
