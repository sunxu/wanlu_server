<?php

declare(strict_types=1);

namespace App\Library\Excel;

class Excel
{
    protected function getHeadFirst(int $num)
    {
        $str = '';

        while ($num > 0) {
            $offset = $num % 26;
            $offset = $offset == 0 ? 26 : $offset;
            $str = chr($offset - 1 + ord('A')) . $str;
            $num = ($num - $offset) / 26;
        }

        return $str;
    }
}