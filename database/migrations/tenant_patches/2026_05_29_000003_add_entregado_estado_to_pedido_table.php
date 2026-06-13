<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $column = DB::selectOne(
            "SELECT COLUMN_TYPE AS col FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedido' AND COLUMN_NAME = 'estado'"
        );

        if ($column && str_contains((string) $column->col, 'ENTREGADO')) {
            return;
        }

        DB::statement(
            "ALTER TABLE pedido MODIFY estado ENUM('PENDIENTE','EN_PREPARACION','LISTO','ENTREGADO','CERRADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE pedido MODIFY estado ENUM('PENDIENTE','EN_PREPARACION','LISTO','CERRADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE'"
        );
    }
};
