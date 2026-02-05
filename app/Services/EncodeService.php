<?php

namespace App\Services;

class EncodeService
{
    public static function encrypte(string $string): string
    {
        $key = substr(hash('sha256', env('ENCRYPT_KEY')), 0, 32); // 32 байта AES‑256
        $iv = random_bytes(16); // 16 байт для IV
        $encrypted = openssl_encrypt($string, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        // Сохраняем IV вместе с зашифрованными данными
        return base64_encode($iv . $encrypted);
    }

    public static function decrypte(string $encoded): string
    {
        $key = substr(hash('sha256', env('ENCRYPT_KEY')), 0, 32);
        $data = base64_decode($encoded);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }
}
