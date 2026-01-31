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
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseActivator::class)]
final class LicenseActivationTest extends TestCase
{
    private const LICENSE_FILE = __DIR__ . '/' . LicenseRepository::LICENSE_FILE;

    private App $kirby;
    private HttpClientInterface&MockObject $mockHttpClient;

    protected function setUp(): void
    {
        // Initialize Kirby application with custom license file path
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ]
        ]);

        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
    }

    protected function tearDown(): void
    {
        if (file_exists(static::LICENSE_FILE)) {
            unlink(static::LICENSE_FILE);
        }

        App::destroy();
    }

    #[Test]
    public function activate_successfully(): void
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
    public function activate_with_wrong_package_name(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Set up API response with wrong package name
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
    public function activate_with_incompatible_version(): void
    {
        // Register a plugin with version 2.0.0 that's incompatible with license ^1.0.0
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '2.0.0'],
            version: '2.0.0'
        );

        // Set up API response with incompatible license version
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0', // Only supports 1.x
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
    public function activate_with_api_error(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Mock API error response
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
    public function activation_updates_license_file(): void
    {
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Mock successful HTTP response
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

        // Verify license file was created
        $this->assertFileExists(static::LICENSE_FILE);

        // Verify license data was saved correctly
        $savedData = json_decode(file_get_contents(static::LICENSE_FILE), true);
        $this->assertArrayHasKey('simple/package', $savedData);
        $this->assertEquals('KT1-ABC123-DEF456', $savedData['simple/package']['licenseKey']);
        $this->assertEquals('^1.0.0', $savedData['simple/package']['licenseCompatibility']);
    }

    #[Test]
    public function activate_from_request_missing_email(): void
    {
        $repository = new LicenseRepository();
        $validator = new LicenseValidator('test/package');
        $activator = new LicenseActivator(
            'test/package',
            $repository,
            $validator,
            $this->mockHttpClient
        );

        // Create a request that's missing email
        $request = new Request([
            'body' => ['orderId' => '123456']
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing license registration parameters "email" or "orderId"');

        $activator->activateFromRequest($request);
    }

    #[Test]
    public function activate_from_request_missing_order_id(): void
    {
        $repository = new LicenseRepository();
        $validator = new LicenseValidator('test/package');
        $activator = new LicenseActivator(
            'test/package',
            $repository,
            $validator,
            $this->mockHttpClient
        );

        // Create a request that's missing order ID
        $request = new Request([
            'body' => ['email' => 'test@example.com']
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing license registration parameters "email" or "orderId"');

        $activator->activateFromRequest($request);
    }

    #[Test]
    public function activate_throws_exception_when_already_activated(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Create a license file with an already activated license
        file_put_contents(static::LICENSE_FILE, json_encode([
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
