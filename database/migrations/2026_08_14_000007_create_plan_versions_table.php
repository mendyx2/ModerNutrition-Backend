<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('e.g. "DRC Plan v2 — Q3 2026"');
            $table->string('country', 3)->comment('ISO 3166-1 alpha-3 country this plan governs');
            $table->text('description')->nullable();

            // Status: draft -> approved -> active -> superseded
            $table->enum('status', ['draft', 'approved', 'active', 'superseded'])->default('draft');

            // Validity window — only one plan can be active per country at a time (enforced at app layer)
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            // Approval audit chain
            $table->foreignId('created_by')->constrained('members')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('plan_versions')->nullOnDelete()
                ->comment('The newer plan version that replaced this one');

            // Validation checkpoint — sum of allocation_categories percentages must equal this
            $table->decimal('required_allocation_total', 8, 4)->default(100.0000)
                ->comment('Percentage sum that allocation categories MUST equal before approval');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['country', 'status']);
            $table->index(['country', 'effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_versions');
    }
};
