<?php

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Tests\Unit\PdfEngine;

use GlpiPlugin\SmartDocs\PdfEngine\RepetitionEngine;
use PHPUnit\Framework\TestCase;

final class RepetitionEngineTest extends TestCase
{
    public function testComputeLayoutSingleItem(): void
    {
        $engine = new RepetitionEngine();
        $layout = $engine->computeLayout(1, [
            'rows'    => 2,
            'columns' => 2,
        ]);

        self::assertSame(1, $layout['totalPages']);
        self::assertCount(1, $layout['pageItems']);
        self::assertSame(0, $layout['pageItems'][0]['pageIndex']);
    }

    public function testComputeLayoutMultipleItems(): void
    {
        $engine = new RepetitionEngine();
        $layout = $engine->computeLayout(5, [
            'rows'    => 2,
            'columns' => 2,
            'itemsPerPage' => 4,
        ]);

        self::assertSame(2, $layout['totalPages']);
        self::assertCount(5, $layout['pageItems']);
    }
}
