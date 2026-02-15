# GitHub Copilot Instructions

Instructions for GitHub Copilot when assisting with this CiviCRM extension.

## Project Context

This is a CiviCRM extension (`io.compuco.automateddirectdebit`) that:

- Enables recurring contributions to switch to direct debit payment schemes (BACS, SEPA, PAD)
- Automates payment collection through external payment processors (GoCardless)
- Requires the `uk.co.compucorp.membershipextras` extension
- Targets CiviCRM 5.51.3 (Compucorp patched version)

## Shared Documentation

Apply standards from these files:

- `.ai/shared-development-guide.md` - Git discipline, code quality standards
- `.ai/ai-code-review.md` - Review checklist and severity levels
- `.ai/civicrm.md` - CiviCRM API4 patterns, hooks, namespacing
- `.ai/extension.md` - Extension structure and lifecycle
- `.ai/unit-testing-guide.md` - Testing requirements

## Code Generation Guidelines

### PHP Style

- Follow PSR-12 with Drupal/CiviCRM modifications
- Use meaningful variable and function names
- Keep functions focused and small

### CiviCRM Patterns

- **Always use API4 over API3** for new code
- Use `is_array()` guards when iterating API4 results
- Follow namespace conventions:
  - `CRM_*` classes: PSR-0 in root directory
  - `Civi\*` classes: PSR-4 in `Civi/` directory

### API4 Example

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

### Security

- Never concatenate user input into SQL queries
- Use CiviCRM's escaping functions for output
- Never commit credentials or API keys

### Testing

- All new features must have tests
- All bug fixes must have regression tests
- Use `BaseHeadlessTest` as the base class
- Follow Arrange-Act-Assert pattern

## PR Review Guidelines

When reviewing PRs, check for:

### Security (BLOCKER)
- SQL injection vulnerabilities
- XSS vulnerabilities
- Hardcoded credentials

### Code Quality (WARNING)
- Missing tests for new functionality
- N+1 query patterns
- Code style violations

### CiviCRM Specific
- API4 over API3 preference
- Proper hook implementation
- Correct namespace usage

## Commands

```bash
# Run tests
phpunit5

# Run specific test
phpunit5 tests/phpunit/CRM/Api4/AutoDirectDebitPaymentPlanTest.php

# Check linting
./bin/phpcs.phar --standard=phpcs-ruleset.xml <file>

# Fix linting
./bin/phpcbf.phar --standard=phpcs-ruleset.xml <file>
```

## Commit Messages

Format: `JIRA-TICKET: Brief description`

Examples:
- `CIVIMM-123: Add payment retry logic`
- `CIVIPLMMI-456: Fix mandate status validation`

## Do Not

- Add AI attribution to commits
- Use `git add -A` or `git add .`
- Skip tests for features or bug fixes
- Use API3 when API4 is available
- Hardcode custom field IDs
