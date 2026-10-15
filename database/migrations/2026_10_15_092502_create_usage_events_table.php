
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
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

            $table->string('idempotency_key');
            $table->string('event_type')->default('usage');
            $table->unsignedBigInteger('quantity')->default(1);
            $table->date('occurred_on');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key']);
            $table->index(['subscription_id', 'occurred_on']);
            $table->index(['merchant_id', 'occurred_on', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
