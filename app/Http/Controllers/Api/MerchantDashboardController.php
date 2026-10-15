<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MerchantDashboardController extends Controller
{
    public function show(Request $request, int $merchantId)
    {
        $merchant = DB::table('merchants')
            ->where('id', $merchantId)
            ->first();

        if (! $merchant) {
            return response()->json([
                'message' => 'Merchant not found.',
            ], 404);
        }

        $monthStart = Carbon::now()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();
        $previousMonthStart = $monthStart->copy()->subMonth();

        // Top five customers by usage this month.
        $topCustomers = DB::table('usage_events')
            ->join('customers', 'customers.id', '=', 'usage_events.customer_id')
            ->where('usage_events.merchant_id', $merchantId)
            ->where('usage_events.occurred_on', '>=', $monthStart->toDateString())
            ->where('usage_events.occurred_on', '<', $nextMonthStart->toDateString())
            ->select(
                'customers.id as customer_id',
                'customers.name as customer_name',
                DB::raw('SUM(usage_events.quantity) as total_usage')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_usage')
            ->limit(5)
            ->get();

        // Projected overage revenue for the current month.
        $projectedOverageRevenue = 0;

        $subscriptions = DB::table('subscriptions')
            ->join('customers', 'customers.id', '=', 'subscriptions.customer_id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('customers.merchant_id', $merchantId)
            ->where('subscriptions.status', 'active')
            ->select(
                'subscriptions.id as subscription_id',
                'plans.included_units',
                'plans.overage_rate_cents'
            )
            ->get();

        foreach ($subscriptions as $subscription) {
            $usage = (int) DB::table('usage_events')
                ->where('subscription_id', $subscription->subscription_id)
                ->where('occurred_on', '>=', $monthStart->toDateString())
                ->where('occurred_on', '<', $nextMonthStart->toDateString())
                ->sum('quantity');

            $extraUnits = max(
                0,
                $usage - (int) $subscription->included_units
            );

            $projectedOverageRevenue +=
                $extraUnits * (int) $subscription->overage_rate_cents;
        }

        // Customers whose usage dropped by more than 50%.
        $currentUsage = DB::table('usage_events')
            ->where('merchant_id', $merchantId)
            ->where('occurred_on', '>=', $monthStart->toDateString())
            ->where('occurred_on', '<', $nextMonthStart->toDateString())
            ->select(
                'customer_id',
                DB::raw('SUM(quantity) as total_usage')
            )
            ->groupBy('customer_id')
            ->pluck('total_usage', 'customer_id');

        $previousUsage = DB::table('usage_events')
            ->where('merchant_id', $merchantId)
            ->where('occurred_on', '>=', $previousMonthStart->toDateString())
            ->where('occurred_on', '<', $monthStart->toDateString())
            ->select(
                'customer_id',
                DB::raw('SUM(quantity) as total_usage')
            )
            ->groupBy('customer_id')
            ->pluck('total_usage', 'customer_id');

        $usageDrops = [];

        foreach ($previousUsage as $customerId => $previousTotal) {
            $previousTotal = (int) $previousTotal;
            $currentTotal = (int) ($currentUsage[$customerId] ?? 0);

            if (
                $previousTotal > 0
                && $currentTotal < $previousTotal * 0.5
            ) {
                $customer = DB::table('customers')
                    ->where('id', $customerId)
                    ->first();

                $usageDrops[] = [
                    'customer_id' => (int) $customerId,
                    'customer_name' => $customer?->name,
                    'previous_month_usage' => $previousTotal,
                    'current_month_usage' => $currentTotal,
                    'drop_percentage' => round(
                        (($previousTotal - $currentTotal) / $previousTotal) * 100,
                        2
                    ),
                ];
            }
        }

        return response()->json([
            'merchant' => [
                'id' => $merchant->id,
                'name' => $merchant->name,
            ],
            'period' => [
                'start' => $monthStart->toDateString(),
                'end_exclusive' => $nextMonthStart->toDateString(),
            ],
            'top_5_customers_by_usage' => $topCustomers,
            'projected_overage_revenue_cents' => $projectedOverageRevenue,
            'customers_with_usage_drop_over_50_percent' => $usageDrops,
        ]);
    }
}
