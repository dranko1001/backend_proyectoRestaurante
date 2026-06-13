<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->table('platform_billing_settings', function (Blueprint $table) {
            $table->unsignedInteger('price_1_month_cop')->nullable()->after('price_per_month_cop');
            $table->unsignedInteger('price_3_months_cop')->nullable()->after('price_1_month_cop');
            $table->unsignedInteger('price_6_months_cop')->nullable()->after('price_3_months_cop');
            $table->unsignedInteger('price_12_months_cop')->nullable()->after('price_6_months_cop');
        });

        $defaults = [
            1 => 50000,
            3 => 140000,
            6 => 270000,
            12 => 500000,
        ];

        $rows = DB::connection('master')->table('platform_billing_settings')->get();

        foreach ($rows as $row) {
            $base = $row->price_per_month_cop ?? $defaults[1];

            DB::connection('master')->table('platform_billing_settings')->where('id', $row->id)->update([
                'price_1_month_cop' => $base,
                'price_3_months_cop' => $defaults[3],
                'price_6_months_cop' => $defaults[6],
                'price_12_months_cop' => $defaults[12],
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('master')->table('platform_billing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'price_1_month_cop',
                'price_3_months_cop',
                'price_6_months_cop',
                'price_12_months_cop',
            ]);
        });
    }
};
