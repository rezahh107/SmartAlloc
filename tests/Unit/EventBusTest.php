<?php
// phpcs:ignoreFile

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\{ListenerInterface, LoggerInterface, EventStoreInterface};

class NullLogger implements LoggerInterface {
        public function info( string $m, array $c = array() ): void {}
        public function error( string $m, array $c = array() ): void {}
        public function debug( string $m, array $c = array() ): void {}
        public function warning( string $m, array $c = array() ): void {}
}
class DupStore implements EventStoreInterface {
	public function insertEventIfNotExists( string $e, string $k, array $p ): int {
		return 0; }
	public function startListenerRun( int $id, string $l ): int {
		return 1; }
	public function finishListenerRun( int $id, string $s, ?string $e, int $d ): void {}
	public function finishEvent( int $id, string $s, ?string $e, int $d ): void {}
}
class CountingListener implements ListenerInterface {
	public static int $n = 0;
	public function handle( string $e, array $p ): void {
		++self::$n; }
}

final class EventBusTest extends TestCase {
	public function test_duplicate_event_skips_listeners(): void {
		$bus = new EventBus( new NullLogger(), new DupStore() );
		$bus->on( 'Evt', new CountingListener() );
		CountingListener::$n = 0;
		$bus->dispatch( 'Evt', array( 'entry_id' => 123 ) );
		$this->assertSame( 0, CountingListener::$n );
	}
}
