<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->enum('kyc_status', ['unsubmitted', 'pending', 'verified', 'rejected'])
                ->default('unsubmitted')
                ->after('status');
            $table->string('kyc_document_type')->nullable()->after('national_id');
            $table->longText('kyc_document_path')->nullable()->after('kyc_document_type');
            $table->string('kyc_rejection_reason')->nullable()->after('kyc_document_path');
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_rejection_reason');

            $table->index('kyc_status');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['kyc_status']);
            $table->dropColumn([
                'kyc_status',
                'kyc_document_type',
                'kyc_document_path',
                'kyc_rejection_reason',
                'kyc_verified_at',
            ]);
        });
    }
};
