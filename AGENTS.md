# AGENTS.md

This file provides guidance to OpenAI Codex and similar agents when working with code in this repository.

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
# Check code style (Drupal standard via CiviCRM Coder)
./bin/phpcs.phar --standard=phpcs-ruleset.xml <file-or-directory>

# Auto-fix issues
./bin/phpcbf.phar --standard=phpcs-ruleset.xml <file-or-directory>
```

## Key Patterns

### API4 Over API3

Always use API4 for new code:

```php
use Civi\Api4\Contribution;

$contributions = Contribution::get(FALSE)
  ->addSelect('id', 'total_amount')
  ->addWhere('contribution_recur_id', '=', $recurId)
  ->execute();

foreach ($contributions as $contribution) {
  if (is_array($contribution)) {
    // Process
  }
}
```

### Class Naming

- `CRM_*` classes use underscore hierarchy in root directory (PSR-0)
- `Civi\*` classes use namespace in `Civi/` directory (PSR-4)

### Testing

- All tests extend `BaseHeadlessTest`
- Follow Arrange-Act-Assert pattern
- Cover positive, negative, and edge cases

## Architecture

### Core Components

**Payment Collection Job** (`CRM/Automateddirectdebit/Job/DirectDebitEvents/PaymentCollectionEvent.php`)
- Entry point for scheduled payment collection
- Two query paths: BACS (stricter) vs non-BACS (SEPA, PAD)
- Uses lock manager to prevent concurrent execution
- Dispatches hook for external processors

**API v4 Action** (`Civi/Api4/Action/AutoDirectDebitPaymentPlan/SwitchToDirectDebitPaymentScheme.php`)
- Switches recurring contribution to direct debit
- Updates payment processor and mandate information

**Setup System** (`CRM/Automateddirectdebit/Setup/`)
- Extension lifecycle handlers
- Custom group management

### Custom Data

- **External DD Mandate Information** (ContributionRecur): mandate_id, status, scheme, next_available_date
- **External DD Payment Information** (Contribution): payment_in_progress, last_payment_status

### Hook

```php
hook_automateddirectdebit_PaymentCollectionEvent($contributionData, $chargeAmount, $mandateId)
```

## Git Discipline

- Never use `git add -A` or `git add .`
- Commit format: `JIRA-TICKET: Brief description`
- No AI attribution in commits

## Security

- No SQL injection (use API4 or parameterized queries)
- No XSS (escape all output)
- No hardcoded credentials
- PCI compliance for payment data

## Pre-Commit Checklist

- [ ] Tests pass (`phpunit5`)
- [ ] Linting passes (`./bin/phpcs.phar`)
- [ ] No debug code
- [ ] No secrets committed
- [ ] Code self-reviewed
