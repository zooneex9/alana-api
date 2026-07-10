<?php

namespace App\Support;

class DressTaxonomy
{
    public const LENGTHS = ['corto', 'midi', 'largo'];

    public const OCCASIONS = [
        'anillo_civil',
        'night_out',
        'boda_playa',
        'boda',
        'viaje_playa',
        'posada',
    ];

    /**
     * @return array<int, string>
     */
    public static function occasionRules(): array
    {
        return ['nullable', 'array', 'max:'.count(self::OCCASIONS)];
    }

    /**
     * @return array<int, string>
     */
    public static function occasionItemRules(): array
    {
        return ['string', 'in:'.implode(',', self::OCCASIONS)];
    }
}
