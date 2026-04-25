<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Audit;
use PHPUnit\Framework\TestCase;

/**
 * Validators for URLs, IPs, user-agents, MAC addresses, credit cards
 * and password entropy. Audit is critical input-validation code path.
 */
final class AuditTest extends TestCase
{
    private Audit $audit;

    protected function setUp(): void
    {
        $this->audit = Audit::instance();
    }

    public function testUrlValid(): void
    {
        $this->assertTrue($this->audit->url('https://example.com/path?q=1'));
        $this->assertTrue($this->audit->url('http://localhost:8080'));
    }

    public function testUrlInvalid(): void
    {
        $this->assertFalse((bool) $this->audit->url('not a url'));
        $this->assertFalse((bool) $this->audit->url(''));
    }

    public function testEmailWithoutMxCheck(): void
    {
        $this->assertTrue($this->audit->email('a@b.co', false));
        $this->assertFalse((bool) $this->audit->email('not-email', false));
        $this->assertFalse((bool) $this->audit->email('@b.co', false));
    }

    public function testIpv4(): void
    {
        $this->assertTrue($this->audit->ipv4('8.8.8.8'));
        $this->assertTrue($this->audit->ipv4('127.0.0.1'));
        $this->assertFalse((bool) $this->audit->ipv4('256.0.0.1'));
        $this->assertFalse((bool) $this->audit->ipv4('not.an.ip'));
    }

    public function testIpv6(): void
    {
        $this->assertTrue($this->audit->ipv6('::1'));
        $this->assertTrue($this->audit->ipv6('2001:db8::1'));
        $this->assertFalse((bool) $this->audit->ipv6('not::valid::ip'));
    }

    public function testIsPrivateRange(): void
    {
        $this->assertTrue($this->audit->isprivate('10.0.0.1'));
        $this->assertTrue($this->audit->isprivate('192.168.1.1'));
        $this->assertTrue($this->audit->isprivate('172.16.0.1'));
        $this->assertFalse($this->audit->isprivate('8.8.8.8'));
    }

    public function testIsReserved(): void
    {
        $this->assertTrue($this->audit->isreserved('127.0.0.1'));
        $this->assertTrue($this->audit->isreserved('0.0.0.0'));
        $this->assertFalse($this->audit->isreserved('8.8.8.8'));
    }

    public function testIsPublic(): void
    {
        $this->assertTrue($this->audit->ispublic('8.8.8.8'));
        $this->assertFalse($this->audit->ispublic('192.168.1.1'));
        $this->assertFalse($this->audit->ispublic('127.0.0.1'));
    }

    public function testIsBotDetectsCommonCrawler(): void
    {
        $this->assertTrue($this->audit->isbot('Googlebot/2.1'));
        $this->assertTrue($this->audit->isbot('Mozilla/5.0 (compatible; bingbot/2.0)'));
        $this->assertFalse($this->audit->isbot('Mozilla/5.0 (Windows NT 10.0)'));
    }

    public function testIsAiDetectsAiCrawler(): void
    {
        $this->assertTrue($this->audit->isai('GPTBot/1.0'));
        $this->assertTrue($this->audit->isai('ClaudeBot'));
    }

    public function testIsDesktopAndMobile(): void
    {
        $desktop = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $mobile  = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36';
        $this->assertTrue($this->audit->isdesktop($desktop));
        $this->assertTrue($this->audit->ismobile($mobile));
    }

    public function testMod10Luhn(): void
    {
        // Visa test card.
        $this->assertTrue($this->audit->mod10('4111111111111111'));
        $this->assertFalse($this->audit->mod10('4111111111111112'));
        $this->assertFalse($this->audit->mod10('not-a-card'));
    }

    public function testCardTypeRecognition(): void
    {
        $this->assertSame('Visa', $this->audit->card('4111111111111111'));
        // Test MasterCard prefix
        $this->assertSame('MasterCard', $this->audit->card('5555555555554444'));
        // Random invalid number
        $this->assertFalse($this->audit->card('1234567890123456'));
    }

    public function testEntropyMonotonic(): void
    {
        $weak = $this->audit->entropy('abc');
        $strong = $this->audit->entropy('Tr0ub4dor&3!@#$Long');
        $this->assertGreaterThan($weak, $strong);
    }

    public function testMacAddressValidation(): void
    {
        $this->assertTrue($this->audit->mac('00:1A:2B:3C:4D:5E'));
        $this->assertTrue($this->audit->mac('00-1a-2b-3c-4d-5e'));
        $this->assertFalse((bool) $this->audit->mac('not:a:mac'));
    }

    public function testIsBotOrAiReturnsTrueForBothAgents(): void
    {
        // Bot agent.
        $this->assertTrue($this->audit->isbotorai('Googlebot/2.1'));
        // AI agent.
        $this->assertTrue($this->audit->isbotorai('GPTBot/1.0'));
        // Plain browser: neither.
        $this->assertFalse($this->audit->isbotorai('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'));
    }

    public function testUrlRejectsJavascriptScheme(): void
    {
        $this->assertFalse($this->audit->url('javascript://foo'));
        $this->assertFalse($this->audit->url('javascript:alert(1)'));
    }

    public function testUrlRejectsPHPScheme(): void
    {
        $this->assertFalse($this->audit->url('php://filter/resource=http://evil'));
    }

    public function testCardAmericanExpress(): void
    {
        // Standard 15-digit AmEx test card.
        $this->assertSame('American Express', $this->audit->card('371449635398431'));
    }

    public function testCardDiscover(): void
    {
        $this->assertSame('Discover', $this->audit->card('6011111111111117'));
    }

    public function testCardJcb(): void
    {
        $this->assertSame('JCB', $this->audit->card('3530111333300000'));
    }

    public function testCardDinersClub(): void
    {
        $this->assertSame('Diners Club', $this->audit->card('30569309025904'));
    }

    public function testMacEui64Format(): void
    {
        // EUI-64: 8 groups of 2 hex digits.
        $this->assertTrue($this->audit->mac('00:1a:2b:ff:fe:3c:4d:5e'));
    }

    public function testEntropySingleCharIsExactlyFour(): void
    {
        // Single character: 4*min(1,1) + 0 + 0 + 0 + 0 = 4.
        $this->assertSame(4, $this->audit->entropy('a'));
    }

    public function testEntropyMixedCaseAndDigitAddSixPointBonus(): void
    {
        // 'A1' (len=2): 4*1 + 2*(2-1) + 0 + 0 + 6*(bool) = 4+2+6 = 12.
        $this->assertSame(12, $this->audit->entropy('A1'));
    }

    // -----------------------------------------------------------------
    // uuid()
    // -----------------------------------------------------------------

    public function testUuidV4StrictValid(): void
    {
        // Canonical v4, lowercase.
        $this->assertTrue($this->audit->uuid('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testUuidV1StrictValid(): void
    {
        $this->assertTrue($this->audit->uuid('6ba7b810-9dad-11d1-80b4-00c04fd430c8'));
    }

    public function testUuidV7StrictValid(): void
    {
        // v7: version digit = 7, variant = 8 (10xx).
        $this->assertTrue($this->audit->uuid('018e3de6-c6f9-7000-8f87-ea07c9e11f67'));
    }

    public function testUuidUppercaseStrictValid(): void
    {
        $this->assertTrue($this->audit->uuid('550E8400-E29B-41D4-A716-446655440000'));
    }

    public function testUuidStrictRejectsVersionZero(): void
    {
        // Version 0 is not defined.
        $this->assertFalse($this->audit->uuid('550e8400-e29b-01d4-a716-446655440000'));
    }

    public function testUuidStrictRejectsVersionNine(): void
    {
        // Version 9 is not defined.
        $this->assertFalse($this->audit->uuid('550e8400-e29b-91d4-a716-446655440000'));
    }

    public function testUuidStrictRejectsWrongVariant(): void
    {
        // Variant 'c' (1100) is reserved, not RFC 4122.
        $this->assertFalse($this->audit->uuid('550e8400-e29b-41d4-c716-446655440000'));
    }

    public function testUuidStrictRejectsNilUuid(): void
    {
        // Nil UUID has version 0 and variant 0 — fails strict.
        $this->assertFalse($this->audit->uuid('00000000-0000-0000-0000-000000000000'));
    }

    public function testUuidStrictRejectsMaxUuid(): void
    {
        // Max UUID has version f — fails strict.
        $this->assertFalse($this->audit->uuid('ffffffff-ffff-ffff-ffff-ffffffffffff'));
    }

    public function testUuidLooseAcceptsNilUuid(): void
    {
        $this->assertTrue($this->audit->uuid('00000000-0000-0000-0000-000000000000', false));
    }

    public function testUuidLooseAcceptsMaxUuid(): void
    {
        $this->assertTrue($this->audit->uuid('ffffffff-ffff-ffff-ffff-ffffffffffff', false));
    }

    public function testUuidRejectsNoHyphens(): void
    {
        $this->assertFalse($this->audit->uuid('550e8400e29b41d4a716446655440000'));
    }

    public function testUuidRejectsNonHex(): void
    {
        $this->assertFalse($this->audit->uuid('zzzzzzzz-zzzz-4zzz-azzz-zzzzzzzzzzzz'));
    }

    public function testUuidRejectsEmpty(): void
    {
        $this->assertFalse($this->audit->uuid(''));
    }

    public function testUuidRejectsTooShort(): void
    {
        $this->assertFalse($this->audit->uuid('550e8400-e29b-41d4-a716'));
    }

    public function testUuidRejectsTrailingNewline(): void
    {
        // The /D modifier must prevent $ from matching before a trailing newline.
        $this->assertFalse($this->audit->uuid("550e8400-e29b-41d4-a716-446655440000\n"));
    }
}
