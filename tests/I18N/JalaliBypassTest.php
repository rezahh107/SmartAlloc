<?php

declare(strict_types=1);

namespace JDC\Core;

if (!class_exists(Core::class)) {
    class Core
    {
        public static function convert(string $date): string
        {
            return 'jalali-' . $date;
        }
    }
}

namespace SmartAlloc\Tests\I18N;

use SmartAlloc\Compat\ThirdParty\JalaliDateConverter;
use SmartAlloc\Tests\BaseTestCase;

final class JalaliBypassTest extends BaseTestCase
{
    public function test_jalali_filter_bypassed_and_restored(): void
    {
        if (!class_exists(JalaliDateConverter::class)) {
            self::markTestSkipped('JalaliDateConverter unavailable');
        }

        add_filter('date_i18n', [\JDC\Core\Core::class, 'convert'], 10, 1);
        $GLOBALS['wp_filter']['date_i18n'][10]['jdc'] = [
            'function' => [\JDC\Core\Core::class, 'convert'],
            'accepted_args' => 1,
        ];
        $this->assertTrue(JalaliDateConverter::hasJalaliFilter());

        $iso = JalaliDateConverter::withDateI18nBypassed(function (): string {
            $dt = new \DateTimeImmutable('2024-03-02 10:12:00', new \DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i:s\Z');
        });

        $this->assertSame('2024-03-02T10:12:00Z', $iso);
        $this->assertTrue(JalaliDateConverter::hasJalaliFilter());
        $this->assertSame('jalali-2024-03-02 10:12:00', self::dateI18n('2024-03-02 10:12:00'));
    }

    private static function dateI18n(string $date): string
    {
        return JalaliDateConverter::hasJalaliFilter()
            ? \JDC\Core\Core::convert($date)
            : $date;
    }
}
