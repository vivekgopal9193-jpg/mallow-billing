<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AggregateUsageJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        DB::transaction(function () {
            DB::table('usage_daily_aggregates')->delete();

            DB::table('usage_events')
                ->select(
                    'merchant_id',
                    'customer_id',
                    'subscription_id',
                    'occurred_on',
                    DB::raw('SUM(quantity) as total_quantity')
                )
                ->groupBy(
                    'merchant_id',
                    'customer_id',
                    'subscription_id',
                    'occurred_on'
                )
                ->orderBy('subscription_id')
                ->orderBy('occurred_on')
                ->chunk(1000, function ($rows) {
                    $now = now();

                    $aggregates = [];

                    foreach ($rows as $row) {
                        $aggregates[] = [
                            'merchant_id' => $row->merchant_id,
                            'customer_id' => $row->customer_id,
                            'subscription_id' => $row->subscription_id,
                            'usage_date' => $row->occurred_on,
                            'total_quantity' => $row->total_quantity,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($aggregates !== []) {
                        DB::table('usage_daily_aggregates')->insert($aggregates);
                    }
                });
        });
    }
}
