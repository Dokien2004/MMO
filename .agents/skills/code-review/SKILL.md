---
name: code-review
description: Use when reviewing code before commits, pull requests, or deployments. Multi-perspective analysis covering bugs, security, test coverage, code quality, contracts, and historical context. Provides comprehensive review from 6 specialized perspectives.
---

# Code Review

Comprehensive multi-perspective code review system. Six specialized review perspectives examine code from different angles to catch issues before they reach production.

## When to Use

- Before committing important changes
- Before creating pull requests
- After implementing a new feature
- After fixing a critical bug
- During security audits
- When onboarding to unfamiliar code

## Review Perspectives

### 1. Bug Hunter

**Focus:** Logic errors, edge cases, race conditions

**Check for:**
- Off-by-one errors
- Null/undefined handling
- Race conditions in concurrent code
- Integer overflow/underflow
- Boundary conditions
- Incorrect comparisons (== vs ===, empty() vs isset())
- Missing return statements
- Infinite loops or recursion
- Resource leaks (unclosed connections, file handles)

### 2. Security Auditor

**Focus:** Vulnerabilities, injection, access control

**Check for:**
- SQL injection (string concatenation in queries)
- XSS (unescaped output)
- CSRF token validation
- Authentication bypass
- Authorization checks (can user X access resource Y?)
- Sensitive data exposure in logs/errors
- Hardcoded secrets or credentials
- File path traversal
- Insecure deserialization
- Missing input validation
- Session fixation

### 3. Test Coverage Reviewer

**Focus:** Test completeness, quality, edge cases

**Check for:**
- Missing tests for new functionality
- Missing edge case tests
- Tests that don't actually test anything meaningful
- Brittle tests (depend on implementation details)
- Missing error path tests
- Integration test gaps
- Mock overuse hiding real bugs

### 4. Code Quality Reviewer

**Focus:** Readability, maintainability, patterns

**Check for:**
- Functions doing too many things (violates SRP)
- Deep nesting (> 3 levels)
- Magic numbers/strings
- Duplicated code
- Misleading variable/function names
- Overengineering (YAGNI violations)
- Missing error handling
- Inconsistent coding style
- God classes/functions
- Dead code

### 5. Contracts Reviewer

**Focus:** API contracts, interfaces, type safety

**Check for:**
- Breaking API changes
- Missing validation on public interfaces
- Inconsistent response formats
- Missing or incorrect type hints
- Undocumented side effects
- Changed function signatures without updating callers
- Return type inconsistencies
- Missing PHPDoc for public methods

### 6. Historical Context Reviewer

**Focus:** Patterns, regressions, technical debt

**Check for:**
- Patterns that differ from established codebase conventions
- Reintroduction of previously fixed bugs
- Increasing technical debt
- Abandoned TODO/FIXME comments
- Copy-pasted code from other modules without adaptation
- Configuration changes that affect other modules

## Review Process

```
1. UNDERSTAND the change
   - Read the diff completely
   - Understand the WHY, not just the WHAT
   - Check related files affected

2. APPLY each perspective
   - Run through all 6 review perspectives
   - Note issues with severity

3. PRIORITIZE findings
   - Critical: Must fix before merge (security, data loss, crashes)
   - Major: Should fix before merge (bugs, performance)
   - Minor: Nice to fix (style, naming, patterns)

4. REPORT with actionable feedback
   - Specific location (file, line)
   - Clear description of issue
   - Suggested fix
   - Severity level
```

## Severity Levels

| Level | Description | Action |
|-------|-------------|--------|
| 🔴 Critical | Security vulnerability, data loss, crash | Block merge |
| 🟠 Major | Bug, performance issue, missing validation | Should fix |
| 🟡 Minor | Code style, naming, minor improvement | Nice to have |
| 🔵 Info | Suggestion, alternative approach | Consider |

## Output Format

```markdown
## Code Review Summary

**Files Reviewed:** [count]
**Issues Found:** [count by severity]

### 🔴 Critical Issues
1. **[File:Line]** - [Description]
   - **Impact:** [What could go wrong]
   - **Fix:** [Suggested solution]

### 🟠 Major Issues
...

### 🟡 Minor Issues
...

### ✅ Positive Highlights
- [What's done well]
```
