<?php
// Mixed patterns
$now = current_time('mysql'); // read
$wpdb->update('table', ['updated_at' => current_time('mysql')]); // write
log_activity(['timestamp' => current_time('mysql')]); // write
