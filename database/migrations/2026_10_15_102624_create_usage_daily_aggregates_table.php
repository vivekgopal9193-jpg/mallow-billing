<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     
public function up(): void
{
    Schema::create('usage_daily_aggregates', function (Blueprint $table) {
        $table->id();

        $table->foreignId('merchant_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->foreignId('customer_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->foreignId('subscription_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->date('usage_date');
        $table->unsignedBigInteger('total_quantity')->default(0);
        $table->timestamps();

        $table->unique(
            ['subscription_id', 'usage_date'],
            'usage_daily_subscription_date_unique'
        );

        $table->index(
            ['merchant_id', 'usage_date', 'customer_id'],
            'usage_daily_merchant_date_customer_index'
        );
    });
}

};
