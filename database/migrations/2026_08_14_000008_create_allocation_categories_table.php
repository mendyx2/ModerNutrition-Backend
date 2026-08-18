<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allocation categories define the 10 CV buckets from the plan image:
     *  MCV Company Allocation        30%
     *  CNRP Community Development    10%
     *  Country Expansion Reserve     12%
     *  Marketing & Market Dev         6%
     *  Platform & Technology          4%
     *  Member Purchase Reward         9%
     *  Distributor Performance        6%
     *  Leadership Development         9%
     *  Binary Team Bonus              8%
     *  Matching Bonus                 6%
     *  TOTAL                        100%
     */
    public function up(): void
    {
        Schema::create('allocation_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_version_id')->constrained('plan_versions')->cascadeOnDelete();

            $table->string('code')->comment('Machine key, e.g. mcv_company, cnrp_community');
            $table->string('name')->comment('Display name, e.g. MCV Company Allocation');
            $table->text('description')->nullable()->comment('Core purpose text from plan');

            // The allocation percentage of total CV for this category
            $table->decimal('percentage', 8, 4)->comment('E.g. 30.0000 for 30%');

            // Which wallet/ledger bucket this flows into
            $table->string('wallet_bucket')->comment('Slug identifying the wallet type, e.g. company_pool, member_reward');

            // Rule engine — points to a handler class that knows how to distribute this bucket
            $table->string('handler_class')->nullable()
                ->comment('FQCN of AllocationHandler implementing AllocationHandlerContract');

            // Some categories pay into specific member wallets; others are pooled
            $table->boolean('is_member_payable')->default(false)
                ->comment('True if this category results in member-level ledger entries');

            $table->boolean('is_pooled')->default(true)
                ->comment('True if the amount flows into a company/country pool rather than member wallets');

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_version_id', 'code']);
            $table->index('plan_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_categories');
    }
};
