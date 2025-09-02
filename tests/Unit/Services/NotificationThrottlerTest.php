<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\NotificationThrottler;

if (!function_exists('get_transient')) { function get_transient($k){ global $t; return $t[$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$e){ global $t; $t[$k] = $v; } }
if (!function_exists('get_option')) { function get_option($k,$d=false){ global $o; return $o[$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k,$v){ global $o; $o[$k] = $v; } }

final class NotificationThrottlerTest extends TestCase
{
    public function test_allows_and_blocks_by_limit(): void
    {
        global $t, $o; $t = $o = [];
        $thr = new NotificationThrottler();
        $this->assertTrue($thr->canSend('a'));    
        for ($i = 0; $i < 10; $i++) {
            $thr->recordSend('a');
        }
        $this->assertFalse($thr->canSend('a'));
        $this->assertSame(['hits' => 10], $thr->getThrottleStats());
    }
}
