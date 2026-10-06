<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Establishment;
use PHPUnit\Framework\TestCase;

class EstablishmentTest extends TestCase
{
    public function testReplySettingsDefaults(): void
    {
        $establishment = new Establishment();

        $this->assertSame('vous', $establishment->getReplyFormality());
        $this->assertSame('cordial', $establishment->getReplyTone());
        $this->assertNull($establishment->getReplySignature());
        $this->assertFalse($establishment->isConnectedToGoogleBusiness());
    }

    public function testInvalidFormalityIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Establishment())->setReplyFormality('vouvoyer');
    }

    public function testInvalidToneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Establishment())->setReplyTone('agressif');
    }
}
