<?php

declare(strict_types=1);

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use Tests\Support\MockWeb;
use Web\Geo;

final class GeoTest extends TestCase
{
    protected function tearDown(): void
    {
        MockWeb::restore();
        \Registry::clear(Geo::class);
    }

    public function testTzInfoReturnsArray(): void
    {
        $g = Geo::instance();
        $info = $g->tzinfo('UTC');
        $this->assertSame(0, (int) $info['offset']);
        $this->assertArrayHasKey('country', $info);
    }

    public function testLocationParsesGeopluginPayload(): void
    {
        $mock = MockWeb::install();
        $mock->enqueue(json_encode([
            'geoplugin_request' => '8.8.8.8',
            'geoplugin_status' => 200,
            'geoplugin_countryCode' => 'US',
            'geoplugin_countryName' => 'United States',
            'geoplugin_latitude' => '37.7510',
            'geoplugin_longitude' => '-97.8220',
            'geoplugin_currencyCode' => 'USD',
            'geoplugin_region' => 'X',
        ]));

        $out = (new Geo())->location('8.8.8.8');
        $this->assertIsArray($out);
        $this->assertSame('US', $out['country_code']);
        $this->assertArrayNotHasKey('currency_code', $out);
    }

    public function testLocationReturnsFalseOnEmptyResponse(): void
    {
        MockWeb::install();
        $this->assertFalse((new Geo())->location('8.8.8.8'));
    }

    public function testWeatherDecodesJson(): void
    {
        $mock = MockWeb::install();
        $mock->enqueue(json_encode(['main' => ['temp' => 21.5]]));
        $out = (new Geo())->weather(50.0, 30.0, 'KEY');
        $this->assertSame(21.5, $out['main']['temp']);
    }

    public function testWeatherReturnsFalseOnFailure(): void
    {
        MockWeb::install();
        $this->assertFalse((new Geo())->weather(0, 0, 'k'));
    }
}
