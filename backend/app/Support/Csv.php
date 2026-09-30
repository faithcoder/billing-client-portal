<?php

namespace App\Support;

class Csv
{
    public static function cell(mixed $value): string
    {
        $s = (string) $value;
        if (preg_match('/^[\s]*[=+\-@]/u', $s) || preg_match('/^[\t\r\n]/', $s)) {
            $s = "'".$s;
        }

return $s;
    }
}
