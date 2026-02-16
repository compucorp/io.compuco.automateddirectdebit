# CiviCRM Extension Development

This guide covers extension-specific patterns for the automateddirectdebit extension.

## Directory Structure

```
io.compuco.automateddirectdebit/
├── api/                    # API endpoints (legacy API3)
├── bin/                    # Scripts (linting tools)
├── Civi/                   # PSR-4 namespaced classes
│   ├── Api4/              # API4 actions and entities
│   └── Automateddirectdebit/
├── CRM/                    # PSR-0 classes
│   └── Automateddirectdebit/
│       ├── Job/           # Scheduled jobs
│       ├── Setup/         # Install/upgrade handlers
│       └── ...
├── docs/                   # Documentation
├── tests/                  # Test files
│   └── phpunit/
│       └── CRM/
├── templates/              # Smarty templates
├── xml/                    # Schema definitions
├── automateddirectdebit.php          # Main extension file
├── automateddirectdebit.civix.php    # Civix-generated code
├── info.xml                # Extension metadata
├── phpcs-ruleset.xml       # PHPCS configuration
└── phpunit.xml             # PHPUnit configuration
```

## Extension Lifecycle

### Installation (`Upgrader.php`)

Located at `CRM/Automateddirectdebit/Setup/Upgrader.php`:

```php
class CRM_Automateddirectdebit_Setup_Upgrader extends CRM_Extension_Upgrader_Base {

  public function install(): void {
    // Called on extension install
  }

  public function enable(): void {
    // Called when extension is enabled
  }

  public function disable(): void {
    // Called when extension is disabled
  }

  public function uninstall(): void {
    // Called on extension uninstall
  }

  public function upgrade_1001(): bool {
    // Schema migration for version 1001
    return TRUE;
  }
}
```

### Upgrade Path

When adding schema changes:

1. Create new `upgrade_XXXX()` method in Upgrader
2. Increment version in `info.xml`
3. Test upgrade path from previous versions

## Database Schema Changes

### DAO Regeneration

After modifying XML schema:

```bash
cv api4 System.updateMappingFile --args='{"entity":"YourEntity"}'
```

### Custom Groups

This extension uses custom groups for data storage:

- Custom groups are defined in `xml/` directory
- Installed via Setup managers
- Attached to core entities (ContributionRecur, Contribution)

## Testing

### Base Test Class

All tests extend `BaseHeadlessTest`:

```php
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use PHPUnit\Framework\TestCase;

class MyTest extends BaseHeadlessTest {

  public function setUp(): void {
    parent::setUp();
    // Test setup
  }

  public function testSomething(): void {
    // Test implementation
  }
}
```

### Running Tests

```bash
# All tests
phpunit5

# Single file
phpunit5 tests/phpunit/CRM/Api4/AutoDirectDebitPaymentPlanTest.php

# Single method
phpunit5 --filter testMethodName
```

### Test Isolation

Tests use transaction rollback for isolation:

```php
public function setUp(): void {
  parent::setUp();
  // Each test runs in a transaction that's rolled back after
}
```

### Fabricators

Use fabricators to create test data:

```php
// Create test contact
$contact = ContactFabricator::fabricate();

// Create test contribution
$contribution = ContributionFabricator::fabricate([
  'contact_id' => $contact['id'],
  'total_amount' => 100.00,
]);
```

## Scheduled Jobs

Jobs are registered in `info.xml` and implemented as classes:

```xml
<job>
  <name>Process Direct Debit Payments</name>
  <description>Collects payments for active direct debit mandates</description>
  <command>automateddirectdebit.PaymentCollection</command>
  <run_frequency>Daily</run_frequency>
</job>
```

Implementation at `CRM/Automateddirectdebit/Job/DirectDebitEvents/PaymentCollectionEvent.php`.

## Hooks

### Extension Hooks

Register hooks in `automateddirectdebit.php`:

```php
function automateddirectdebit_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  // Handle post-operation events
}
```

### Dispatching Custom Hooks

This extension dispatches:

```php
// In PaymentCollectionEvent.php
CRM_Utils_Hook::singleton()->invoke(
  ['contributionData', 'chargeAmount', 'mandateId'],
  $contributionData,
  $chargeAmount,
  $mandateId,
  CRM_Utils_Hook::$_nullObject,
  CRM_Utils_Hook::$_nullObject,
  CRM_Utils_Hook::$_nullObject,
  'automateddirectdebit_PaymentCollectionEvent'
);
```

## Dependencies

### Required Extension

This extension requires `uk.co.compucorp.membershipextras`:

```xml
<!-- In info.xml -->
<requires>
  <ext>uk.co.compucorp.membershipextras</ext>
</requires>
```

### Installing Dependencies in Tests

In `BaseHeadlessTest`:

```php
public function setUpHeadless(): CiviEnvBuilder {
  return \Civi\Test::headless()
    ->installMe(__DIR__)
    ->install('uk.co.compucorp.membershipextras')
    ->apply();
}
```

## Error Handling

### Logging

Use CiviCRM's logging:

```php
\Civi::log()->info('Payment collected', [
  'contribution_id' => $contributionId,
  'amount' => $amount,
]);

\Civi::log()->error('Payment failed', [
  'contribution_id' => $contributionId,
  'error' => $e->getMessage(),
]);
```

### Exceptions

Throw appropriate exceptions:

```php
use CRM_Core_Exception;

throw new CRM_Core_Exception('Unable to process payment: ' . $reason);
```

## API4 Entity Registration

Register custom API4 entities in `Civi/Api4/`:

```php
namespace Civi\Api4;

class AutoDirectDebitPaymentPlan extends Generic\AbstractEntity {

  public static function getFields(bool $checkPermissions = TRUE): Generic\BasicGetFieldsAction {
    return (new Generic\BasicGetFieldsAction(static::getEntityName(), __FUNCTION__, function() {
      return [];
    }))->setCheckPermissions($checkPermissions);
  }
}
```
