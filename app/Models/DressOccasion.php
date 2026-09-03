<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DressOccasion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public static function makeSlug(string $name): string
    {
        $slug = Str::slug($name, '_');

        return $slug !== '' ? $slug : 'ocasion';
    }
}
