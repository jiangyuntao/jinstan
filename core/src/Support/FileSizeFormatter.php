<?php

declare(strict_types=1);

namespace Jinstan\Core\Support;

final class FileSizeFormatter
{
    public function format(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            ++$unit;
        }

        if ($unit === 0) {
            return sprintf('%d B', $bytes);
        }

        $rounded = round($value, 1);
        $number = $rounded === floor($rounded)
            ? number_format($rounded, 0)
            : number_format($rounded, 1);

        return $number . ' ' . $units[$unit];
    }
}
