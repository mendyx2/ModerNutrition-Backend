<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('member_number')->unique()->comment('Human-readable MN-XXXXXX code');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('phone_country_code', 10)->nullable();

            // Geographic / operational
            $table->string('country', 3)->comment('ISO 3166-1 alpha-3');
            $table->string('currency', 3)->comment('ISO 4217');
            $table->string('city')->nullable();
            $table->string('address')->nullable();

            // Binary tree structure
            $table->foreignId('sponsor_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('leg', ['left', 'right'])->nullable()->comment('Which leg of parent this member sits in');

            // Rank (denormalised read for performance; source of truth is ranks table)
            $table->foreignId('current_rank_id')->nullable()->constrained('ranks')->nullOnDelete();

            // Account status
            $table->enum('status', ['pending', 'active', 'suspended', 'terminated'])->default('pending');

            // Profile
            $table->string('avatar_path')->nullable();
            $table->text('bio')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('national_id')->nullable();

            // Tokens
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('country');
            $table->index('status');
            $table->index('sponsor_id');
            $table->index(['parent_id', 'leg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
