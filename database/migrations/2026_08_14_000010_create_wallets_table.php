<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wallets is a READ MODEL — a materialised summary derived from ledger_entries.
     * Updated by the allocation job and withdrawal processing.
     * The authoritative numbers always come from summing ledger_entries.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('allocation_category_id')->nullable()
                ->constrained('allocation_categories')->nullOnDelete()
                ->comment('NULL for the master wallet / total balance');

            $table->string('wallet_bucket')->comment('Matches allocation_categories.wallet_bucket slug');
            $table->string('currency', 3)->comment('ISO 4217');
            $table->string('country', 3)->comment('ISO 3166-1 alpha-3');

            // Balances — materialised sums; always reconcilable against ledger_entries
            $table->decimal('total_earned', 20, 8)->default(0);
            $table->decimal('total_withdrawn', 20, 8)->default(0);
            $table->decimal('total_reversed', 20, 8)->default(0);
            $table->decimal('available_balance', 20, 8)->default(0)
                ->comment('total_earned - total_withdrawn - total_reversed');
            $table->decimal('pending_withdrawal', 20, 8)->default(0)
                ->comment('Amount in pending withdrawal requests not yet approved');

            $table->timestamp('last_ledger_entry_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'wallet_bucket', 'currency']);
            $table->index('member_id');
            $table->index('wallet_bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
