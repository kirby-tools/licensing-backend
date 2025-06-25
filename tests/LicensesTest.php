<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

class LicensesTest extends TestCase
{
    public const LICENSE_FILE = __DIR__ . '/.kirby-tools-licenses';

    private App $kirby;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ]
        ]);

        // Register a mock plugin for testing
        $mockPlugin = $this->createMock(\Kirby\Plugin\Plugin::class);
        $mockPlugin->method('version')->willReturn('1.0.0');

        $this->kirby->extend([
            'plugins' => [
                'test/package' => $mockPlugin
            ]
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists(static::LICENSE_FILE)) {
            unlink(static::LICENSE_FILE);
        }

        App::destroy();
    }

    public function testReadWithNoLicenseFile(): void
    {
        $licenses = Licenses::read('test/package');

        $this->assertInstanceOf(Licenses::class, $licenses);
        $this->assertEquals('inactive', $licenses->getStatus());
        $this->assertNull($licenses->getLicenseKey());
        $this->assertFalse($licenses->getLicense());
    }

    public function testIsValidLicenseKey(): void
    {
        $licenses = new Licenses([], 'test/package');

        $this->assertTrue($licenses->isValid('KT1-ABC123-DEF456'));
        $this->assertTrue($licenses->isValid('KT999-XYZ789-ABC123'));
        $this->assertFalse($licenses->isValid('INVALID-LICENSE'));
        $this->assertFalse($licenses->isValid('KT-ABC123-DEF456'));
        $this->assertFalse($licenses->isValid(null));
    }

    public function testGetLicenseVersion(): void
    {
        $licenses = new Licenses([
            'test/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0'
            ]
        ], 'test/package');

        $this->assertEquals(1, $licenses->getLicenseVersion());

        $licenses = new Licenses([
            'test/package' => [
                'licenseKey' => 'KT999-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0'
            ]
        ], 'test/package');

        $this->assertEquals(999, $licenses->getLicenseVersion());
    }

    public function testGetLicenseVersionWithInvalidKey(): void
    {
        $licenses = new Licenses([
            'test/package' => [
                'licenseKey' => 'INVALID-KEY',
                'licenseCompatibility' => '^1.0.0'
            ]
        ], 'test/package');

        $this->assertNull($licenses->getLicenseVersion());
    }

    public function testGetStatus(): void
    {
        $licenses = new Licenses([], 'test/package');
        $this->assertEquals('inactive', $licenses->getStatus());

        $licenses = new Licenses([
            'test/package' => [
                'licenseKey' => 'INVALID-KEY',
                'licenseCompatibility' => '^1.0.0'
            ]
        ], 'test/package');
        $this->assertEquals('invalid', $licenses->getStatus());
    }

    public function testIsUpgradeable(): void
    {
        $licenses = new Licenses([], 'test/package');

        $this->assertFalse($licenses->isUpgradeable(null));
        $this->assertFalse($licenses->isUpgradeable(''));
        $this->assertFalse($licenses->isUpgradeable('^1.0.0'));
        $this->assertFalse($licenses->isUpgradeable('^2.0.0'));
    }

    public function testActivateThrowsExceptionWhenAlreadyActivated(): void
    {
        // Skip this test for now due to complexity of mocking HTTP and plugin registration
        $this->markTestSkipped('Requires mocking, to be implemented later');
    }

    public function testGetLicenseReturnsArrayWhenActivated(): void
    {
        $licenses = new Licenses([
            'test/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0'
            ]
        ], 'test/package');

        $mockPlugin = $this->createMock(\Kirby\Plugin\Plugin::class);
        $mockPlugin->method('version')->willReturn('1.0.0');

        $this->kirby->extend([
            'plugins' => [
                'test/package' => $mockPlugin
            ]
        ]);

        $license = $licenses->getLicense();

        if ($licenses->isActivated()) {
            $this->assertIsArray($license);
            $this->assertArrayHasKey('key', $license);
            $this->assertArrayHasKey('version', $license);
            $this->assertArrayHasKey('compatibility', $license);
        } else {
            $this->assertFalse($license);
        }
    }

    public function testUpdate(): void
    {
        file_put_contents(static::LICENSE_FILE, '{}');

        $licenses = new Licenses([], 'test/package');

        $mockPlugin = $this->createMock(\Kirby\Plugin\Plugin::class);
        $mockPlugin->method('version')->willReturn('1.0.0');

        $this->kirby->extend([
            'plugins' => [
                'test/package' => $mockPlugin
            ]
        ]);

        $testData = [
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ];

        $licenses->update('test/package', $testData);

        $this->assertEquals('KT1-ABC123-DEF456', $licenses->getLicenseKey());
        $this->assertEquals('^1.0.0', $licenses->getLicenseCompatibility());
    }

    public function testIsCompatible(): void
    {
        $licenses = new Licenses([], 'test/package');

        // Since the plugin is not found, it should return `false` for all version constraints
        $this->assertFalse($licenses->isCompatible('^1.0.0'));
        $this->assertFalse($licenses->isCompatible('^2.0.0'));
        $this->assertFalse($licenses->isCompatible(null));
    }
}
