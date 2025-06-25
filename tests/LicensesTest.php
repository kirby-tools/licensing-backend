<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use Kirby\Http\Request;
use PHPUnit\Framework\TestCase;

class LicensesTest extends TestCase
{
    public const LICENSE_FILE_PATH = __DIR__ . '/' . Licenses::LICENSE_FILE;

    private App $kirby;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ]
        ]);

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
        if (file_exists(static::LICENSE_FILE_PATH)) {
            unlink(static::LICENSE_FILE_PATH);
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
        $licenses = new Licenses(
            licenses: [],
            packageName: 'test/package'
        );

        $this->assertTrue($licenses->isValid('KT1-ABC123-DEF456'));
        $this->assertTrue($licenses->isValid('KT999-XYZ789-ABC123'));
        $this->assertFalse($licenses->isValid('INVALID-LICENSE'));
        $this->assertFalse($licenses->isValid('KT-ABC123-DEF456'));
        $this->assertFalse($licenses->isValid(null));
    }

    public function testGetLicenseVersion(): void
    {
        $licenses = new Licenses(
            licenses: [
                'test/package' => [
                    'licenseKey' => 'KT1-ABC123-DEF456',
                    'licenseCompatibility' => '^1.0.0'
                ]
            ],
            packageName: 'test/package'
        );

        $this->assertEquals(1, $licenses->getLicenseVersion());

        $licenses = new Licenses(
            licenses: [
                'test/package' => [
                    'licenseKey' => 'KT999-ABC123-DEF456',
                    'licenseCompatibility' => '^1.0.0'
                ]
            ],
            packageName: 'test/package'
        );

        $this->assertEquals(999, $licenses->getLicenseVersion());
    }

    public function testGetLicenseVersionWithInvalidKey(): void
    {
        $licenses = new Licenses(
            licenses: [
                'test/package' => [
                    'licenseKey' => 'INVALID-KEY',
                    'licenseCompatibility' => '^1.0.0'
                ]
            ],
            packageName: 'test/package'
        );

        $this->assertNull($licenses->getLicenseVersion());
    }

    public function testGetStatus(): void
    {
        $licenses = new Licenses(
            licenses: [],
            packageName: 'test/package'
        );
        $this->assertEquals('inactive', $licenses->getStatus());

        $licenses = new Licenses(
            licenses: [
                'test/package' => [
                    'licenseKey' => 'INVALID-KEY',
                    'licenseCompatibility' => '^1.0.0'
                ]
            ],
            packageName: 'test/package'
        );
        $this->assertEquals('invalid', $licenses->getStatus());
    }

    public function testIsUpgradeable(): void
    {
        $licenses = new Licenses(
            licenses: [],
            packageName: 'test/package'
        );

        $this->assertFalse($licenses->isUpgradeable(null));
        $this->assertFalse($licenses->isUpgradeable(''));
        $this->assertFalse($licenses->isUpgradeable('^1.0.0'));
        $this->assertFalse($licenses->isUpgradeable('^2.0.0'));
    }

    public function testActivateThrowsExceptionWhenAlreadyActivated(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Create a licenses instance with an already activated license
        $licenses = new Licenses(
            licenses: [
                'test/package' => [
                    'licenseKey' => 'KT1-ABC123-DEF456',
                    'licenseCompatibility' => '^1.0.0'
                ]
            ],
            packageName: 'test/package',
            httpClient: null
        );

        $this->expectException(\Kirby\Exception\LogicException::class);
        $this->expectExceptionMessage('License key already activated');

        $licenses->activate('test@example.com', '123456');
    }

    public function testActivateFromRequestMissingEmail(): void
    {
        $licenses = new Licenses(
            licenses: [],
            packageName: 'test/package'
        );

        // Create a request that's missing email
        $request = new Request([
            'body' => ['orderId' => '123456']
        ]);

        $this->expectException(\Kirby\Exception\LogicException::class);
        $this->expectExceptionMessage('Missing license registration parameters "email" or "orderId"');

        $licenses->activateFromRequest($request);
    }

    public function testActivateFromRequestMissingOrderId(): void
    {
        $licenses = new Licenses(
            licenses: [],
            packageName: 'test/package'
        );

        // Create a request that's missing orderId
        $request = new Request([
            'body' => ['email' => 'test@example.com']
        ]);

        $this->expectException(\Kirby\Exception\LogicException::class);
        $this->expectExceptionMessage('Missing license registration parameters "email" or "orderId"');

        $licenses->activateFromRequest($request);
    }

    public function testGetLicenseReturnsArrayWhenActivated(): void
    {
        $licenses = new Licenses(
            licenses: [
                'test/package' => [
                    'licenseKey' => 'KT1-ABC123-DEF456',
                    'licenseCompatibility' => '^1.0.0'
                ]
            ],
            packageName: 'test/package'
        );

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
        file_put_contents(static::LICENSE_FILE_PATH, '{}');

        $licenses = new Licenses(
            licenses: [],
            packageName: 'test/package'
        );

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
        $licenses = new Licenses(
            licenses: [],
            packageName: 'test/package'
        );

        // Since the plugin is not found, it should return `false` for all version constraints
        $this->assertFalse($licenses->isCompatible('^1.0.0'));
        $this->assertFalse($licenses->isCompatible('^2.0.0'));
        $this->assertFalse($licenses->isCompatible(null));
    }
}
