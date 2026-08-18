<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ledger_entries is APPEND-ONLY at the application layer.
     * No UPDATE path exists for existing rows.
     * Reversals are new rows with reversal_reference pointing back to the original entry.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            // What triggered this entry
            $table->string('entry_type')->comment('e.g. cv_allocation, reversal, withdrawal, adjustment');
            $table->string('reference_type')->nullable()->comment('Polymorphic: e.g. App\\Models\\Order');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID of the triggering entity');

            // Attribution
            $table->foreignId('member_id')->nullable()->constrained('members')->restrictOnDelete()
                ->comment('Beneficiary member; NULL for pooled/company entries');
            $table->foreignId('allocation_category_id')->nullable()
                ->constrained('allocation_categories')->restrictOnDelete();

            // CRITICAL: permanently store the plan version active at time of this entry
            $table->foreignId('plan_version_id')->constrained('plan_versions')->restrictOnDelete()
                ->comment('Plan version active when this entry was created — NEVER changes retroactively');

            // Financial values — immutable after insert
            $table->string('currency', 3)->comment('ISO 4217');
            $table->string('country', 3)->comment('ISO 3166-1 alpha-3');
            $table->decimal('amount', 20, 8)->comment('Signed: positive = credit, negative = debit');
            $table->decimal('cv_basis', 12, 4)->nullable()
                ->comment('CV from the triggering order that this entry was calculated from');
            $table->decimal('percentage_applied', 8, 4)->nullable()
                ->comment('The allocation_category.percentage used at time of calculation');

            // Reversal tracking — append-only reversal pattern
            $table->unsignedBigInteger('reversal_reference')->nullable()
                ->comment('If this is a reversal row, points to the original ledger_entry.id being reversed');
            $table->boolean('is_reversal')->default(false);
            $table->text('reversal_reason')->nullable();

            // Balance snapshot (running total per member+category at time of insert — for fast wallet reads)
            $table->decimal('running_balance', 20, 8)->nullable()
                ->comment('Balance after this entry; denormalised for wallet read-model');

            // Description / notes
            $table->text('description')->nullable();

            // Immutable audit timestamps — never updated
            $table->timestamp('created_at')->useCurrent();
            // Deliberately no updated_at — this table is append-only

            $table->index(['member_id', 'allocation_category_id']);
            $table->index('reference_type');
            $table->index('reference_id');
            $table->index('plan_version_id');
            $table->index('entry_type');
            $table->index('created_at');
            $table->index('reversal_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
