<?php

use Pebble\Cart\CartData;
use Pebble\Cart\CartItemData;
use Pebble\Cart\CartTotalData;
use PHPUnit\Framework\TestCase;

class CartItemDataTest extends TestCase
{
    // -------------------------------------------------------------------------
    // CartItemData
    // -------------------------------------------------------------------------

    public function testItemDefaults()
    {
        $item = new CartItemData();

        self::assertSame('', $item->label);
        self::assertSame(0.0, $item->quantity);
        self::assertSame(0.0, $item->price);
        self::assertNull($item->unit);
        self::assertSame(CartData::NO_RATE, $item->taxe);
        self::assertTrue($item->is_service);
    }

    public function testItemExport()
    {
        $item = new CartItemData(['label' => 'Formation', 'quantity' => 2, 'price' => 450, 'unit' => CartData::UNITS[CartData::DAYS_UNIT], 'taxe' => CartData::NORMAL_RATE]);

        self::assertSame([
            'label' => 'Formation',
            'quantity' => 2,
            'price' => 450,
            'unit' => 'jour(s)',
            'taxe' => 1,
            'is_service' => true,
        ], $item->export());
    }

    // -------------------------------------------------------------------------
    // CartTotalData
    // -------------------------------------------------------------------------

    public function testTotalDefaults()
    {
        self::assertSame(['ttc' => 0, 'ht' => 0, 'tva' => [], 'total_tva' => 0], (new CartTotalData())->export());
    }
}
