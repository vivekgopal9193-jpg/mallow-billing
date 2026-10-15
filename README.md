# Mallow Technologies — Subscription Billing & Usage Metering

A Laravel-based subscription billing system that records customer usage, calculates subscription charges, supports mid-cycle plan changes, and provides merchant usage analytics.

## 1. Technology Stack

- PHP 8.5
- Laravel
- SQLite for local development
- Laravel database queue
- Laravel Cache
- PHPUnit / Laravel testing tools

## 2. Features

- Merchant, plan, customer, and subscription management schema
- Usage ingestion API with validation and idempotency
- Daily usage aggregation using queued jobs
- Base subscription charges, overage calculations, and proration
- Mid-cycle subscription plan changes
- Merchant dashboard with top usage customers, overage revenue, and usage-drop analysis
- Cached plan pricing with cache invalidation
- Database indexes and unique constraints for common queries
- Automated tests for billing, plan changes, usage ingestion, and dashboard responses

## 3. Local Setup

Requirements: PHP, Composer, and a supported PHP database driver.

Clone the repository and install dependencies:

```bash
composer install
```

Create your environment file if it does not exist:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure SQLite in `.env` using the appropriate database path for your local environment. Create the database file if required.

Run migrations:

```bash
php artisan migrate
```

Start the application:

```bash
php artisan serve
```

The default local URL is:

`http://127.0.0.1:8000`

Run automated tests:

```bash
php artisan test
```

## 4. Usage API

### POST /api/usage

Records a usage event.

Example request:

```json
{
  "merchant_id": 1,
  "customer_id": 1,
  "subscription_id": 1,
  "idempotency_key": "event-unique-001",
  "quantity": 10,
  "occurred_on": "2026-10-15",
  "event_type": "api_call"
}
```

The API validates the request, verifies the active subscription relationship, and uses a unique merchant/idempotency-key constraint to prevent duplicate events.

A newly inserted event dispatches an aggregation job to Laravel's queue.

### GET /api/merchants/{merchantId}/dashboard

Returns merchant dashboard data, including:

- Top five customers by usage for the current month
- Current accrued overage revenue estimate
- Customers whose usage has dropped by more than 50% compared with the previous month

## 5. Billing Rules

Amounts are stored as integer currency units to avoid floating-point rounding issues.

For an INR implementation, amounts are represented in paise.

**Overage units**

```text
Overage units = max(0, total usage - included units)
```

**Overage charge**

```text
Overage charge = overage units × overage rate
```

**Invoice total**

```text
Invoice total = base charge + overage charge + applicable adjustments
```

Proration is calculated according to the active portion of the billing period. Mid-cycle plan changes split the billing period into plan segments so usage can be evaluated using the applicable plan.

The exact day-count convention and treatment of unused allowances should be documented as billing policy decisions.

## 6. Queue Processing

The application uses Laravel's database queue to run aggregation jobs asynchronously.

Configure the queue connection in `.env`:

```env
QUEUE_CONNECTION=database
```

Start a worker:

```bash
php artisan queue:work
```

New usage events dispatch `AggregateUsageJob`. The current implementation rebuilds daily aggregates from raw usage events, making reruns easier to reason about for a small demo dataset.

For production-scale workloads, replace full rebuilds with incremental or partitioned aggregation. Ensure retries, concurrency, and duplicate job delivery cannot cause incorrect totals.

## 7. Plan Pricing Cache

`PlanPricingService` caches active plan details for 30 minutes.

- `getPlan()` reads cached pricing or loads it from the database.
- `updatePlanPricing()` updates pricing and invalidates the cached entry.
- `forgetPlan()` explicitly removes a plan's cached entry.

All plan mutation paths should use the same invalidation strategy.

## 8. Database Design and Indexing

The main tables include:

- `merchants`
- `plans`
- `customers`
- `subscriptions`
- `usage_events`
- `usage_daily_aggregates`
- `invoices`
- `subscription_plan_changes`

Unique constraints protect idempotency, subscription/customer relationships, daily aggregate keys, and invoice periods.

Indexes support subscription/date usage queries, merchant/date/customer dashboard queries, and subscription status/period lookups.

## 9. Scaling to Five Million Usage Events

For a production deployment handling millions of usage events:

1. Use a production database such as PostgreSQL or MySQL rather than relying on a local SQLite setup.
2. Batch usage ingestion where possible and retain a unique idempotency key.
3. Partition large usage tables by time when supported and justified by query patterns.
4. Process events in bounded chunks using queue workers.
5. Use incremental daily aggregates or partitioned batch jobs instead of rebuilding every aggregate after each event.
6. Configure separate queues and worker capacity for ingestion, aggregation, and invoice generation.
7. Monitor queue depth, processing latency, failed jobs, and database query performance.
8. Benchmark realistic data volumes and inspect query plans before selecting additional indexes.
9. Make invoice generation idempotent and protect invoice periods with database constraints.
10. Use appropriate locking or concurrency-safe upserts to prevent lost updates.

The current local implementation is a demonstration baseline; these production optimizations require load testing and further engineering.

## 10. Automated Tests

Run:

```bash
php artisan test
```

The test suite covers:

- Overage calculations
- Billing totals
- Proration
- Mid-cycle plan changes
- Usage event validation
- Duplicate idempotency keys
- Dashboard data and unknown merchants

## 11. Future Improvements

- Add authenticated merchant management endpoints.
- Add plan and subscription management APIs.
- Generate invoices through scheduled or queued jobs.
- Add dedicated tests for aggregation retries, cache invalidation, and concurrent usage ingestion.
- Improve revenue projections using expected end-of-cycle usage.
- Add production database and load-testing documentation.
