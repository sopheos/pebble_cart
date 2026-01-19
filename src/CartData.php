<?php

namespace Pebble\Cart;

use InvalidArgumentException;
use Pebble\Models\ModelAbstract;

class CartData extends ModelAbstract
{
    const NO_RATE = 0;
    const NORMAL_RATE = 1;
    const INTERMEDIATE_RATE = 2;
    const REDUCED_RATE = 3;
    const SPECIAL_RATE = 4;

    const DAYS_UNIT = 0;
    const HOURS_UNIT = 1;

    const RATE_LANG = [
        self::NO_RATE => "Taux non-applicable",
        self::NORMAL_RATE => "Taux normal",
        self::INTERMEDIATE_RATE => "Taux intermédiaire",
        self::REDUCED_RATE => "Taux réduit",
        self::SPECIAL_RATE => "Taux spécial",
    ];

    const UNITS = [
        self::DAYS_UNIT => "jour(s)",
        self::HOURS_UNIT => "heure(s)",
    ];

    const TVA_METRO = [
        self::NORMAL_RATE => 0.20,
        self::INTERMEDIATE_RATE => 0.10,
        self::REDUCED_RATE => 0.055,
        self::SPECIAL_RATE => 0.021,
    ];

    const TVA_DOM = [
        self::NORMAL_RATE => 0.085,
        self::INTERMEDIATE_RATE => 0.021,
        self::REDUCED_RATE => 0.021,
        self::SPECIAL_RATE => 0.0175,
    ];

    const TVA_EXO = [
        self::NORMAL_RATE => 0,
        self::INTERMEDIATE_RATE => 0,
        self::REDUCED_RATE => 0,
        self::SPECIAL_RATE => 0,
    ];

    const EU = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "DE", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "SE"];
    const DOM = ["GP", "MQ", "GF", "RE", "YT"];

    public bool $is_btb = false;
    public bool $is_ttc = true;
    public bool $is_intraco = false;
    public string $country = 'FR';

    /**
     * @var CartItemData[]
     */
    public array $items = [];

    public CartTotalData $total;

    /**
     * @var string[]
     */
    public array $mentions = [];

    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------

    public function __construct(array $data = [])
    {
        $this->total = new CartTotalData();
        parent::__construct($data);
    }

    public function import(array $data = []): static
    {
        foreach ($data['items'] ?? [] as  $k => $item) {
            if (is_array($item)) {
                $data['items'][$k] = new CartItemData($item);
            }
        }

        if (is_array($data['total'] ?? null)) {
            $data['total'] = new CartTotalData($data['total']);
        }

        parent::import($data);

        if (! ($data['total'] ?? null)) {
            $this->total();
        }

        return $this;
    }

    // -------------------------------------------------------------------------

    /**
     * Met à jour le total
     *
     * @return static
     */
    public function total(): static
    {
        $totalByTax = [];
        $mentions = [];

        foreach ($this->items as $item) {

            if (!$item->quantity) {
                continue;
            }

            [$taxe, $mention] = $this->getTaxValue($item);

            if ($taxe < 0) {
                throw new InvalidArgumentException("taxe MUST BE supperior or equal to 0. {$taxe} is given");
            }

            $key = (string) $taxe;

            if (!isset($totalByTax[$key])) {
                $totalByTax[$key] = 0;
            }

            $totalByTax[$key] += $item->quantity * $item->price;

            if ($mention && !isset($mentions[$mention])) {
                $mentions[$mention] = $mention;
            }
        }

        $this->total = $this->is_ttc ? self::totalFromTtc($totalByTax) : self::totalFromHt($totalByTax);
        $this->mentions = array_values($mentions);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getAmount(): float
    {
        return $this->total->ttc ?: $this->total->ht;
    }

    /**
     * @param CartItemData $item
     * @return array taxe, mention
     */
    public function getTaxValue(CartItemData $item): array
    {
        // Vente de services
        if ($item->is_service) {
            // Professionnels
            if ($this->is_btb) {
                if (in_array($this->country, ["FR", "MC"])) {
                    return self::taxTableResult(self::TVA_METRO, $item->taxe);
                } elseif (in_array($this->country, ["GP", "MQ", "RE"])) {
                    return self::taxTableResult(self::TVA_DOM, $item->taxe);
                } elseif (in_array($this->country, ["GF", "YT"])) {
                    return self::taxTableResult(self::TVA_EXO, $item->taxe, "Exonération de TVA, article 294 du Code général des impôts et auto liquidation.");
                } elseif (in_array($this->country, self::EU)) {
                    if ($this->is_intraco) {
                        return self::taxTableResult(self::TVA_EXO, $item->taxe, "Exonération de TVA, article 283-2 du Code général des impôts et auto liquidation.");
                    } else {
                        return self::taxTableResult(self::TVA_METRO, $item->taxe);
                    }
                } else {
                    return self::taxTableResult(self::TVA_EXO, $item->taxe, "Exonération de TVA, article 283-2 du Code général des impôts et auto liquidation.");
                }
            }
            // Particuliers
            else {
                return self::taxTableResult(self::TVA_METRO, $item->taxe);
            }
        }
        // Vente de biens
        else {
            // Professionnels
            if ($this->is_btb) {
                if (in_array($this->country, ["FR", "MC"])) {
                    return self::taxTableResult(self::TVA_METRO, $item->taxe);
                } elseif (in_array($this->country, self::DOM)) {
                    return self::taxTableResult(self::TVA_EXO, $item->taxe, "Exonération de TVA, article 294 du Code général des impôts et auto liquidation.");
                } elseif (in_array($this->country, self::EU)) {
                    if ($this->is_intraco) {
                        return self::taxTableResult(self::TVA_EXO, $item->taxe, "Exonération de TVA, article 262-2 du Code général des impôts et auto liquidation.");
                    } else {
                        return self::taxTableResult(self::TVA_METRO, $item->taxe);
                    }
                } else {
                    return self::taxTableResult(self::TVA_EXO, $item->taxe, "Exonération de TVA, article 262-1 du Code général des impôts et auto liquidation.");
                }
            }
            // Particuliers
            else {
                if (in_array($this->country, ["FR", "MC"])) {
                    return self::taxTableResult(self::TVA_METRO, $item->taxe);
                } elseif (in_array($this->country, self::DOM)) {
                    return self::taxTableResult(self::TVA_EXO, $item->taxe, "Exonération de TVA, article 294 du Code général des impôts et auto liquidation.");
                } elseif (in_array($this->country, self::EU)) {
                    return self::taxTableResult(self::TVA_METRO, $item->taxe);
                } else {
                    return self::taxTableResult(self::TVA_EXO, $item->taxe, "Exonération de TVA, article 262-1 du Code général des impôts et auto liquidation.");
                }
            }
        }

        return self::taxTableResult(self::TVA_EXO, $item->taxe, "TVA non applicable et auto liquidation.");
    }


    /**
     * Retourne la valeur de la TVA et une mention spéciale si il y en a une
     *
     * @param array $table
     * @param integer $taxe
     * @param string|null $mention
     * @return array
     */
    private static function taxTableResult(array $table, int $taxe, string $mention = null): array
    {
        return [$table[$taxe] ?? 0, $mention];
    }

    /**
     * Calcule du total HT
     *
     * @param array $totalByTax
     * @return CartTotalData
     */
    private static function totalFromHt(array $totalByTax): CartTotalData
    {
        $total = new CartTotalData();

        foreach ($totalByTax as $taxe => $ht) {
            $tx = (float) $taxe;
            $ttc = 0;
            $tva = 0;

            // Calcul du ttc en fonction du taux de tva
            if ($tx > 0) {
                $tva = self::tvaFromHt($ht, $tx);
                $ttc = $ht + $tva;
            } else {
                $ttc = $ht;
            }

            // Incrementation des totaux
            $total->ttc += $ttc;
            $total->ht += $ht;
            $total->total_tva += $tva;

            if ($tx > 0) {
                $total->tva[$taxe] = $tva;
            }
        }

        // Mise à jours du total tva si necessaire
        if (!$total->total_tva) {
            $total->tva = [];
            $total->ttc = 0;
        }

        return $total;
    }

    /**
     * Calcule du total TTC
     *
     * @return CartTotalData
     */
    private static function totalFromTtc(array $totalByTax): CartTotalData
    {
        $total = new CartTotalData();

        foreach ($totalByTax as $taxe => $ttc) {
            $tx = (float) $taxe;
            $ht = 0;
            $tva = 0;

            // Calcul du ht en fonction du taux de tva
            if ($tx > 0) {
                $tva = self::tvaFromTtc($ttc, $tx);
                $ht = $ttc - $tva;
            } else {
                $ht = $ttc;
            }

            // Incrementation des totaux
            $total->ttc += $ttc;
            $total->ht += $ht;
            $total->total_tva += $tva;

            if ($tx > 0) {
                $total->tva[$taxe] = $tva;
            }
        }

        // Mise à jours du total tva si necessaire
        if (!$total->total_tva) {
            $total->tva = [];
            $total->ttc = 0;
        }

        return $total;
    }

    /**
     * Calcule du prix TTC à partir du prix HT
     *
     * @param float $ht
     * @param float $tx
     * @return float
     */
    private static function tvaFromHt(float $ht, float $tx): float
    {
        return $ht * $tx;
    }

    /**
     * Calcule du total tva unitaire
     *
     * @param float $ttc
     * @param float $tx
     * @return float
     */
    private static function tvaFromTtc(float $ttc, float $tx): float
    {
        return round($ttc * (1 - 1 / (1 + $tx)), 10);
    }

    // -------------------------------------------------------------------------
}
