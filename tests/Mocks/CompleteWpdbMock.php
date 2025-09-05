<?php
// phpcs:ignoreFile
/**
 * Minimal in-memory wpdb mock.
 *
 * @package SmartAlloc\Tests
 */
class CompleteWpdbMock {
public $prefix = 'wp_';
public $last_query = '';
public $insert_id = 0;
public $num_rows = 0;
public $last_result = [];
private array $data = [];
public function __construct() {
$this->data['wp_options'] = [
['option_name' => 'siteurl', 'option_value' => 'http://example.org'],
];
}
public function query($query) {
$this->last_query = $query;
if (stripos($query, 'select') === 0) {
return $this->select($query);
}
$this->num_rows = 1;
return 1;
}
private function select($query) {
$this->last_result = [];
$this->num_rows = 0;
if (preg_match('/from\s+(\w+)/i', $query, $m)) {
$t = $m[1];
if (isset($this->data[$t])) {
$this->last_result = array_map(fn($r) => (object) $r, $this->data[$t]);
$this->num_rows = count($this->last_result);
}
}
return $this->num_rows;
}
public function get_results($q = null) {
if ($q) {
$this->query($q);
}
return $this->last_result;
}
public function get_var($q = null) {
$r = $this->get_results($q);
return $r[0]->option_value ?? null;
}
public function get_row($q = null) {
$r = $this->get_results($q);
return $r[0] ?? null;
}
public function get_col($q = null) {
$r = $this->get_results($q);
return array_map(fn($o) => current((array) $o), $r);
}
public function insert($t, $d) {
$this->insert_id++;
$this->data[$t][] = $d;
return 1;
}
public function update($t, $d, $w) {
return 1;
}
public function delete($t, $w) {
return 1;
}
public function prepare($q, ...$a) {
foreach ($a as $v) {
$q = preg_replace('/%s/', '\'' . addslashes((string) $v) . '\'', $q, 1);
}
return $q;
}
public function flush() {
$this->last_result = [];
$this->num_rows = 0;
}
}
