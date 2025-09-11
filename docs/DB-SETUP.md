# Database Setup for CI/CD

## Overview
This project uses MySQL 8.0 for WordPress testing in GitHub Actions.

## Local Development

### Prerequisites
- MySQL 8.0 or higher
- PHP 8.1+ with mysqli extension

### Setup
1. Copy `.env.example` to `.env`
2. Update database credentials
3. Run `bash scripts/db-init.sh`
4. Run `composer setup:wp-tests`

## CI Environment

GitHub Actions automatically:
1. Spins up MySQL 8.0 service container
2. Creates test database and user
3. Runs all migrations
4. Executes test suite

## Troubleshooting

### Connection refused
- Ensure MySQL is running: `mysqladmin ping -h 127.0.0.1`
- Check credentials in `.env`

### Character set issues
- Database uses `utf8mb4` with `utf8mb4_unicode_ci` collation
- Ensure PHP mysqli extension supports utf8mb4
