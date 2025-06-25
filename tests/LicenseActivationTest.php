<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\Licenses;
use JohannSchopplich\Licensing\Plugin\PluginRegistryInterface;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use Kirby\Http\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LicenseActivationTest extends TestCase
{
    private const LICENSE_FILE = __DIR__ . '/' . Licenses::LICENSE_FILE;

    private App $kirby;
    private HttpClientInterface&MockObject $mockHttpClient;
    private PluginRegistryInterface&MockObject $mockPluginRegistry;

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
        $this->mockPluginRegistry = $this->createMock(PluginRegistryInterface::class);
    }

    protected function tearDown(): void
    {
        if (file_exists(static::LICENSE_FILE)) {
            unlink(static::LICENSE_FILE);
        }

        App::destroy();
    }

    public function testActivateSuccessfully(): void
    {
        // Mock successful HTTP response
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        // Mock plugin version resolver to return the expected version
        $this->mockPluginRegistry->method('getPluginVersion')
            ->with('simple/package')
            ->willReturn('1.0.0');

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient,
            pluginRegistry: $this->mockPluginRegistry
        );
        $licenses->activate('test@example.com', '123456');

        $this->assertEquals('KT1-ABC123-DEF456', $licenses->getLicenseKey());
        $this->assertEquals('^1.0.0', $licenses->getLicenseCompatibility());
        $this->assertEquals('active', $licenses->getStatus());
    }

    public function testActivateWithWrongPackageName(): void
    {
        // Set up API response with wrong package name
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'wrong/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $this->mockPluginRegistry->method('getPluginVersion')
            ->with('simple/package')
            ->willReturn('1.0.0');

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient,
            pluginRegistry: $this->mockPluginRegistry
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key not valid for this plugin');

        $licenses->activate('test@example.com', '123456');
    }

    public function testActivateWithIncompatibleVersion(): void
    {
        // Set up API response with incompatible license version
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0', // Only supports 1.x
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        // Plugin version 2.0.0 that's incompatible with license ^1.0.0
        $this->mockPluginRegistry->method('getPluginVersion')
            ->with('simple/package')
            ->willReturn('2.0.0');

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient,
            pluginRegistry: $this->mockPluginRegistry
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key not valid for this plugin version');

        $licenses->activate('test@example.com', '123456');
    }

    public function testActivateWithApiError(): void
    {
        // Set up error response
        $this->mockHttpClient->method('request')
            ->willThrowException(new LogicException('Invalid order ID'));

        $this->mockPluginRegistry->method('getPluginVersion')
            ->with('simple/package')
            ->willReturn('1.0.0');

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient,
            pluginRegistry: $this->mockPluginRegistry
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid order ID');

        $licenses->activate('test@example.com', '123456');
    }

    public function testActivateFromRequestWithValidData(): void
    {
        // Set up successful API response
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        // Create a request with valid data using Kirby's Request class
        $request = new Request([
            'body' => [
                'email' => 'test@example.com',
                'orderId' => '123456'
            ]
        ]);

        $this->mockPluginRegistry->method('getPluginVersion')
            ->with('simple/package')
            ->willReturn('1.0.0');

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient,
            pluginRegistry: $this->mockPluginRegistry
        );
        $result = $licenses->activateFromRequest($request);

        $this->assertEquals([
            'code' => 200,
            'status' => 'ok',
            'message' => 'License key successfully activated'
        ], $result);

        $this->assertEquals('KT1-ABC123-DEF456', $licenses->getLicenseKey());
    }

    public function testActivationUpdatesLicenseFile(): void
    {
        // Set up successful API response
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $this->mockPluginRegistry->method('getPluginVersion')
            ->with('simple/package')
            ->willReturn('1.0.0');

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient,
            pluginRegistry: $this->mockPluginRegistry
        );
        $licenses->activate('test@example.com', '123456');

        // Verify license file was created
        $this->assertFileExists(static::LICENSE_FILE);

        // Verify license data was saved correctly
        $savedData = json_decode(file_get_contents(static::LICENSE_FILE), true);
        $this->assertArrayHasKey('simple/package', $savedData);
        $this->assertEquals('KT1-ABC123-DEF456', $savedData['simple/package']['licenseKey']);
        $this->assertEquals('^1.0.0', $savedData['simple/package']['licenseCompatibility']);
    }
}
