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
            if (! Schema::hasColumn('venta', 'numero_factura')) {
                $table->string('numero_factura', 30)->nullable()->unique()->after('idVenta');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table) {
            if (Schema::hasColumn('venta', 'numero_factura')) {
                $table->dropUnique(['numero_factura']);
                $table->dropColumn('numero_factura');
            }
        });
    }
};
