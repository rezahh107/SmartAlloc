# UTC Invariant

SmartAlloc requires all persisted timestamps to use UTC.

## Correct
```php
$wpdb->insert($table, ['created_at' => current_time('mysql', true)]);
```

## Incorrect
```php
$wpdb->insert($table, ['created_at' => current_time('mysql')]);
```

Resolve critical Site Health failures by ensuring `current_time('mysql', true)` is used for all write paths.
