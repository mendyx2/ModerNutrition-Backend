<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique()->comment('Human-readable ORD-XXXXXXXX');
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();

            // Geographic + currency context (captured at order time — immutable)
            $table->string('country', 3)->comment('ISO 3166-1 alpha-3 of member at order time');
            $table->string('currency', 3)->comment('ISO 4217');
            $table->decimal('fx_rate_to_usd', 20, 8)->default(1.00000000)
                ->comment('Exchange rate at order time for reporting');

            // Totals (in currency above, smallest unit for amounts)
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->decimal('total_pv', 12, 4)->default(0);
            $table->decimal('total_cv', 12, 4)->default(0);

            // Status lifecycle: pending -> paid -> processing -> shipped -> delivered -> cancelled | refunded
            $table->enum('status', [
                'pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'
            ])->default('pending');

            // CV is only generated on paid — track when it was allocated
            $table->timestamp('cv_allocated_at')->nullable();

            // Payment
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Shipping
            $table->text('shipping_address')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Admin
            $table->foreignId('processed_by')->nullable()->constrained('members')->nullOnDelete()
                ->comment('Admin who last changed status');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('member_id');
            $table->index('status');
            $table->index('country');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
