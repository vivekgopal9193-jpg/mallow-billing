<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Jobs\AggregateUsageJob;

class UsageController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|integer|exists:merchants,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'subscription_id' => 'required|integer|exists:subscriptions,id',
            'idempotency_key' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'occurred_on' => 'required|date_format:Y-m-d',
            'event_type' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Verify that the customer belongs to this merchant
        // and the subscription belongs to that customer.
        $validSubscription = DB::table('subscriptions')
            ->join(
                'customers',
                'subscriptions.customer_id',
                '=',
                'customers.id'
            )
            ->where('subscriptions.id', $data['subscription_id'])
            ->where('subscriptions.customer_id', $data['customer_id'])
            ->where('customers.merchant_id', $data['merchant_id'])
            ->where('subscriptions.status', 'active')
            ->exists();

        if (! $validSubscription) {
            return response()->json([
                'message' => 'Customer, merchant, or active subscription mismatch.',
            ], 422);
        }

        $now = now();

        // The database unique constraint protects against duplicate events.
        $inserted = DB::table('usage_events')->insertOrIgnore([
            'merchant_id' => $data['merchant_id'],
            'customer_id' => $data['customer_id'],
            'subscription_id' => $data['subscription_id'],
            'idempotency_key' => $data['idempotency_key'],
            'event_type' => $data['event_type'] ?? 'usage',
            'quantity' => $data['quantity'],
            'occurred_on' => $data['occurred_on'],
            'metadata' => isset($data['metadata'])
                ? json_encode($data['metadata'])
                : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $event = DB::table('usage_events')
            ->where('merchant_id', $data['merchant_id'])
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if (! $event) {
            return response()->json([
                'message' => 'Unable to record usage.',
            ], 500);
        }

        if ($inserted) {
            AggregateUsageJob::dispatch();
        }

        return response()->json([
            'message' => $inserted
                ? 'Usage recorded successfully.'
                : 'Duplicate event; existing usage was returned.',
            'duplicate' => $inserted === 0,
            'data' => $event,
        ], $inserted ? 201 : 200);
    }
}
