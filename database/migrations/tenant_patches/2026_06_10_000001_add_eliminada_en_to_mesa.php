<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mesa')) {
            return;
        }

        Schema::table('mesa', function (Blueprint $table) {
            if (! Schema::hasColumn('mesa', 'eliminada_en')) {
                $table->dateTime('eliminada_en')->nullable()->after('activa');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mesa')) {
            return;
        }

        Schema::table('mesa', function (Blueprint $table) {
            if (Schema::hasColumn('mesa', 'eliminada_en')) {
                $table->dropColumn('eliminada_en');
            }
        });
    }
};
