<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('tree_path', 1000)->nullable()->after('leg');
            $table->unsignedInteger('tree_depth')->default(0)->after('tree_path');

            $table->index('tree_path');
            $table->index('tree_depth');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['tree_path']);
            $table->dropIndex(['tree_depth']);
            $table->dropColumn(['tree_path', 'tree_depth']);
        });
    }
};
