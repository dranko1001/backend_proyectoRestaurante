<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cocina_llamada_mesero')) {
            return;
        }

        Schema::table('cocina_llamada_mesero', function (Blueprint $table) {
            if (! Schema::hasColumn('cocina_llamada_mesero', 'cajero_idUsuario')) {
                $table->integer('cajero_idUsuario')->nullable()->after('cocinero_idUsuario');
                $table->foreign('cajero_idUsuario')->references('idUsuario')->on('usuario');
            }
        });

        DB::statement('ALTER TABLE cocina_llamada_mesero MODIFY cocinero_idUsuario int(11) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('cocina_llamada_mesero')) {
            return;
        }

        Schema::table('cocina_llamada_mesero', function (Blueprint $table) {
            if (Schema::hasColumn('cocina_llamada_mesero', 'cajero_idUsuario')) {
                $table->dropForeign(['cajero_idUsuario']);
                $table->dropColumn('cajero_idUsuario');
            }
        });
    }
};
