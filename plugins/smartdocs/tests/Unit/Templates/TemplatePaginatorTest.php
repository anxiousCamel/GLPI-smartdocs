<?php

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Tests\Unit\Templates;

use GlpiPlugin\SmartDocs\Templates\TemplatePaginator;
use PHPUnit\Framework\TestCase;

final class TemplatePaginatorTest extends TestCase
{
    // ------------------------------------------------------------------
    // itemsPerPage
    // ------------------------------------------------------------------

    public function testItemsPerPageWithOnlyGlobalFields(): void
    {
        $fields = [
            ['scope' => 'global'],
            ['scope' => 'global'],
        ];

        self::assertSame(0, TemplatePaginator::itemsPerPage($fields));
    }

    public function testItemsPerPageWithTwoSlots(): void
    {
        $fields = [
            ['scope' => 'item', 'slot_index' => 0],
            ['scope' => 'item', 'slot_index' => 1],
            ['scope' => 'item', 'slot_index' => 0],
            ['scope' => 'global'],
        ];

        self::assertSame(2, TemplatePaginator::itemsPerPage($fields));
    }

    // ------------------------------------------------------------------
    // compute
    // ------------------------------------------------------------------

    public function testComputeWithZeroItems(): void
    {
        $fields = [
            ['scope' => 'item', 'slot_index' => 0],
        ];

        $result = TemplatePaginator::compute($fields, 0);

        self::assertSame(1, $result['itemsPerPage']);
        self::assertSame(0, $result['totalPages']);
        self::assertSame([], $result['assignments']);
    }

    public function testComputeWithThreeItemsAndTwoSlots(): void
    {
        $fields = [
            ['scope' => 'item', 'slot_index' => 0],
            ['scope' => 'item', 'slot_index' => 1],
        ];

        $result = TemplatePaginator::compute($fields, 3);

        self::assertSame(2, $result['itemsPerPage']);
        self::assertSame(2, $result['totalPages']);
        self::assertCount(3, $result['assignments']);

        // Item 0 → página 0, slot 0
        self::assertSame(0, $result['assignments'][0]['pageOrdinal']);
        self::assertSame(0, $result['assignments'][0]['slotIndex']);
        self::assertSame(0, $result['assignments'][0]['itemIndex']);

        // Item 1 → página 0, slot 1
        self::assertSame(0, $result['assignments'][1]['pageOrdinal']);
        self::assertSame(1, $result['assignments'][1]['slotIndex']);
        self::assertSame(1, $result['assignments'][1]['itemIndex']);

        // Item 2 → página 1, slot 0
        self::assertSame(1, $result['assignments'][2]['pageOrdinal']);
        self::assertSame(0, $result['assignments'][2]['slotIndex']);
        self::assertSame(2, $result['assignments'][2]['itemIndex']);
    }

    public function testComputeThrowsWhenNoSlotsAndItemsPositive(): void
    {
        $fields = [
            ['scope' => 'global'],
        ];

        $this->expectException(\RuntimeException::class);
        TemplatePaginator::compute($fields, 1);
    }

    // ------------------------------------------------------------------
    // validate
    // ------------------------------------------------------------------

    public function testValidateReturnsEmptyForValidFields(): void
    {
        $fields = [
            ['scope' => 'global', 'label' => 'Título'],
            ['scope' => 'item', 'slot_index' => 0, 'label' => 'Nome'],
            ['scope' => 'item', 'slot_index' => 1, 'label' => 'Serial'],
        ];

        self::assertSame([], TemplatePaginator::validate($fields));
    }

    public function testValidateRejectsGlobalWithSlotIndex(): void
    {
        $fields = [
            ['scope' => 'global', 'slot_index' => 0, 'label' => 'Global com slot'],
        ];

        $errors = TemplatePaginator::validate($fields);
        self::assertCount(1, $errors);
        self::assertStringContainsString('global mas possui slot_index', $errors[0]);
    }

    public function testValidateRejectsItemWithoutSlotIndex(): void
    {
        $fields = [
            ['scope' => 'item', 'label' => 'Item sem slot'],
        ];

        $errors = TemplatePaginator::validate($fields);
        self::assertCount(1, $errors);
        self::assertStringContainsString('não possui slot_index', $errors[0]);
    }

    public function testValidateRejectsNonContiguousSlots(): void
    {
        $fields = [
            ['scope' => 'item', 'slot_index' => 0],
            ['scope' => 'item', 'slot_index' => 2], // gap
        ];

        $errors = TemplatePaginator::validate($fields);
        self::assertCount(1, $errors);
        self::assertStringContainsString('contíguos', $errors[0]);
    }

    public function testValidateAccumulatesMultipleErrors(): void
    {
        $fields = [
            ['scope' => 'global', 'slot_index' => 0, 'label' => 'Global com slot'],
            ['scope' => 'item', 'label' => 'Item sem slot'],
        ];

        $errors = TemplatePaginator::validate($fields);
        self::assertCount(2, $errors);
    }
}
