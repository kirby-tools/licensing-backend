<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicenseValidator;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(LicenseValidator::class)]
final class LicenseValidatorTest extends LicenseTestCase
{
    #[Test]
    public function is_valid_accepts_keys_in_the_kt_format(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertTrue($validator->isValid('KT1-ABC123-DEF456'));
        $this->assertTrue($validator->isValid('KT999-XYZ789-ABC123'));
    }

    #[Test]
    public function is_valid_rejects_malformed_empty_and_null_keys(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isValid('INVALID-LICENSE'));
        $this->assertFalse($validator->isValid('KT-ABC123-DEF456'));
        $this->assertFalse($validator->isValid(''));
        $this->assertFalse($validator->isValid(null));
    }

    #[Test]
    public function is_upgradeable_returns_false_for_a_null_or_empty_constraint(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.5.0'],
            version: '1.5.0'
        );

        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isUpgradeable(null));
        $this->assertFalse($validator->isUpgradeable(''));
    }

    #[Test]
    public function is_upgradeable_returns_false_without_an_installed_plugin(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isUpgradeable('^1.0.0'));
        $this->assertFalse($validator->isUpgradeable('^2.0.0'));
    }

    #[Test]
    public function is_upgradeable_returns_true_only_when_the_plugin_outgrew_the_constraint(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '2.0.0'],
            version: '2.0.0'
        );

        $validator = new LicenseValidator('test/package');

        $this->assertTrue($validator->isUpgradeable('^1.0.0'));
        $this->assertFalse($validator->isUpgradeable('^2.0.0'));
    }

    #[Test]
    public function is_upgradeable_reads_the_licensed_major_from_tilde_and_exact_constraints(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '2.0.0'],
            version: '2.0.0'
        );

        $validator = new LicenseValidator('test/package');

        $this->assertTrue($validator->isUpgradeable('~1.2'));
        $this->assertTrue($validator->isUpgradeable('1.2.3'));
        $this->assertFalse($validator->isUpgradeable('~2.0'));
        $this->assertFalse($validator->isUpgradeable('^1 || ~2.0'));
    }

    #[Test]
    public function is_compatible_returns_false_without_an_installed_plugin(): void
    {
        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isCompatible('^1.0.0'));
        $this->assertFalse($validator->isCompatible('^2.0.0'));
        $this->assertFalse($validator->isCompatible(null));
    }

    #[Test]
    public function is_compatible_returns_true_when_the_installed_version_satisfies_the_constraint(): void
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

    #[Test]
    public function is_compatible_returns_false_for_an_unparsable_constraint(): void
    {
        App::plugin(
            name: 'test/package',
            extends: [],
            info: ['version' => '1.5.0'],
            version: '1.5.0'
        );

        $validator = new LicenseValidator('test/package');

        $this->assertFalse($validator->isCompatible(''));
        $this->assertFalse($validator->isCompatible('   '));
        $this->assertFalse($validator->isCompatible('invalid'));
        $this->assertFalse($validator->isCompatible('^1 || '));
    }
}
