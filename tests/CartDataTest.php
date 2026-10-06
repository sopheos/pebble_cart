<?php

use Pebble\Cart\CartData;
use Pebble\Cart\CartItemData;
use Pebble\Cart\CartTotalData;
use PHPUnit\Framework\TestCase;

class CartDataTest extends TestCase
{
    private function cart(array $data, array ...$items): CartData
    {
        $data['items'] = $items;
        return new CartData($data);
    }

    private function item(float $price, int $taxe = CartData::NORMAL_RATE, bool $is_service = true, float $quantity = 1): array
    {
        return ['quantity' => $quantity, 'price' => $price, 'taxe' => $taxe, 'is_service' => $is_service];
    }

    // -------------------------------------------------------------------------
    // Totals
    // -------------------------------------------------------------------------

    public function testTotalFromTtcPrices()
    {
        $cart = $this->cart([], $this->item(60, quantity: 2));

        self::assertInstanceOf(CartTotalData::class, $cart->total);
        self::assertSame(120.0, $cart->total->ttc);
        self::assertSame(100.0, $cart->total->ht);
        self::assertSame(20.0, $cart->total->total_tva);
        self::assertSame(['0.2' => 20.0], $cart->total->tva);
        self::assertSame(120.0, $cart->getAmount());
    }

    public function testTotalFromHtPrices()
    {
        $cart = $this->cart(['is_ttc' => false], $this->item(50, quantity: 2));

        self::assertSame(120.0, $cart->total->ttc);
        self::assertSame(100.0, $cart->total->ht);
        self::assertSame(20.0, $cart->total->total_tva);
        self::assertSame(['0.2' => 20.0], $cart->total->tva);
    }

    public function testVatIsGroupedByRate()
    {
        $cart = $this->cart(
            ['is_ttc' => false],
            $this->item(100, CartData::NORMAL_RATE),
            $this->item(100, CartData::INTERMEDIATE_RATE),
            $this->item(100, CartData::REDUCED_RATE),
            $this->item(100, CartData::SPECIAL_RATE),
            $this->item(100, CartData::NORMAL_RATE),
            $this->item(10, CartData::NO_RATE),
        );

        self::assertSame(['0.2' => 40.0, '0.1' => 10.0, '0.055' => 5.5, '0.021' => 2.1], $cart->total->tva);
        self::assertSame(510.0, $cart->total->ht);
        self::assertEqualsWithDelta(567.6, $cart->total->ttc, 1e-9);
    }

    public function testZeroQuantityLinesAreIgnored()
    {
        $cart = $this->cart(
            ['is_btb' => true, 'country' => 'US'],
            $this->item(100, quantity: 0),
        );

        self::assertSame(0.0, $cart->total->ht);
        self::assertSame([], $cart->mentions);
    }

    public function testUnknownRateFallsBackToZero()
    {
        $cart = $this->cart([], $this->item(120, 9));

        self::assertSame(0.0, $cart->total->total_tva);
        self::assertSame(120.0, $cart->total->ht);
    }

    public function testTtcIsZeroWhenNoVatApplies()
    {
        $cart = $this->cart(['is_btb' => true, 'country' => 'US'], $this->item(120));

        self::assertSame(0.0, $cart->total->ttc);
        self::assertSame(120.0, $cart->total->ht);
        self::assertSame([], $cart->total->tva);
        self::assertSame(120.0, $cart->getAmount());
    }

    // -------------------------------------------------------------------------
    // VAT zones
    // -------------------------------------------------------------------------

    /**
     * @dataProvider zoneProvider
     */
    public function testVatZones(array $cart, bool $is_service, int $taxe, int|float $expectedRate, ?string $mention)
    {
        $cart = new CartData($cart);
        $item = new CartItemData(['quantity' => 1, 'price' => 100, 'taxe' => $taxe, 'is_service' => $is_service]);

        self::assertSame([$expectedRate, $mention], $cart->getTaxValue($item));
    }

    public static function zoneProvider(): array
    {
        $art294 = "Exonération de TVA, article 294 du Code général des impôts et auto liquidation.";
        $art2832 = "Exonération de TVA, article 283-2 du Code général des impôts et auto liquidation.";
        $art2622 = "Exonération de TVA, article 262-2 du Code général des impôts et auto liquidation.";
        $art2621 = "Exonération de TVA, article 262-1 du Code général des impôts et auto liquidation.";

        $n = CartData::NORMAL_RATE;
        $r = CartData::REDUCED_RATE;
        $btb = fn(string $country, bool $intraco = false) => ['is_btb' => true, 'country' => $country, 'is_intraco' => $intraco];
        $btc = fn(string $country) => ['country' => $country];

        return [
            // Services B2B
            'service btb FR' => [$btb('FR'), true, $n, 0.20, null],
            'service btb MC' => [$btb('MC'), true, $r, 0.055, null],
            'service btb GP' => [$btb('GP'), true, $n, 0.085, null],
            'service btb RE reduced' => [$btb('RE'), true, $r, 0.021, null],
            'service btb GF' => [$btb('GF'), true, $n, 0, $art294],
            'service btb YT' => [$btb('YT'), true, $n, 0, $art294],
            'service btb DE intraco' => [$btb('DE', true), true, $n, 0, $art2832],
            'service btb DE not intraco' => [$btb('DE'), true, $n, 0.20, null],
            'service btb US' => [$btb('US'), true, $n, 0, $art2832],
            // Services B2C
            'service btc FR' => [$btc('FR'), true, $n, 0.20, null],
            'service btc GP' => [$btc('GP'), true, $n, 0.20, null],
            'service btc US' => [$btc('US'), true, $n, 0.20, null],
            // Goods B2B
            'goods btb FR' => [$btb('FR'), false, $n, 0.20, null],
            'goods btb GP' => [$btb('GP'), false, $n, 0, $art294],
            'goods btb DE intraco' => [$btb('DE', true), false, $n, 0, $art2622],
            'goods btb DE not intraco' => [$btb('DE'), false, $n, 0.20, null],
            'goods btb US' => [$btb('US'), false, $n, 0, $art2621],
            // Goods B2C
            'goods btc FR' => [$btc('FR'), false, $n, 0.20, null],
            'goods btc MQ' => [$btc('MQ'), false, $n, 0, $art294],
            'goods btc DE' => [$btc('DE'), false, $n, 0.20, null],
            'goods btc US' => [$btc('US'), false, $n, 0, $art2621],
        ];
    }

    public function testMentionsAreDeduplicated()
    {
        $cart = $this->cart(
            ['is_btb' => true, 'country' => 'US'],
            $this->item(10),
            $this->item(20),
            $this->item(30, is_service: false),
        );

        self::assertSame([
            "Exonération de TVA, article 283-2 du Code général des impôts et auto liquidation.",
            "Exonération de TVA, article 262-1 du Code général des impôts et auto liquidation.",
        ], $cart->mentions);
    }

    public function testCountryIsCaseSensitive()
    {
        $cart = $this->cart(['country' => 'fr'], $this->item(120, is_service: false));

        self::assertSame(0.0, $cart->total->total_tva);
        self::assertSame(["Exonération de TVA, article 262-1 du Code général des impôts et auto liquidation."], $cart->mentions);
    }

    public function testExemptCartInTtcModeKeepsThePriceAsHt()
    {
        // 120 "TTC" sold to an export customer: no VAT is removed, the customer pays 120 HT.
        $cart = $this->cart(['is_btb' => true, 'country' => 'US'], $this->item(120));

        self::assertSame(120.0, $cart->total->ht);
    }

    // -------------------------------------------------------------------------
    // Rounding
    // -------------------------------------------------------------------------

    public function testVatIsComputedOnTheTotalByRateNotPerLine()
    {
        // Per line: round(0.99 - 0.99 / 1.055, 2) = 0.05, so 3 lines = 0.15.
        // On the total: 2.97 - 2.97 / 1.055 = 0.1548...
        $cart = $this->cart([], ...array_fill(0, 3, $this->item(0.99, CartData::REDUCED_RATE)));

        self::assertSame(0.1548341232, $cart->total->total_tva);
    }

    public function testTtcModeRoundsVatToTenDecimals()
    {
        $cart = $this->cart([], $this->item(10));

        self::assertSame(1.6666666667, $cart->total->total_tva);
        self::assertSame(10 - 1.6666666667, $cart->total->ht);
    }

    // -------------------------------------------------------------------------
    // Import / export
    // -------------------------------------------------------------------------

    public function testImportConvertsArraysAndExportRoundTrips()
    {
        $cart = $this->cart(['is_ttc' => false], $this->item(100, CartData::REDUCED_RATE));

        self::assertInstanceOf(CartItemData::class, $cart->items[0]);

        $copy = new CartData($cart->export());
        self::assertEquals($cart, $copy);
        self::assertSame(['0.055' => 5.5], $copy->total->tva);
    }

    public function testProvidedTotalIsNotRecomputed()
    {
        $cart = new CartData(['items' => [$this->item(120)], 'total' => ['ttc' => 5]]);

        self::assertSame(5.0, $cart->total->ttc);
        self::assertSame(0.0, $cart->total->ht);
    }

    public function testDirectPropertyChangeNeedsTotal()
    {
        $cart = $this->cart([], $this->item(120));
        $cart->is_btb = true;
        $cart->country = 'US';

        self::assertSame(20.0, $cart->total->total_tva);
        self::assertSame(0.0, $cart->total()->total->total_tva);
    }

    public function testUnknownKeysAreSilentlyIgnored()
    {
        $cart = $this->cart(['is_b2b' => true, 'country' => 'US'], $this->item(120));

        self::assertFalse($cart->is_btb);
        self::assertSame(20.0, $cart->total->total_tva);
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testAmountsAreNeverRoundedToTheCent()
    {
        // BUG: no amount is rounded to 2 decimals. In HT mode the VAT is $ht * $tx
        // with no rounding at all, and float noise accumulates in the totals.
        $cart = $this->cart(['is_ttc' => false], $this->item(19.99));
        self::assertEqualsWithDelta(23.988, $cart->total->ttc, 1e-9);
        self::assertSame(2398, (int) ($cart->getAmount() * 100));

        $cart = $this->cart(['is_ttc' => false], $this->item(0.1, quantity: 3));
        self::assertSame(0.30000000000000004, $cart->total->ht);
        self::assertSame(0.36000000000000004, $cart->total->ttc);
    }
}
