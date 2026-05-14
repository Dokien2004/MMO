---
name: reflexion
description: Self-refinement loop forcing reflection on generated output before finalizing. Use after generating code, plans, or analysis to catch errors, improve quality, and verify completeness. Based on Reflexion research by NeoLabHQ.
---

# Reflexion — Self-Correction Loop

## Purpose
Force a structured self-review of generated output before presenting it as final. Prevents common AI mistakes: incomplete implementations, missed edge cases, inconsistent naming, and logic errors.

## When to Use
- After generating any code change (controller, model, service, view)
- After creating an implementation plan
- After suggesting a fix for a bug
- Before claiming work is "done"

## The Reflexion Loop

```
Generate Output → Self-Critique → Identify Issues → Revise → Verify
      ↑                                                         |
      └─────────── If still has issues ────────────────────────┘
```

Maximum 3 iterations. If output is still unsatisfactory after 3 rounds, flag it explicitly.

## Self-Critique Checklist

### For PHP Code (Controller/Service/Model)

**Correctness:**
- [ ] Does the logic actually solve the stated problem?
- [ ] Are all code paths handled (if/else branches, switch defaults)?
- [ ] Are error conditions handled (null, empty, exception)?
- [ ] Does it match the method signature and return type expectations?

**Security (per project rules):**
- [ ] SQL uses `bind()`, never string concatenation?
- [ ] User input escaped with `e()` or `htmlspecialchars()` in views?
- [ ] CSRF token included in POST forms?
- [ ] `$this->checkPermission()` called before sensitive actions?
- [ ] `site_id` scoping applied (or `$isSiteSpecific = true` in model)?

**Architecture:**
- [ ] No SQL queries in Controller? (Should be in Model)
- [ ] No business logic in Controller? (Should be in Service)
- [ ] No cross-module table access? (Use other module's Model/Service)
- [ ] File ≤ 300 lines?

**Naming:**
- [ ] Controller: PascalCase + `Controller`?
- [ ] Model: PascalCase + `Model`?
- [ ] Method: camelCase?
- [ ] DB columns: snake_case?
- [ ] URLs: kebab-case?

### For SQL Queries

- [ ] Table and column names verified against `app/db_schema.sql`?
- [ ] Proper JOINs (not subqueries where join suffices)?
- [ ] Index-friendly WHERE clauses?
- [ ] `site_id` filter included?
- [ ] Soft delete filter (`deleted_at IS NULL`) if applicable?

### For Views/JavaScript

- [ ] All user data escaped with `e()` or `htmlspecialchars()`?
- [ ] CSRF token in forms (`<?php csrf_field(); ?>`)?
- [ ] JS in separate file (`public/js/modules/{module}/{entity}.js`)?
- [ ] AJAX uses standard pattern with CSRF header?
- [ ] Error responses handled (403, 419, 500)?

## Critique Format

After generating code, perform this self-review:

```
REFLEXION REVIEW:
✅ Correctness: Logic handles all stated requirements
✅ Security: bind() used, permissions checked, CSRF included
⚠️ Edge Case: What if $items is empty array? → Added empty check
❌ Architecture: Business logic in controller → Moved to Service
⚠️ Naming: Method `getData` is too generic → Renamed to `getActiveEmployees`

REVISED: [apply changes]
VERIFICATION: All issues addressed in revision.
```

## Anti-Patterns This Prevents

1. **Premature completion** — saying "done" before verifying
2. **Copy-paste errors** — variable names from templates not updated
3. **Missing guards** — null checks, type checks, permission checks
4. **Inconsistency** — mixing naming conventions within same file
5. **Hardcoded values** — literals that should come from config
