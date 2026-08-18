<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Snapshot all financials at order time — products can change later
            $table->string('product_sku');
            $table->string('product_name');
            $table->string('currency', 3);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_cents')->comment('Price per unit at order time');
            $table->unsignedBigInteger('line_total_cents')->comment('unit_price * quantity');
            $table->decimal('unit_pv', 10, 4);
            $table->decimal('unit_cv', 10, 4);
            $table->decimal('line_pv', 10, 4)->comment('unit_pv * quantity');
            $table->decimal('line_cv', 10, 4)->comment('unit_cv * quantity');

            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
