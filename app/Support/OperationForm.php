<?php

namespace App\Support;

final class OperationForm
{
    public static function old(string $key, mixed $fallback = null): mixed
    {
        $value = old($key, $fallback);

        return is_scalar($value) || $value === null ? $value : $fallback;
    }

    public static function rows(array $fallback): array
    {
        $rows = old('lines');
        if (! is_array($rows)) {
            return $fallback;
        }
        $safe = [];
        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];
            $ids = is_array($row['unit_ids'] ?? null) ? array_values(array_filter($row['unit_ids'], fn ($id) => is_scalar($id))) : [];
            $row = array_filter($row, fn ($value) => is_scalar($value) || $value === null);
            $row['unit_ids'] = $ids;
            $safe[] = $row;
        }

        return $safe ?: [[]];
    }
}
