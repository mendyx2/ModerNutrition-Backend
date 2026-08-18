<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();

            // Who performed the action
            $table->foreignId('actor_id')->nullable()->constrained('members')->nullOnDelete()
                ->comment('Member/admin who performed the action; NULL for system events');
            $table->string('actor_email')->nullable()->comment('Denormalised — preserved even if member deleted');
            $table->string('actor_ip', 45)->nullable();
            $table->string('actor_user_agent')->nullable();

            // What was changed
            $table->string('event')->comment('e.g. plan_version.activated, order.status_changed, member.suspended');
            $table->string('auditable_type')->nullable()->comment('Model class, e.g. App\\Models\\PlanVersion');
            $table->unsignedBigInteger('auditable_id')->nullable();

            // Before / after state
            $table->json('old_values')->nullable()->comment('State before the change');
            $table->json('new_values')->nullable()->comment('State after the change');

            // Extra context
            $table->json('metadata')->nullable()->comment('Any additional structured context');
            $table->text('description')->nullable()->comment('Human-readable summary of the change');

            // Geographic context
            $table->string('country', 3)->nullable();

            // Immutable timestamp — no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_id');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
