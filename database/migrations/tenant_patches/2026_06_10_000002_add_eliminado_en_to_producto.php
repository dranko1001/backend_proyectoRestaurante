<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producto')) {
            return;
        }

        Schema::table('producto', function (Blueprint $table) {
            if (! Schema::hasColumn('producto', 'eliminado_en')) {
                $table->dateTime('eliminado_en')->nullable()->after('activo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('producto')) {
            return;
        }

        Schema::table('producto', function (Blueprint $table) {
            if (Schema::hasColumn('producto', 'eliminado_en')) {
                $table->dropColumn('eliminado_en');
            }
        });
    }
};
