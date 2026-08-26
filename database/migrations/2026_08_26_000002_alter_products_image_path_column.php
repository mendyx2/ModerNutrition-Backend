<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Support PostgreSQL & SQLite ALTER COLUMN to TEXT
        try {
            DB::statement('ALTER TABLE products ALTER COLUMN image_path TYPE TEXT;');
        } catch (\Throwable $e) {
            // Fallback for other database drivers if applicable
            Schema::table('products', function (Blueprint $table) {
                $table->longText('image_path')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE products ALTER COLUMN image_path TYPE VARCHAR(255);');
        } catch (\Throwable $e) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('image_path', 255)->nullable()->change();
            });
        }
    }
};
