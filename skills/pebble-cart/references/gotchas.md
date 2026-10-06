# pebble-cart — gotchas

Things the property names don't tell you, grouped by topic. Every item below is pinned by a test in `tests/`. Items marked **(bug)** are listed in the package's `TODO.md` and may be fixed in a later version. Check the test of the same name in `vendor/sopheos/pebble_cart/tests/` to see the current behavior.

## Totals

- **Prices are TTC by default.** `is_ttc` defaults to `true`; in that mode VAT is extracted from the price (`ttc * (1 - 1 / (1 + rate))`). Pass `'is_ttc' => false` for HT prices.
- **VAT is grouped by rate.** Lines are summed per rate first, then VAT is computed once per rate. `total->tva` is keyed by the rate as a string (`'0.2'`, `'0.055'`) and omits rate 0.
- **`total->ttc` is 0 when total VAT is 0.** An exempt cart has `ht = 120`, `ttc = 0`, `tva = []`. `getAmount()` falls back to `ht`; use it instead of `total->ttc`.
- **In TTC mode an exempt customer pays the entered price as HT.** A 120 "TTC" line for an export business customer gives `ht = 120`; no VAT is removed.
- **Zero-quantity lines are skipped**, including their mention.
- **An unknown rate code gives 0 %**, silently.

## Rounding

- **VAT is computed on the total per rate, not per line.** Three lines at 0.99 TTC, 5.5 %, give `total_tva = 0.1548341232`, not `3 × 0.05`.
- **TTC mode rounds VAT to 10 decimals only.** 10 TTC at 20 % gives `total_tva = 1.6666666667` and `ht = 10 - 1.6666666667`.
- **(bug) Amounts are never rounded to the cent.** HT mode multiplies without rounding: 19.99 HT at 20 % gives `ttc = 23.988`, and `(int) ($cart->getAmount() * 100)` is `2398`. Three lines at 0.10 HT give `ht = 0.30000000000000004`. Always `round(..., 2)` before display or payment.

## VAT zones

- **The decision depends on `is_service`, `is_btb`, `country` and `is_intraco`.** See the table in `api-reference.md`; every row is covered by `testVatZones`.
- **`is_intraco` is only read for B2B customers in an `EU` country.** A B2B EU customer without it pays the mainland rate.
- **B2C services always use the mainland rate**, even in a DOM or outside the EU, while B2B services in `GP`/`MQ`/`RE` use `TVA_DOM`. Listed as "non vérifié" in `TODO.md`.
- **Exempt rates are the integer `0`**, other rates are floats.
- **Mentions are deduplicated**, in the order the lines produce them.
- **Country codes are case-sensitive.** `'fr'` matches no list and falls into the "anything else" branch, so goods are exempted under art. 262-1.

## Import / export

- **`import()` converts `items` and `total` arrays into objects**, and `export()` output can be passed back to the constructor unchanged.
- **A provided `total` is never recomputed.** `new CartData(['items' => …, 'total' => ['ttc' => 5]])` keeps `ttc = 5` and `ht = 0`.
- **Direct property assignments are not tracked.** Call `total()` after changing `country`, `is_btb`, `items`…
- **Unknown keys are silently ignored** (`ModelAbstract::__set` is a no-op): `'is_b2b' => true` leaves `is_btb` at `false`.
