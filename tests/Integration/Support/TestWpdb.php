<?php
declare(strict_types=1);

// Stub to align with core wpdb without typed property conflicts.
if (!class_exists('TestWpdb')) {
    class TestWpdb extends \wpdb {
        /** @var string|null */
        public $last_query = '';
        /** @var string|null */
        public $last_error = '';
        /** @var int */
        public $num_queries = 0;
        /** @var string */
        public $prefix = 'wp_';

        public function __construct() {
            // Do not call parent constructor to avoid real DB connections.
        }

        public function query($query) {
            $this->last_query = (string) $query;
            $this->num_queries++;
            return true;
        }

        public function get_results($query = null, $output = OBJECT) {
            $this->last_query = (string) $query;
            return [];
        }
    }
}
