# pebble-cart — API cheat sheet

Quick lookup by intent. This is not exhaustive. Read the source in `vendor/sopheos/pebble_cart/src/` for exact signatures and for edge cases not covered here.

All classes extend `Pebble\Models\ModelAbstract`: `new X(array $data)`, `X::create(array $data)`, `import(array): static`, `export(): array`, `jsonSerialize()`. Unknown keys are ignored.

## CartData (`Pebble\Cart\CartData`)

### Properties

| Property | Type | Default | Meaning |
| -------- | ---- | ------- | ------- |
| `is_btb` | `bool` | `false` | Business customer |
| `is_ttc` | `bool` | `true` | Item prices include VAT |
| `is_intraco` | `bool` | `false` | EU business customer with an intra-EU VAT number (only read when `is_btb` and country in `EU`) |
| `country` | `string` | `'FR'` | Upper-case ISO 3166 alpha-2 code |
| `items` | `CartItemData[]` | `[]` | Arrays are converted by `import()` |
| `total` | `CartTotalData` | computed | Not nullable |
| `mentions` | `string[]` | `[]` | Distinct exemption mentions, in line order |

### Methods

| Intent | Method |
| ------ | ------ |
| Import data, convert `items`/`total`, recompute unless `total` given | `import(array $data = []): static` |
| Recompute `total` and `mentions` | `total(): static` |
| Rate and mention for one line | `getTaxValue(CartItemData $item): array` → `[int\|float $rate, ?string $mention]` |
| Amount to charge | `getAmount(): float` → `total->ttc ?: total->ht` |

### Constants

| Constant | Value |
| -------- | ----- |
| `NO_RATE` / `NORMAL_RATE` / `INTERMEDIATE_RATE` / `REDUCED_RATE` / `SPECIAL_RATE` | `0` / `1` / `2` / `3` / `4` |
| `RATE_LANG` | French labels per rate code |
| `DAYS_UNIT` / `HOURS_UNIT`, `UNITS` | `0` / `1`, labels `jour(s)` / `heure(s)` |
| `TVA_METRO` | normal 0.20, intermediate 0.10, reduced 0.055, special 0.021 |
| `TVA_DOM` | normal 0.085, intermediate 0.021, reduced 0.021, special 0.0175 |
| `TVA_EXO` | all 0 |
| `EU` | 26 EU member codes, without `FR` (Greece is `GR`) |
| `DOM` | `GP`, `MQ`, `GF`, `RE`, `YT` |

### VAT decision tree (`getTaxValue`)

| Customer | Country | Services | Goods |
| -------- | ------- | -------- | ----- |
| B2B | `FR`, `MC` | `TVA_METRO` | `TVA_METRO` |
| B2B | `GP`, `MQ`, `RE` | `TVA_DOM` | exempt, art. 294 |
| B2B | `GF`, `YT` | exempt, art. 294 | exempt, art. 294 |
| B2B | `EU` + `is_intraco` | exempt, art. 283-2 | exempt, art. 262-2 |
| B2B | `EU` without `is_intraco` | `TVA_METRO` | `TVA_METRO` |
| B2B | anything else | exempt, art. 283-2 | exempt, art. 262-1 |
| B2C | `FR`, `MC` | `TVA_METRO` | `TVA_METRO` |
| B2C | `DOM` | `TVA_METRO` | exempt, art. 294 |
| B2C | `EU` | `TVA_METRO` | `TVA_METRO` |
| B2C | anything else | `TVA_METRO` | exempt, art. 262-1 |

## CartItemData (`Pebble\Cart\CartItemData`)

| Property | Type | Default |
| -------- | ---- | ------- |
| `label` | `string` | `''` |
| `quantity` | `float` | `0` (a 0 line is skipped) |
| `price` | `float` | `0.0` (unit price, HT or TTC per `CartData::$is_ttc`) |
| `unit` | `?string` | `null` |
| `taxe` | `int` | `CartData::NO_RATE` |
| `is_service` | `bool` | `true` |

## CartTotalData (`Pebble\Cart\CartTotalData`)

| Property | Type | Meaning |
| -------- | ---- | ------- |
| `ht` | `float` | Total excluding VAT |
| `ttc` | `float` | Total including VAT, **0 when total VAT is 0** |
| `tva` | `array` | VAT amount per rate, keyed by the rate as a string (`'0.2'`, `'0.055'`); rate 0 is omitted |
| `total_tva` | `float` | Sum of `tva` |
