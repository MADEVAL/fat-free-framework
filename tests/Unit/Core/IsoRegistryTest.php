<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use ISO;
use Registry;
use PHPUnit\Framework\TestCase;

/**
 * ISO class (language/country code lookups) and Registry (singleton catalog).
 * Both are part of base.php but have zero test coverage.
 */
final class IsoRegistryTest extends TestCase
{
    // -- ISO: languages -----------------------------------------------------

    public function testLanguagesReturnsNonEmptyArray(): void
    {
        $iso = new ISO();
        $langs = $iso->languages();
        $this->assertIsArray($langs);
        $this->assertNotEmpty($langs);
    }

    public function testLanguagesContainsEnglish(): void
    {
        $iso = new ISO();
        $this->assertSame('English', $iso->languages()['en']);
    }

    public function testLanguagesContainsGerman(): void
    {
        $iso = new ISO();
        $this->assertSame('German', $iso->languages()['de']);
    }

    public function testLanguagesContainsFrench(): void
    {
        $iso = new ISO();
        $this->assertSame('French', $iso->languages()['fr']);
    }

    public function testLanguagesKeyIsLowercase(): void
    {
        $iso = new ISO();
        foreach (array_keys($iso->languages()) as $code) {
            $this->assertSame(strtolower($code), $code, "Language code '$code' must be lowercase.");
        }
    }

    // -- ISO: countries -----------------------------------------------------

    public function testCountriesReturnsNonEmptyArray(): void
    {
        $iso = new ISO();
        $countries = $iso->countries();
        $this->assertIsArray($countries);
        $this->assertNotEmpty($countries);
    }

    public function testCountriesContainsUnitedStates(): void
    {
        $iso = new ISO();
        $this->assertSame('United States', $iso->countries()['us']);
    }

    public function testCountriesContainsGermany(): void
    {
        $iso = new ISO();
        $this->assertSame('Germany', $iso->countries()['de']);
    }

    public function testCountriesContainsJapan(): void
    {
        $iso = new ISO();
        $this->assertSame('Japan', $iso->countries()['jp']);
    }

    public function testCountriesKeyIsLowercase(): void
    {
        $iso = new ISO();
        foreach (array_keys($iso->countries()) as $code) {
            $this->assertSame(strtolower($code), $code, "Country code '$code' must be lowercase.");
        }
    }

    // -- Registry -----------------------------------------------------------

    public function testSetAndGet(): void
    {
        $obj = new \stdClass();
        $obj->value = 'hello';
        Registry::set('reg_test_a', $obj);
        $this->assertSame($obj, Registry::get('reg_test_a'));
    }

    public function testExistsReturnsTrueAfterSet(): void
    {
        $key = 'reg_exist_' . uniqid();
        Registry::set($key, new \stdClass());
        $this->assertTrue(Registry::exists($key));
    }

    public function testExistsReturnsFalseForUnknownKey(): void
    {
        $this->assertFalse(Registry::exists('reg_never_set_' . uniqid()));
    }

    public function testClearRemovesEntry(): void
    {
        $key = 'reg_clear_' . uniqid();
        Registry::set($key, new \stdClass());
        Registry::clear($key);
        $this->assertFalse(Registry::exists($key));
    }

    public function testSetReturnsTheStoredObject(): void
    {
        $obj = new \stdClass();
        $returned = Registry::set('reg_ret_' . uniqid(), $obj);
        $this->assertSame($obj, $returned);
    }

    protected function tearDown(): void
    {
        foreach (['reg_test_a'] as $k) {
            if (Registry::exists($k)) {
                Registry::clear($k);
            }
        }
    }
}
