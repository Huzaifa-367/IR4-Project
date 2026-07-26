<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * AES-256-GCM envelope for .ir4bak / .ir4exp archives (DOC-19).
 */
final class ArchiveEncryptor
{
    public const MAGIC_BACKUP = 'IR4BAK01';

    public const MAGIC_EXPORT = 'IR4EXP01';

    public function encryptFile(string $plaintextPath, string $ciphertextPath, string $key, string $magic = self::MAGIC_BACKUP): void
    {
        $keyBytes = $this->normalizeKey($key);
        $plaintext = file_get_contents($plaintextPath);
        if ($plaintext === false) {
            throw new RuntimeException("Unable to read {$plaintextPath}");
        }

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $keyBytes,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16,
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Encryption failed.');
        }

        $payload = $magic.chr(1).$nonce.$ciphertext.$tag;
        if (file_put_contents($ciphertextPath, $payload) === false) {
            throw new RuntimeException("Unable to write {$ciphertextPath}");
        }
    }

    public function decryptFile(string $ciphertextPath, string $plaintextPath, string $key, ?string $expectedMagic = null): string
    {
        $keyBytes = $this->normalizeKey($key);
        $payload = file_get_contents($ciphertextPath);
        if ($payload === false || strlen($payload) < 8 + 1 + 12 + 16) {
            throw new RuntimeException('Archive too small or unreadable.');
        }

        $magic = substr($payload, 0, 8);
        if ($expectedMagic !== null && $magic !== $expectedMagic) {
            throw new RuntimeException("Unexpected archive magic [{$magic}].");
        }
        if (! in_array($magic, [self::MAGIC_BACKUP, self::MAGIC_EXPORT], true)) {
            throw new RuntimeException("Unknown archive magic [{$magic}].");
        }

        $version = ord($payload[8]);
        if ($version !== 1) {
            throw new RuntimeException("Unsupported archive version [{$version}].");
        }

        $nonce = substr($payload, 9, 12);
        $tag = substr($payload, -16);
        $ciphertext = substr($payload, 21, -16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $keyBytes,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed — wrong key or tampered archive.');
        }

        if (file_put_contents($plaintextPath, $plaintext) === false) {
            throw new RuntimeException("Unable to write {$plaintextPath}");
        }

        return $magic;
    }

    public function resolveKey(?string $explicit = null): string
    {
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $configured = config('backup.encryption_key');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appKey = (string) config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7), true) ?: $appKey;
        }

        return base64_encode(hash_hkdf('sha256', $appKey, 32, 'ir4-backup-v1'));
    }

    public function fingerprint(string $key): string
    {
        return 'sha256:'.hash('sha256', $this->normalizeKey($key));
    }

    private function normalizeKey(string $key): string
    {
        $decoded = base64_decode($key, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        if (strlen($key) === 32) {
            return $key;
        }

        // Derive a stable 32-byte key from arbitrary passphrases.
        return hash('sha256', $key, true);
    }
}
