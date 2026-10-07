<?php

namespace App\Tests\Unit\Service;

use App\Service\TokenCipher;
use PHPUnit\Framework\TestCase;

class TokenCipherTest extends TestCase
{
    private function cipher(): TokenCipher
    {
        return new TokenCipher('', 'un-secret-de-test-stable');
    }

    public function testRoundTrip(): void
    {
        $cipher = $this->cipher();
        $token = 'ya29.a0AfH6SMExampleGoogleAccessToken';

        $encrypted = $cipher->encrypt($token);

        $this->assertNotNull($encrypted);
        $this->assertStringStartsWith('enc:v1:', (string) $encrypted);
        $this->assertStringNotContainsString($token, (string) $encrypted);
        $this->assertSame($token, $cipher->decrypt($encrypted));
    }

    public function testPlainLegacyValueIsReturnedAsIs(): void
    {
        // Un ancien jeton stocké en clair (sans préfixe) reste lisible.
        $this->assertSame('ancien-jeton', $this->cipher()->decrypt('ancien-jeton'));
    }

    public function testNullAndEmptyAreUntouched(): void
    {
        $cipher = $this->cipher();
        $this->assertNull($cipher->encrypt(null));
        $this->assertNull($cipher->decrypt(null));
        $this->assertSame('', $cipher->encrypt(''));
    }

    public function testTamperedCiphertextReturnsNull(): void
    {
        $cipher = $this->cipher();
        $encrypted = (string) $cipher->encrypt('secret');
        $tampered = substr($encrypted, 0, -4).'AAAA';

        $this->assertNull($cipher->decrypt($tampered));
    }
}
