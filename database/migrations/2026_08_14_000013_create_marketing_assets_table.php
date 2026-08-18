<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_assets', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('category')->comment('e.g. banner, flyer, social_post, video, brochure');
            $table->string('asset_type')->comment('e.g. image, video, pdf, document');

            // Product association (optional — an asset can be general or product-specific)
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete()
                ->comment('Target product, if asset is product-specific');

            // Physical / digital dimensions
            $table->string('dimensions')->nullable()->comment('e.g. 1920x1080, A4, 1080x1080');
            $table->string('format')->nullable()->comment('e.g. JPG, PNG, MP4, PDF');
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('duration_seconds')->nullable()->comment('For video assets');

            // File location
            $table->string('file_path')->comment('Storage path (relative to disk root)');
            $table->string('file_disk')->default('local')->comment('Laravel filesystem disk name');
            $table->string('thumbnail_path')->nullable();

            // Copywriting / text content
            $table->text('headline')->nullable();
            $table->text('body_copy')->nullable();
            $table->text('cta_text')->nullable()->comment('Call-to-action text');
            $table->text('disclaimer')->nullable();
            $table->string('language', 10)->default('en')->comment('BCP 47 language tag');

            // Availability
            $table->json('target_countries')->nullable()->comment('ISO 3166-1 alpha-3 array; NULL = global');
            $table->json('target_roles')->nullable()->comment('Spatie role names this asset is visible to');

            // Status
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');

            // Admin
            $table->foreignId('created_by')->constrained('members')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('asset_type');
            $table->index('status');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_assets');
    }
};
