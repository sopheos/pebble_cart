<?php

namespace Pebble\Cart;

use Pebble\Models\ModelAbstract;

class CartTotalData extends ModelAbstract
{
    public float $ttc = 0;
    public float $ht = 0;
    public array $tva = [];
    public float $total_tva = 0;
}
