<?php
// tests/Support/ObPhpUnitExtension.php
declare(strict_types=1);

namespace SmartAlloc\Tests\Support;

use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class ObPhpUnitExtension implements Extension, PreparedSubscriber, FinishedSubscriber {
    private int $baseline = 0;

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void {
        $facade->registerSubscriber($this);
    }

    public function notify(Prepared|Finished $event): void {
        if ($event instanceof Prepared) {
            $this->baseline = \ob_get_level();
            return;
        }

        while (\ob_get_level() > $this->baseline) {
            \ob_end_clean();
        }
        if (\ob_get_level() !== $this->baseline) {
            throw new \RuntimeException('Leaked output buffers detected after test: ' . $event->test()->id());
        }
    }
}
