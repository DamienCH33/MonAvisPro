<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Chiffre les jetons Google stockés en base (libsodium, secretbox).
 *
 * Format stocké : « enc:v1: » + base64(nonce + texte chiffré).
 * Une valeur sans préfixe est considérée comme un ancien jeton en clair,
 * pour ne pas casser les fiches déjà reliées.
 */
class TokenCipher
{
    private const PREFIX = 'enc:v1:';

    private string $key;

    public function __construct(string $encryptionKey, string $appSecret)
    {
        $decoded = '' !== $encryptionKey ? base64_decode($encryptionKey, true) : false;

        if (false !== $decoded && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen($decoded)) {
            $this->key = $decoded;
        } else {
            // Repli : clé dérivée de APP_SECRET (à remplacer par APP_ENCRYPTION_KEY en production).
            $this->key = sodium_crypto_generichash('monavispro-tokens|'.$appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }
    }

    public function encrypt(?string $plain): ?string
    {
        if (null === $plain || '' === $plain) {
            return $plain;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(?string $stored): ?string
    {
        if (null === $stored || !str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if (false === $raw || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);

        return false === $plain ? null : $plain;
    }
}
