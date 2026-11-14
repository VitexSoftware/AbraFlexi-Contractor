<?php

declare(strict_types=1);

/**
 * This file is part of the AbraflexiContractor package
 *
 * https://github.com/VitexSoftware/AbraFlexi-Contractor
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Contractor;

use AbraFlexi\Cenik;
use AbraFlexi\Functions;
use AbraFlexi\Smlouva;
use AbraFlexi\SmlouvaPolozka;

/**
 * Create AbraFlexi contracts and add or remove their items.
 *
 * Works on the evidences "smlouva" and "smlouva-polozka" of the AbraFlexi
 * company the connection is configured for.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class ContractManager
{
    /**
     * Columns read when items of a contract are listed.
     */
    public const ITEM_COLUMNS = ['id', 'kod', 'nazev', 'cenik', 'mnozMj', 'cenaMj'];

    /**
     * @param array<string, mixed> $options AbraFlexi connection options (url, company, user, password...)
     */
    public function __construct(private array $options = [])
    {
    }

    /**
     * Parse an item specification "PRICELIST[:QUANTITY[:UNIT_PRICE]]".
     *
     * @return array{cenik: string, mnozMj: float, cenaMj: ?float}
     *
     * @throws \InvalidArgumentException when the specification is not valid
     */
    public static function parseItem(string $spec): array
    {
        $parts = explode(':', trim($spec));

        // "code:XYZ" is the AbraFlexi way of naming a record by its code
        if (strtolower($parts[0]) === 'code' && \count($parts) > 1) {
            array_shift($parts);
        }

        $cenik = trim((string) array_shift($parts));

        if ($cenik === '') {
            throw new \InvalidArgumentException(_('Item needs a pricelist code'));
        }

        $quantity = $parts !== [] ? array_shift($parts) : '1';
        $price = $parts !== [] ? array_shift($parts) : null;

        if (!is_numeric($quantity) || (float) $quantity <= 0.0) {
            throw new \InvalidArgumentException(sprintf(_('Invalid quantity "%s" of item %s'), $quantity, $cenik));
        }

        if ($price !== null && !is_numeric($price)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid price "%s" of item %s'), $price, $cenik));
        }

        return ['cenik' => $cenik, 'mnozMj' => (float) $quantity, 'cenaMj' => $price === null ? null : (float) $price];
    }

    /**
     * Create a contract, optionally with items.
     *
     * @param string               $firma  customer: address book code (with or without "code:" prefix)
     * @param string               $type   contract type code
     * @param array<string, mixed> $data   extra contract columns (kod, nazev, smlouvaOd, smlouvaDo, ...)
     * @param array<int, string>   $items  item specifications, see parseItem()
     *
     * @return string code of the created contract
     *
     * @throws \AbraFlexi\Exception when AbraFlexi refuses the contract
     */
    public function createContract(string $firma, string $type, array $data = [], array $items = []): string
    {
        // Validate everything before the first write so a bad item cannot leave a half-built contract
        $parsedItems = array_map([self::class, 'parseItem'], $items);

        $contract = new Smlouva(null, $this->options);
        $row = array_merge([
            'firma' => $this->code($firma),
            'typSml' => $this->code($type),
            'smlouvaOd' => date('Y-m-d'),
        ], $data);

        if (empty($row['kod'])) {
            $row['kod'] = 'MF'.date('ymdHis');
        }

        if (empty($row['nazev'])) {
            $row['nazev'] = sprintf(_('Contract %s'), $row['kod']);
        }

        if ($parsedItems !== []) {
            // Items travel with the contract: AbraFlexi does not accept smlouva-polozka on its own
            $row['polozkySmlouvy'] = array_map([$this, 'itemRow'], $parsedItems);
        }

        if (!$contract->insertToAbraFlexi($row) || $contract->lastResponseCode !== 201) {
            throw new \AbraFlexi\Exception(sprintf(_('Contract %s was not created'), $row['kod']), $contract->lastResponseCode);
        }

        return (string) $row['kod'];
    }

    /**
     * Add an item to a contract.
     *
     * @param string      $contract contract code (with or without "code:" prefix) or numeric id
     * @param string      $cenik    pricelist item code
     * @param float       $quantity quantity in units
     * @param null|float  $price    unit price; the base price without VAT of the pricelist item when null
     * @param null|string $name     item name; the pricelist name is used when null
     *
     * @return int id of the created item
     *
     * @throws \AbraFlexi\Exception when AbraFlexi refuses the item
     */
    public function addItem(string $contract, string $cenik, float $quantity = 1.0, ?float $price = null, ?string $name = null): int
    {
        $before = array_keys($this->listItems($contract));
        $this->updateContract($contract, [
            'polozkySmlouvy' => [$this->itemRow(['cenik' => $cenik, 'mnozMj' => $quantity, 'cenaMj' => $price], $name)],
        ], sprintf(_('Item %s was not added to contract %s'), $cenik, $contract));
        $added = array_diff(array_keys($this->listItems($contract)), $before);

        return $added === [] ? 0 : (int) max($added);
    }

    /**
     * List the items of a contract.
     *
     * @return array<int, array<string, mixed>> items keyed by their id
     */
    public function listItems(string $contract): array
    {
        $item = new SmlouvaPolozka(null, $this->options);
        $rows = $item->getColumnsFromAbraFlexi(self::ITEM_COLUMNS, ['smlouva' => $this->code($contract)], 'id');
        $items = [];

        foreach (\is_array($rows) ? $rows : [] as $row) {
            // AbraFlexi returns a relation as a structure: keep only the pricelist code
            $cenik = $row['cenik'] ?? '';
            $row['cenik'] = Functions::uncode((string) (\is_array($cenik) ? ($cenik['value'] ?? '') : $cenik));
            $items[(int) $row['id']] = $row;
        }

        return $items;
    }

    /**
     * Remove items of a contract: one by id, or every item of a pricelist code.
     *
     * @param string   $contract contract code or numeric id
     * @param null|int $itemId   id of one item to remove
     * @param null|string $cenik pricelist code; removes all items of the contract with this pricelist item
     *
     * @return array<int, int> ids of the removed items
     *
     * @throws \InvalidArgumentException when neither item id nor pricelist code is given, or nothing matches
     * @throws \AbraFlexi\Exception      when AbraFlexi refuses to delete an item
     */
    public function removeItems(string $contract, ?int $itemId = null, ?string $cenik = null): array
    {
        if ($itemId === null && ($cenik === null || $cenik === '')) {
            throw new \InvalidArgumentException(_('Give the item id or the pricelist code to remove'));
        }

        $present = $this->listItems($contract);
        $victims = [];

        foreach ($present as $id => $row) {
            if ($itemId !== null && $id === $itemId) {
                $victims[] = $id;
            } elseif ($cenik !== null && $cenik !== '' && $this->sameCode((string) ($row['cenik'] ?? ''), $cenik)) {
                $victims[] = $id;
            }
        }

        if ($victims === []) {
            throw new \InvalidArgumentException(sprintf(_('Contract %s has no such item'), $contract));
        }

        $this->updateContract($contract, [
            'polozkySmlouvy' => array_map(static fn (int $id): array => ['id' => $id, '@action' => 'delete'], $victims),
        ], sprintf(_('Items of contract %s were not removed'), $contract));

        return $victims;
    }

    /**
     * Build the AbraFlexi row of one contract item.
     *
     * @param array{cenik: string, mnozMj: float, cenaMj: ?float} $item
     *
     * @return array<string, mixed>
     */
    private function itemRow(array $item, ?string $name = null): array
    {
        $row = ['cenik' => $this->code($item['cenik']), 'mnozMj' => $item['mnozMj']];

        $price = $item['cenaMj'] ?? $this->pricelistPrice($item['cenik']);

        if ($price !== null) {
            $row['cenaMj'] = $price;
        }

        if ($name !== null && $name !== '') {
            $row['nazev'] = $name;
        }

        return $row;
    }

    /**
     * Base price without VAT of a pricelist item; AbraFlexi does not copy it into contract items on its own.
     */
    private function pricelistPrice(string $cenik): ?float
    {
        $pricelist = new Cenik($this->code($cenik), array_merge($this->options, ['ignore404' => true]));
        $price = $pricelist->getDataValue('cenaZaklBezDph');

        return is_numeric($price) ? (float) $price : null;
    }

    /**
     * Update an existing contract (used for its sub-items).
     *
     * @param array<string, mixed> $changes
     *
     * @throws \AbraFlexi\Exception when AbraFlexi refuses the change
     */
    private function updateContract(string $contract, array $changes, string $failure): void
    {
        $record = new Smlouva(null, $this->options);

        if (!$record->insertToAbraFlexi(array_merge(['id' => $this->code($contract)], $changes)) || $record->lastResponseCode !== 201) {
            throw new \AbraFlexi\Exception($failure, $record->lastResponseCode);
        }
    }

    /**
     * Compare two AbraFlexi record references ignoring "code:" prefix and letter case.
     */
    private function sameCode(string $a, string $b): bool
    {
        return strcasecmp(Functions::uncode($a), Functions::uncode($b)) === 0;
    }

    /**
     * Turn a plain code (or numeric id) into an AbraFlexi record reference.
     */
    private function code(string $code): string
    {
        $code = trim($code);

        if (is_numeric($code) || str_starts_with($code, 'code:') || str_starts_with($code, 'ext:')) {
            return $code;
        }

        return Functions::code($code);
    }
}
