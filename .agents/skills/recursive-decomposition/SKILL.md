---
name: recursive-decomposition
description: Handle large-scale tasks involving 100+ files or 50k+ tokens by breaking them into independent sub-tasks. Use when a request affects multiple modules, requires bulk refactoring, or involves complex cross-cutting concerns. Based on RLM research.
---

# Recursive Decomposition

## Purpose
When facing a task too large for a single context window (100+ files, 50k+ tokens, cross-module changes), decompose it into smaller, independently solvable sub-tasks.

## When to Use
- Task affects 5+ files across different modules
- Refactoring that touches multiple controllers/models/views
- Adding a cross-cutting feature (e.g., audit logging to all modules)
- Database migration affecting many tables
- Bulk code quality improvements

## Decomposition Strategy

### Level 1: Module Decomposition
Split by module boundary:
```
Task: "Add export-to-Excel to all list pages"
├── HR Module (employees, leaves, overtime)
├── Inventory Module (goods receipt, issue, transfer)
├── Purchasing Module (PO, PR, vendor)
├── Sales Module (SO, quotation, invoice)
└── Finance Module (GL, AP, AR)
```

### Level 2: Layer Decomposition
Within each module, split by architectural layer:
```
HR Module - Export Feature:
├── Model: Add query methods for export data
├── Service: Add export logic, formatting
├── Controller: Add export endpoint
├── View: Add export button
└── JS: Add click handler, download trigger
```

### Level 3: Entity Decomposition
Within each layer, handle one entity at a time:
```
HR Model Layer:
├── EmployeeModel::getExportData()
├── LeaveRequestModel::getExportData()
└── OvertimeModel::getExportData()
```

## Execution Rules

### Rule 1: Dependencies First
```
1. Database migration (schema changes)
2. Model layer (data access)
3. Service layer (business logic)
4. Controller layer (endpoints)
5. View/JS layer (UI)
```

### Rule 2: One Sub-Task = One Testable Unit
Each sub-task must be independently verifiable:
```
Sub-task: "Add getExportData() to EmployeeModel"
Verify: $model->getExportData(['status' => 'active']) returns array
```

### Rule 3: Interface Contracts
Define the interface BEFORE implementing:
```php
// Define contract first
interface ExportableModel {
    public function getExportData(array $filters = []): array;
    public function getExportColumns(): array;
}

// Then implement per entity
class EmployeeModel extends BaseModel implements ExportableModel {
    public function getExportData(array $filters = []): array { ... }
    public function getExportColumns(): array { ... }
}
```

### Rule 4: Track Progress
Maintain a checklist as you work:
```markdown
## Export Feature Progress
- [x] Database: No schema changes needed
- [x] EmployeeModel::getExportData()
- [x] EmployeeController::export()  
- [ ] LeaveRequestModel::getExportData()
- [ ] LeaveRequestController::export()
- [ ] Views: Add export buttons
- [ ] JS: Download handlers
```

## Decomposition Template

```markdown
# Task: [Description]

## Analysis
- Files affected: ~N files
- Modules affected: [list]
- Dependencies: [what must be done first]

## Sub-tasks (ordered by dependency)

### Phase 1: Foundation
1. [ ] [Sub-task 1] - [files affected] - [~time]
2. [ ] [Sub-task 2] - [files affected] - [~time]

### Phase 2: Core Implementation  
3. [ ] [Sub-task 3] - [files affected] - [~time]
4. [ ] [Sub-task 4] - [files affected] - [~time]

### Phase 3: Integration
5. [ ] [Sub-task 5] - [files affected] - [~time]

### Phase 4: Verification
6. [ ] Test each sub-task independently
7. [ ] Integration test across modules
```

## Anti-Patterns

### ❌ Big Bang Implementation
Trying to change everything at once → merge conflicts, hard to debug, impossible to test.

### ❌ Depth-First Tunnel Vision  
Going deep into one module while ignoring shared patterns → inconsistent implementations.

### ❌ Skipping the Contract
Implementing without agreeing on interfaces → integration failures later.

## Recovery
If a sub-task reveals the decomposition was wrong:
1. STOP current implementation
2. Re-analyze the dependency graph
3. Update the decomposition
4. Resume from the corrected plan
