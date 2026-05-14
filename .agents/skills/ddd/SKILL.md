---
name: ddd
description: Use when designing new modules, refactoring existing architecture, or reviewing code structure. Applies Domain-Driven Design, Clean Architecture, and SOLID principles to ensure maintainable, scalable systems with clear separation of concerns.
---

# Domain-Driven Development

Battle-tested software architecture principles for building maintainable, scalable systems. Based on Eric Evans' "Domain-Driven Design", Robert C. Martin's "Clean Architecture", and SOLID principles.

## When to Use

- Designing new modules or features
- Refactoring existing architecture
- Reviewing code structure and organization
- Deciding where business logic should live
- Planning domain boundaries between modules
- Evaluating coupling between components

## Core Principles

### 1. Separation of Concerns

```
┌─────────────────────────────────────┐
│  Controllers (HTTP/API Layer)       │  ← Thin, routing only
├─────────────────────────────────────┤
│  Services (Business Logic Layer)    │  ← Core domain logic
├─────────────────────────────────────┤
│  Models (Data Access Layer)         │  ← Database operations
├─────────────────────────────────────┤
│  Helpers (Shared Utilities)         │  ← Cross-cutting concerns
└─────────────────────────────────────┘
```

**Rules:**
- Controllers: Parse request, call service, return response. No business logic.
- Services: Business rules, validation, orchestration. No SQL, no HTTP.
- Models: Database queries, data mapping. No business rules.
- Helpers: Reusable utilities (formatting, security, logging).

### 2. SOLID Principles

| Principle | Meaning | PHP Example |
|-----------|---------|-------------|
| **S**ingle Responsibility | One class = one reason to change | `EmployeeModel` handles employee DB operations only |
| **O**pen/Closed | Open for extension, closed for modification | Use helpers/traits instead of modifying base classes |
| **L**iskov Substitution | Subtypes must be substitutable | Child controllers work correctly with base `Controller` |
| **I**nterface Segregation | Don't force unnecessary methods | Split large models into focused ones |
| **D**ependency Inversion | Depend on abstractions, not concretions | Use dependency injection, not global state |

### 3. Domain Language

**Use business terminology in code, not technical jargon:**

| Bad | Good |
|-----|------|
| `$data['flag1']` | `$employee['is_active']` |
| `processItem()` | `approveLeaveRequest()` |
| `handleStuff()` | `calculateOvertimeHours()` |
| `$arr` | `$departments` |
| `doAction($type)` | `submitExpenseReport($report)` |

### 4. Bounded Contexts

Each ERP module should be a bounded context:

```
HR Module          Working Plan Module       Portal Module
├── Controllers    ├── Controllers           ├── Controllers
├── Models         ├── Models                ├── Models
├── Views          ├── Views                 ├── Views
└── Helpers        └── Helpers               └── Helpers
```

**Rules:**
- Modules communicate through well-defined interfaces
- Don't directly query another module's database tables
- Use shared services for cross-module operations
- Each module owns its data and business rules

### 5. Clean Architecture Rules

**Dependency Rule:** Dependencies point INWARD only.

```
❌ Model imports Controller logic
❌ Helper depends on specific Controller
❌ View contains business logic
✅ Controller → Service → Model
✅ Helper ← used by any layer
```

## Anti-Patterns to Avoid

### Fat Controllers
```php
// ❌ BAD: Controller doing everything
public function approve($id) {
    $request = $this->model->get($id);
    if ($request['status'] != 'pending') { /* validation */ }
    if ($request['days'] > $remainingDays) { /* business rule */ }
    $this->model->update($id, ['status' => 'approved']);
    $this->sendEmail($request); // notification
    $this->logAction($id); // logging
}

// ✅ GOOD: Controller delegates to service
public function approve($id) {
    $result = $this->leaveService->approve($id, $this->session->userId);
    $this->json($result);
}
```

### God Models
```php
// ❌ BAD: Model with 50+ methods covering everything
class EmployeeModel {
    public function getAll() { }
    public function getById() { }
    public function calculateSalary() { }      // business logic!
    public function sendNotification() { }      // notification!
    public function generateReport() { }        // reporting!
    public function validatePermission() { }    // authorization!
}

// ✅ GOOD: Focused models
class EmployeeModel { /* CRUD only */ }
class SalaryService { /* salary calculations */ }
class NotificationHelper { /* notifications */ }
```

### Tight Coupling Between Modules
```php
// ❌ BAD: HR module directly using Working Plan tables
$this->db->query("SELECT * FROM working_plan_tasks WHERE...");

// ✅ GOOD: Use cross-module service
$tasks = $this->workingPlanService->getTasksByEmployee($empId);
```

## Checklist for New Features

- [ ] Business logic in Service layer, not Controller
- [ ] Database operations in Model layer only
- [ ] Clear naming using domain language
- [ ] No cross-module direct database access
- [ ] Single Responsibility for each class/method
- [ ] Dependencies point inward only
- [ ] Input validation at boundary (Controller)
- [ ] Error handling at appropriate layer
- [ ] No God classes or fat controllers

## Foundation Literature

- Eric Evans, "Domain-Driven Design" (2003)
- Robert C. Martin, "Clean Architecture" (2017)
- Martin Fowler, "Patterns of Enterprise Application Architecture" (2002)
- SOLID Principles (Robert C. Martin)
