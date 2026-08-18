<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('withdrawal_number')->unique()->comment('Human-readable WD-XXXXXXXX');
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->string('wallet_bucket')->comment('Which wallet bucket funds are drawn from');

            $table->string('currency', 3);
            $table->string('country', 3);
            $table->decimal('amount', 20, 8);

            // Maker-checker enforcement columns
            // requester_id is the "maker" — they initiate the request
            $table->foreignId('requester_id')->constrained('members')->restrictOnDelete()
                ->comment('Maker: the admin who submitted this withdrawal for approval');
            // approver_id MUST differ from requester_id — enforced at application layer
            $table->foreignId('approver_id')->nullable()->constrained('members')->nullOnDelete()
                ->comment('Checker: the admin who approves/rejects — MUST NOT equal requester_id');

            $table->enum('status', ['pending', 'approved', 'rejected', 'processed', 'failed'])
                ->default('pending');

            $table->text('rejection_reason')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            // Payment details
            $table->string('payment_method')->nullable();
            $table->json('payment_details')->nullable()->comment('Bank/mobile money details — encrypted at rest');
            $table->string('payment_reference')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('member_id');
            $table->index('status');
            $table->index('requester_id');
            $table->index('approver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
