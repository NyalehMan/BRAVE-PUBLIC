<?php

namespace App\Http\Controllers;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PublicPsiController extends Controller
{
    private const SOURCE_NAME = 'Department of Environment, Parks and Recreation (JASTRe)';

    private const SOURCE_URL = 'https://www.env.gov.bn/';

    private const CURRENT_CACHE_KEY = 'brave:jastre-psi:ocr:current:v2';

    private const LAST_OFFICIAL_CACHE_KEY = 'brave:jastre-psi:ocr:last-official:v2';

    private const DISTRICTS = [
        [
            'code' => 'BM',
            'district' => 'Brunei-Muara',
        ],
        [
            'code' => 'BL',
            'district' => 'Belait',
        ],
        [
            'code' => 'TM',
            'district' => 'Temburong',
        ],
        [
            'code' => 'TT',
            'district' => 'Tutong',
        ],
    ];

    public function __invoke(): JsonResponse
    {
        try {
            $data = Cache::remember(
                self::CURRENT_CACHE_KEY,
                now()->addMinutes(15),
                fn (): array => $this->fetchOfficialPsi()
            );

            if (! $this->isVerifiedOfficialPayload($data)) {
                Cache::forget(self::CURRENT_CACHE_KEY);

                throw new RuntimeException('The cached JASTRe PSI response failed verification.');
            }

            Cache::put(
                self::LAST_OFFICIAL_CACHE_KEY,
                $data,
                now()->addDays(7)
            );

            return response()->json($data);
        } catch (Throwable $officialError) {
            report($officialError);

            $lastOfficial = Cache::get(self::LAST_OFFICIAL_CACHE_KEY);

            if ($this->isVerifiedOfficialPayload($lastOfficial)) {
                return response()->json(array_merge($lastOfficial, [
                    'stale' => true,
                    'warning' => 'JASTRe could not be refreshed. Showing the last successfully extracted official PSI reading.',
                ]));
            }

            return response()->json([
                'message' => 'Official JASTRe PSI readings are temporarily unavailable.',
                'source' => self::SOURCE_NAME,
                'source_url' => self::SOURCE_URL,
                'source_type' => 'unavailable',
                'official' => false,
                'fallback' => false,
                'metric' => 'psi',
                'metric_label' => 'PSI',
                'readings' => [],
                'official_error' => app()->hasDebugModeEnabled()
                    ? $officialError->getMessage()
                    : null,
            ], 503);
        }
    }

    /**
     * Download JASTRe's current PSI image and extract the four official values.
     */
    private function fetchOfficialPsi(): array
    {
        $html = Http::accept('text/html')
            ->withHeaders([
                'User-Agent' => 'BRAVE-Public/1.0 (official PSI reader)',
            ])
            ->connectTimeout(8)
            ->timeout(20)
            ->retry(2, 500)
            ->get(self::SOURCE_URL)
            ->throw()
            ->body();

        $image = $this->findPsiImage($html);
        $imageBytes = $this->downloadPsiImage($image['url']);
        $ocrText = $this->runTesseract($imageBytes);

        try {
            $values = $this->extractDistrictValues($ocrText);
        } catch (RuntimeException) {
            // Recent JASTRe artwork places large white readings on coloured
            // circles. Whole-image OCR can see the district headings while
            // missing those digits, so retry each district's reading area.
            $values = $this->extractDistrictValuesFromImage($imageBytes);
        }

        $updatedAtLabel = $this->extractUpdatedLabel($ocrText, $image['alt']);

        $readings = [];

        foreach (self::DISTRICTS as $index => $district) {
            $readings[] = [
                'code' => $district['code'],
                'district' => $district['district'],
                // Keep `psi` for compatibility with the existing FirePublicView.
                'psi' => $values[$index],
                'value' => $values[$index],
            ];
        }

        return [
            'source' => self::SOURCE_NAME,
            'source_url' => self::SOURCE_URL,
            'source_type' => 'official',
            'official' => true,
            'fallback' => false,
            'metric' => 'psi',
            'metric_label' => 'PSI',
            'updated_at_label' => $updatedAtLabel,
            'retrieved_at' => now()->toIso8601String(),
            'stale' => false,
            'readings' => $readings,
        ];
    }

    /**
     * @return array{url: string, alt: string}
     */
    private function findPsiImage(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="utf-8" ?>'.$html,
                LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new RuntimeException('Unable to parse the JASTRe homepage.');
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//img');

        if ($nodes === false) {
            throw new RuntimeException('Unable to inspect JASTRe images.');
        }

        $candidates = [];

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $alt = trim($node->getAttribute('alt'));
            $title = trim($node->getAttribute('title'));
            $src = $this->bestImageSource($node);

            if ($src === '') {
                continue;
            }

            $haystack = mb_strtolower($alt.' '.$title.' '.$src);
            $score = 0;

            if (str_contains($haystack, 'psi reading')) {
                $score += 10;
            }

            if (str_contains($haystack, 'pollutant standard index')) {
                $score += 10;
            }

            if (preg_match('/(?:^|[^a-z])psi(?:[^a-z]|$)/i', $haystack)) {
                $score += 5;
            }

            if (str_contains($haystack, 'brunei')) {
                $score += 2;
            }

            if ($score > 0) {
                $candidates[] = [
                    'url' => $this->absoluteJastreUrl($src),
                    'alt' => $alt,
                    'score' => $score,
                ];
            }
        }

        if ($candidates === []) {
            throw new RuntimeException('JASTRe did not publish a recognisable PSI image.');
        }

        usort(
            $candidates,
            fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        $candidate = $candidates[0];
        $this->assertAllowedImageUrl($candidate['url']);

        return [
            'url' => $candidate['url'],
            'alt' => $candidate['alt'],
        ];
    }

    private function bestImageSource(DOMElement $node): string
    {
        foreach (['data-lazy-src', 'data-src', 'src'] as $attribute) {
            $value = trim($node->getAttribute($attribute));

            if ($value !== '' && ! str_starts_with($value, 'data:')) {
                return $value;
            }
        }

        $srcset = trim($node->getAttribute('srcset'));

        if ($srcset === '') {
            return '';
        }

        $sources = array_filter(array_map('trim', explode(',', $srcset)));
        $last = end($sources);

        return is_string($last)
            ? (preg_split('/\s+/', $last)[0] ?? '')
            : '';
    }

    private function absoluteJastreUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        return rtrim(self::SOURCE_URL, '/').'/'.ltrim($url, '/');
    }

    private function assertAllowedImageUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($scheme !== 'https' || ! in_array($host, ['env.gov.bn', 'www.env.gov.bn'], true)) {
            throw new RuntimeException('JASTRe returned an untrusted PSI image URL.');
        }
    }

    private function downloadPsiImage(string $url): string
    {
        $this->assertAllowedImageUrl($url);

        $response = Http::accept('image/avif,image/webp,image/jpeg,image/png,image/*')
            ->withHeaders([
                'User-Agent' => 'BRAVE-Public/1.0 (official PSI reader)',
                'Referer' => self::SOURCE_URL,
            ])
            ->connectTimeout(8)
            ->timeout(25)
            ->retry(2, 500)
            ->get($url)
            ->throw();

        $contentType = strtolower((string) $response->header('Content-Type'));
        $bytes = $response->body();

        if (! str_starts_with($contentType, 'image/') && @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('The JASTRe PSI URL did not return an image.');
        }

        if ($bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
            throw new RuntimeException('The JASTRe PSI image is empty or unexpectedly large.');
        }

        return $bytes;
    }

    private function runTesseract(string $imageBytes): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'brave-psi-');

        if ($temporaryFile === false) {
            throw new RuntimeException('Unable to create a temporary PSI image file.');
        }

        try {
            if (file_put_contents($temporaryFile, $imageBytes) === false) {
                throw new RuntimeException('Unable to save the temporary PSI image.');
            }

            $process = new Process([
                $this->tesseractBinary(),
                $temporaryFile,
                'stdout',
                '--psm',
                '6',
                '-l',
                'eng',
            ]);
            $process->setTimeout(35);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'Tesseract OCR failed: '.trim($process->getErrorOutput())
                );
            }

            $text = trim($process->getOutput());

            if ($text === '') {
                throw new RuntimeException('Tesseract returned no text from the PSI image.');
            }

            return $text;
        } finally {
            @unlink($temporaryFile);
        }
    }

    private function tesseractBinary(): string
    {
        $configured = config('services.tesseract.path');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $defaultWindowsPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';

            if (is_file($defaultWindowsPath)) {
                return $defaultWindowsPath;
            }
        }

        return 'tesseract';
    }

    /**
     * JASTRe's template lists districts left-to-right as Brunei-Muara, Belait,
     * Temburong and Tutong. OCR reads the four large values in that same order.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function extractDistrictValues(string $ocrText): array
    {
        $normalised = preg_replace('/[\x{2010}-\x{2015}]/u', '-', $ocrText) ?? $ocrText;
        $upper = mb_strtoupper($normalised);

        $notePosition = mb_strpos($upper, 'NOTA');
        $beforeNote = $notePosition !== false
            ? mb_substr($upper, 0, $notePosition)
            : $upper;
        // The masthead also contains "Brunei Darussalam". Use the final
        // occurrence before NOTA, which is the Brunei-Muara district heading.
        $start = mb_strrpos($beforeNote, 'BRUNEI');
        $end = $start !== false ? mb_strpos($upper, 'NOTA', $start) : false;

        if ($start !== false) {
            $length = $end !== false ? $end - $start : null;
            $districtSection = mb_substr($normalised, $start, $length);
            $values = $this->numbersInRange($districtSection);

            if (count($values) === count(self::DISTRICTS)) {
                return $values;
            }
        }

        foreach (preg_split('/\R/u', $normalised) ?: [] as $line) {
            $values = $this->numbersInRange($line);

            if (count($values) === count(self::DISTRICTS)) {
                return $values;
            }
        }

        throw new RuntimeException(
            'OCR completed, but four district PSI values could not be identified.'
        );
    }

    /**
     * Extract the four large readings from JASTRe's four-column PSI artwork.
     * The blue channel cleanly separates white digits from green, yellow,
     * orange, or red PSI circles without depending on the current category.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function extractDistrictValuesFromImage(string $imageBytes): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException(
                'The GD extension is required for PSI image preprocessing.'
            );
        }

        $source = @imagecreatefromstring($imageBytes);

        if ($source === false) {
            throw new RuntimeException('Unable to decode the JASTRe PSI image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 400 || $height < 400) {
            throw new RuntimeException('The JASTRe PSI image is unexpectedly small.');
        }

        $values = [];

        try {
            foreach (array_keys(self::DISTRICTS) as $index) {
                $crop = imagecrop($source, [
                    'x' => (int) floor($width * $index / 4),
                    'y' => (int) floor($height * 0.48),
                    'width' => (int) ceil($width / 4),
                    'height' => (int) floor($height * 0.20),
                ]);

                if ($crop === false) {
                    throw new RuntimeException('Unable to isolate a PSI district reading.');
                }

                try {
                    $values[] = $this->readDistrictValue($crop);
                } finally {
                    imagedestroy($crop);
                }
            }
        } finally {
            imagedestroy($source);
        }

        return $values;
    }

    private function readDistrictValue(\GdImage $crop): int
    {
        $width = imagesx($crop);
        $height = imagesy($crop);
        $thresholded = imagecreatetruecolor($width, $height);
        $black = imagecolorallocate($thresholded, 0, 0, 0);
        $white = imagecolorallocate($thresholded, 255, 255, 255);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $blue = imagecolorat($crop, $x, $y) & 0xff;

                imagesetpixel(
                    $thresholded,
                    $x,
                    $y,
                    $blue >= 220 ? $black : $white
                );
            }
        }

        $scaled = imagescale($thresholded, $width * 3, $height * 3);
        imagedestroy($thresholded);

        if ($scaled === false) {
            throw new RuntimeException('Unable to enlarge a PSI district reading.');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'brave-psi-value-');

        if ($temporaryFile === false) {
            imagedestroy($scaled);

            throw new RuntimeException('Unable to create a PSI crop file.');
        }

        try {
            if (! imagepng($scaled, $temporaryFile)) {
                throw new RuntimeException('Unable to save a PSI crop file.');
            }

            foreach (['10', '13'] as $pageSegmentationMode) {
                $process = new Process([
                    $this->tesseractBinary(),
                    $temporaryFile,
                    'stdout',
                    '--psm',
                    $pageSegmentationMode,
                    '-l',
                    'eng',
                    '-c',
                    'tessedit_char_whitelist=0123456789',
                    '-c',
                    'user_defined_dpi=300',
                ]);
                $process->setTimeout(15);
                $process->run();

                if (
                    $process->isSuccessful()
                    && preg_match('/\b(\d{1,3})\b/', $process->getOutput(), $match)
                ) {
                    $value = (int) $match[1];

                    if ($value <= 500) {
                        return $value;
                    }
                }
            }
        } finally {
            imagedestroy($scaled);
            @unlink($temporaryFile);
        }

        throw new RuntimeException('Unable to read a PSI district value.');
    }

    /** @return list<int> */
    private function numbersInRange(string $text): array
    {
        preg_match_all('/(?<![\d.])(\d{1,3})(?![\d.])/', $text, $matches);

        return array_values(array_filter(
            array_map('intval', $matches[1] ?? []),
            fn (int $value): bool => $value >= 0 && $value <= 500
        ));
    }

    private function extractUpdatedLabel(string $ocrText, string $alt): ?string
    {
        if (preg_match(
            '/\bPada\s+(\d{1,2}\s+[A-Za-z]+\s+20\d{2}\s*,?\s*\d{1,2}[.:]\d{2}\s*(?:pagi|petang|malam)?)/iu',
            $ocrText,
            $match
        )) {
            return 'Pada '.trim($match[1]);
        }

        if (preg_match(
            '/\bas of\s+(\d{1,2}\s+[A-Za-z]+\s+20\d{2}(?:\s*,?\s*\d{1,2}[.:]\d{2}\s*(?:am|pm)?)?)/iu',
            $ocrText.' '.$alt,
            $match
        )) {
            return 'As of '.trim($match[1]);
        }

        return trim($alt) !== '' ? trim($alt) : null;
    }

    /**
     * Reject incomplete, duplicate, non-official, or legacy fallback payloads
     * before they can be returned from either cache.
     */
    private function isVerifiedOfficialPayload(mixed $payload): bool
    {
        if (
            ! is_array($payload)
            || ($payload['source'] ?? null) !== self::SOURCE_NAME
            || ($payload['source_url'] ?? null) !== self::SOURCE_URL
            || ($payload['source_type'] ?? null) !== 'official'
            || ($payload['official'] ?? null) !== true
            || ($payload['fallback'] ?? null) !== false
            || ($payload['metric'] ?? null) !== 'psi'
            || ! is_array($payload['readings'] ?? null)
            || count($payload['readings']) !== count(self::DISTRICTS)
        ) {
            return false;
        }

        foreach (self::DISTRICTS as $index => $district) {
            $reading = $payload['readings'][$index] ?? null;

            if (
                ! is_array($reading)
                || ($reading['code'] ?? null) !== $district['code']
                || ($reading['district'] ?? null) !== $district['district']
                || ! is_int($reading['value'] ?? null)
                || ! is_int($reading['psi'] ?? null)
                || $reading['value'] !== $reading['psi']
                || $reading['value'] < 0
                || $reading['value'] > 500
            ) {
                return false;
            }
        }

        return true;
    }
}
