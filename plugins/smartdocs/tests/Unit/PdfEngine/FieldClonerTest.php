<?php

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Tests\Unit\PdfEngine;

use GlpiPlugin\SmartDocs\PdfEngine\FieldCloner;
use PHPUnit\Framework\TestCase;

final class FieldClonerTest extends TestCase
{
    public function testCloneForItemsWithOneItem(): void
    {
        $cloner = new FieldCloner();
        $fields = [
            ['id' => 1, 'scope' => 'global', 'page_index' => 0, 'position' => json_encode(['x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.04])],
            ['id' => 2, 'scope' => 'item', 'slot_index' => 0, 'page_index' => 0, 'position' => json_encode(['x' => 0.1, 'y' => 0.2, 'width' => 0.2, 'height' => 0.04])],
            ['id' => 3, 'scope' => 'item', 'slot_index' => 1, 'page_index' => 0, 'position' => json_encode(['x' => 0.5, 'y' => 0.2, 'width' => 0.2, 'height' => 0.04])],
        ];

        $result = $cloner->cloneForItems($fields, 1);

        // 1 item = 1 global + 2 itens (slot 0 e 1)
        self::assertCount(3, $result);

        // Global
        $global = array_values(array_filter($result, static fn($f) => $f['id'] === 1));
        self::assertCount(1, $global);
        self::assertSame(0, $global[0]['item_index']);
        self::assertSame(0, $global[0]['computedPageIndex']);

        // Item slot 0
        $slot0 = array_values(array_filter($result, static fn($f) => $f['id'] === 2));
        self::assertCount(1, $slot0);
        self::assertSame(0, $slot0[0]['item_index']);
        self::assertSame(0, $slot0[0]['computedPageIndex']);

        // Item slot 1
        $slot1 = array_values(array_filter($result, static fn($f) => $f['id'] === 3));
        self::assertCount(1, $slot1);
        self::assertSame(0, $slot1[0]['item_index']);
        self::assertSame(0, $slot1[0]['computedPageIndex']);
    }

    public function testCloneForItemsWithThreeItemsAndTwoSlots(): void
    {
        $cloner = new FieldCloner();
        $fields = [
            ['id' => 1, 'scope' => 'global', 'page_index' => 0, 'position' => json_encode(['x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.04])],
            ['id' => 2, 'scope' => 'item', 'slot_index' => 0, 'page_index' => 0, 'position' => json_encode(['x' => 0.1, 'y' => 0.2, 'width' => 0.2, 'height' => 0.04])],
            ['id' => 3, 'scope' => 'item', 'slot_index' => 1, 'page_index' => 0, 'position' => json_encode(['x' => 0.5, 'y' => 0.2, 'width' => 0.2, 'height' => 0.04])],
        ];

        $result = $cloner->cloneForItems($fields, 3);

        // Página 0: global + slot0 (item 0) + slot1 (item 1) = 3 campos
        // Página 1: global + slot0 (item 2) = 2 campos
        self::assertCount(5, $result);

        // Globals: uma vez por página → 2 globals
        $globals = array_values(array_filter($result, static fn($f) => $f['id'] === 1));
        self::assertCount(2, $globals);
        self::assertSame(0, $globals[0]['item_index']);
        self::assertSame(0, $globals[0]['computedPageIndex']);
        self::assertSame(0, $globals[1]['item_index']);
        self::assertSame(1, $globals[1]['computedPageIndex']);

        // Item 0 (slot 0, página 0)
        $item0 = array_values(array_filter($result, static fn($f) => $f['id'] === 2 && $f['item_index'] === 0));
        self::assertCount(1, $item0);
        self::assertSame(0, $item0[0]['computedPageIndex']);

        // Item 1 (slot 1, página 0)
        $item1 = array_values(array_filter($result, static fn($f) => $f['id'] === 3 && $f['item_index'] === 1));
        self::assertCount(1, $item1);
        self::assertSame(0, $item1[0]['computedPageIndex']);

        // Item 2 (slot 0, página 1)
        $item2 = array_values(array_filter($result, static fn($f) => $f['id'] === 2 && $f['item_index'] === 2));
        self::assertCount(1, $item2);
        self::assertSame(1, $item2[0]['computedPageIndex']);
    }

    public function testCloneForItemsReturnsBaseFieldsWhenZeroItems(): void
    {
        $cloner = new FieldCloner();
        $fields = [
            ['id' => 1, 'scope' => 'global', 'page_index' => 0, 'position' => json_encode(['x' => 0.1, 'y' => 0.1])],
        ];

        $result = $cloner->cloneForItems($fields, 0);
        self::assertSame($fields, $result);
    }

    public function testCloneForItemsThrowsWhenNoSlotsAndMultipleItems(): void
    {
        $cloner = new FieldCloner();
        $fields = [
            ['id' => 1, 'scope' => 'global', 'page_index' => 0, 'position' => json_encode(['x' => 0.1])],
        ];

        $this->expectException(\RuntimeException::class);
        $cloner->cloneForItems($fields, 2);
    }

    public function testCloneForItemsPreservesOriginalPositions(): void
    {
        $cloner = new FieldCloner();
        $position = json_encode(['x' => 0.15, 'y' => 0.25, 'width' => 0.3, 'height' => 0.05]);
        $fields = [
            ['id' => 1, 'scope' => 'item', 'slot_index' => 0, 'page_index' => 0, 'position' => $position],
        ];

        $result = $cloner->cloneForItems($fields, 1);
        self::assertCount(1, $result);

        $pos = $result[0]['computedPosition'];
        self::assertSame(0.15, $pos['x']);
        self::assertSame(0.25, $pos['y']);
        self::assertSame(0.3, $pos['width']);
        self::assertSame(0.05, $pos['height']);
    }
}
