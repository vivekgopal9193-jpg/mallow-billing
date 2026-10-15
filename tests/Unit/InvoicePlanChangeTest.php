<?php

namespace Tests\Unit;

use App\Services\InvoiceService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoicePlanChangeTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_invoice_prorates_both_plans_after_mid_cycle_change(): void
    {
        $merchantId = DB::table('merchants')->insertGetId([
            'name' => 'Test Merchant',
            'email' => 'test-merchant@example.com',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oldPlanId = DB::table('plans')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'Old Plan',
            'base_price_cents' => 30000,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate_cents' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newPlanId = DB::table('plans')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'New Plan',
            'base_price_cents' => 60000,
            'billing_cycle' => 'monthly',
            'included_units' => 2000,
            'overage_rate_cents' => 5,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'Test Customer',
            'email' => 'test-customer@example.com',
            'external_id' => 'test-plan-change-customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'customer_id' => $customerId,
            'plan_id' => $newPlanId,
            'status' => 'active',
            'starts_at' => '2026-10-01 00:00:00',
            'current_period_start' => '2026-10-01 00:00:00',
            'current_period_end' => '2026-11-01 00:00:00',
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscription_plan_changes')->insert([
            'subscription_id' => $subscriptionId,
            'from_plan_id' => $oldPlanId,
            'to_plan_id' => $newPlanId,
            'effective_at' => '2026-10-16 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoice = app(InvoiceService::class)->generate($subscriptionId);

        // October 1-15: 15 days on the old plan.
        // October 16-31: 16 days on the new plan.
        $expectedOldCharge = (int) round(30000 * 15 / 31);
        $expectedNewCharge = (int) round(60000 * 16 / 31);
        $expectedTotal = $expectedOldCharge + $expectedNewCharge;

        $this->assertSame($expectedTotal, (int) $invoice->total_amount_cents);
        $this->assertSame(0, (int) $invoice->overage_amount_cents);
    }
}
