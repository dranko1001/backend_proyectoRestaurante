<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table) {
            if (! Schema::hasColumn('venta', 'recibido')) {
                $table->decimal('recibido', 10, 2)->nullable()->after('total');
            }
            if (! Schema::hasColumn('venta', 'cambio')) {
                $table->decimal('cambio', 10, 2)->nullable()->after('recibido');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table) {
            foreach (['cambio', 'recibido'] as $col) {
                if (Schema::hasColumn('venta', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
