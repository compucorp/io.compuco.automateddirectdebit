# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Shared Documentation

Read and apply standards from these files:

- `.ai/shared-development-guide.md` - Git discipline, code quality standards
- `.ai/ai-code-review.md` - Review checklist and severity levels
- `.ai/civicrm.md` - CiviCRM API4 patterns, hooks, namespacing
- `.ai/extension.md` - Extension structure and lifecycle
- `.ai/unit-testing-guide.md` - Testing requirements

## Project Overview

CiviCRM extension that enables recurring contributions to switch to direct debit payment schemes (BACS, SEPA, PAD) and automates payment collection through external payment processors like GoCardless.

**Key dependency:** `uk.co.compucorp.membershipextras` extension (required)
**CiviCRM version:** 5.51.3 (patched version from compucorp)

## Commands

### Testing
```bash
# Run all tests
phpunit5

# Run a single test file
phpunit5 tests/phpunit/CRM/Api4/AutoDirectDebitPaymentPlanTest.php

# Run a specific test method
phpunit5 --filter testMethodName
```

### Linting
```bash
# First time setup - install PHPCS and CiviCRM Coder
./bin/install-php-linter

# Check code style (Drupal standard via CiviCRM Coder)
./bin/phpcs.phar --standard=phpcs-ruleset.xml <file-or-directory>

# Auto-fix issues
./bin/phpcbf.phar --standard=phpcs-ruleset.xml <file-or-directory>
```

## Architecture

### Core Components

**Payment Collection Job** (`CRM/Automateddirectdebit/Job/DirectDebitEvents/PaymentCollectionEvent.php`)
- Entry point for scheduled payment collection
- Two separate query paths: BACS (stricter status requirements) vs non-BACS (SEPA, PAD)
- Uses CiviCRM lock manager to prevent concurrent execution
- Dispatches hook `automateddirectdebit_PaymentCollectionEvent` for external processors to handle actual payment

**API v4 Action** (`Civi/Api4/Action/AutoDirectDebitPaymentPlan/SwitchToDirectDebitPaymentScheme.php`)
- Switches recurring contribution to direct debit payment scheme
- Updates payment processor, mandate information, and clears frequency settings

**Setup System** (`CRM/Automateddirectdebit/Setup/`)
- `AbstractManager`: Base class for entity lifecycle management
- `Upgrader.php`: Extension install/enable/disable/uninstall handlers
- Custom groups for mandate and payment tracking attached to ContributionRecur and Contribution entities

### Custom Data Schema

- **External DD Mandate Information** (on ContributionRecur): mandate_id, status, scheme, next_available_date
- **External DD Payment Information** (on Contribution): payment_in_progress flag, last_payment_status

### Hook Integration

Payment processors implement:
```php
hook_automateddirectdebit_PaymentCollectionEvent($contributionData, $chargeAmount, $mandateId)
```

### Class Naming

- `CRM_*` classes use underscore hierarchy in root directory (PSR-0)
- `Civi\*` classes use namespace in `Civi/` directory (PSR-4)

### Test Base Class

Tests extend `BaseHeadlessTest` which:
- Installs required extensions automatically
- Provides transaction isolation (auto-rollback)
- Sets up CiviCRM headless environment

## Slash Commands

Available slash commands in `.claude/commands/`:

- `/review` - Review current changes for issues before committing
- `/pre-commit` - Run all pre-commit validations
