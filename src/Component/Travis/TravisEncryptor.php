<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Component\Travis;

use RuntimeException;

class TravisEncryptor
{
    private ?string $publicKey;

    public function setPublicKey(string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }

    public function encrypt(string $value, $encodeResultWithBase64 = true): string
    {
        if ($this->publicKey === null) {
            throw new RuntimeException('The public key for encryption has not been set.');
        }

        $encryptedValue = null;
        if (openssl_public_encrypt($value, $encryptedValue, $this->publicKey) === false) {
            throw new RuntimeException('Failed to encrypt data.');
        }

        if ($encodeResultWithBase64) {
            $encryptedValue = base64_encode($encryptedValue);
        }

        return $encryptedValue;
    }
}
