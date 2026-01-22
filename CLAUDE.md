# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

CiviCRM extension (`io.compuco.automateddirectdebit`) for automated direct debit payment processing. Enables recurring contributions to switch to direct debit payment schemes (BACS, SEPA, PAD) and automates payment collection through external payment processors.

**Dependency:** Requires `uk.co.compucorp.membershipextras` extension.

## Commands

### Testing
```bash
# Run all tests
phpunit5

# Run a single test file
phpunit5 tests/phpunit/CRM/Api4/AutoDirectDebitPaymentPlanTest.php

# Run a specific test method
phpunit5 --filter testSwitchToDirectDebitPaymentSchemeActionSuccess
```

### Linting
```bash
# Install linter (first time setup)
./bin/install-php-linter

# Check code style
./bin/phpcs.phar --standard=phpcs-ruleset.xml <file-or-directory>

# Auto-fix code style issues
./bin/phpcbf.phar --standard=phpcs-ruleset.xml <file-or-directory>
```

### CI Environment
Tests run in `compucorp/civicrm-buildkit:1.3.1-php8.0` container with CiviCRM 5.51.3 (patched) on Drupal 7.

## Architecture

### Namespace Conventions
- **PSR-0:** `CRM_Automateddirectdebit_*` classes in `CRM/Automateddirectdebit/`
- **PSR-4:** `Civi\*` classes in `Civi/`

### Core Components

**Payment Collection Job** (`CRM/Automateddirectdebit/Job/DirectDebitEvents/PaymentCollectionEvent.php`):
- Fetches pending invoices (BACS vs other schemes have different query logic)
- Dispatches `automateddirectdebit_PaymentCollectionEvent` hook for payment processors to handle
- Uses CiviCRM lock manager to prevent concurrent execution
- Tracks payment state via custom fields: `payment_in_progress`, `payment_in_progress_at`

**API v4 Action** (`Civi/Api4/Action/AutoDirectDebitPaymentPlan/SwitchToDirectDebitPaymentScheme.php`):
- Switches recurring contributions to direct debit payment schemes
- Validates mandate status and scheme compatibility

**Setup System** (`CRM/Automateddirectdebit/Setup/`):
- `Manage/` - Entity lifecycle managers for custom groups and scheduled jobs (Abstract Manager pattern)
- `Configure/` - Configuration steps implementing `ConfigurerInterface`

### Custom Database Tables
- `civicrm_value_external_dd_mandate_information` - Mandate tracking (scheme, status, next available date)
- `civicrm_value_external_dd_payment_information` - Payment state tracking (in_progress flag, timestamps)

### Hook Integration
Payment processors (e.g., GoCardless) implement `automateddirectdebit_PaymentCollectionEvent` hook to receive pending contributions and process payments.

## Testing Patterns

Tests use CiviCRM's headless testing framework:
```php
class MyTest extends BaseHeadlessTest {
    // Extends Civi\Test\HeadlessInterface and TransactionalInterface
    // Each test runs in isolated transaction
}
```

Create test data using CiviCRM API v4:
```php
Contact::create()->setValues([...])->execute();
Contribution::create()->setValues([...])->execute();
```