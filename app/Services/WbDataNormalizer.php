<?php

namespace App\Services;

use Carbon\Carbon;

class WbDataNormalizer
{
    private const DATE_FIELDS = ['date', 'last_change_date', 'cancel_dt', 'date_close'];

    private const BOOL_FIELDS = ['is_cancel', 'is_supply', 'is_realization'];

    private const STRING_FIELDS = [
        'barcode', 'g_number', 'sale_id', 'srid', 'sticker', 'number', 'sc_code',
        'supplier_article', 'tech_size', 'warehouse_name',
    ];

    /** Поля, которые API иногда отдаёт как null */
    private const NULLABLE_INT_DEFAULTS = [
        'is_storno' => 0,
    ];

    public static function fromApiRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $snake = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst((string) $key)) ?? (string) $key);
            $normalized[$snake] = $value;
        }

        return self::castValues($normalized);
    }

    private static function castValues(array $data): array
    {
        foreach (self::DATE_FIELDS as $field) {
            if (empty($data[$field])) {
                continue;
            }

            try {
                $parsed = Carbon::parse($data[$field]);
                $data[$field] = strlen((string) $data[$field]) <= 10
                    ? $parsed->toDateString()
                    : $parsed->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                // keep original
            }
        }

        foreach (self::BOOL_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (self::STRING_FIELDS as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = (string) $data[$field];
            }
        }

        foreach (self::NULLABLE_INT_DEFAULTS as $field => $default) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $data[$field] = $default;
            } else {
                $data[$field] = (int) $data[$field];
            }
        }

        return $data;
    }
}
