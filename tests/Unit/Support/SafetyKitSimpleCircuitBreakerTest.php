<?php // phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Support\{SimpleCircuitBreaker, SafetyKit};

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}
if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('wp_date')) {
    function wp_date($format)
    {
        return 'U' === $format ? (string) time() : date($format);
    }
}

final class SafetyKitSimpleCircuitBreakerTest extends TestCase
{
    public function testBasicExecution(): void
    {
        $c = new SimpleCircuitBreaker(2, 10, 't');
        $this->assertSame('ok', $c->execute(fn() => 'ok'));
        $this->assertFalse($c->isOpen());
    }

    public function testCircuitOpensAfterFailures(): void
    {
        $c = new SimpleCircuitBreaker(1, 10, 't');
        try { $c->execute(fn() => throw new \Exception('fail')); } catch (\Exception $e) {}
        $this->assertTrue($c->isOpen());
        $this->expectException(\Exception::class);
        $c->execute(fn() => 'nope');
    }

    public function testFailureCountResetsAfterSuccess(): void
    {
        $c = new SimpleCircuitBreaker(2, 10, 't');
        try { $c->execute(fn() => throw new \Exception('fail')); } catch (\Exception $e) {}
        $this->assertFalse($c->isOpen());
        $c->execute(fn() => 'ok'); // success should reset failure count
        try { $c->execute(fn() => throw new \Exception('fail')); } catch (\Exception $e) {}
        $this->assertFalse($c->isOpen());
        try { $c->execute(fn() => throw new \Exception('fail')); } catch (\Exception $e) {}
        $this->assertTrue($c->isOpen());
    }

    public function testFactoryMethod(): void
    {
        $c = SafetyKit::createSimpleCircuitBreaker('f', 3, 30);
        $s = $c->getStatistics();
        $this->assertSame('f', $s['name']);
        $this->assertSame(3, $s['failure_threshold']);
    }

    public function testSafeExecuteStaticMethod(): void
    {
        $this->assertSame('safe', SafetyKit::safeExecute(fn() => 'safe', 'safe', 3));
        $r = null;
        for ($i = 0; $i < 2; $i++) {
            try { $r = SafetyKit::safeExecute(fn() => throw new \Exception('fail'), 'sf', 1, 1); } catch (\Exception $e) { $r = null; }
        }
        $this->assertNull($r);
    }

    public function testStatusMessageEscaping(): void
    {
        $c = new SimpleCircuitBreaker(1, 10, 'x<script>');
        $m = $c->getStatusMessage();
        $this->assertStringNotContainsString('<script>', $m);
        $this->assertStringContainsString('&lt;script&gt;', $m);
    }
}
