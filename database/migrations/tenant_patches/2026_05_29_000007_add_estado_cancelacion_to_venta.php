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
            if (! Schema::hasColumn('venta', 'estado')) {
                $table->string('estado', 20)->default('ACTIVA')->after('cajero_idUsuario');
            }
            if (! Schema::hasColumn('venta', 'motivo_cancelacion')) {
                $table->text('motivo_cancelacion')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('venta', 'cancelada_en')) {
                $table->dateTime('cancelada_en')->nullable()->after('motivo_cancelacion');
            }
            if (! Schema::hasColumn('venta', 'cancelada_por_idUsuario')) {
                $table->integer('cancelada_por_idUsuario')->nullable()->after('cancelada_en');
                $table->foreign('cancelada_por_idUsuario')->references('idUsuario')->on('usuario');
            }
            if (! Schema::hasColumn('venta', 'admin_visto')) {
                $table->boolean('admin_visto')->default(true)->after('cancelada_por_idUsuario');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table) {
            if (Schema::hasColumn('venta', 'cancelada_por_idUsuario')) {
                $table->dropForeign(['cancelada_por_idUsuario']);
                $table->dropColumn('cancelada_por_idUsuario');
            }
            foreach (['admin_visto', 'cancelada_en', 'motivo_cancelacion', 'estado'] as $col) {
                if (Schema::hasColumn('venta', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
