<?php

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Tests\Unit\Equipment;

use GlpiPlugin\SmartDocs\Equipment\NamingConvention;
use PHPUnit\Framework\TestCase;

final class NamingConventionTest extends TestCase
{
    public function testGenerate(): void
    {
        $name = NamingConvention::generate('Computer', 'São Paulo', 42);
        self::assertSame('PC-SPO-0042', $name);
    }

    public function testAbbreviateType(): void
    {
        self::assertSame('PC', NamingConvention::abbreviateType('Computer'));
        self::assertSame('MON', NamingConvention::abbreviateType('Monitor'));
        self::assertSame('IMP', NamingConvention::abbreviateType('Printer'));
        self::assertSame('NET', NamingConvention::abbreviateType('NetworkEquipment'));
    }

    public function testAbbreviateLocation(): void
    {
        self::assertSame('RIO', NamingConvention::abbreviateLocation('Rio de Janeiro'));
        self::assertSame('XXX', NamingConvention::abbreviateLocation('123'));
    }
}
