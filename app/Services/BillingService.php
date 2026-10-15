<?php

namespace App\Services;

class BillingService
{
    public function calculateOverage(
        int $totalUsage,
        int $includedUnits,
        int $overageRateCents
    ): int {
        $extraUnits = max(0, $totalUsage - $includedUnits);

        return $extraUnits * $overageRateCents;
    }

    public function calculateTotal(
        int $basePriceCents,
        int $totalUsage,
        int $includedUnits,
        int $overageRateCents
    ): int {
        $overage = $this->calculateOverage(
            $totalUsage,
            $includedUnits,
            $overageRateCents
        );

        return $basePriceCents + $overage;
    }

            
    public function calculateProration(
            int $basePriceCents,
            int $activeDays,
            int $totalDays
    ): int {
            if ($totalDays <= 0) {
                throw new \InvalidArgumentException(
                    'Total days must be greater than zero.'
                );
            }

            $activeDays = max(0, min($activeDays, $totalDays));

            return (int) round(
                $basePriceCents * $activeDays / $totalDays
            );
    }



}
