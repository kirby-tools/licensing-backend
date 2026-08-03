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
    private App $app;

    protected function setUp(): void
    {
        $this->app = new App([
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
    public function accepts_keys_matching_the_kt_format(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertTrue($validator->isValid('KT1-ABC123-DEF456'));
        $this->assertTrue($validator->isValid('KT999-XYZ789-ABC123'));
    }

    #[Test]
    public function rejects_malformed_empty_and_null_keys(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isValid('INVALID-LICENSE'));
        $this->assertFalse($validator->isValid('KT-ABC123-DEF456'));
        $this->assertFalse($validator->isValid(''));
        $this->assertFalse($validator->isValid(null));
    }

    #[Test]
    public function extracts_the_generation_number_from_the_key(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertEquals(1, $validator->getLicenseGeneration('KT1-ABC123-DEF456'));
        $this->assertEquals(999, $validator->getLicenseGeneration('KT999-ABC123-DEF456'));
        $this->assertNull($validator->getLicenseGeneration('INVALID-KEY'));
    }

    #[Test]
    public function treats_a_null_compatibility_as_not_upgradeable(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.5.0'],
            version: '1.5.0'
        );

        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isUpgradeable(null));
    }

    #[Test]
    public function treats_an_empty_compatibility_as_outgrown_by_any_installed_version(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.5.0'],
            version: '1.5.0'
        );

        $validator = new LicenseValidator('test/package');

        $this->assertTrue($validator->isUpgradeable(''));
    }

    #[Test]
    public function is_not_upgradeable_without_an_installed_plugin(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isUpgradeable('^1.0.0'));
        $this->assertFalse($validator->isUpgradeable('^2.0.0'));
    }

    #[Test]
    public function is_upgradeable_only_when_the_plugin_outgrew_the_compatibility_range(): void
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
    public function is_never_compatible_without_an_installed_plugin(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isCompatible('^1.0.0'));
        $this->assertFalse($validator->isCompatible('^2.0.0'));
        $this->assertFalse($validator->isCompatible(null));
    }

    #[Test]
    public function is_compatible_when_the_installed_version_falls_in_the_range(): void
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
