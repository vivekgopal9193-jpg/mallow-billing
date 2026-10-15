<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_usage_and_revenue_data(): void
    {
        $now = now();

        $merchantId = DB::table('merchants')->insertGetId([
            'name' => 'Dashboard Test Merchant',
            'email' => 'dashboard@example.com',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $planId = DB::table('plans')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'Dashboard Plan',
            'base_price_cents' => 99900,
            'billing_cycle' => 'monthly',
            'included_units' => 100,
            'overage_rate_cents' => 10,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'Dashboard Customer',
            'email' => 'dashboard-customer@example.com',
            'external_id' => 'dashboard-customer-001',
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

        DB::table('usage_events')->insert([
            'merchant_id' => $merchantId,
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
            'idempotency_key' => 'dashboard-event-001',
            'event_type' => 'api_call',
            'quantity' => 150,
            'occurred_on' => now()->startOfMonth()->toDateString(),
            'metadata' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->getJson(
            "/api/merchants/{$merchantId}/dashboard"
        );

        $response->assertOk()
            ->assertJsonPath('merchant.id', $merchantId)
            ->assertJsonPath('top_5_customers_by_usage.0.customer_id', $customerId)
            ->assertJsonPath('top_5_customers_by_usage.0.total_usage', 150)
            ->assertJsonPath('projected_overage_revenue_cents', 500);
    }

    public function test_dashboard_returns_404_for_unknown_merchant(): void
    {
        $this->getJson('/api/merchants/999999/dashboard')
            ->assertNotFound()
            ->assertJsonPath('message', 'Merchant not found.');
    }
}
