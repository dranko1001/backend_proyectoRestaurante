<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PlatformBillingSetting extends Model
{
    public const PACKAGE_MONTHS = [1, 3, 6, 12];

    /** @var array<int, int> */
    public const DEFAULT_PACKAGE_PRICES = [
        1 => 50000,
        3 => 140000,
        6 => 270000,
        12 => 500000,
    ];

    protected $connection = 'master';

    protected $fillable = [
        'nequi_key',
        'nequi_qr_path',
        'payment_instructions',
        'price_per_month_cop',
        'price_1_month_cop',
        'price_3_months_cop',
        'price_6_months_cop',
        'price_12_months_cop',
    ];

    protected function casts(): array
    {
        return [
            'price_per_month_cop' => 'integer',
            'price_1_month_cop' => 'integer',
            'price_3_months_cop' => 'integer',
            'price_6_months_cop' => 'integer',
            'price_12_months_cop' => 'integer',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();

        if ($row === null) {
            $row = static::query()->create([
                'price_per_month_cop' => self::DEFAULT_PACKAGE_PRICES[1],
                'price_1_month_cop' => self::DEFAULT_PACKAGE_PRICES[1],
                'price_3_months_cop' => self::DEFAULT_PACKAGE_PRICES[3],
                'price_6_months_cop' => self::DEFAULT_PACKAGE_PRICES[6],
                'price_12_months_cop' => self::DEFAULT_PACKAGE_PRICES[12],
            ]);
        }

        return $row;
    }

    /**
     * @return array<int, int>
     */
    public function packagePrices(): array
    {
        $fallbackOne = $this->price_1_month_cop
            ?? $this->price_per_month_cop
            ?? self::DEFAULT_PACKAGE_PRICES[1];

        return [
            1 => $fallbackOne,
            3 => $this->price_3_months_cop ?? self::DEFAULT_PACKAGE_PRICES[3],
            6 => $this->price_6_months_cop ?? self::DEFAULT_PACKAGE_PRICES[6],
            12 => $this->price_12_months_cop ?? self::DEFAULT_PACKAGE_PRICES[12],
        ];
    }

    public function priceForMonths(int $months): int
    {
        $packages = $this->packagePrices();

        if (! array_key_exists($months, $packages)) {
            throw new InvalidArgumentException("Paquete de {$months} mes(es) no configurado.");
        }

        return $packages[$months];
    }

    /**
     * @param  array<int, int|null>  $prices
     */
    public function syncPackagePrices(array $prices): void
    {
        $map = [
            1 => 'price_1_month_cop',
            3 => 'price_3_months_cop',
            6 => 'price_6_months_cop',
            12 => 'price_12_months_cop',
        ];

        foreach ($map as $months => $column) {
            if (! array_key_exists($months, $prices)) {
                continue;
            }

            $value = $prices[$months];
            $this->{$column} = $value ?? self::DEFAULT_PACKAGE_PRICES[$months];
        }

        $this->price_per_month_cop = $this->price_1_month_cop ?? self::DEFAULT_PACKAGE_PRICES[1];
    }
}
