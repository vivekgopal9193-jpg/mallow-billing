<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UsageApiTest extends TestCase
{
    use RefreshDatabase;

    private function createTestSubscription(): array
    {
        $now = now();

        $merchantId = DB::table('merchants')->insertGetId([
            'name' => 'Test Merchant',
            'email' => 'merchant-test@example.com',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $planId = DB::table('plans')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'Test Plan',
            'base_price_cents' => 99900,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate_cents' => 10,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'Test Customer',
            'email' => 'customer-test@example.com',
            'external_id' => 'customer-test-001',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'status' => 'active',
            'starts_at' => '2026-10-01 00:00:00',
            'current_period_start' => '2026-10-01 00:00:00',
            'current_period_end' => '2026-11-01 00:00:00',
            'ends_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$merchantId, $customerId, $subscriptionId];
    }

    public function test_it_records_a_valid_usage_event(): void
    {
        [$merchantId, $customerId, $subscriptionId] =
            $this->createTestSubscription();

        $response = $this->postJson('/api/usage', [
            'merchant_id' => $merchantId,
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
            'idempotency_key' => 'test-event-001',
            'quantity' => 5,
            'occurred_on' => '2026-10-15',
            'event_type' => 'api_call',
        ]);

        $response->assertCreated()
            ->assertJsonPath('duplicate', false);

        $this->assertDatabaseHas('usage_events', [
            'merchant_id' => $merchantId,
            'idempotency_key' => 'test-event-001',
            'quantity' => 5,
        ]);
    }

    public function test_duplicate_idempotency_key_does_not_duplicate_usage(): void
    {
        [$merchantId, $customerId, $subscriptionId] =
            $this->createTestSubscription();

        $payload = [
            'merchant_id' => $merchantId,
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
            'idempotency_key' => 'test-event-duplicate',
            'quantity' => 5,
            'occurred_on' => '2026-10-15',
        ];

        $this->postJson('/api/usage', $payload)->assertCreated();

        $this->postJson('/api/usage', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(
            1,
            DB::table('usage_events')
                ->where('merchant_id', $merchantId)
                ->where('idempotency_key', 'test-event-duplicate')
                ->count()
        );
    }

    public function test_it_rejects_invalid_usage_data(): void
    {
        $response = $this->postJson('/api/usage', [
            'merchant_id' => 999999,
            'customer_id' => 999999,
            'subscription_id' => 999999,
            'idempotency_key' => '',
            'quantity' => 0,
            'occurred_on' => 'not-a-date',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'merchant_id',
                'customer_id',
                'subscription_id',
                'idempotency_key',
                'quantity',
                'occurred_on',
            ]);
    }
}
