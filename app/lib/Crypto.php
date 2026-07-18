<?php

/**
 * Crypto — authenticated encryption for secrets at rest (AES-256-GCM).
 *
 * Every tenant secret (Cloud API tokens, panel API keys, gateway keys,
 * DeepSeek keys) is stored via encrypt() and read back via decrypt().
 * The master key lives ONLY in config/.env as APP_KEY (base64, 32 bytes)
 * and must never be committed or placed inside the webroot.
 *
 * Storage format (base64): [12-byte IV][16-byte GCM tag][ciphertext]
 */
class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    private static ?string $key = null;

    /**
     * Initialise with the raw APP_KEY string from .env (expected: "base64:...."
     * or a plain base64 string decoding to 32 bytes).
     */
    public static function init(string $appKey): void
    {
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = substr($appKey, 7);
        }

        $decoded = base64_decode($appKey, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('APP_KEY must be a base64-encoded 32-byte key. Generate one with: php -r "echo base64_encode(random_bytes(32));"');
        }

        self::$key = $decoded;
    }

    /** Encrypt plaintext -> base64 blob. Empty/null input returns null (nothing to store). */
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }

        $key = self::requireKey();
        $iv = random_bytes(self::IV_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /** Decrypt a base64 blob -> plaintext. Empty/null input returns null. Tampering returns null. */
    public static function decrypt(?string $blob): ?string
    {
        if ($blob === null || $blob === '') {
            return null;
        }

        $key = self::requireKey();
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN) {
            return null;
        }

        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext === false ? null : $plaintext;
    }

    private static function requireKey(): string
    {
        if (self::$key === null) {
            throw new RuntimeException('Crypto::init() must be called with APP_KEY before use.');
        }

        return self::$key;
    }
}
