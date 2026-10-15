<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlanPricingService
{
    public function getPlan(int $planId): ?object
    {
        return Cache::remember(
            "plan_pricing_{$planId}",
            now()->addMinutes(30),
            function () use ($planId) {
                return DB::table('plans')
                    ->where('id', $planId)
                    ->where('status', 'active')
                    ->first();
            }
        );
    }

    public function forgetPlan(int $planId): void
    {
        Cache::forget("plan_pricing_{$planId}");
    }

        
    public function updatePlanPricing(
        int $planId,
        int $basePriceCents,
        int $includedUnits,
        int $overageRateCents
    ): bool {
        $updated = DB::table('plans')
            ->where('id', $planId)
            ->update([
                'base_price_cents' => $basePriceCents,
                'included_units' => $includedUnits,
                'overage_rate_cents' => $overageRateCents,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->forgetPlan($planId);
            return true;
        }

        return false;
    }


}
