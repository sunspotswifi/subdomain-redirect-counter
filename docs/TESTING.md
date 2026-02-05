# Testing Guide

This document describes how to run tests for the Subdomain Redirect Counter plugin.

## Prerequisites

- **PHP 8.0+** with extensions: mysqli, pdo_mysql, mbstring
- **MySQL/MariaDB** for integration tests
- **Composer** for dependency management
- **Git** (optional, for cloning)

## Quick Start

```bash
# Install dependencies
composer install

# Run unit tests (no database required)
composer test:unit

# Set up WordPress test environment (required for integration tests)
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run integration tests
composer test:integration

# Run all tests
composer test

# Generate code coverage report
composer coverage
```

## Test Types

### Unit Tests

Unit tests are fast, isolated tests that use [WP_Mock](https://github.com/10up/wp_mock) to mock WordPress functions. They don't require a database or actual WordPress installation.

**Location:** `tests/unit/`

**Run command:**
```bash
composer test:unit
```

**What they test:**
- IP anonymization (GDPR compliance)
- Subdomain detection and parsing
- Input validation and sanitization
- Settings sanitization

### Integration Tests

Integration tests run against a real WordPress test database. They verify actual behavior with WordPress core functions and database operations.

**Location:** `tests/integration/`

**Run command:**
```bash
composer test:integration
```

**Prerequisites:**
1. MySQL/MariaDB running
2. WordPress test suite installed (see below)

**What they test:**
- Database table creation and schema
- CRUD operations for mappings
- Statistics recording and retrieval
- Log entry management
- Plugin activation/deactivation

### Acceptance Tests

Acceptance tests verify end-to-end scenarios and complete user flows.

**Location:** `tests/acceptance/`

**Run command:**
```bash
# Acceptance tests run with integration tests
composer test:integration
```

**What they test:**
- Complete redirect flows
- Statistics recording after redirects
- Logging with visitor information
- Domain redirect handling

## Setting Up the Test Environment

### 1. Install Composer Dependencies

```bash
composer install
```

### 2. Install WordPress Test Suite

Run the installation script:

```bash
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version>
```

**Example:**
```bash
# Local development
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# With password
bash bin/install-wp-tests.sh wordpress_test testuser testpass 127.0.0.1 6.4

# Specific WordPress version
bash bin/install-wp-tests.sh wordpress_test root root localhost 6.0
```

**Parameters:**
- `db-name`: Test database name (will be created if it doesn't exist)
- `db-user`: MySQL username
- `db-pass`: MySQL password (use `''` for no password)
- `db-host`: Database host (usually `localhost` or `127.0.0.1`)
- `wp-version`: WordPress version (`latest`, `6.4`, `6.3`, etc.)

### 3. Multisite Testing

To run tests in multisite mode:

```bash
WP_MULTISITE=1 composer test:integration
```

Or run only multisite-specific tests:

```bash
WP_MULTISITE=1 vendor/bin/phpunit --configuration phpunit-integration.xml.dist --group multisite
```

## Writing Tests

### Test File Location

| Test Type | Location | Base Class |
|-----------|----------|------------|
| Unit | `tests/unit/` | `WP_Mock\Tools\TestCase` |
| Integration | `tests/integration/` | `WP_UnitTestCase` |
| Acceptance | `tests/acceptance/` | `WP_UnitTestCase` |

### Naming Conventions

- Test files: `*Test.php` (e.g., `LoggerTest.php`)
- Test methods: `test_*` (e.g., `test_ip_anonymization_ipv4()`)
- Use descriptive names that explain what's being tested

### Using Traits

The `MockServerVarsTrait` provides helpers for mocking `$_SERVER` variables:

```php
use SRC\Tests\Support\Traits\MockServerVarsTrait;

class MyTest extends WP_UnitTestCase {
    use MockServerVarsTrait;

    public function setUp(): void {
        parent::setUp();
        $this->setUpServerVars();
    }

    public function tearDown(): void {
        $this->tearDownServerVars();
        parent::tearDown();
    }

    public function test_something(): void {
        $this->setHttpHost('tickets.example.com');
        $this->setRemoteAddr('192.168.1.100');
        // ... test code
    }
}
```

### PHPDoc Annotations

Use annotations for test organization:

```php
/**
 * @covers SRC_Logger
 * @group unit
 * @group logger
 */
class LoggerTest extends TestCase {

    /**
     * @covers SRC_Logger::anonymize_ip
     */
    public function test_ip_anonymization(): void {
        // ...
    }
}
```

### Database Setup/Teardown

For integration tests, clean up tables in `setUp()`:

```php
public function setUp(): void {
    parent::setUp();

    global $wpdb;
    $wpdb->query('TRUNCATE TABLE ' . SRC_Database::get_mappings_table());
    $wpdb->query('TRUNCATE TABLE ' . SRC_Database::get_stats_table());
    $wpdb->query('TRUNCATE TABLE ' . SRC_Database::get_logs_table());
}
```

## CI/CD Pipeline

Tests run automatically via GitHub Actions on:
- Push to `main` or `develop` branches
- Pull requests targeting those branches

### Test Matrix

**Unit Tests:**
- PHP: 8.0, 8.1, 8.2, 8.3

**Integration Tests:**
- PHP: 8.0, 8.1, 8.2
- WordPress: 6.0, 6.3, 6.4, latest

**Multisite Tests:**
- PHP: 8.1, 8.2
- WordPress: latest

### Code Coverage

Coverage reports are generated on pushes to `main` and uploaded to Codecov.

## Troubleshooting

### "Could not find WordPress test library"

The WordPress test suite isn't installed. Run:

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Database Connection Errors

1. Verify MySQL is running
2. Check credentials in the install script
3. Ensure the database user has CREATE privileges

```bash
# Test connection
mysql -u root -p -e "SELECT 1"
```

### WP_Mock Errors in Unit Tests

Ensure `WP_Mock::setUp()` and `WP_Mock::tearDown()` are called:

```php
public function setUp(): void {
    parent::setUp();
    WP_Mock::setUp();
}

public function tearDown(): void {
    WP_Mock::tearDown();
    parent::tearDown();
}
```

### Tests Pass Locally but Fail in CI

1. Check PHP version differences
2. Verify MySQL version compatibility
3. Review CI logs for specific error messages
4. Ensure all dependencies are in `composer.json`

### Slow Integration Tests

Integration tests are slower because they use a real database. To run specific tests:

```bash
# Run a specific test file
vendor/bin/phpunit --configuration phpunit-integration.xml.dist tests/integration/MappingsTest.php

# Run a specific test method
vendor/bin/phpunit --configuration phpunit-integration.xml.dist --filter test_add_mapping

# Run tests in a specific group
vendor/bin/phpunit --configuration phpunit-integration.xml.dist --group statistics
```

## Test Coverage Goals

| Component | Target Coverage |
|-----------|----------------|
| SRC_Logger | 90%+ (GDPR critical) |
| SRC_Interceptor | 85%+ |
| SRC_Mappings | 85%+ |
| SRC_Statistics | 80%+ |
| SRC_Database | 75%+ |
| SRC_Admin | 70%+ |

## Contributing Tests

When contributing new features or bug fixes:

1. **New features** must include tests demonstrating the feature works
2. **Bug fixes** should include a regression test that would have caught the bug
3. Run the full test suite before submitting a PR
4. Ensure tests pass on all supported PHP versions

See [CONTRIBUTING.md](../CONTRIBUTING.md) for more details.
