<?php

// Enforce deterministic time behavior
date_default_timezone_set('UTC');
ini_set('date.timezone', 'UTC');

// If you use Carbon in tests, you can set TestNow per-test as needed.
// (Kept minimal per patch_guard)
