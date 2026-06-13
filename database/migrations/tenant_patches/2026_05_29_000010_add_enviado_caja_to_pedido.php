<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedido')) {
            return;
        }

        Schema::table('pedido', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido', 'enviado_caja_en')) {
                $table->dateTime('enviado_caja_en')->nullable()->after('cerrado_en');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pedido')) {
            return;
        }

        Schema::table('pedido', function (Blueprint $table) {
            if (Schema::hasColumn('pedido', 'enviado_caja_en')) {
                $table->dropColumn('enviado_caja_en');
            }
        });
    }
};
