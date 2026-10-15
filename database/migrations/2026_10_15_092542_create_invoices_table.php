
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('merchant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('subscription_id')
                ->constrained()
                ->restrictOnDelete();

            $table->date('period_start');
            $table->date('period_end');

            $table->unsignedBigInteger('base_amount_cents')->default(0);
            $table->unsignedBigInteger('overage_amount_cents')->default(0);
            $table->unsignedBigInteger('proration_amount_cents')->default(0);
            $table->unsignedBigInteger('total_amount_cents')->default(0);

            $table->string('status')->default('draft');
            $table->dateTime('issued_at')->nullable();
            $table->timestamps();

            $table->unique([
                'subscription_id',
                'period_start',
                'period_end',
            ]);

            $table->index(['merchant_id', 'period_start']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
