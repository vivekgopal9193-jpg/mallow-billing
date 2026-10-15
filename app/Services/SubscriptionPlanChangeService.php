<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SubscriptionPlanChangeService
{
    public function changePlan(
        int $subscriptionId,
        int $newPlanId,
        string $effectiveAt
    ): object {
        return DB::transaction(function () use (
            $subscriptionId,
            $newPlanId,
            $effectiveAt
        ) {
            $subscription = DB::table('subscriptions')
                ->where('id', $subscriptionId)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                throw new RuntimeException('Subscription not found.');
            }

            if ($subscription->status !== 'active') {
                throw new InvalidArgumentException(
                    'Only active subscriptions can change plans.'
                );
            }

            $newPlan = DB::table('plans')
                ->where('id', $newPlanId)
                ->where('merchant_id', function ($query) use ($subscription) {
                    $query->select('merchant_id')
                        ->from('customers')
                        ->where('id', $subscription->customer_id);
                })
                ->where('status', 'active')
                ->first();

            if (! $newPlan) {
                throw new InvalidArgumentException(
                    'The new plan is invalid or belongs to another merchant.'
                );
            }

            $changeTime = \Illuminate\Support\Carbon::parse($effectiveAt);

            $periodStart = \Illuminate\Support\Carbon::parse(
                $subscription->current_period_start
            );

            $periodEnd = \Illuminate\Support\Carbon::parse(
                $subscription->current_period_end
            );

            if ($changeTime->lt($periodStart) || $changeTime->gte($periodEnd)) {
                throw new InvalidArgumentException(
                    'The change must occur within the current billing period.'
                );
            }

            if ((int) $subscription->plan_id === $newPlanId) {
                throw new InvalidArgumentException(
                    'The subscription already uses this plan.'
                );
            }

            $changeId = DB::table('subscription_plan_changes')->insertGetId([
                'subscription_id' => $subscriptionId,
                'from_plan_id' => $subscription->plan_id,
                'to_plan_id' => $newPlanId,
                'effective_at' => $changeTime,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('subscriptions')
                ->where('id', $subscriptionId)
                ->update([
                    'plan_id' => $newPlanId,
                    'updated_at' => now(),
                ]);

            return DB::table('subscription_plan_changes')
                ->where('id', $changeId)
                ->first();
        });
    }
}
