# Pebble/Cart

Calcul des totaux d'un panier (HT, TTC, TVA par taux) avec les règles de TVA françaises : métropole, DOM, Union européenne (intracommunautaire ou non) et export.

La lib ne gère ni la persistance, ni les remises, ni les frais de port. Elle n'arrondit pas les montants au centime : c'est à l'appelant de le faire (voir [`TODO.md`](TODO.md)).

## Installation

```bash
composer require sopheos/pebble_cart
```

## Claude Code

Ce package fournit un skill Claude Code dans [`skills/pebble-cart/`](skills/pebble-cart/). Il documente les patterns d'usage et les pièges de la librairie : prix HT ou TTC selon `is_ttc`, `ttc` à 0 quand aucune TVA ne s'applique, montants non arrondis, total fourni jamais recalculé, codes pays sensibles à la casse, etc.

Dans un projet qui dépend de `sopheos/pebble_cart`, copie-le une fois dans `.claude/skills/` après `composer install` pour que Claude Code le charge automatiquement. Le nom du dossier doit correspondre au `name` déclaré dans `SKILL.md` :

```bash
cp -r vendor/sopheos/pebble_cart/skills/pebble-cart .claude/skills/pebble-cart
```

Pour la maintenance de la lib elle-même, voir [`CLAUDE.md`](CLAUDE.md). Les bugs connus sont listés dans [`TODO.md`](TODO.md).

## CartData

`\Pebble\Cart\CartData` étend `\Pebble\Models\ModelAbstract`. Le total est calculé à la construction, sauf si une clé `total` est fournie.

Propriétés :

* `is_btb` Client professionnel (défaut `false`).
* `is_ttc` Les prix des lignes sont TTC (défaut `true`) ou HT.
* `is_intraco` Client professionnel UE avec numéro de TVA intracommunautaire (défaut `false`).
* `country` Code pays ISO en majuscules (défaut `'FR'`).
* `items` Lignes `CartItemData` (les tableaux sont convertis à l'import).
* `total` Résultat `CartTotalData`.
* `mentions` Mentions d'exonération à imprimer sur la facture, sans doublon.

Méthodes :

* `__construct(array $data = [])` Importe les données puis calcule le total.
* `import(array $data = []) : static` Convertit `items` et `total`, puis recalcule le total si `total` n'est pas fourni.
* `total() : static` Recalcule `total` et `mentions`. À appeler après avoir modifié une propriété directement.
* `getTaxValue(CartItemData $item) : array` Renvoie `[taux, mention|null]` pour une ligne selon le client et le pays.
* `getAmount() : float` Montant à encaisser : `ttc`, ou `ht` si `ttc` vaut 0.
* `export() : array` Export tableau (hérité), réimportable tel quel.

Constantes : codes de taux `NO_RATE`, `NORMAL_RATE`, `INTERMEDIATE_RATE`, `REDUCED_RATE`, `SPECIAL_RATE` (libellés dans `RATE_LANG`), unités `DAYS_UNIT`/`HOURS_UNIT` (libellés dans `UNITS`), tables `TVA_METRO`, `TVA_DOM`, `TVA_EXO`, listes de pays `EU` et `DOM`.

```php
use Pebble\Cart\CartData;

$cart = new CartData([
    'is_ttc' => false,
    'items' => [
        ['label' => 'Formation', 'quantity' => 2, 'price' => 450, 'unit' => CartData::UNITS[CartData::DAYS_UNIT], 'taxe' => CartData::NORMAL_RATE],
    ],
]);

$cart->total->ht;        // 900
$cart->total->ttc;       // 1080
$cart->total->tva;       // ['0.2' => 180]
$cart->getAmount();      // 1080
```

### Règles de TVA

| Client | Pays | Services | Biens |
|---|---|---|---|
| Professionnel | `FR`, `MC` | métropole | métropole |
| Professionnel | `GP`, `MQ`, `RE` | DOM | exonéré (art. 294) |
| Professionnel | `GF`, `YT` | exonéré (art. 294) | exonéré (art. 294) |
| Professionnel | UE, `is_intraco` | exonéré (art. 283-2) | exonéré (art. 262-2) |
| Professionnel | UE sans `is_intraco` | métropole | métropole |
| Professionnel | autre | exonéré (art. 283-2) | exonéré (art. 262-1) |
| Particulier | `FR`, `MC` | métropole | métropole |
| Particulier | DOM | métropole | exonéré (art. 294) |
| Particulier | UE | métropole | métropole |
| Particulier | autre | métropole | exonéré (art. 262-1) |

La TVA est calculée sur le total de chaque taux, pas ligne par ligne. Quand aucune TVA ne s'applique, `total->ttc` vaut 0 : utiliser `getAmount()`.

## CartItemData

`\Pebble\Cart\CartItemData` décrit une ligne.

* `label` Désignation.
* `quantity` Quantité. Une ligne à 0 est ignorée.
* `price` Prix unitaire, HT ou TTC selon `CartData::$is_ttc`.
* `unit` Unité libre (par exemple `CartData::UNITS[CartData::HOURS_UNIT]`).
* `taxe` Code de taux (`CartData::NORMAL_RATE`…). Un code inconnu vaut un taux de 0.
* `is_service` Service (défaut `true`) ou bien.

## CartTotalData

`\Pebble\Cart\CartTotalData` porte le résultat.

* `ttc` Total TTC (0 si aucune TVA).
* `ht` Total HT.
* `tva` Montant de TVA par taux, la clé est le taux en chaîne (`'0.2'`, `'0.055'`).
* `total_tva` Somme des TVA.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Les bugs connus sont figés par des tests annotés `// BUG:` qui vérifient le comportement actuel.
