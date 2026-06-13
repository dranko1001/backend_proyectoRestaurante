<?php

use App\Models\Cargo;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Cargo::query()->firstOrCreate(['nombre' => 'CAJERO']);
    }

    public function down(): void
    {
        Cargo::query()->where('nombre', 'CAJERO')->delete();
    }
};
