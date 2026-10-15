<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoBillingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 1. Create a merchant.
        DB::table('merchants')->updateOrInsert(
            ['email' => 'demo@mallow.test'],
            [
                'name' => 'Mallow Demo Merchant',
                'status' => 'active',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $merchant = DB::table('merchants')
            ->where('email', 'demo@mallow.test')
            ->first();

        // 2. Create a monthly plan.
        DB::table('plans')->updateOrInsert(
            [
                'merchant_id' => $merchant->id,
                'name' => 'Starter Monthly',
            ],
            [
                'base_price_cents' => 99900,
                'billing_cycle' => 'monthly',
                'included_units' => 1000,
                'overage_rate_cents' => 10,
                'status' => 'active',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $plan = DB::table('plans')
            ->where('merchant_id', $merchant->id)
            ->where('name', 'Starter Monthly')
            ->first();

        // 3. Create a customer.
        DB::table('customers')->updateOrInsert(
            [
                'merchant_id' => $merchant->id,
                'external_id' => 'demo-customer-001',
            ],
            [
                'name' => 'Demo Customer',
                'email' => 'customer@mallow.test',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $customer = DB::table('customers')
            ->where('merchant_id', $merchant->id)
            ->where('external_id', 'demo-customer-001')
            ->first();

        // 4. Create an active subscription.
        $periodStart = $now->copy()->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonth();

        DB::table('subscriptions')->updateOrInsert(
            [
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
            ],
            [
                'status' => 'active',
                'starts_at' => $periodStart,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'ends_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $this->command->info('Demo billing data created successfully.');
        $this->command->info("Merchant ID: {$merchant->id}");
        $this->command->info("Plan ID: {$plan->id}");
        $this->command->info("Customer ID: {$customer->id}");
    }
}
