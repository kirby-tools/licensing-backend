<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicenseValidator;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseValidator::class)]
final class LicenseValidatorTest extends TestCase
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

    #[Test]
    public function is_valid_with_valid_keys(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertTrue($validator->isValid('KT1-ABC123-DEF456'));
        $this->assertTrue($validator->isValid('KT999-XYZ789-ABC123'));
    }

    #[Test]
    public function is_valid_with_invalid_keys(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isValid('INVALID-LICENSE'));
        $this->assertFalse($validator->isValid('KT-ABC123-DEF456'));
        $this->assertFalse($validator->isValid(''));
        $this->assertFalse($validator->isValid(null));
    }

    #[Test]
    public function get_license_generation(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertEquals(1, $validator->getLicenseGeneration('KT1-ABC123-DEF456'));
        $this->assertEquals(999, $validator->getLicenseGeneration('KT999-ABC123-DEF456'));
        $this->assertNull($validator->getLicenseGeneration('INVALID-KEY'));
    }

    #[Test]
    public function is_upgradeable_with_null_or_empty(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isUpgradeable(null));
        $this->assertFalse($validator->isUpgradeable(''));
    }

    #[Test]
    public function is_upgradeable_without_plugin(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isUpgradeable('^1.0.0'));
        $this->assertFalse($validator->isUpgradeable('^2.0.0'));
    }

    #[Test]
    public function is_upgradeable_with_plugin(): void
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

    #[Test]
    public function is_compatible_without_plugin(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isCompatible('^1.0.0'));
        $this->assertFalse($validator->isCompatible('^2.0.0'));
        $this->assertFalse($validator->isCompatible(null));
    }

    #[Test]
    public function is_compatible_with_plugin(): void
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
