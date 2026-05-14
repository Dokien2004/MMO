---
name: root-cause-tracing
description: Deep investigation technique to identify fundamental causes of bugs. Use when systematic-debugging identifies a symptom but the root cause remains elusive. Traces error propagation through call chains, data flows, and state mutations.
---

# Root Cause Tracing

## Purpose
When a bug's symptoms are clear but the fundamental cause is hidden, this skill provides a structured investigation methodology to trace backward from symptoms to root causes.

## When to Use
- After systematic-debugging identifies a failing behavior but the fix remains unclear
- When a bug recurs despite previous "fixes" (symptom-masking detected)
- When errors propagate across multiple modules/layers
- When state corruption is suspected but the source is unknown

## Core Principle: The 5 Whys Chain

Never stop at the first explanation. Ask "Why?" at least 5 times:

```
Symptom: Purchase Order total is wrong
→ Why? Line amounts don't sum correctly
→ Why? One line has negative quantity
→ Why? The quantity was updated after approval
→ Why? The edit endpoint doesn't check status
→ Why? The controller skips validateEditPermissions() for AJAX updates
ROOT CAUSE: Missing status guard in AJAX edit path
```

## Investigation Phases

### Phase 1: Symptom Mapping
Document exactly what is wrong:
- **What** is the incorrect behavior?
- **When** does it occur? (Always? Sometimes? After specific actions?)
- **Where** in the system does it manifest?
- **What data** is affected?

### Phase 2: Backward Tracing
Starting from the symptom, trace backward through:

1. **View Layer**: Is the data displayed correctly given what it receives?
2. **Controller Layer**: Is the controller passing correct data to the view?
3. **Service Layer**: Is the business logic producing correct results?
4. **Model Layer**: Is the ORM returning correct data from DB?
5. **Database Layer**: Is the stored data correct?
6. **Input Layer**: Was the original input valid?

```php
// Example: Trace a wrong total in PHP
// [1] View: Check what $data contains
error_log("[TRACE-VIEW] Total displayed: " . json_encode($data['total']));

// [2] Controller: Check what model returns  
$result = $model->findById($id);
error_log("[TRACE-CTRL] Model result: " . json_encode($result));

// [3] Model: Check raw SQL result
$this->db->query("SELECT * FROM po_lines WHERE po_id = :id");
$this->db->bind(':id', $id);
$raw = $this->db->resultSet();
error_log("[TRACE-MODEL] Raw DB rows: " . json_encode($raw));
```

### Phase 3: State Transition Analysis
For state-related bugs, map the entity's lifecycle:

```
Created (draft) → Submitted → Approved → Received → Closed
                     ↓            ↓
                  Rejected     Cancelled
```

Check: At which state transition did the data become corrupt?

### Phase 4: Data Flow Diagram
For cross-module bugs, trace data as it flows:

```
User Input → Controller → Validation → Service → Model → DB
                                          ↓
                                    Other Service
                                          ↓
                                    Other Model → DB
```

## Anti-Patterns to Detect

### 1. Symptom Masking
```php
// ❌ BAD: Hiding the problem
if ($total < 0) $total = 0; // "Fix" negative totals

// ✅ GOOD: Find WHY total is negative
if ($total < 0) {
    error_log("[ROOT-CAUSE] Negative total detected: $total for PO #$poId");
    throw new Exception("Data integrity error: negative total");
}
```

### 2. Shotgun Debugging
Making multiple changes hoping one fixes it. Instead:
- Change ONE thing at a time
- Verify the hypothesis before and after
- Document what you tried and the result

### 3. Blame the Framework
"BaseModel is returning wrong data" → First verify your query, bindings, and conditions are correct.

## Verification
Before declaring root cause found:
1. Can you explain the FULL chain from cause → symptom?
2. Does fixing the root cause eliminate the symptom WITHOUT side effects?
3. Are there other code paths with the same vulnerability?
4. Write a test that reproduces the bug BEFORE applying the fix
