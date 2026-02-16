# CiviCRM Development Patterns

This guide covers CiviCRM-specific patterns for this extension.

## Namespace Conventions

### CRM_* Classes (PSR-0)

Located in root directory with underscore hierarchy:

```
CRM/Automateddirectdebit/Job/DirectDebitEvents/PaymentCollectionEvent.php
→ class CRM_Automateddirectdebit_Job_DirectDebitEvents_PaymentCollectionEvent
```

### Civi\* Classes (PSR-4)

Located in `Civi/` directory with namespace:

```
Civi/Api4/Action/AutoDirectDebitPaymentPlan/SwitchToDirectDebitPaymentScheme.php
→ namespace Civi\Api4\Action\AutoDirectDebitPaymentPlan;
→ class SwitchToDirectDebitPaymentScheme
```

## API4 vs API3

**Always prefer API4 over API3** for new code.

### API4 Basic Usage

```php
use Civi\Api4\Contribution;

// Get contributions
$contributions = Contribution::get(FALSE)
  ->addSelect('id', 'total_amount', 'contribution_status_id')
  ->addWhere('contribution_recur_id', '=', $recurId)
  ->addWhere('contribution_status_id:name', '=', 'Pending')
  ->execute();
```

### API4 Result Handling

**Important:** Always use `is_array()` guard when iterating results:

```php
$results = Contribution::get(FALSE)
  ->addWhere('id', '=', $id)
  ->execute();

// Safe iteration
foreach ($results as $contribution) {
  if (is_array($contribution)) {
    // Process contribution
  }
}

// Or get first result
$contribution = $results->first();
if ($contribution) {
  // Process
}
```

### API4 Actions

```php
// Create
$result = Contribution::create(FALSE)
  ->addValue('contact_id', $contactId)
  ->addValue('total_amount', 100.00)
  ->execute();

// Update
Contribution::update(FALSE)
  ->addWhere('id', '=', $id)
  ->addValue('contribution_status_id:name', 'Completed')
  ->execute();

// Delete
Contribution::delete(FALSE)
  ->addWhere('id', '=', $id)
  ->execute();
```

## PHPStan Compatibility

CiviCRM uses dynamic classes that PHPStan doesn't understand natively.

### Using @phpstan Annotations

```php
/**
 * Get mandate information.
 *
 * @phpstan-param array{mandate_id: string, status: string} $mandate
 * @phpstan-return array{id: int, scheme: string}
 */
public function processMandate(array $mandate): array {
  // Implementation
}
```

### Stub Files

For classes PHPStan can't analyze, create stub files in `phpstan/stubs/`.

### Ignoring Specific Errors

In `phpstan.neon`:

```neon
parameters:
  ignoreErrors:
    - '#Call to an undefined method Civi\\Api4\\Generic\\Result::#'
```

## Common Hooks

### Payment Collection Hook

This extension dispatches:

```php
/**
 * Implements hook_automateddirectdebit_PaymentCollectionEvent.
 *
 * @param array $contributionData Contribution details
 * @param float $chargeAmount Amount to charge
 * @param string $mandateId External mandate identifier
 */
function myextension_automateddirectdebit_PaymentCollectionEvent(
  array $contributionData,
  float $chargeAmount,
  string $mandateId
): void {
  // Handle payment collection with external processor
}
```

### Standard CiviCRM Hooks

```php
// After database changes
function myextension_civicrm_post($op, $objectName, $objectId, &$objectRef) {}

// Before database changes
function myextension_civicrm_pre($op, $objectName, $id, &$params) {}

// Alter forms
function myextension_civicrm_buildForm($formName, &$form) {}

// Add menu items
function myextension_civicrm_navigationMenu(&$menu) {}
```

## Service Registration

Register services in `Civi/Automateddirectdebit/Container.php` or via `hook_civicrm_container`:

```php
function automateddirectdebit_civicrm_container(\Symfony\Component\DependencyInjection\ContainerBuilder $container) {
  $container->register('automateddirectdebit.payment_processor', PaymentProcessor::class)
    ->setPublic(TRUE);
}
```

## Custom Data Access

This extension uses custom groups attached to entities:

### External DD Mandate Information (on ContributionRecur)

- `mandate_id` - External mandate identifier
- `status` - Mandate status
- `scheme` - Payment scheme (BACS, SEPA, PAD)
- `next_available_date` - Next available collection date

### External DD Payment Information (on Contribution)

- `payment_in_progress` - Flag for payment processing
- `last_payment_status` - Last payment status

### Accessing Custom Data via API4

```php
use Civi\Api4\ContributionRecur;

$recur = ContributionRecur::get(FALSE)
  ->addSelect('*', 'custom.*')  // Include all custom fields
  ->addWhere('id', '=', $recurId)
  ->execute()
  ->first();

// Access custom field
$mandateId = $recur['External_DD_Mandate_Information.mandate_id'] ?? NULL;
```

## Database Transactions

Use CiviCRM's transaction handling:

```php
use CRM_Core_Transaction;

$transaction = new CRM_Core_Transaction();
try {
  // Database operations
  $transaction->commit();
} catch (Exception $e) {
  $transaction->rollback();
  throw $e;
}
```

## Lock Manager

For preventing concurrent execution (used in payment collection):

```php
$lock = \Civi::lockManager()->acquire('data.automateddirectdebit.payment_collection');
if (!$lock) {
  // Could not acquire lock, another process is running
  return;
}

try {
  // Perform exclusive operation
} finally {
  $lock->release();
}
```

## Target Version Compatibility

This extension targets CiviCRM 5.51.3 (Compucorp patched version). Ensure all APIs and hooks used are available in this version.
