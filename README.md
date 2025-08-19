# SmartAlloc WordPress Plugin

Event-driven student support allocation with Gravity Forms + Exporter.

## Description

SmartAlloc is a comprehensive WordPress plugin designed for automatic mentor allocation to students. It features an event-driven architecture, config-driven export functionality, and seamless integration with Gravity Forms.

## Features

- **Event-Driven Architecture**: Robust event system with deduplication support
- **Config-Driven Export**: Excel export with configurable schemas
- **Gravity Forms Integration**: Automatic form processing with validation
- **Three-Layer Caching**: Object cache, transients, and database caching
- **REST API**: Health, metrics, and export endpoints
- **WP-CLI Support**: Command-line tools for management
- **Circuit Breaker Pattern**: Graceful handling of external service failures
- **Comprehensive Logging**: Structured logging with data masking

## Requirements

- WordPress 6.3+
- PHP 8.1+
- MySQL 8.0+ (InnoDB)
- Gravity Forms Pro
- Action Scheduler (recommended)

## Installation

1. Upload the plugin files to `/wp-content/plugins/smart-alloc/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings
4. Run database migrations: `wp smartalloc upgrade`

## Configuration

### Gravity Forms Setup

1. Create a form with the required fields (see field mapping below)
2. Set the form ID in plugin settings or via CLI:
   ```bash
   wp smartalloc set-form --id=150
   ```

### Export Configuration

Place the export configuration file at:
`/wp-content/uploads/SmartAlloc_Exporter_Config_v1.json`

### Field Mapping

The plugin expects the following Gravity Forms fields:

| Field ID | Description | Type |
|----------|-------------|------|
| 101 | First Name | Text |
| 102 | Last Name | Text |
| 3 | Father's Name | Text |
| 143 | National ID | Text (10 digits) |
| 92 | Gender | Radio (F/M) |
| 73 | Group Code | Select |
| 94 | Center | Select |
| 30 | School Code | Select |
| 20 | Mobile | Phone |
| 21 | Contact 1 Mobile | Phone |
| 23 | Contact 2 Mobile | Phone |
| 75 | Registration Status | Radio |
| 76 | Tracking Code | Text |
| 97 | Package Type | Select |
| 5 | GPA | Number |
| 7 | Class Number | Text |
| 8 | Seat Number | Text |
| 96 | Quota | Select |
| 60 | Postal Code | Text |
| 61 | Postal Code Alias | Text |
| 62 | Notes | Textarea |

## Usage

### Automatic Allocation

When a student submits the form, the plugin automatically:

1. Validates and normalizes the data
2. Selects appropriate mentors based on criteria
3. Allocates the student to a mentor
4. Triggers export and notification events

### Manual Export

Export data via REST API:
```bash
curl -X POST /wp-json/smartalloc/v1/export \
  -H "Content-Type: application/json" \
  -d '{"rows": [...]}'
```

Or via WP-CLI:
```bash
wp smartalloc export
```

### Health Monitoring

Check system health:
```bash
wp smartalloc health
```

Or via REST API:
```bash
curl /wp-json/smartalloc/v1/health
```

### Metrics

View metrics:
```bash
curl /wp-json/smartalloc/v1/metrics
```

## API Endpoints

### Health Check
- **GET** `/wp-json/smartalloc/v1/health`
- Returns system health status

### Metrics
- **GET** `/wp-json/smartalloc/v1/metrics`
- Returns aggregated metrics
- Query parameters: `key`, `limit`

### Export
- **POST** `/wp-json/smartalloc/v1/export`
- Exports data to Excel file
- Requires `manage_smartalloc` capability

## WP-CLI Commands

```bash
# Run database migrations
wp smartalloc upgrade

# Set Gravity Forms ID
wp smartalloc set-form --id=150

# Export data
wp smartalloc export

# Rebuild statistics
wp smartalloc rebuild-stats

# Check health
wp smartalloc health
```

## Development

### Building

```bash
# Install dependencies
composer install

# Run tests
composer test

# Lint code
composer lint

# Static analysis
composer analyze

# Build package
composer zip
```

### Testing

```bash
# Run all tests
composer test

# Run specific test
vendor/bin/phpunit tests/DigitsNormalizerTest.php
```

## End-to-End Testing

Run a11y and editor smoke tests via Playwright using one of three paths:

### A) Playground CLI (default)

```bash
npm run e2e:install && npm run e2e:all
```

### B) Docker Compose

```bash
npm run e2e:install && npm run e2e:all:docker
```

### C) wp-env (Docker-based)

```bash
npm run e2e:install && npm run e2e:all:wpenv
```

The Playground CLI mounts the current plugin automatically via `--auto-mount` and serves on port 9400 (override with `WP_BASE_URL`).

### Troubleshooting

- Offline? Playground download may fail → use Docker or wp-env paths
- Port in use → set `WP_BASE_URL` accordingly (defaults to 9400)
- ECONNREFUSED → ensure the chosen path is running and `WP_BASE_URL` matches
- Node < 20 → upgrade Node for Playground CLI

CI E2E is optional and won’t block merges.

### Code Quality

```bash
# Check coding standards
composer lint

# Static analysis
composer analyze
```

## Architecture

The plugin follows a layered architecture:

- **Core**: Bootstrap, Container, EventBus
- **Services**: Business logic services
- **Integration**: External system adapters
- **HTTP**: REST API and admin interfaces
- **Infra**: Infrastructure components

### Event Flow

1. Form submission → `StudentSubmitted` event
2. Auto-assignment → `MentorAssigned` event
3. Allocation commit → `AllocationCommitted` event
4. Export → Excel file generation

## Troubleshooting

### Common Issues

1. **Export fails**: Check PhpSpreadsheet installation and file permissions
2. **Allocation fails**: Verify mentor data and capacity settings
3. **Form not processing**: Check Gravity Forms integration and field mapping

### Logs

Check WordPress error logs for detailed information. The plugin logs with the prefix `[SmartAlloc]`.

### Database

The plugin creates several tables with the prefix `wp_salloc_`:

- `salloc_event_log`: Event logging
- `salloc_export_log`: Export history
- `salloc_export_errors`: Export error details
- `salloc_circuit_breakers`: Circuit breaker states
- `salloc_stats_daily`: Daily statistics
- `salloc_metrics`: Metrics data

## Security

- All SQL queries use prepared statements
- Input validation and sanitization
- Capability-based access control
- Sensitive data masking in logs

## Support

For support and documentation, please refer to the plugin documentation or contact the development team.

## Uninstall

Set the `purge_on_uninstall` option to true to remove SmartAlloc options and caches. By default only transient caches are cleared and allocation data remains.

## License

This plugin is licensed under the MIT License. See `LICENSE` for details.

## Changelog

### 1.1.0
- Initial release
- Event-driven architecture
- Config-driven export
- Gravity Forms integration
- REST API endpoints
- WP-CLI commands
- Comprehensive testing 