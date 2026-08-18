<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rank_id')->constrained('ranks')->cascadeOnDelete();

            // Qualification thresholds
            $table->unsignedInteger('min_personal_pv')->default(0)
                ->comment('Minimum personal PV to hold this rank');
            $table->unsignedInteger('min_group_gv')->default(0)
                ->comment('Minimum group GV (left + right leg combined)');
            $table->unsignedInteger('min_left_leg_gv')->default(0);
            $table->unsignedInteger('min_right_leg_gv')->default(0);
            $table->unsignedTinyInteger('min_active_frontline')->default(0)
                ->comment('Minimum personally sponsored active members');
            $table->unsignedTinyInteger('min_qualified_legs')->default(0)
                ->comment('Min legs with qualified distributors');

            // Version / effective period
            $table->string('country', 3)->nullable()->comment('NULL = global default');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['rank_id', 'country', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualification_rules');
    }
};
