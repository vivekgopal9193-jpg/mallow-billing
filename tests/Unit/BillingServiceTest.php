<?php

namespace Tests\Unit;

use App\Services\BillingService;
use PHPUnit\Framework\TestCase;

class BillingServiceTest extends TestCase
{
    public function test_it_calculates_overage_correctly(): void
    {
        $service = new BillingService();

        $overage = $service->calculateOverage(
            totalUsage: 1200,
            includedUnits: 1000,
            overageRateCents: 10
        );

        $this->assertSame(2000, $overage);
    }

    public function test_it_does_not_charge_overage_below_allowance(): void
    {
        $service = new BillingService();

        $overage = $service->calculateOverage(
            totalUsage: 800,
            includedUnits: 1000,
            overageRateCents: 10
        );

        $this->assertSame(0, $overage);
    }

    public function test_it_calculates_total_invoice_amount(): void
    {
        $service = new BillingService();

        $total = $service->calculateTotal(
            basePriceCents: 99900,
            totalUsage: 1200,
            includedUnits: 1000,
            overageRateCents: 10
        );

        $this->assertSame(101900, $total);
    }
    
    public function test_it_calculates_proration_for_half_month(): void
    {
        $service = new BillingService();

        $proration = $service->calculateProration(
            basePriceCents: 99900,
            activeDays: 15,
            totalDays: 30
        );

        $this->assertSame(49950, $proration);
    }


}
