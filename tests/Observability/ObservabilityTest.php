<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Observability;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\StatsService;
use SmartAlloc\Logging\Logger;
use SmartAlloc\Observability\Tracer;
use SmartAlloc\Observability\AlertService;

class ObservabilityTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
        if (!defined('SMARTALLOC_TEST_MODE')) {
            define('SMARTALLOC_TEST_MODE', true);
        }
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_metrics_are_consistent_with_operations(): void
    {
        $stats = new StatsService($this->createMock(\SmartAlloc\Services\Db::class), new \SmartAlloc\Services\Logging());
        $stats->counter('alloc_total');
        $stats->counter('alloc_success');
        $stats->gauge('dlq_backlog', 2);
        $stats->histogram('resp_ms', 120, [100,200,500]);
        $stats->histogram('resp_ms', 50, [100,200,500]);
        $rate = $stats->getMetric('alloc_success') / $stats->getMetric('alloc_total');
        $this->assertSame(1.0, $rate);
        $this->assertSame(2.0, $stats->getMetric('dlq_backlog'));
        $this->assertSame(1, $stats->getBucket('resp_ms', '200'));
        $this->assertSame(1, $stats->getBucket('resp_ms', '100'));
    }

    public function test_tracing_spans_cover_critical_paths(): void
    {
        Tracer::reset();
        Tracer::start('alloc.find_candidates'); usleep(1000); Tracer::finish('alloc.find_candidates');
        Tracer::start('alloc.rank'); usleep(1000); Tracer::finish('alloc.rank');
        Tracer::start('alloc.commit'); usleep(1000); Tracer::finish('alloc.commit');
        Tracer::start('notify.dispatch'); usleep(1000); Tracer::finish('notify.dispatch');
        Tracer::start('dlq.retry'); usleep(1000); Tracer::finish('dlq.retry');
        $spans = Tracer::spans();
        foreach (['alloc.find_candidates','alloc.rank','alloc.commit','notify.dispatch','dlq.retry'] as $name) {
            $this->assertArrayHasKey($name, $spans);
            $span = $spans[$name][0];
            $this->assertGreaterThan(0, $span['end'] - $span['start']);
        }
        $this->assertLessThan($spans['alloc.commit'][0]['start'], $spans['alloc.rank'][0]['start']);
    }

    public function test_structured_logging_masks_pii(): void
    {
        $logger = new Logger(Logger::INFO);
        $logger->debug('skip');
        $logger->info('ok', ['email'=>'foo@bar.com','mobile'=>'12345','national_id'=>'9988']);
        $this->assertCount(1, $logger->records);
        $ctx = $logger->records[0]['context'];
        $this->assertStringNotContainsString('foo@bar.com', $ctx['email']);
        $this->assertStringNotContainsString('12345', $ctx['mobile']);
        $this->assertStringNotContainsString('9988', $ctx['national_id']);
    }

    public function test_alert_thresholds_trigger_and_dedupe(): void
    {
        $alerts = new AlertService();
        $alerts->check('alloc_p95_ms', 3000);
        $alerts->check('alloc_p95_ms', 3100);
        $alerts->check('notify_failure_rate', 0.1);
        $alerts->check('notify_failure_rate', 0.2);
        $alerts->check('dlq_backlog', 500);
        $alerts->check('dlq_backlog', 600);
        $this->assertSame(['alloc_p95_ms','notify_failure_rate','dlq_backlog'], $alerts->events);
    }
}
