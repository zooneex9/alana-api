<?php

namespace App\Support;

class DressTaxonomy
{
    public const LENGTHS = ['corto', 'midi', 'largo'];

    /**
     * @return array<int, string>
     */
    public static function occasionRules(): array
    {
        return ['nullable', 'array', 'max:20'];
    }

    /**
     * @return array<int, string>
     */
    public static function occasionItemRules(): array
    {
        return ['string', 'max:100', 'exists:dress_occasions,slug'];
    }
}
