# TODO — pebble_cart

Problèmes restant à traiter, détectés lors de l'audit du 2026-10-06. Le code `src/` n'a **pas** été modifié. Chaque bug est figé par un test qui vérifie le comportement actuel : il faut l'adapter au moment de la correction.

## Bugs

- [ ] **Aucun montant n'est arrondi au centime.** `src/CartData.php:328, 340`.
  - En mode HT, `tvaFromHt()` renvoie `$ht * $tx` brut : 19,99 HT à 20 % donne `ttc = 23.988`, et `(int) ($cart->getAmount() * 100)` vaut `2398` au lieu de `2399`. Le bruit flottant s'accumule aussi : 3 × 0,10 HT donne `ht = 0.30000000000000004`. En mode TTC, la TVA est arrondie à 10 décimales seulement, et `ht = ttc - tva` hérite du bruit.
  - Impact : montants envoyés à un prestataire de paiement ou imprimés sur une facture faux d'un centime selon la façon dont l'appelant arrondit, et somme des TVA par taux différente de `total_tva` après arrondi côté affichage.
  - Correctif : arrondir `tva` à 2 décimales par taux (`round(..., 2)`) puis dériver `ttc`/`ht` et les totaux de valeurs arrondies. Garder le calcul sur le total par taux (pas par ligne).
  - Test : `tests/CartDataTest.php::testAmountsAreNeverRoundedToTheCent`.
- [ ] **Services B2C vers un DOM facturés au taux métropole (non vérifié, règle fiscale).** `src/CartData.php:183-185`.
  - Un particulier en Guadeloupe paie 20 % sur un service, alors qu'un professionnel au même endroit paie 8,5 % (`TVA_DOM`). L'incohérence est reproduite, mais la règle applicable doit être confirmée par le comptable.
  - Correctif : à décider avec le comptable.
  - Test : `tests/CartDataTest.php::testVatZones` (jeu « service btc GP »).

## Dette / qualité

- [ ] `src/CartData.php:219` : `return` inatteignable, toutes les branches de `getTaxValue()` retournent avant. Sa mention « TVA non applicable et auto liquidation. » n'est jamais produite.
- [ ] `src/CartData.php:124-126` : l'exception « taxe MUST BE supperior » est inatteignable (aucune table ne contient de taux négatif), et le message contient une faute (« supperior » pour « superior »).
- [ ] `src/CartData.php:233` : un code de taux inconnu donne silencieusement un taux de 0 au lieu de lever une exception.
- [ ] `src/CartData.php:128` : la clé du tableau `tva` est `(string) $taxe`, donc dépend de l'ini `precision` (« 0.2 » par défaut, « 0.20000000000000001 » avec `precision=17`).
- [ ] `src/CartData.php:166, 191, 207` : pays comparés sensiblement à la casse, sans normalisation : `'fr'` est traité comme un pays d'export (exonéré).
- [ ] `getTaxValue()` renvoie `0` (int) pour un taux exonéré et un `float` sinon.
- [ ] `src/CartData.php:73-75` : bannières de section vides en double.
- [ ] Décision métier à documenter : en mode TTC, un client exonéré (export, intracommunautaire) paie le prix TTC saisi comme montant HT, aucune TVA n'est déduite.
