<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use Bedriox\Data\AdmittedRegistryDataSet;
use Bedriox\Data\ItemNetworkRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ItemNetworkRegistryTest extends TestCase
{
    public function testAdmittedItemsAreAvailableByIdentifierAndRuntimeId(): void
    {
        $data = AdmittedRegistryDataSet::bundled();
        $registry = $data->itemNetworkRegistry();

        self::assertCount(2_076, $registry->definitions());
        $musicDisc = $registry->definitionForIdentifier('minecraft:music_disc_bounce');
        self::assertSame('minecraft:music_disc_bounce', $musicDisc->identifier());
        self::assertSame(837, $musicDisc->networkRuntimeId());
        self::assertTrue($musicDisc->componentBased());
        self::assertSame(2, $musicDisc->version());
        self::assertSame($musicDisc, $registry->definitionForNetworkRuntimeId(837));

        $cookedCod = $registry->definitionForIdentifier('minecraft:cooked_cod');
        self::assertNotNull($cookedCod->componentNbt());
        $encodedComponentNbt = $data->requiredItems()['minecraft:cooked_cod']['component_nbt'] ?? null;
        self::assertIsString($encodedComponentNbt);
        self::assertSame(base64_decode($encodedComponentNbt, true), $cookedCod->componentNbt());
        self::assertSame($cookedCod, $registry->definitionForNetworkRuntimeId($cookedCod->networkRuntimeId()));
        self::assertSame(-1_125, $registry->definitionForIdentifier('minecraft:sulfur_spike')->networkRuntimeId());
    }

    public function testUnknownLookupsFailClosed(): void
    {
        $registry = AdmittedRegistryDataSet::bundled()->itemNetworkRegistry();
        foreach ([
            static fn() => $registry->definitionForIdentifier('minecraft:not_admitted'),
            static fn() => $registry->definitionForNetworkRuntimeId(32_768),
        ] as $lookup) {
            try {
                $lookup();
                self::fail('An unknown item-network lookup was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testConstructionRejectsInvalidAndDuplicateDefinitions(): void
    {
        $valid = ['runtime_id' => 1, 'component_based' => false, 'version' => 0];
        foreach ([
            [],
            ['invalid' => $valid],
            ['minecraft:first' => $valid, 'minecraft:second' => $valid],
            ['minecraft:item' => ['runtime_id' => 32_768, 'component_based' => false, 'version' => 0]],
            ['minecraft:item' => ['runtime_id' => 1, 'component_based' => false, 'version' => 3]],
            ['minecraft:item' => ['runtime_id' => 1, 'component_based' => false, 'version' => 0, 'component_nbt' => '%%%']],
            ['minecraft:item' => ['runtime_id' => 1, 'component_based' => false]],
            ['minecraft:item' => ['runtime_id' => 1, 'component_based' => false, 'version' => 0, 'unexpected' => true]],
        ] as $items) {
            try {
                ItemNetworkRegistry::fromRequiredItems($items);
                self::fail('An invalid item network registry was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
