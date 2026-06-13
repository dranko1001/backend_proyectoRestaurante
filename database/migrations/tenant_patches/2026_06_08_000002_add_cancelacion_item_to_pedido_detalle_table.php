<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pedido_detalle', 'motivo_cancelacion')) {
            Schema::table('pedido_detalle', function (Blueprint $table) {
                $table->string('motivo_cancelacion', 500)->nullable()->after('estado_item');
            });
        }

        if (! Schema::hasColumn('pedido_detalle', 'cancelado_en')) {
            Schema::table('pedido_detalle', function (Blueprint $table) {
                $table->dateTime('cancelado_en')->nullable()->after('motivo_cancelacion');
            });
        }

        if (! Schema::hasColumn('pedido_detalle', 'cancelado_por_idUsuario')) {
            Schema::table('pedido_detalle', function (Blueprint $table) {
                $table->integer('cancelado_por_idUsuario')->nullable()->after('cancelado_en');
                $table->foreign('cancelado_por_idUsuario')->references('idUsuario')->on('usuario');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pedido_detalle', 'cancelado_por_idUsuario')) {
            Schema::table('pedido_detalle', function (Blueprint $table) {
                $table->dropForeign(['cancelado_por_idUsuario']);
                $table->dropColumn('cancelado_por_idUsuario');
            });
        }

        Schema::table('pedido_detalle', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('pedido_detalle', 'cancelado_en')) {
                $cols[] = 'cancelado_en';
            }
            if (Schema::hasColumn('pedido_detalle', 'motivo_cancelacion')) {
                $cols[] = 'motivo_cancelacion';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
