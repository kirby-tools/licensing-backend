<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\LicenseActivator;
use JohannSchopplich\Licensing\LicenseRepository;
use JohannSchopplich\Licensing\Licenses;
use JohannSchopplich\Licensing\LicenseValidator;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use Kirby\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(LicenseActivator::class)]
final class LicenseActivationTest extends LicenseTestCase
{
    private App $app;
    private HttpClientInterface&MockObject $mockHttpClient;

    protected function setUp(): void
    {
        $this->app = $this->appWithLicenseRoots();

        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
    }

    #[Test]
    public function marks_the_license_active_when_the_api_confirms_the_key(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->mockHttpClient->method('request')->willReturn([
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
            $this->mockHttpClient
        );

        $activator->activate('test@example.com', '123456');

        $licenses = Licenses::read('simple/package');
        $this->assertEquals('active', $licenses->getStatus());
    }

    #[Test]
    public function throws_when_the_key_belongs_to_another_package(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->mockHttpClient->method('request')->willReturn([
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
            $this->mockHttpClient
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key not valid for this plugin');

        $activator->activate('test@example.com', '123456');
    }

    #[Test]
    public function throws_naming_the_upgrade_when_the_plugin_outgrew_the_license(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '2.0.0'],
            version: '2.0.0'
        );

        $this->mockHttpClient->method('request')->willReturn([
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
            $this->mockHttpClient
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key not valid for this plugin version, please upgrade your license');

        $activator->activate('test@example.com', '123456');
    }

    #[Test]
    public function throws_when_the_api_response_omits_the_package_name(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->mockHttpClient->method('request')->willReturn([
            'error' => 'License not found'
        ]);

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('simple/package');
        $activator = new LicenseActivator(
            'simple/package',
            $repository,
            $validator,
            $this->mockHttpClient
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key not valid for this plugin');

        $activator->activate('test@example.com', '123456');
    }

    #[Test]
    public function persists_key_and_compatibility_to_the_license_file(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->mockHttpClient->method('request')->willReturn([
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
            $this->mockHttpClient
        );

        $activator->activate('test@example.com', '123456');

        $this->assertFileExists(self::LICENSE_FILE_PATH);

        $savedData = json_decode(file_get_contents(self::LICENSE_FILE_PATH), true);
        $this->assertArrayHasKey('simple/package', $savedData);
        $this->assertEquals('KT1-ABC123-DEF456', $savedData['simple/package']['licenseKey']);
        $this->assertEquals('^1.0.0', $savedData['simple/package']['licenseCompatibility']);
    }

    #[Test]
    public function throws_when_the_request_lacks_an_email(): void
    {
        $repository = new LicenseRepository();
        $validator = new LicenseValidator('test/package');
        $activator = new LicenseActivator(
            'test/package',
            $repository,
            $validator,
            $this->mockHttpClient
        );

        $request = new Request([
            'body' => ['licenseKey' => 'KT2-xxxxx-xxxxx']
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing license activation parameters "email" or "licenseKey"');

        $activator->activateFromRequest($request);
    }

    #[Test]
    public function throws_when_the_request_lacks_a_license_key(): void
    {
        $repository = new LicenseRepository();
        $validator = new LicenseValidator('test/package');
        $activator = new LicenseActivator(
            'test/package',
            $repository,
            $validator,
            $this->mockHttpClient
        );

        $request = new Request([
            'body' => ['email' => 'test@example.com']
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing license activation parameters "email" or "licenseKey"');

        $activator->activateFromRequest($request);
    }

    #[Test]
    public function falls_back_to_the_order_id_an_older_plugin_sends(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'test/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $repository = new LicenseRepository();
        $validator = new LicenseValidator('test/package');
        $activator = new LicenseActivator(
            'test/package',
            $repository,
            $validator,
            $this->mockHttpClient
        );

        $request = new Request([
            'body' => ['email' => 'test@example.com', 'orderId' => '12345-67890']
        ]);

        $result = $activator->activateFromRequest($request);

        $this->assertSame('ok', $result['status']);
    }

    #[Test]
    public function throws_when_the_license_is_already_activated(): void
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
            $this->mockHttpClient
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key already activated');

        $activator->activate('test@example.com', '123456');
    }
}
