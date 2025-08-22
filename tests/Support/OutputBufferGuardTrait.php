<?php
// tests/Support/OutputBufferGuardTrait.php

declare(strict_types=1);

namespace SmartAlloc\Tests\Support;

trait OutputBufferGuardTrait {
    /** @var int */
    private int $obBaseline = 0;

    protected function obRememberBaseline(): void {
        $this->obBaseline = \ob_get_level();
    }

    protected function obEndLeakedBuffers(): void {
        // Close any buffers above baseline to avoid PHPUnit "did not close output buffers"
        while (\ob_get_level() > $this->obBaseline) {
            \ob_end_clean();
        }
    }

    protected function assertNoLeakedOutput(): void {
        $this->assertSame(
            $this->obBaseline,
            \ob_get_level(),
            'Leaked output buffers detected; ensure every ob_start() has a matching ob_end_*().'
        );
    }
}

