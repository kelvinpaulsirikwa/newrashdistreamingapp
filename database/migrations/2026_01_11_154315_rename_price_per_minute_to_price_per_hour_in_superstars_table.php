<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('superstars', function (Blueprint $table) {
            $table->renameColumn('price_per_minute', 'price_per_hour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('superstars', function (Blueprint $table) {
            $table->renameColumn('price_per_hour', 'price_per_minute');
        });
    }
};
