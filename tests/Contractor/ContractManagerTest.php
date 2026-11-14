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

namespace Test\AbraFlexi\Contractor;

use AbraFlexi\Contractor\ContractManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Item specification parsing; the AbraFlexi calls themselves need a live server.
 */
class ContractManagerTest extends TestCase
{
    public function testPricelistCodeOnlyMeansOneUnitAtPricelistPrice(): void
    {
        $this->assertSame(['cenik' => 'MULTIFLEXI', 'mnozMj' => 1.0, 'cenaMj' => null], ContractManager::parseItem('MULTIFLEXI'));
    }

    public function testQuantityAndPrice(): void
    {
        $this->assertSame(['cenik' => 'MULTIFLEXI', 'mnozMj' => 3.0, 'cenaMj' => 49.5], ContractManager::parseItem('MULTIFLEXI:3:49.5'));
    }

    public function testQuantityWithoutPrice(): void
    {
        $this->assertSame(['cenik' => 'NEXTCLOUD1', 'mnozMj' => 2.0, 'cenaMj' => null], ContractManager::parseItem('NEXTCLOUD1:2'));
    }

    public function testCodePrefixIsStripped(): void
    {
        $this->assertSame(['cenik' => 'MULTIFLEXI', 'mnozMj' => 2.0, 'cenaMj' => null], ContractManager::parseItem('code:MULTIFLEXI:2'));
    }

    public function testZeroPriceIsKept(): void
    {
        $this->assertSame(0.0, ContractManager::parseItem('MULTIFLEXI:1:0')['cenaMj']);
    }

    #[DataProvider('invalidSpecifications')]
    public function testInvalidSpecificationIsRejected(string $specification): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ContractManager::parseItem($specification);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidSpecifications(): array
    {
        return [
            'empty' => [''],
            'no code' => [':2'],
            'text quantity' => ['MULTIFLEXI:abc'],
            'zero quantity' => ['MULTIFLEXI:0'],
            'negative quantity' => ['MULTIFLEXI:-1'],
            'text price' => ['MULTIFLEXI:1:free'],
        ];
    }
}
