<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('producto_estado_log')) {
            return;
        }

        Schema::create('producto_estado_log', function (Blueprint $table) {
            $table->id('idLog');
            $table->integer('producto_idProducto');
            $table->boolean('activo');
            $table->integer('usuario_idUsuario');
            $table->dateTime('creado_en');
            $table->dateTime('atendida_en')->nullable();
            $table->integer('mesero_atendio_idUsuario')->nullable();

            $table->foreign('producto_idProducto')->references('idProducto')->on('producto');
            $table->foreign('usuario_idUsuario')->references('idUsuario')->on('usuario');
            $table->foreign('mesero_atendio_idUsuario')->references('idUsuario')->on('usuario');
            $table->index(['producto_idProducto', 'creado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_estado_log');
    }
};
