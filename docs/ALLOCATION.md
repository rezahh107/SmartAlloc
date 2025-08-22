# Allocation Engine

This document summarises the allocation flow used by SmartAlloc.

## Filters

Candidates are filtered in the following order:

1. gender
2. group / grade
3. school (when a mentor is school-backed)
4. center
5. target manager
6. mentors of that manager
7. active mentors with remaining capacity

## Scoring

The default scoring strategy uses `ScoringAllocator`:

```
score = (1 - load_ratio) * W1 + allocations_new_boost * W2 + mentor_id_tiebreak
```

- `load_ratio = current_assigned / capacity`
- `allocations_new_boost` is `1` when the mentor has no new allocations
- `mentor_id_tiebreak` is a tiny fraction based on the id to keep ordering deterministic
- Weights `W1` and `W2` are filterable via `smartalloc_scoring_weights` (defaults `1.0` and `0.1`)

## Guarded update

Capacity is reserved via a guarded `UPDATE` statement:

```
UPDATE ... SET assigned = assigned + 1
WHERE mentor_id = ? AND active = 1 AND capacity > assigned
```

The reservation retries up to three times with small random jitter when a race is lost.

## Events

On successful assignment the service emits two events via the internal `EventBus`:

1. `MentorAssigned`
2. `AllocationCommitted`

Events use a dedupe key of `alloc:{student_id}:v1` and include a minimal payload of
`student_id`, `mentor_id` and `ts_utc`. A trace id header is forwarded when present.

## Notifications, retries and DLQ

Notifications to mentors/admins are processed asynchronously with a circuit breaker.
Failures are retried with exponential backoff (max five attempts, jitter added).
Permanent failures are moved to the `wp_salloc_dlq` table where they can be retried via REST.

## REST

`GET /smartalloc/v1/dlq` lists `status=ready` items and supports `?limit=` and `?offset=`.
`POST /smartalloc/v1/dlq/{id}/retry` re-enqueues a single item and marks it consumed on success.
