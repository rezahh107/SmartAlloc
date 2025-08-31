<?php
use PHPUnit\Framework\TestCase;
use SmartAlloc\Notifications\DlqRepository;
final class DlqRepositoryTest extends TestCase {
    public function testEnqueueUsesPreparedSql(): void {
        $fake = new class implements \SmartAlloc\Infra\DbPort {
            public string $lastSql = '';public array $lastArgs = [];
            public function exec(string $sql, array $args = []): int { $this->lastSql = $sql; $this->lastArgs = $args; return 1; }
        };
        $repo = new DlqRepository($fake, 'wp_smartalloc_dlq');
        $repo->enqueue(['channel' => 'mail','payload' => ['k' => 'v'],'reason' => 'oops']);
        $this->assertStringContainsString('%s', $fake->lastSql);
        $this->assertCount(4, $fake->lastArgs);
    }
}
