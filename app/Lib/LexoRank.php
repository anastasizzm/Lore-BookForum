<?php
declare(strict_types=1);

namespace App\Lib;

final class LexoRank
{
    public const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';
    private const BASE = 36;

    public static function between(?string $a, ?string $b): string
    {
        $A = $a ?? '';
        $B = $b ?? '';
        $out = '';
        $i = 0;

        while (true) {
            $ca = $A[$i] ?? null;
            $cb = $B[$i] ?? null;
            $na = $ca === null ? 0 : (int)strpos(self::ALPHABET, $ca);
            $nb = $cb === null ? self::BASE - 1 : (int)strpos(self::ALPHABET, $cb);

            if ($nb - $na > 1) {
                return $out . self::ALPHABET[intdiv($na + $nb, 2)];
            }
            if ($nb - $na === 1) {
                $out .= self::ALPHABET[$na];
                $i++;
                continue;
            }
            if ($nb === $na && $ca !== null) {
                $out .= self::ALPHABET[$na];
                $i++;
                continue;
            }
            if ($nb === $na && $ca === null && $cb === null) {
                return $out . 'h';
            }

            throw new \RuntimeException("LexoRank::between: {$A} >= {$B}");
        }
    }
}