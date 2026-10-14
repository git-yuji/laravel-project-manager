<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as BaseTrimStrings;

class TrimStrings extends BaseTrimStrings
{
    protected function transform(mixed $key, mixed $value): mixed
    {
        if (in_array($key, ['search', 'name'], true) && is_string($value) && str_contains($value, "\0")) {
            return $value;
        }

        return parent::transform($key, $value);
    }
}
