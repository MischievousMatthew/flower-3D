<?php

namespace App\Support;

/**
 * The code that stands for a product in customer-facing URLs and API paths
 * (1 → "2oSZSU7"). Must match frontend/src/utils/productLink.js exactly.
 * It hides the product number; access rules still decide what anyone sees.
 */
class ProductCode
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    private const KEYS = [0x5bd1, 0x9e37, 0x2c6b, 0xd2a7];
    private const LENGTH = 6;

    public static function encode(int $id): string
    {
        $body = self::toBase62(self::scramble($id));
        return $body . self::checkChar($body);
    }

    /** The product id for a code, or null (a plain id like "1" is never accepted) */
    public static function decode(?string $code): ?int
    {
        $value = (string) $code;
        if (strlen($value) !== self::LENGTH + 1 || strspn($value, self::ALPHABET) !== strlen($value)) {
            return null;
        }
        $body = substr($value, 0, self::LENGTH);
        if (self::checkChar($body) !== $value[self::LENGTH]) {
            return null;
        }
        $n = 0;
        foreach (str_split($body) as $c) {
            $n = $n * 62 + strpos(self::ALPHABET, $c);
        }
        if ($n > 0xFFFFFFFF) {
            return null;
        }
        $id = self::scramble($n, true);
        return $id > 0 ? $id : null;
    }

    private static function round(int $half, int $key): int
    {
        // Same as JS (Math.imul(half ^ key, 0x45d9f3b) >>> 7) & 0xffff
        return ((((($half ^ $key) * 0x45d9f3b) & 0xFFFFFFFF) >> 7) & 0xFFFF);
    }

    private static function scramble(int $n, bool $decode = false): int
    {
        $left = ($n >> 16) & 0xFFFF;
        $right = $n & 0xFFFF;
        $keys = $decode ? array_reverse(self::KEYS) : self::KEYS;
        if ($decode) {
            [$left, $right] = [$right, $left];
        }
        foreach ($keys as $key) {
            [$left, $right] = [$right, $left ^ self::round($right, $key)];
        }
        if ($decode) {
            [$left, $right] = [$right, $left];
        }
        return (($left << 16) | $right) & 0xFFFFFFFF;
    }

    private static function toBase62(int $n): string
    {
        $out = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out = self::ALPHABET[$n % 62] . $out;
            $n = intdiv($n, 62);
        }
        return $out;
    }

    private static function checkChar(string $body): string
    {
        $sum = 7;
        foreach (str_split($body) as $i => $c) {
            $sum += strpos(self::ALPHABET, $c) * ($i + 3);
        }
        return self::ALPHABET[$sum % 62];
    }
}
