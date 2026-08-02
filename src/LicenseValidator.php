<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Composer\Semver\Semver;

/**
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicenseValidator
{
    private const LICENSE_PATTERN = '!^KT(\d+)-\w+-\w+$!';

    public function __construct(
        private readonly string $packageName
    ) {
    }

    /**
     * Checks whether a license key has the expected `KT<generation>-…` shape.
     */
    public function isValid(string|null $licenseKey): bool
    {
        return $licenseKey !== null && preg_match(self::LICENSE_PATTERN, $licenseKey) === 1;
    }

    /**
     * Checks whether the installed plugin version satisfies the license's version constraint.
     */
    public function isCompatible(string|null $versionConstraint): bool
    {
        $version = $this->getPluginVersion();

        return $versionConstraint !== null &&
            $version !== null &&
            Semver::satisfies($version, $versionConstraint);
    }

    /**
     * Checks whether the installed plugin's major version is newer than every major the license covers.
     */
    public function isUpgradeable(string|null $versionConstraint): bool
    {
        if ($versionConstraint === null) {
            return false;
        }

        $version = $this->getPluginVersion();
        if ($version === null) {
            return false;
        }

        // Compatibility constraints are always caret ranges like `^1 || ^2`;
        // anything else contributes no licensed major
        $constraints = explode('||', $versionConstraint);
        $maxLicensedMajor = 0;

        foreach ($constraints as $constraint) {
            $constraint = trim($constraint);
            if (preg_match('/\^(\d+)/', $constraint, $matches)) {
                $maxLicensedMajor = max($maxLicensedMajor, (int)$matches[1]);
            }
        }

        if (preg_match('/^(\d+)\./', $version, $matches)) {
            $currentMajor = (int)$matches[1];

            return $currentMajor > $maxLicensedMajor;
        }

        return false;
    }

    public function getLicenseGeneration(string|null $licenseKey): int|null
    {
        if ($licenseKey !== null && preg_match(self::LICENSE_PATTERN, $licenseKey, $matches) === 1) {
            return (int)$matches[1];
        }

        return null;
    }

    public function getPluginVersion(): string|null
    {
        return LicenseUtils::getPluginVersion($this->packageName);
    }
}
