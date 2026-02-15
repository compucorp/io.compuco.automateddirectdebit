# Unit Testing Guide

This guide covers testing practices for the automateddirectdebit extension.

## When to Write Tests

**Always write tests for:**

- New features
- Bug fixes (regression tests)
- Refactored code (ensure behavior preserved)
- Complex business logic
- API endpoints
- Scheduled jobs

## The Three Cases Rule

Every test should cover at minimum:

1. **Positive case** - Happy path, expected input, success scenario
2. **Negative case** - Error conditions, invalid input, failure scenarios
3. **Edge case** - Boundary conditions, empty data, null values

### Example

```php
// Testing mandate status validation
public function testValidMandateStatusAllowsPayment(): void {
  // Positive case
}

public function testInvalidMandateStatusBlocksPayment(): void {
  // Negative case
}

public function testNullMandateStatusHandledGracefully(): void {
  // Edge case
}
```

## Arrange-Act-Assert Pattern

Structure every test with three clear sections:

```php
public function testPaymentCollectionCreatesContribution(): void {
  // Arrange - Set up test data and dependencies
  $contact = ContactFabricator::fabricate();
  $recurringContribution = RecurringContributionFabricator::fabricate([
    'contact_id' => $contact['id'],
    'amount' => 50.00,
  ]);
  $mandate = $this->createActiveMandate($recurringContribution['id']);

  // Act - Execute the code under test
  $result = $this->paymentCollector->collect($recurringContribution['id']);

  // Assert - Verify the expected outcome
  $this->assertTrue($result->isSuccess());
  $this->assertContributionCreated($recurringContribution['id'], 50.00);
}
```

## Test Base Class

All tests extend `BaseHeadlessTest`:

```php
use Civi\Test\HeadlessInterface;

class PaymentCollectionTest extends BaseHeadlessTest {

  public function setUp(): void {
    parent::setUp();
    // Test-specific setup
  }

  public function tearDown(): void {
    // Test-specific cleanup (usually not needed due to transaction rollback)
    parent::tearDown();
  }
}
```

## Testing with Fabricators

Use fabricators to create test entities:

```php
// Contact
$contact = ContactFabricator::fabricate([
  'first_name' => 'Test',
  'last_name' => 'User',
]);

// Contribution
$contribution = ContributionFabricator::fabricate([
  'contact_id' => $contact['id'],
  'total_amount' => 100.00,
  'contribution_status_id:name' => 'Pending',
]);

// Recurring contribution
$recurring = RecurringContributionFabricator::fabricate([
  'contact_id' => $contact['id'],
  'amount' => 50.00,
  'frequency_unit' => 'month',
  'frequency_interval' => 1,
]);
```

## Mocking External Services

For payment processor integration, mock external calls:

```php
public function testPaymentProcessorHandlesFailure(): void {
  // Create mock for external API
  $mockClient = $this->createMock(GoCardlessClient::class);
  $mockClient->expects($this->once())
    ->method('createPayment')
    ->willThrowException(new ApiException('Insufficient funds'));

  // Inject mock
  $processor = new PaymentProcessor($mockClient);

  // Test error handling
  $result = $processor->processPayment($mandate, 100.00);

  $this->assertFalse($result->isSuccess());
  $this->assertEquals('Insufficient funds', $result->getError());
}
```

## Testing Webhooks

```php
public function testWebhookUpdatesMandateStatus(): void {
  // Arrange
  $mandate = $this->createMandate(['status' => 'pending']);
  $webhookPayload = $this->buildWebhookPayload([
    'resource_type' => 'mandates',
    'action' => 'confirmed',
    'mandate_id' => $mandate['external_id'],
  ]);

  // Act
  $handler = new WebhookHandler();
  $handler->process($webhookPayload);

  // Assert
  $updatedMandate = $this->getMandateById($mandate['id']);
  $this->assertEquals('active', $updatedMandate['status']);
}
```

## Testing Scheduled Jobs

```php
public function testPaymentCollectionJobProcessesDueContributions(): void {
  // Arrange - Create contributions due for collection
  $contribution1 = $this->createPendingContribution(['receive_date' => 'today']);
  $contribution2 = $this->createPendingContribution(['receive_date' => 'today']);
  $futureContribution = $this->createPendingContribution(['receive_date' => '+7 days']);

  // Act - Run the job
  $job = new CRM_Automateddirectdebit_Job_DirectDebitEvents_PaymentCollectionEvent();
  $result = $job->run();

  // Assert - Only due contributions processed
  $this->assertContributionProcessed($contribution1['id']);
  $this->assertContributionProcessed($contribution2['id']);
  $this->assertContributionNotProcessed($futureContribution['id']);
}
```

## Data Providers

Use data providers for testing multiple scenarios:

```php
/**
 * @dataProvider schemeProvider
 */
public function testPaymentSchemeHandling(string $scheme, bool $expectedSupport): void {
  $processor = new PaymentSchemeProcessor();

  $this->assertEquals($expectedSupport, $processor->supports($scheme));
}

public function schemeProvider(): array {
  return [
    'BACS is supported' => ['BACS', true],
    'SEPA is supported' => ['SEPA', true],
    'PAD is supported' => ['PAD', true],
    'Unknown scheme not supported' => ['UNKNOWN', false],
  ];
}
```

## Anti-Patterns to Avoid

### Testing Implementation Instead of Behavior

```php
// Bad - tests internal implementation
public function testMethodCallsInternalHelper(): void {
  $mock = $this->getMockBuilder(MyClass::class)
    ->onlyMethods(['internalHelper'])
    ->getMock();
  $mock->expects($this->once())->method('internalHelper');
  $mock->doSomething();
}

// Good - tests observable behavior
public function testDoSomethingProducesExpectedResult(): void {
  $result = $this->instance->doSomething($input);
  $this->assertEquals($expectedOutput, $result);
}
```

### Tests That Depend on Order

```php
// Bad - test2 depends on test1 running first
public function test1CreateRecord(): void {
  $this->recordId = $this->createRecord();
}

public function test2UpdateRecord(): void {
  $this->updateRecord($this->recordId); // Fails if test1 didn't run
}

// Good - each test is independent
public function testUpdateRecord(): void {
  $record = $this->createRecord(); // Create own test data
  $result = $this->updateRecord($record['id']);
  $this->assertTrue($result);
}
```

### Tests Without Assertions

```php
// Bad - test doesn't verify anything
public function testProcess(): void {
  $this->processor->process($data);
  // No assertions!
}

// Good - test verifies outcome
public function testProcessCreatesExpectedOutput(): void {
  $result = $this->processor->process($data);
  $this->assertNotEmpty($result);
  $this->assertEquals('processed', $result['status']);
}
```

### Excessive Mocking

```php
// Bad - mocking everything makes test meaningless
public function testComplexOperation(): void {
  $mockA = $this->createMock(A::class);
  $mockB = $this->createMock(B::class);
  $mockC = $this->createMock(C::class);
  $mockD = $this->createMock(D::class);
  // Test becomes a tautology
}

// Good - mock only external dependencies
public function testComplexOperation(): void {
  $externalApi = $this->createMock(ExternalApi::class);
  $realService = new Service($externalApi);
  $result = $realService->process($input);
  // Test actual integration of internal components
}
```

## Running Tests

```bash
# Run all tests
phpunit5

# Run specific test file
phpunit5 tests/phpunit/CRM/Api4/AutoDirectDebitPaymentPlanTest.php

# Run specific test method
phpunit5 --filter testSwitchToDirectDebitPaymentScheme

# Run with verbose output
phpunit5 --verbose

# Run with code coverage (if configured)
phpunit5 --coverage-html coverage/
```

## Debugging Failing Tests

1. Run with `--verbose` for more output
2. Add `$this->markTestIncomplete('message')` to isolate issues
3. Use `var_dump()` temporarily (remove before committing)
4. Check database state with `\Civi::log()->debug()`
5. Ensure `setUp()` properly initializes test state
