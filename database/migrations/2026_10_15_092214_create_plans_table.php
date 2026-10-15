
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('merchant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedBigInteger('base_price_cents');
            $table->string('billing_cycle')->default('monthly');
            $table->unsignedBigInteger('included_units')->default(0);
            $table->unsignedBigInteger('overage_rate_cents')->default(0);
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
