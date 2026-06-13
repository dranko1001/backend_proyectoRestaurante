<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cocina_llamada_mesero')) {
            return;
        }

        Schema::create('cocina_llamada_mesero', function (Blueprint $table) {
            $table->id();
            $table->integer('cocinero_idUsuario');
            $table->dateTime('creado_en');
            $table->dateTime('atendida_en')->nullable();
            $table->integer('mesero_idUsuario')->nullable();

            $table->foreign('cocinero_idUsuario')->references('idUsuario')->on('usuario');
            $table->foreign('mesero_idUsuario')->references('idUsuario')->on('usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cocina_llamada_mesero');
    }
};
