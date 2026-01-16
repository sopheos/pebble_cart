<?php

namespace Pebble\Cart;

use Pebble\Models\ModelAbstract;

class CartItemData extends ModelAbstract
{
    /**
     * Désignation du produit ou du service
     */
    public string $label = '';

    /**
     * Quantité
     */
    public float $quantity = 0;

    /**
     * Prix unitaire en euros
     */
    public float $price = 0.0;

    /**
     * Unité lié à la quantité
     */
    public ?string $unit = null;

    /**
     * TVA appliquable
     */
    public int $taxe = CartData::NO_RATE;

    /**
     * L'article est un service
     */
    public bool $is_service = true;


    public function __construct(array $data = [])
    {
        if ($data) {
            $this->import($data);
        }
    }
}
