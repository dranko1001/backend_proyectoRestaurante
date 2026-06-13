<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('producto', 'imagen')) {
            return;
        }

        Schema::table('producto', function (Blueprint $table) {
            $table->string('imagen', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn('imagen');
        });
    }
};
