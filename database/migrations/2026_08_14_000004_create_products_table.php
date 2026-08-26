<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->longText('image_path')->nullable();

            // Financials — stored per-unit
            $table->string('currency', 3)->comment('ISO 4217 base currency');
            $table->unsignedBigInteger('price_cents')->comment('Retail price in smallest currency unit');
            $table->decimal('pv', 10, 4)->comment('Point Value per unit');
            $table->decimal('cv', 10, 4)->comment('Commission Value per unit — allocation engine draws from this');

            // Country availability (JSON array of ISO 3166-1 alpha-3 codes; NULL = global)
            $table->json('available_countries')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');

            // Physical attributes
            $table->string('weight_grams')->nullable();
            $table->string('dimensions')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
