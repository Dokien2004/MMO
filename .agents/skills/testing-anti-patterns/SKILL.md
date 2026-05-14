---
name: testing-anti-patterns
description: Identify and prevent ineffective testing practices. Use when reviewing test code, encountering flaky tests, or when tests pass but bugs still escape to production. Covers common anti-patterns in PHPUnit and general testing methodology.
---

# Testing Anti-Patterns

## Purpose
Recognize and eliminate testing practices that give false confidence. Tests that always pass are often worse than no tests — they create an illusion of safety.

## When to Use
- Reviewing existing test suites for effectiveness
- When tests pass but bugs keep appearing in production
- When tests are flaky (pass/fail randomly)
- When test suite is slow and developers skip running it
- Before writing new tests (to avoid these patterns)

## Critical Anti-Patterns

### 1. The Liar (Test That Never Fails)
```php
// ❌ ANTI-PATTERN: No meaningful assertion
public function testCreateEmployee() {
    $model = new EmployeeModel();
    $result = $model->create(['name' => 'Test']);
    $this->assertTrue(true); // Always passes!
}

// ✅ CORRECT: Assert the actual outcome
public function testCreateEmployee() {
    $model = new EmployeeModel();
    $id = $model->create(['name' => 'Test', 'site_id' => 1]);
    $this->assertIsInt($id);
    $this->assertGreaterThan(0, $id);
    
    $employee = $model->findById($id);
    $this->assertEquals('Test', $employee->name);
}
```

### 2. The Giant (Test That Tests Everything)
```php
// ❌ ANTI-PATTERN: One test, 50 assertions
public function testPurchaseOrder() {
    // Creates PO, adds lines, approves, receives, checks inventory,
    // validates accounting entries, sends email... 200 lines
}

// ✅ CORRECT: One behavior per test
public function testPOCreation() { /* only creation */ }
public function testPOApproval() { /* only approval */ }
public function testPOReceiving() { /* only receiving */ }
```

### 3. The Mockery (Over-Mocking)
```php
// ❌ ANTI-PATTERN: Mocking the thing you're testing
public function testCalculateTotal() {
    $service = $this->createMock(PricingService::class);
    $service->method('calculateTotal')->willReturn(100.00);
    
    // This tests your mock, not your code!
    $this->assertEquals(100.00, $service->calculateTotal($items));
}

// ✅ CORRECT: Mock dependencies, not the subject
public function testCalculateTotal() {
    $taxService = $this->createMock(TaxService::class);
    $taxService->method('getRate')->willReturn(0.10);
    
    $service = new PricingService($taxService);
    $result = $service->calculateTotal($items);
    
    $this->assertEquals(110.00, $result); // Tests real calculation
}
```

### 4. The Inspector (Testing Implementation, Not Behavior)
```php
// ❌ ANTI-PATTERN: Testing HOW, not WHAT
public function testApproveLeave() {
    // Asserts that specific SQL was called
    // Asserts that specific method was called 3 times
    // Breaks when you refactor internal implementation
}

// ✅ CORRECT: Test observable behavior
public function testApproveLeave() {
    $service->approve($leaveId);
    $leave = $model->findById($leaveId);
    
    $this->assertEquals('approved', $leave->status);
    $this->assertNotNull($leave->approved_at);
    $this->assertEquals($approverUserId, $leave->approved_by);
}
```

### 5. The Slow Poke (Unnecessary Integration)
```php
// ❌ ANTI-PATTERN: Hitting real DB for unit tests
public function testFormatCurrency() {
    $db = Database::getInstance(); // Why?
    $result = format_currency(1234.56);
    $this->assertEquals('1,234.56', $result);
}

// ✅ CORRECT: Pure unit test, no external deps
public function testFormatCurrency() {
    $this->assertEquals('1,234.56', format_currency(1234.56));
    $this->assertEquals('0.00', format_currency(0));
    $this->assertEquals('-1,234.56', format_currency(-1234.56));
}
```

### 6. The Happy Path Only
```php
// ❌ ANTI-PATTERN: Only tests success
public function testLogin() {
    $result = $auth->login('admin', 'password123');
    $this->assertTrue($result['success']);
}

// ✅ CORRECT: Test failure modes too
public function testLoginSuccess() { /* valid credentials */ }
public function testLoginWrongPassword() { /* bad password → error */ }
public function testLoginNonexistentUser() { /* unknown user → error */ }
public function testLoginLockedAccount() { /* locked → error */ }
public function testLoginSQLInjection() { /* malicious input → safe */ }
public function testLoginRateLimit() { /* too many attempts → blocked */ }
```

### 7. The Time Bomb (Date/Time Dependent)
```php
// ❌ ANTI-PATTERN: Fails on specific dates
public function testLeaveBalance() {
    $balance = $service->calculateBalance($empId); // Depends on "today"
    $this->assertEquals(12, $balance); // Fails in December!
}

// ✅ CORRECT: Control time explicitly
public function testLeaveBalance() {
    $referenceDate = '2024-06-15';
    $balance = $service->calculateBalance($empId, $referenceDate);
    $this->assertEquals(12, $balance);
}
```

### 8. The Secret Catcher (No Test for the Bug)
When fixing a bug, ALWAYS write a test that reproduces it first:
```php
// ✅ Write regression test before fixing
public function testBug_NegativeTotalWhenAllLinesDeleted() {
    // This failed before the fix
    $po = $this->createPOWithLines(3);
    $service->deleteAllLines($po->id);
    
    $total = $model->findById($po->id)->total_amount;
    $this->assertEquals(0, $total); // Was returning -1 before fix
}
```

## Test Audit Checklist
- [ ] Every test has at least one meaningful assertion
- [ ] Each test method tests ONE behavior
- [ ] Failure messages are descriptive
- [ ] Edge cases covered (null, empty, zero, negative, boundary)
- [ ] Error paths tested, not just happy paths
- [ ] No hard-coded dates/times that will expire
- [ ] Tests are independent (no order dependency)
- [ ] Mocks only used for external dependencies
