<?php

declare(strict_types = 1);

use KirbyTools\Licensing\LicenseRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(LicenseRepository::class)]
final class LicenseRepositoryTest extends LicenseTestCase
{
    private const LICENSE = [
        'licenseKey' => 'KT1-ABC123-DEF456',
        'licenseCompatibility' => '^1.0.0',
        'order' => ['createdAt' => '2024-01-01T00:00:00Z']
    ];

    #[Test]
    public function save_keeps_a_license_another_instance_stored_meanwhile(): void
    {
        $repository = new LicenseRepository();
        // Caches the file before another instance stores its license.
        $repository->readAll();

        (new LicenseRepository())->save('other/package', self::LICENSE, '1.0.0');
        $repository->save('simple/package', self::LICENSE, '1.0.0');

        $savedData = json_decode(file_get_contents(self::LICENSE_FILE_PATH), true);
        $this->assertSame(['other/package', 'simple/package'], array_keys($savedData));
    }
}
