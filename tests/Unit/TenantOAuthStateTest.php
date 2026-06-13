<?php

namespace Tests\Unit;

use App\Support\OAuth\TenantOAuthState;
use Tests\TestCase;

class TenantOAuthStateTest extends TestCase
{
    private TenantOAuthState $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new TenantOAuthState;
    }

    public function test_roundtrip_encode_decode(): void
    {
        $state = $this->codec->encode('/cliente/carta', 'chispa');

        $decoded = $this->codec->decode($state, fn (?string $p) => $p ?? '/cliente/carta');

        $this->assertTrue($decoded['valid']);
        $this->assertSame('/cliente/carta', $decoded['redirect']);
        $this->assertSame('chispa', $decoded['tenant']);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $state = $this->codec->encode('/cliente', 'demo');
        $tampered = substr($state, 0, -4).'xxxx';

        $decoded = $this->codec->decode($tampered, fn (?string $p) => $p ?? '/cliente/carta');

        $this->assertFalse($decoded['valid']);
        $this->assertNull($decoded['tenant']);
    }

    public function test_legacy_unsigned_state_is_rejected(): void
    {
        $legacy = rtrim(strtr(base64_encode(json_encode([
            'redirect' => '/cliente',
            'tenant' => 'demo',
        ])), '+/', '-_'), '=');

        $decoded = $this->codec->decode($legacy, fn (?string $p) => $p ?? '/cliente/carta');

        $this->assertFalse($decoded['valid']);
    }
}
