<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                 // e.g. Consumer, Distributor, Leader, Country Operations, Global Administration
            $table->string('slug')->unique();                 // e.g. consumer, distributor
            $table->unsignedTinyInteger('level')->unique();   // ordinal 1..5
            $table->text('description')->nullable();
            $table->string('badge_icon_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
