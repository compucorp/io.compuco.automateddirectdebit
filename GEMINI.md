# GEMINI.md

This file provides guidance to Gemini when working with code in this repository.

## Shared Documentation

Read and apply standards from these files:

- `.ai/shared-development-guide.md` - Git discipline, code quality standards
- `.ai/ai-code-review.md` - Review checklist and severity levels
- `.ai/civicrm.md` - CiviCRM API4 patterns, hooks, namespacing
- `.ai/extension.md` - Extension structure and lifecycle
- `.ai/unit-testing-guide.md` - Testing requirements

## Configuration

Gemini-specific configuration is in `.gemini/`:

- `.gemini/config.yaml` - Code Assist configuration
- `.gemini/styleguide.md` - Code review style guide

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

## Commit Message Format

```
JIRA-TICKET: Brief description
```

Examples:
- `CIVIMM-123: Add payment retry logic`
- `CIVIPLMMI-456: Fix mandate status validation`

## Security Considerations

- Never concatenate user input into SQL queries
- Use CiviCRM's escaping functions for output
- Never commit credentials or API keys
- Follow PCI compliance for payment data
