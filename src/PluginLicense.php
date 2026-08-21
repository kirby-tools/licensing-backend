<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Kirby\Plugin\License as KirbyLicense;
use Kirby\Plugin\LicenseStatus as KirbyLicenseStatus;
use Kirby\Plugin\Plugin;
use Kirby\Toolkit\I18n;

/**
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class PluginLicense extends KirbyLicense
{
    public const LICENSE_NAME = 'Kirby Tools Plugin License';
    public const LICENSE_URL = 'https://kirby.tools/license';

    public function __construct(
        Plugin $plugin,
        private readonly string $packageName
    ) {
        $licenses = Licenses::read($packageName);
        $status = $this->toKirbyStatus($licenses->getStatusEnum());

        parent::__construct(
            plugin: $plugin,
            name: self::LICENSE_NAME,
            link: self::LICENSE_URL,
            status: $status
        );
    }

    private function toKirbyStatus(LicenseStatus $customStatus): KirbyLicenseStatus
    {
        $dialogPrefix = LicenseUtils::toPackageSlug($this->packageName);
        $label = self::translateStatus($customStatus);

        return match ($customStatus) {
            LicenseStatus::Active => new KirbyLicenseStatus(
                value: LicenseStatus::Active->value,
                label: $label,
                icon: 'check',
                theme: 'positive',
                dialog: "{$dialogPrefix}/license"
            ),
            LicenseStatus::Inactive => new KirbyLicenseStatus(
                value: 'missing',
                label: $label,
                icon: 'key',
                theme: 'love',
                dialog: "{$dialogPrefix}/activate"
            ),
            LicenseStatus::Invalid => new KirbyLicenseStatus(
                value: LicenseStatus::Invalid->value,
                label: $label,
                icon: 'alert',
                theme: 'negative',
                dialog: "{$dialogPrefix}/activate"
            ),
            LicenseStatus::Incompatible => new KirbyLicenseStatus(
                value: LicenseStatus::Incompatible->value,
                label: $label,
                icon: 'alert',
                theme: 'negative',
                dialog: "{$dialogPrefix}/license"
            ),
            LicenseStatus::Upgradeable => new KirbyLicenseStatus(
                value: LicenseStatus::Upgradeable->value,
                label: $label,
                icon: 'refresh',
                theme: 'notice',
                dialog: "{$dialogPrefix}/license"
            )
        };
    }

    /**
     * Translates the status label, dropping the translation cache when a lookup
     * during plugin loading froze it before any plugin registered its strings.
     */
    private static function translateStatus(LicenseStatus $status): string
    {
        $key = 'kirby-tools.license.status.' . $status->value;
        $locale = I18n::locale();

        // `I18n::translate()` falls through to the fallback locales, so a frozen
        // locale yields another language's string instead of `null`.
        if (isset(I18n::translation($locale)[$key]) === false) {
            I18n::$translations = [];
        }

        $label = I18n::translate($key);

        // `Kirby\Plugin\LicenseStatus` types `$label` as `string`, so a host that
        // never registered `LicensePanel::translations()` would hit a type error.
        return is_string($label) ? $label : LicensePanel::translations()['en'][$key];
    }
}
