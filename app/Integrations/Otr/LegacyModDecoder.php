<?php

namespace App\Integrations\Otr;

class LegacyModDecoder
{
    /**
     * @var array<int, string>
     */
    private const FLAGS = [
        1 => 'NF',
        2 => 'EZ',
        8 => 'HD',
        16 => 'HR',
        32 => 'SD',
        64 => 'DT',
        128 => 'RX',
        256 => 'HT',
        512 => 'NC',
        1024 => 'FL',
        2048 => 'AT',
        4096 => 'SO',
        8192 => 'AP',
        16384 => 'PF',
        32768 => '4K',
        65536 => '5K',
        131072 => '6K',
        262144 => '7K',
        524288 => '8K',
        1048576 => 'FI',
        2097152 => 'RD',
        4194304 => 'CN',
        8388608 => 'TP',
        16777216 => '9K',
        33554432 => 'CO',
        67108864 => '1K',
        134217728 => '3K',
        268435456 => '2K',
        536870912 => 'V2',
        1073741824 => 'MR',
    ];

    /**
     * @return list<string>
     */
    public function decode(int $mods): array
    {
        $decoded = collect(self::FLAGS)
            ->filter(fn (string $acronym, int $flag): bool => ($mods & $flag) === $flag)
            ->values()
            ->all();

        if (in_array('NC', $decoded, true)) {
            $decoded = array_values(array_diff($decoded, ['DT']));
        }

        if (in_array('PF', $decoded, true)) {
            $decoded = array_values(array_diff($decoded, ['SD']));
        }

        return $decoded;
    }
}
