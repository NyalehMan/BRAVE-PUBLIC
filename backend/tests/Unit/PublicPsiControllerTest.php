<?php

namespace Tests\Unit;

use App\Http\Controllers\PublicPsiController;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class PublicPsiControllerTest extends TestCase
{
    public function test_ocr_extraction_accepts_exactly_four_district_values(): void
    {
        $values = $this->extractDistrictValues(
            "POLLUTANT STANDARD INDEX\nBRUNEI-MUARA BELAIT TEMBURONG TUTONG\n18 21 15 19\nNOTA"
        );

        $this->assertSame([18, 21, 15, 19], $values);
    }

    public function test_ocr_extraction_rejects_an_ambiguous_extra_value(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('four district PSI values could not be identified');

        $this->extractDistrictValues(
            "POLLUTANT STANDARD INDEX\nBRUNEI-MUARA BELAIT TEMBURONG TUTONG\n18 21 15 19 42\nNOTA"
        );
    }

    /** @return list<int> */
    private function extractDistrictValues(string $ocrText): array
    {
        $method = new ReflectionMethod(PublicPsiController::class, 'extractDistrictValues');

        return $method->invoke(new PublicPsiController, $ocrText);
    }
}
