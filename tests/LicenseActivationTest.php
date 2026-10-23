<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Http\Request;
use KirbyTools\Licensing\ActivationException;
use KirbyTools\Licensing\ActivationOutcome;
use KirbyTools\Licensing\Http\HttpClientInterface;
use KirbyTools\Licensing\LicenseActivator;
use KirbyTools\Licensing\LicenseRepository;
use KirbyTools\Licensing\Licenses;
use KirbyTools\Licensing\LicenseValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;

#[CoversClass(LicenseActivator::class)]
final class LicenseActivationTest extends LicenseTestCase
{
    private App $app;
    private HttpClientInterface&Stub $httpClient;

    protected function setUp(): void
    {
        $this->app = $this->appWithLicenseRoots();

        $this->httpClient = $this->createStub(HttpClientInterface::class);
    }

    private function assertActivationFails(ActivationOutcome $outcome, Closure $activation): ActivationException
    {
        try {
            $activation();
        } catch (ActivationException $e) {
            $this->assertSame($outcome, $e->outcome);

            return $e;
        }

        $this->fail('The activation did not fail.');
    }

    #[Test]
    public function activate_marks_the_license_active_when_the_api_confirms_the_key(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->httpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('simple/package');
        $activator = new LicenseActivator(
            'simple/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $activator->activate('test@example.com', '123456');

        $licenses = Licenses::read('simple/package');
        $this->assertEquals('active', $licenses->getStatus());
    }

    #[Test]
    public function activate_throws_when_the_key_belongs_to_another_package(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->httpClient->method('request')->willReturn([
            'packageName' => 'wrong/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('simple/package');
        $activator = new LicenseActivator(
            'simple/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $this->assertActivationFails(
            ActivationOutcome::WrongPlugin,
            fn () => $activator->activate('test@example.com', '123456')
        );
    }

    #[Test]
    public function activate_throws_naming_the_upgrade_when_the_plugin_outgrew_the_license(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '2.0.0'],
            version: '2.0.0'
        );

        $this->httpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0', // Only supports 1.x.
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('simple/package');
        $activator = new LicenseActivator(
            'simple/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $this->assertActivationFails(
            ActivationOutcome::Upgradeable,
            fn () => $activator->activate('test@example.com', '123456')
        );
    }

    #[Test]
    public function activate_throws_when_the_license_does_not_cover_the_plugin_version(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->httpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^2.0.0', // Only supports 2.x.
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('simple/package');
        $activator = new LicenseActivator(
            'simple/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $this->assertActivationFails(
            ActivationOutcome::Incompatible,
            fn () => $activator->activate('test@example.com', '123456')
        );
    }

    public static function requestFailures(): array
    {
        return [
            'a 401' => [new ActivationException(ActivationOutcome::InvalidCredentials), ActivationOutcome::InvalidCredentials],
            'a 500' => [new ActivationException(ActivationOutcome::RequestFailed, ['status' => 500]), ActivationOutcome::RequestFailed]
        ];
    }

    #[Test]
    #[DataProvider('requestFailures')]
    public function activate_passes_on_the_outcome_of_a_rejected_request(ActivationException $failure, ActivationOutcome $outcome): void
    {
        $this->httpClient->method('request')->willThrowException($failure);

        $activator = new LicenseActivator(
            'test/package',
            new LicenseRepository(),
            new LicenseValidator('test/package'),
            $this->httpClient
        );

        $e = $this->assertActivationFails(
            $outcome,
            fn () => $activator->activate('test@example.com', 'KT1-ABC123-DEF456')
        );

        $this->assertSame($failure, $e);
    }

    #[Test]
    public function activate_throws_when_the_api_response_omits_the_package_name(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->httpClient->method('request')->willReturn([
            'error' => 'License not found'
        ]);

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('simple/package');
        $activator = new LicenseActivator(
            'simple/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $this->assertActivationFails(
            ActivationOutcome::WrongPlugin,
            fn () => $activator->activate('test@example.com', '123456')
        );
    }

    #[Test]
    public function activate_writes_license_key_and_license_compatibility_to_the_license_file(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->httpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('simple/package');
        $activator = new LicenseActivator(
            'simple/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $activator->activate('test@example.com', '123456');

        $this->assertFileExists(self::LICENSE_FILE_PATH);

        $savedData = json_decode(file_get_contents(self::LICENSE_FILE_PATH), true);
        $this->assertArrayHasKey('simple/package', $savedData);
        $this->assertEquals('KT1-ABC123-DEF456', $savedData['simple/package']['licenseKey']);
        $this->assertEquals('^1.0.0', $savedData['simple/package']['licenseCompatibility']);
    }

    #[Test]
    public function activate_from_request_throws_when_the_request_lacks_an_email(): void
    {
        $repository = new LicenseRepository();
        $validator = new LicenseValidator('test/package');
        $activator = new LicenseActivator(
            'test/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $request = new Request([
            'body' => ['licenseKey' => 'KT2-xxxxx-xxxxx']
        ]);

        $this->assertActivationFails(
            ActivationOutcome::MissingParameters,
            fn () => $activator->activateFromRequest($request)
        );
    }

    #[Test]
    public function activate_from_request_throws_when_the_request_lacks_a_license_key(): void
    {
        $repository = new LicenseRepository();
        $validator = new LicenseValidator('test/package');
        $activator = new LicenseActivator(
            'test/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $request = new Request([
            'body' => ['email' => 'test@example.com']
        ]);

        $this->assertActivationFails(
            ActivationOutcome::MissingParameters,
            fn () => $activator->activateFromRequest($request)
        );
    }

    #[Test]
    public function activate_throws_when_the_license_is_already_activated(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => '1.0.0',
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]));

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('test/package');
        $activator = new LicenseActivator(
            'test/package',
            $repository,
            $validator,
            $this->httpClient
        );

        $this->assertActivationFails(
            ActivationOutcome::AlreadyActivated,
            fn () => $activator->activate('test@example.com', '123456')
        );
    }
}
