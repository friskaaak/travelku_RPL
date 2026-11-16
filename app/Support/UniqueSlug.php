<?php

namespace App\Support;

use Illuminate\Support\Str;

class UniqueSlug
{
    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
    public static function make(string $name, string $modelClass): string
    {
        $base = Str::limit(Str::slug($name), 80, '') ?: 'item';
        $slug = $base;
        $i = 2;

        while ($modelClass::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}