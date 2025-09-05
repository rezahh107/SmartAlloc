<?php
// phpcs:ignoreFile
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-complete.php';

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = [];
        public function get_param(string $key) {
            return $this->params[$key] ?? null;
        }
        public function set_param(string $key, $value): void {
            $this->params[$key] = $value;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;
        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
        public function get_data() {
            return $this->data;
        }
    }
}

