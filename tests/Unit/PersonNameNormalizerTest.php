<?php

namespace Tests\Unit;

use App\Support\PersonNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PersonNameNormalizerTest extends TestCase
{
    #[DataProvider('names')]
    public function test_it_normalizes_person_names(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PersonNameNormalizer::normalize($input));
    }

    /** @return array<string, array{?string, ?string}> */
    public static function names(): array
    {
        return [
            'null' => [null, null],
            'blank' => ['   ', null],
            'lowercase' => ['willy kurniawan setyobudi', 'Willy Kurniawan Setyobudi'],
            'uppercase with initials' => ['WILLY K S', 'Willy K S'],
            'mixed case and repeated whitespace' => ["  marLENA\t  nduru  ", 'Marlena Nduru'],
            'multi-letter initialism' => ['pinta NR', 'Pinta Nr'],
            'hyphen and apostrophe' => ["ANNE-MARIE O'CONNOR", "Anne-Marie O'Connor"],
            'unicode' => ["ÉLODIE D'ANGELO", "Élodie D'Angelo"],
        ];
    }
}
