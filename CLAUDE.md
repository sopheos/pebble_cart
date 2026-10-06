# CLAUDE.md — pebble_cart

Ce fichier guide Claude Code quand il **maintient** cette librairie. Pour l'**utiliser** depuis un projet, voir le skill [`skills/pebble-cart/`](skills/pebble-cart/SKILL.md).

## Rôle

`sopheos/pebble_cart`, namespace `Pebble\Cart\`, PHP >= 8.1, dépend de `sopheos/pebble_models` (`Pebble\Models\ModelAbstract`). La lib fournit :
- un panier `CartData` qui calcule ses totaux HT/TTC/TVA à partir de lignes `CartItemData` ;
- le choix du taux de TVA selon les règles françaises (métropole, DOM, UE intracommunautaire, export), avec les mentions d'exonération à faire figurer sur la facture.

Pas de persistance, pas de remises, pas de frais de port, pas d'arrondi au centime.

## Commandes

```bash
composer install
vendor/bin/phpunit            # toute la suite
vendor/bin/phpunit --filter CartDataTest
```

## Carte de `src/`

| Fichier | Rôle |
|---|---|
| `CartData.php` | Panier : constantes de taux (`NORMAL_RATE`…), tables `TVA_METRO`/`TVA_DOM`/`TVA_EXO`, listes `EU`/`DOM`, `import()` (convertit les tableaux et recalcule), `total()`, `getTaxValue()` (arbre de décision des zones), `getAmount()` |
| `CartItemData.php` | Ligne : libellé, quantité, prix unitaire (HT ou TTC selon `CartData::$is_ttc`), unité, code de taux, `is_service` |
| `CartTotalData.php` | Résultat : `ttc`, `ht`, `tva` (montant par taux, clé = taux en chaîne), `total_tva` |

## Tests

- PHPUnit 9.5. `tests/bootstrap.php` ne charge que l'autoload et la locale.
- Les classes de test n'ont pas de namespace. Les méthodes s'appellent `testPhraseEnCamelCase`, les assertions passent par `self::assertSame`, et des bannières `// ----` séparent les sections.
- `testVatZones` couvre l'arbre de décision de `getTaxValue()` par un data provider : une ligne par zone. Toute modification des règles fiscales doit y être reflétée.
- Les montants flottants se comparent avec `assertSame` quand la valeur est exacte, sinon `assertEqualsWithDelta`.

## Conventions du code

Respecter le style existant, sans le « moderniser » au passage :
- pas de `declare(strict_types=1)` ;
- constantes `public const`, messages et commentaires en français ;
- arbres `if / elseif / else` explicites dans `getTaxValue()` plutôt que des tables ;
- docblocks `@param` / `@return` sur les helpers privés.

Une modification de comportement doit être répercutée dans `skills/pebble-cart/` (SKILL.md, `references/api-reference.md`, `references/gotchas.md`) et dans le `README.md`.

## Bugs connus

Ils sont listés dans [`TODO.md`](TODO.md). Chacun est **figé par un test** annoté `// BUG:` qui vérifie le comportement *actuel*, dans la section « Known bugs » de `tests/CartDataTest.php`.

Pour corriger un bug :
1. Corriger `src/`.
2. Réécrire le test `// BUG:` pour qu'il vérifie le comportement attendu.
3. Mettre à jour l'entrée « (bug) » de `skills/pebble-cart/references/gotchas.md` et le SKILL.md.
4. Retirer l'entrée de `TODO.md` (il ne liste que ce qui reste à faire).
