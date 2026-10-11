<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class SkuField implements FieldType
{
    /** @return list<array{title: string, code: string, stock: int, price: int|float}> */
    public function normalize(mixed $value): array
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return [['title' => '', 'code' => strtoupper(trim((string) $value)), 'stock' => 0, 'price' => 0]];
        }
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($title === '' && $code === '' && trim((string) ($row['stock'] ?? '')) === '' && trim((string) ($row['price'] ?? '')) === '') {
                continue;
            }
            $price = (float) ($row['price'] ?? 0);
            $rows[] = [
                'title' => $title,
                'code' => $code,
                'stock' => (int) ($row['stock'] ?? 0),
                'price' => floor($price) === $price ? (int) $price : $price,
            ];
        }

        return $rows;
    }

    public function valid(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        $codes = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                return false;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $stock = $row['stock'] ?? null;
            $price = $row['price'] ?? null;
            if ($title === '' || $code === '' || preg_match('/^[A-Z0-9][A-Z0-9._-]{0,99}$/D', $code) !== 1) {
                return false;
            }
            if (filter_var($stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false || !is_numeric($price) || (float) $price < 0) {
                return false;
            }
            if (isset($codes[$code])) {
                return false;
            }
            $codes[$code] = true;
        }

        return true;
    }

    public function adminView(): string
    {
        return 'fields/sku.jst';
    }

    public function adminData(): array
    {
        return [
            'pattern' => '[A-Za-z0-9][A-Za-z0-9._-]{0,99}',
            'placeholder' => 'JST-PRODUCT-001',
        ];
    }

    public function adminValue(mixed $value): mixed
    {
        return $this->normalize($value);
    }
}
