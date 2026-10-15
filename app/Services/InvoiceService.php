<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use RuntimeException;

class InvoiceService
{
    public function generate(int $subscriptionId): object
    {
        return DB::transaction(function () use ($subscriptionId) {
            $subscription = DB::table('subscriptions')
                ->join('customers', 'customers.id', '=', 'subscriptions.customer_id')
                ->where('subscriptions.id', $subscriptionId)
                ->select(
                    'subscriptions.*',
                    'customers.merchant_id'
                )
                ->first();

            if (! $subscription) {
                throw new RuntimeException('Subscription not found.');
            }

            $periodStart = Carbon::parse(
                $subscription->current_period_start
            )->startOfDay();

            $periodEnd = Carbon::parse(
                $subscription->current_period_end
            )->startOfDay();

            $activeStart = Carbon::parse(
                $subscription->starts_at
            )->startOfDay();

            if ($activeStart->gt($periodStart)) {
                $activeStart = $activeStart->copy();
            } else {
                $activeStart = $periodStart->copy();
            }

            if ($activeStart->gte($periodEnd)) {
                throw new RuntimeException(
                    'Subscription has no active days in this billing period.'
                );
            }

            $totalPeriodDays = $periodStart->diffInDays($periodEnd);

            $changes = DB::table('subscription_plan_changes')
                ->where('subscription_id', $subscriptionId)
                ->where('effective_at', '>', $activeStart)
                ->where('effective_at', '<', $periodEnd)
                ->orderBy('effective_at')
                ->get();

            $segments = [];
            $segmentStart = $activeStart->copy();

            $firstPlanId = $subscription->plan_id;

            if ($changes->isNotEmpty()) {
                $firstPlanId = $changes->first()->from_plan_id;
            }

            $currentPlanId = (int) $firstPlanId;

            foreach ($changes as $change) {
                $changeDate = Carbon::parse(
                    $change->effective_at
                )->startOfDay();

                if ($changeDate->gt($segmentStart)) {
                    $segments[] = [
                        'plan_id' => $currentPlanId,
                        'start' => $segmentStart->toDateString(),
                        'end' => $changeDate->toDateString(),
                        'days' => $segmentStart->diffInDays($changeDate),
                    ];
                }

                $segmentStart = $changeDate;
                $currentPlanId = (int) $change->to_plan_id;
            }

            if ($segmentStart->lt($periodEnd)) {
                $segments[] = [
                    'plan_id' => $currentPlanId,
                    'start' => $segmentStart->toDateString(),
                    'end' => $periodEnd->toDateString(),
                    'days' => $segmentStart->diffInDays($periodEnd),
                ];
            }

            $baseCharge = 0;
            $overage = 0;
            $nominalBasePrice = 0;
            $billing = new BillingService();

            foreach ($segments as $segment) {
                $plan = DB::table('plans')
                    ->where('id', $segment['plan_id'])
                    ->first();

                if (! $plan) {
                    throw new RuntimeException('Plan not found.');
                }

                // $nominalBasePrice += (int) $plan->base_price_cents;
                $nominalBasePrice += (int) round(
                    (int) $plan->base_price_cents
                    * $segment['days']
                    / $totalPeriodDays
                );

                $baseCharge += $billing->calculateProration(
                    (int) $plan->base_price_cents,
                    $segment['days'],
                    $totalPeriodDays
                );

                $usage = (int) DB::table('usage_events')
                    ->where('subscription_id', $subscriptionId)
                    ->where('occurred_on', '>=', $segment['start'])
                    ->where('occurred_on', '<', $segment['end'])
                    ->sum('quantity');

                // Prorate the included allowance for this plan segment.
                $includedUnits = (int) floor(
                    (int) $plan->included_units
                    * $segment['days']
                    / $totalPeriodDays
                );

                $overage += $billing->calculateOverage(
                    $usage,
                    $includedUnits,
                    (int) $plan->overage_rate_cents
                );
            }

            $total = $baseCharge + $overage;

            $start = $periodStart->toDateString();
            $end = $periodEnd->toDateString();

            DB::table('invoices')->updateOrInsert(
                [
                    'subscription_id' => $subscriptionId,
                    'period_start' => $start,
                    'period_end' => $end,
                ],
                [
                    'merchant_id' => $subscription->merchant_id,
                    'customer_id' => $subscription->customer_id,
                    // 'base_amount_cents' => $nominalBasePrice,
                    'base_amount_cents' => $baseCharge,
                    'overage_amount_cents' => $overage,
                    'proration_amount_cents' => $baseCharge,
                    'total_amount_cents' => $total,
                    'status' => 'draft',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return DB::table('invoices')
                ->where('subscription_id', $subscriptionId)
                ->where('period_start', $start)
                ->where('period_end', $end)
                ->first();
        });
    }
}
