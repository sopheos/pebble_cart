---
name: pebble-cart
description: How to correctly build a shopping cart and compute its HT/TTC/VAT totals with French VAT rules using the sopheos/pebble_cart PHP library (namespace Pebble\Cart — classes CartData, CartItemData, CartTotalData). Use this whenever the project's composer.json requires sopheos/pebble_cart, code imports from Pebble\Cart\*, or you're asked to add a product line, compute an order or invoice total, pick a VAT rate, handle a foreign, DOM or intra-EU customer, print VAT exemption mentions, or send an amount to a payment provider in a PHP project that has this library available — even if the request is phrased generically like "compute the price with tax" or "why is the invoice off by one cent" without naming the library. Also check this before hand-rolling VAT math or a country switch in such a project, since this library replaces those and has non-obvious behavior (prices are TTC by default, ttc is 0 when no VAT applies, amounts are never rounded to the cent, a provided total is never recomputed, country codes are case-sensitive, direct property changes need total()) that hand-rolled code would miss.
---

# pebble-cart

`sopheos/pebble_cart` is a small PHP 8.1+ cart calculator. A `CartData` holds a customer profile (business or not, country, intra-EU VAT number or not), whether line prices are HT or TTC, and a list of `CartItemData` lines. On construction it groups the lines by VAT rate and fills a `CartTotalData` (`ht`, `ttc`, `tva` per rate, `total_tva`) plus the list of VAT exemption `mentions` to print on the invoice. It does **not** persist anything, handle discounts or shipping, or round amounts to the cent.

Namespace: `Pebble\Cart\*`. All three classes extend `Pebble\Models\ModelAbstract` (from `sopheos/pebble_models`): build them from arrays, read public properties, `export()` to arrays. Source lives in `vendor/sopheos/pebble_cart/src/`.

## Orientation

- `CartData` is the cart. `new CartData([...])` imports the data and computes `total` and `mentions` immediately.
- `CartItemData` is a line: `label`, `quantity`, `price` (unit price), `unit`, `taxe` (a rate **code**, not a percentage), `is_service`.
- `CartTotalData` is the result: `ht`, `ttc`, `tva` (`['0.2' => 20.0, …]`), `total_tva`.
- `CartData::getTaxValue($item)` is the French VAT decision tree. It returns `[rate, mention|null]`.
- `CartData::getAmount()` is the amount to charge: `ttc`, or `ht` when `ttc` is 0.

For a full property and constant cheat sheet, see `references/api-reference.md`. For the complete list of easy-to-miss behaviors, see `references/gotchas.md`. Read it before debugging a total that "looks wrong".

## Core recipes

### Build a cart and read the totals

```php
use Pebble\Cart\CartData;

$cart = new CartData([
    'is_ttc' => false,                         // prices below are HT (default is TTC!)
    'is_btb' => true,
    'country' => 'FR',                         // upper-case ISO code
    'items' => [
        ['label' => 'Formation', 'quantity' => 2, 'price' => 450, 'taxe' => CartData::NORMAL_RATE],
        ['label' => 'Livre', 'quantity' => 1, 'price' => 30, 'taxe' => CartData::REDUCED_RATE, 'is_service' => false],
    ],
]);

$cart->total->ht;         // 930
$cart->total->tva;        // ['0.2' => 180, '0.055' => 1.65]
$cart->total->total_tva;  // 181.65
$cart->mentions;          // [] — exemption texts to print, when any
```

`taxe` is one of `CartData::NO_RATE`, `NORMAL_RATE`, `INTERMEDIATE_RATE`, `REDUCED_RATE`, `SPECIAL_RATE`. The actual percentage depends on the zone (`TVA_METRO`, `TVA_DOM` or `TVA_EXO`).

### Charge the customer

```php
$cents = (int) round($cart->getAmount() * 100);
```

Always round yourself. The library never rounds to the cent, and `(int) ($amount * 100)` alone loses a cent on values like `23.988`.

### Change the customer after construction

```php
$cart->import(['country' => 'DE', 'is_intraco' => true]);  // recomputes
// or
$cart->country = 'DE';
$cart->total();                                             // required after direct assignment
```

### Store and reload

```php
$json = json_encode($cart->export());
$cart = new CartData(json_decode($json, true));   // total is restored, NOT recomputed
```

## Behavior to keep in mind while writing code

- **Prices are TTC by default** (`is_ttc = true`). Pass `'is_ttc' => false` for HT prices.
- **`total->ttc` is 0 when no VAT applies at all** (export, intra-EU, DOM goods). Use `getAmount()` for the amount to charge, never `total->ttc` directly.
- **In TTC mode an exempt customer pays the entered price as HT.** Nothing is deducted from a 120 TTC price for an export customer.
- **Amounts are never rounded to the cent.** HT mode gives `23.988` for 19.99 HT at 20 % and float noise like `0.30000000000000004`; TTC mode rounds VAT to 10 decimals only.
- **VAT is computed on the total of each rate, not per line.**
- **A provided `total` is never recomputed.** `new CartData(['items' => …, 'total' => …])` keeps the given total, even if stale.
- **Direct property changes are not tracked.** Call `total()` after assigning `country`, `is_btb`, `items`…
- **Country codes are case-sensitive.** `'fr'` is treated as an export country and exempted.
- **Unknown keys and unknown rate codes are silently ignored** (`'is_b2b'` does nothing, `taxe => 9` gives 0 %).
- **B2C services always use the mainland rate**, even for a DOM or non-EU customer.

Read `references/gotchas.md` for the full list before assuming a VAT result is a bug.
