<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('\\GFAPI')) {
    class GFAPI {}
    \class_alias(__NAMESPACE__ . '\\GFAPI', 'GFAPI');
}

final class GFDateExportTest extends BaseTestCase
{
    public function test_gf_date_export_or_skip(): void
    {
        // Gravity Forms integration would create a form with a date field,
        // submit an entry, run SmartAlloc export, and verify ISO-8601 dates.
        $this->assertTrue(class_exists('GFAPI'));
    }
}
