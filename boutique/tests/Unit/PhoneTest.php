<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            ['620 00 00 00', '224620000000'],
            ['+224 620-00-00-00', '224620000000'],
            ['00224620000000', '224620000000'],
            ['224 664 12 34 56', '224664123456'],
            ['12345', null],
            ['520000000', null],
            ['', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public function test_display_and_gnf(): void
    {
        $this->assertSame('+224 620 00 00 00', Phone::display('224620000000'));
        $this->assertSame('1 500 000 GNF', gnf(1500000));
        $this->assertSame('0 GNF', gnf(null));
    }
}
