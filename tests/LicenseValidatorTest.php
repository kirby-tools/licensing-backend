<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicenseValidator;
use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

class LicenseValidatorTest extends TestCase
{
    private App $kirby;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    public function testIsValidWithValidKeys(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertTrue($validator->isValid('KT1-ABC123-DEF456'));
        $this->assertTrue($validator->isValid('KT999-XYZ789-ABC123'));
    }

    public function testIsValidWithInvalidKeys(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isValid('INVALID-LICENSE'));
        $this->assertFalse($validator->isValid('KT-ABC123-DEF456'));
        $this->assertFalse($validator->isValid(''));
        $this->assertFalse($validator->isValid(null));
    }

    public function testGetLicenseGeneration(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertEquals(1, $validator->getLicenseGeneration('KT1-ABC123-DEF456'));
        $this->assertEquals(999, $validator->getLicenseGeneration('KT999-ABC123-DEF456'));
        $this->assertNull($validator->getLicenseGeneration('INVALID-KEY'));
    }

    public function testIsUpgradeableWithNullOrEmpty(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isUpgradeable(null));
        $this->assertFalse($validator->isUpgradeable(''));
    }

    public function testIsUpgradeableWithoutPlugin(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isUpgradeable('^1.0.0'));
        $this->assertFalse($validator->isUpgradeable('^2.0.0'));
    }

    public function testIsUpgradeableWithPlugin(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '2.0.0'],
            version: '2.0.0'
        );

        $validator = new LicenseValidator('test/package');

        // Plugin is v2.0.0, license supports ^1.0.0 → upgradeable
        $this->assertTrue($validator->isUpgradeable('^1.0.0'));
        // Plugin is v2.0.0, license supports ^2.0.0 → not upgradeable (already compatible)
        $this->assertFalse($validator->isUpgradeable('^2.0.0'));
    }

    public function testIsCompatibleWithoutPlugin(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isCompatible('^1.0.0'));
        $this->assertFalse($validator->isCompatible('^2.0.0'));
        $this->assertFalse($validator->isCompatible(null));
    }

    public function testIsCompatibleWithPlugin(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.5.0'],
            version: '1.5.0'
        );

        $validator = new LicenseValidator('test/package');

        $this->assertTrue($validator->isCompatible('^1.0.0'));
        $this->assertFalse($validator->isCompatible('^2.0.0'));
    }
}
