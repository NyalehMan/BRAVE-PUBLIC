<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class FireNewsController extends Controller
{
    private const MAX_ARTICLE_AGE_DAYS = 120;

    public function index(): JsonResponse
    {
        try {
            $articles = $this->fetchAllSourcesInParallel();

            $articles = $this->removeDuplicates($articles);

            usort($articles, function (array $a, array $b): int {
                return $b['_timestamp'] <=> $a['_timestamp'];
            });

            $articles = array_slice($articles, 0, 12);

            foreach ($articles as &$article) {
                $proxiedImage = $this->buildImageProxyUrl(
                    (string) ($article['image'] ?? '')
                );

                if ($proxiedImage !== '') {
                    $article['image'] = $proxiedImage;
                } else {
                    $article['image'] = '/images/news-placeholder.jpg';
                }

                unset($article['_timestamp']);
            }
            unset($article);

            return response()->json(
                $articles,
                200,
                [],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (Throwable $error) {
            return response()->json([], 200);
        }
    }

    /**
     * Serve Google News thumbnails through Laravel. Google marks its attachment
     * responses as same-site, so a Vue app on a different localhost port is
     * otherwise blocked by the browser before the <img> can render.
     */
    public function image(Request $request): Response
    {
        $remoteUrl = trim((string) $request->query('url', ''));

        if (!$this->isAllowedGoogleThumbnail($remoteUrl)) {
            return response('', 404);
        }

        try {
            $remoteResponse = Http::withOptions([
                    'verify' => false,
                    'allow_redirects' => true,
                ])
                ->withHeaders([
                    'User-Agent' =>
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                        . 'AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/png,image/jpeg,*/*',
                ])
                ->connectTimeout(3)
                ->timeout(12)
                ->get($remoteUrl);

            if (!$remoteResponse->successful()) {
                return response('', 404);
            }

            $contentType = strtolower(trim(explode(
                ';',
                (string) $remoteResponse->header('Content-Type')
            )[0]));

            if (strpos($contentType, 'image/') !== 0) {
                return response('', 415);
            }

            $body = $remoteResponse->body();

            if ($body === '' || strlen($body) > 5 * 1024 * 1024) {
                return response('', 413);
            }

            return response($body, 200)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=21600')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Cross-Origin-Resource-Policy', 'cross-origin')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            return response('', 404);
        }
    }

    private function buildImageProxyUrl(string $remoteUrl): string
    {
        if (!$this->isAllowedGoogleThumbnail($remoteUrl)) {
            return '';
        }

        $baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        return $baseUrl . '/api/fire-news/image?' . http_build_query(
            ['url' => $remoteUrl],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    private function isAllowedGoogleThumbnail(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        return $scheme === 'https'
            && $host === 'news.google.com'
            && strpos($path, '/api/attachments/') === 0;
    }

    /**
     * Use one dependable Google News RSS request for the article list.
     * Publisher RSS endpoints are currently protected by Cloudflare and the
     * public rss2json endpoint rejects the required parameters without an API
     * key. Neither service is allowed to make the whole response empty.
     */
    private function fetchAllSourcesInParallel(): array
    {
        $headers = [
            'User-Agent' =>
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                . 'AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
            'Accept' =>
                'application/rss+xml, application/json, application/xml, '
                . 'text/xml, text/html, */*',
        ];

        $options = [
            'verify' => false,
            'allow_redirects' => true,
        ];

        try {
            $response = Http::withOptions($options)
                ->withHeaders($headers)
                ->connectTimeout(3)
                ->timeout(10)
                ->get($this->googleNewsUrl(
                    '"Brunei" (fire OR blaze OR wildfire OR flames '
                    . 'OR kebakaran OR terbakar OR rentung OR hangus '
                    . 'OR bomba) when:120d'
                ));
        } catch (Throwable $error) {
            return $this->fetchEmergencyGoogleFeed();
        }

        $articles = $this->articlesFromXmlResponse(
            $response,
            'Brunei News'
        );

        // A request can finish without throwing while still returning an
        // unusable body. Retry once rather than sending [] to the frontend.
        if ($articles === []) {
            return $this->fetchEmergencyGoogleFeed();
        }

        // Thumbnail lookup is deliberately separate from the news pool.
        // If it fails, the valid articles above are still returned.
        $imageMap = $this->fetchGoogleImageMapSafely();

        return $this->applyImageMap($articles, $imageMap);
    }

    private function fetchEmergencyGoogleFeed(): array
    {
        try {
            $response = Http::withOptions([
                    'verify' => false,
                    'allow_redirects' => true,
                ])
                ->withHeaders([
                    'User-Agent' =>
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                        . 'AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                    'Accept' => 'application/rss+xml, application/xml, */*',
                ])
                ->connectTimeout(2)
                ->timeout(6)
                ->get($this->googleNewsUrl(
                    '"Brunei" (fire OR blaze OR wildfire OR kebakaran '
                    . 'OR terbakar OR rentung) when:120d'
                ));

            if (!$response->successful()) {
                return [];
            }

            return $this->parseRss(
                $response->body(),
                'Brunei News'
            );
        } catch (Throwable $error) {
            return [];
        }
    }

    private function fetchGoogleImageMapSafely(): array
    {
        try {
            $response = Http::withOptions([
                    'verify' => false,
                    'allow_redirects' => true,
                ])
                ->withHeaders([
                    'User-Agent' =>
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                        . 'AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,*/*',
                ])
                ->connectTimeout(2)
                ->timeout(4)
                ->get($this->googleNewsSearchUrl(
                    '"Brunei" (fire OR blaze OR wildfire OR '
                    . 'kebakaran OR terbakar OR rentung) when:120d'
                ));

            return $this->imageMapFromGoogleHtmlResponse($response);
        } catch (Throwable $error) {
            return [];
        }
    }

    private function articlesFromPublisherResponse(
        $response,
        string $source
    ): array {
        try {
            if (!$this->isSuccessfulResponse($response)) {
                return [];
            }

            $data = $response->json();

            if (
                !is_array($data)
                || ($data['status'] ?? '') !== 'ok'
                || !isset($data['items'])
                || !is_array($data['items'])
            ) {
                return [];
            }

            $articles = [];

            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $rawContent = (string) ($item['content'] ?? '');
                $rawDescription = (string) ($item['description'] ?? '');

                $image = $this->normaliseImageUrl(
                    (string) ($item['thumbnail'] ?? '')
                );

                if ($image === '') {
                    $image = $this->imageFromEnclosure(
                        $item['enclosure'] ?? null
                    );
                }

                if ($image === '') {
                    $image = $this->extractImageFromMarkup(
                        $rawContent . ' ' . $rawDescription
                    );
                }

                $article = $this->makeArticle(
                    (string) ($item['title'] ?? ''),
                    $rawDescription !== ''
                        ? $rawDescription
                        : $rawContent,
                    (string) ($item['link'] ?? ''),
                    (string) ($item['pubDate'] ?? ''),
                    $source,
                    $image
                );

                if ($article !== null) {
                    $articles[] = $article;
                }
            }

            return $articles;
        } catch (Throwable $error) {
            return [];
        }
    }

    private function articlesFromXmlResponse(
        $response,
        string $source
    ): array {
        try {
            if (!$this->isSuccessfulResponse($response)) {
                return [];
            }

            $body = trim($response->body());

            return $body !== ''
                ? $this->parseRss($body, $source)
                : [];
        } catch (Throwable $error) {
            return [];
        }
    }

    private function isSuccessfulResponse($response): bool
    {
        return is_object($response)
            && method_exists($response, 'successful')
            && $response->successful();
    }

    private function googleNewsUrl(string $query): string
    {
        return 'https://news.google.com/rss/search?' . http_build_query(
            [
                'q' => $query,
                'hl' => 'en-MY',
                'gl' => 'MY',
                'ceid' => 'MY:en',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    private function googleNewsSearchUrl(string $query): string
    {
        return 'https://news.google.com/search?' . http_build_query(
            [
                'q' => $query,
                'hl' => 'en-MY',
                'gl' => 'MY',
                'ceid' => 'MY:en',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    private function imageMapFromGoogleHtmlResponse($response): array
    {
        try {
            if (!$this->isSuccessfulResponse($response)) {
                return [];
            }

            $html = trim($response->body());

            if ($html === '') {
                return [];
            }

            if (!class_exists('DOMDocument')) {
                return $this->imageMapFromHtmlWithoutDom($html);
            }

            $previousSetting = libxml_use_internal_errors(true);

            try {
                $dom = new \DOMDocument();
                $loaded = $dom->loadHTML(
                    '<?xml encoding="UTF-8">' . $html,
                    LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR
                );
            } catch (Throwable $error) {
                $loaded = false;
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousSetting);
            }

            if (!$loaded) {
                return $this->imageMapFromHtmlWithoutDom($html);
            }

            $xpath = new \DOMXPath($dom);
            $articleNodes = $xpath->query(
                '//article | //div[contains('
                . 'concat(" ", normalize-space(@class), " "), '
                . '" IFHyqb ")]'
            );

            if ($articleNodes === false) {
                return [];
            }

            $imageMap = [];

            foreach ($articleNodes as $articleNode) {
                $imageNodes = $xpath->query(
                    './/figure//img[@src or @data-src or @srcset]',
                    $articleNode
                );

                if ($imageNodes === false || $imageNodes->length === 0) {
                    $imageNodes = $xpath->query(
                        './/img[@src or @data-src or @srcset]',
                        $articleNode
                    );
                }

                if ($imageNodes === false || $imageNodes->length === 0) {
                    continue;
                }

                $imageNode = $imageNodes->item(0);

                if (!$imageNode instanceof \DOMElement) {
                    continue;
                }

                $imageUrl = $imageNode->getAttribute('src');

                if (
                    $imageUrl === ''
                    || strpos($imageUrl, 'data:') === 0
                ) {
                    $imageUrl = $imageNode->getAttribute('data-src');
                }

                if (
                    $imageUrl === ''
                    || strpos($imageUrl, 'data:') === 0
                ) {
                    $imageUrl = $imageNode->getAttribute('srcset');
                }

                $imageUrl = $this->normaliseGoogleImageUrl($imageUrl);

                if ($imageUrl === '') {
                    continue;
                }

                $linkNodes = $xpath->query('.//a', $articleNode);

                if ($linkNodes === false) {
                    continue;
                }

                foreach ($linkNodes as $linkNode) {
                    $candidateTitle = $this->cleanText(
                        (string) $linkNode->textContent
                    );

                    if (strlen($candidateTitle) < 15) {
                        continue;
                    }

                    $titleKey = $this->normaliseTitleKey($candidateTitle);

                    if ($titleKey !== '') {
                        $imageMap[$titleKey] = $imageUrl;
                    }
                }
            }

            return $imageMap;
        } catch (Throwable $error) {
            return [];
        }
    }

    private function imageMapFromHtmlWithoutDom(string $html): array
    {
        $articleMatches = [];

        if (preg_match_all(
            '/<article\b[^>]*>(.*?)<\/article>/is',
            $html,
            $legacyMatches
        )) {
            $articleMatches = $legacyMatches[1];
        } elseif (preg_match_all(
            '/<div\s+class="IFHyqb\b[^>]*>(.*?)<\/c-wiz>/is',
            $html,
            $currentMatches
        )) {
            $articleMatches = $currentMatches[1];
        }

        if ($articleMatches === []) {
            return [];
        }

        $imageMap = [];

        foreach ($articleMatches as $articleHtml) {
            $hasImage = preg_match(
                '/<figure\b.*?<img\b[^>]*'
                . '(?:data-src|srcset|src)=["\']([^"\']+)["\']/is',
                $articleHtml,
                $imageMatch
            );

            if (!$hasImage) {
                $hasImage = preg_match(
                    '/<img\b[^>]*(?:data-src|srcset|src)='
                    . '["\']([^"\']+)["\']/i',
                    $articleHtml,
                    $imageMatch
                );
            }

            if (!$hasImage) {
                continue;
            }

            $imageUrl = $this->normaliseGoogleImageUrl($imageMatch[1]);

            if ($imageUrl === '') {
                continue;
            }

            if (!preg_match_all(
                '/<a\b[^>]*>(.*?)<\/a>/is',
                $articleHtml,
                $linkMatches
            )) {
                continue;
            }

            foreach ($linkMatches[1] as $linkHtml) {
                $candidateTitle = $this->cleanText($linkHtml);

                if (strlen($candidateTitle) < 15) {
                    continue;
                }

                $titleKey = $this->normaliseTitleKey($candidateTitle);

                if ($titleKey !== '') {
                    $imageMap[$titleKey] = $imageUrl;
                }
            }
        }

        return $imageMap;
    }

    private function normaliseGoogleImageUrl(string $url): string
    {
        $url = trim(html_entity_decode(
            $url,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        if (strpos($url, ',') !== false) {
            $url = trim(explode(',', $url)[0]);
        }

        if (preg_match('/^(\S+)\s+\d+[wx]$/i', $url, $match)) {
            $url = $match[1];
        }

        if ($url === '' || strpos($url, 'data:') === 0) {
            return '';
        }

        if (strpos($url, './') === 0) {
            $url = 'https://news.google.com/' . substr($url, 2);
        } elseif (strpos($url, '/') === 0) {
            $url = 'https://news.google.com' . $url;
        } elseif (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        return preg_match('/^https?:\/\//i', $url)
            ? $url
            : '';
    }

    private function applyImageMap(
        array $articles,
        array $imageMap
    ): array {
        foreach ($articles as &$article) {
            if (!empty($article['image'])) {
                continue;
            }

            $titleKey = $this->normaliseTitleKey(
                (string) ($article['title'] ?? '')
            );

            if ($titleKey !== '' && isset($imageMap[$titleKey])) {
                $article['image'] = $imageMap[$titleKey];
                continue;
            }

            foreach ($imageMap as $candidateKey => $candidateImage) {
                if (
                    $titleKey !== ''
                    && (
                        strpos($candidateKey, $titleKey) !== false
                        || strpos($titleKey, $candidateKey) !== false
                    )
                ) {
                    $article['image'] = $candidateImage;
                    break;
                }
            }
        }
        unset($article);

        return $articles;
    }

    private function normaliseTitleKey(string $title): string
    {
        $title = strtolower($this->cleanText($title));
        $title = preg_replace('/\s+-\s+[^-]+$/u', '', $title) ?? $title;
        $title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title) ?? $title;

        return trim($title);
    }

    private function parseRss(
        string $xmlText,
        string $source
    ): array {
        if (function_exists('simplexml_load_string')) {
            $articles = $this->parseWithSimpleXml($xmlText, $source);

            if ($articles !== []) {
                return $articles;
            }
        }

        return $this->parseWithoutXmlExtension($xmlText, $source);
    }

    private function parseWithSimpleXml(
        string $xmlText,
        string $source
    ): array {
        $previousSetting = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $xmlText,
                'SimpleXMLElement',
                LIBXML_NOCDATA | LIBXML_NONET
            );
        } catch (Throwable $error) {
            $xml = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }

        if ($xml === false || !isset($xml->channel->item)) {
            return [];
        }

        $articles = [];

        foreach ($xml->channel->item as $item) {
            $itemSource = $this->cleanText((string) $item->source);

            if ($itemSource === '') {
                $itemSource = $source;
            }

            $rawItem = $item->asXML();
            $rawItem = is_string($rawItem) ? $rawItem : '';

            $rawDescription = (string) $item->description;
            $rawContent = '';

            $namespaces = $item->getNameSpaces(true);

            if (isset($namespaces['content'])) {
                $contentNode = $item->children($namespaces['content']);
                $rawContent = (string) $contentNode->encoded;
            }

            $image = $this->extractImageFromMarkup(
                $rawContent . ' ' . $rawDescription . ' ' . $rawItem
            );

            $article = $this->makeArticle(
                (string) $item->title,
                $rawDescription !== ''
                    ? $rawDescription
                    : $rawContent,
                (string) $item->link,
                (string) $item->pubDate,
                $itemSource,
                $image
            );

            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    private function parseWithoutXmlExtension(
        string $xmlText,
        string $source
    ): array {
        if (
            !preg_match_all(
                '/<item\b[^>]*>(.*?)<\/item>/is',
                $xmlText,
                $matches
            )
        ) {
            return [];
        }

        $articles = [];

        foreach ($matches[1] as $itemXml) {
            $itemSource = $this->cleanText(
                $this->readXmlTag($itemXml, 'source')
            );

            if ($itemSource === '') {
                $itemSource = $source;
            }

            $description = $this->readXmlTag(
                $itemXml,
                'description'
            );

            $content = $this->readXmlTag(
                $itemXml,
                'content:encoded'
            );

            $article = $this->makeArticle(
                $this->readXmlTag($itemXml, 'title'),
                $description !== '' ? $description : $content,
                $this->readXmlTag($itemXml, 'link'),
                $this->readXmlTag($itemXml, 'pubDate'),
                $itemSource,
                $this->extractImageFromMarkup(
                    $content . ' ' . $description . ' ' . $itemXml
                )
            );

            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    private function readXmlTag(
        string $xml,
        string $tag
    ): string {
        $pattern =
            '/<' . preg_quote($tag, '/') . '\b[^>]*>'
            . '(.*?)<\/' . preg_quote($tag, '/') . '>/is';

        if (!preg_match($pattern, $xml, $match)) {
            return '';
        }

        return preg_replace(
            '/^<!\[CDATA\[(.*)\]\]>$/is',
            '$1',
            trim($match[1])
        ) ?? '';
    }

    private function makeArticle(
        string $rawTitle,
        string $rawDescription,
        string $rawLink,
        string $rawDate,
        string $source,
        string $image = ''
    ): ?array {
        $title = $this->cleanText($rawTitle);
        $description = $this->cleanText($rawDescription);

        $link = trim(html_entity_decode(
            $rawLink,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        if ($title === '' || $link === '') {
            return null;
        }

        $title = preg_replace(
            '/\s+-\s+' . preg_quote($source, '/')
            . '(?:\s+Online)?\s*$/i',
            '',
            $title
        ) ?? $title;

        $timestamp = strtotime($rawDate);

        if ($timestamp === false) {
            return null;
        }

        $oldestAllowed = time()
            - (self::MAX_ARTICLE_AGE_DAYS * 24 * 60 * 60);

        if (
            $timestamp < $oldestAllowed
            || $timestamp > time() + (2 * 24 * 60 * 60)
        ) {
            return null;
        }

        if (!$this->isBruneiFireIncident($title, $description)) {
            return null;
        }

        $image = $this->normaliseImageUrl($image);

        return [
            'title' => $title,
            'description' => $description !== ''
                ? $this->limitText($description, 220)
                : 'Click to read the full article.',
            'url' => $link,
            'image' => $image,
            'source' => $source,
            'published_at' => date('Y-m-d H:i:s', $timestamp),
            '_timestamp' => $timestamp,
        ];
    }

    /**
     * Build a self-contained thumbnail. It does not call Google, a publisher,
     * or a screenshot service, so it cannot fail because of redirects,
     * hot-link blocking, Cloudflare or an unavailable remote image.
     */
    private function generatedArticleThumbnail(
        string $title,
        string $source
    ): string
    {
        $baseUrl = rtrim(
            request()->getSchemeAndHttpHost(),
            '/'
        );

        return $baseUrl . '/fire-news/thumbnail?' . http_build_query(
            [
                'title' => $this->limitText($title, 110),
                'source' => $this->limitText($source, 40),
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    public function thumbnail(Request $request): Response
    {
        $title = (string) $request->query(
            'title',
            'Brunei Fire Incident Update'
        );

        $source = (string) $request->query(
            'source',
            'BRAVE Fire News'
        );

        $svg = $this->buildArticleThumbnailSvg(
            $title,
            $source
        );

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function buildArticleThumbnailSvg(
        string $title,
        string $source
    ): string {
        $wrappedTitle = wordwrap(
            $this->cleanText($title),
            34,
            "\n",
            true
        );

        $lines = array_slice(explode("\n", $wrappedTitle), 0, 3);

        while (count($lines) < 3) {
            $lines[] = '';
        }

        $safeSource = htmlspecialchars(
            $this->limitText($source, 40),
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );

        $safeLines = [];

        foreach ($lines as $line) {
            $safeLines[] = htmlspecialchars(
                $line,
                ENT_QUOTES | ENT_XML1,
                'UTF-8'
            );
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" '
            . 'width="800" height="450" viewBox="0 0 800 450">'
            . '<defs>'
            . '<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="#1a1a1a"/>'
            . '<stop offset="0.55" stop-color="#501313"/>'
            . '<stop offset="1" stop-color="#b22121"/>'
            . '</linearGradient>'
            . '<radialGradient id="glow" cx="0.78" cy="0.28" r="0.52">'
            . '<stop offset="0" stop-color="#ffb13b" stop-opacity="0.75"/>'
            . '<stop offset="1" stop-color="#ff5a2b" stop-opacity="0"/>'
            . '</radialGradient>'
            . '</defs>'
            . '<rect width="800" height="450" fill="url(#bg)"/>'
            . '<rect width="800" height="450" fill="url(#glow)"/>'
            . '<circle cx="710" cy="86" r="125" fill="#ff7a2f" opacity="0.08"/>'
            . '<circle cx="710" cy="86" r="82" fill="#ffc04d" opacity="0.08"/>'
            . '<path d="M690 151 C646 126 652 84 685 51 '
            . 'C683 83 709 92 716 65 C753 103 758 140 729 166 '
            . 'C718 176 704 181 690 179 C708 164 707 143 696 129 '
            . 'C697 143 693 148 690 151 Z" fill="#ffb13b"/>'
            . '<rect x="54" y="49" width="185" height="38" rx="19" '
            . 'fill="#e53935"/>'
            . '<text x="78" y="75" fill="#ffffff" font-size="19" '
            . 'font-family="Arial, sans-serif" font-weight="700" '
            . 'letter-spacing="1.5">BRAVE FIRE NEWS</text>'
            . '<text x="56" y="155" fill="#ffffff" font-size="34" '
            . 'font-family="Arial, sans-serif" font-weight="700">'
            . $safeLines[0] . '</text>'
            . '<text x="56" y="200" fill="#ffffff" font-size="34" '
            . 'font-family="Arial, sans-serif" font-weight="700">'
            . $safeLines[1] . '</text>'
            . '<text x="56" y="245" fill="#ffffff" font-size="34" '
            . 'font-family="Arial, sans-serif" font-weight="700">'
            . $safeLines[2] . '</text>'
            . '<line x1="56" y1="350" x2="744" y2="350" '
            . 'stroke="#ffffff" stroke-opacity="0.24"/>'
            . '<text x="56" y="391" fill="#ffd4d4" font-size="21" '
            . 'font-family="Arial, sans-serif">Source: '
            . $safeSource . '</text>'
            . '<text x="744" y="391" text-anchor="end" fill="#ffffff" '
            . 'font-size="18" font-family="Arial, sans-serif" '
            . 'font-weight="700">BRUNEI</text>'
            . '</svg>';

        return $svg;
    }

    private function isBruneiFireIncident(
        string $title,
        string $description
    ): bool {
        $combined = strtolower($title . ' ' . $description);

        $hasIncidentTerm = preg_match(
            '/\bfires?\b|blaze|wildfire|flames?|burning|'
            . 'kebakaran|terbakar|rentung|hangus|dijilat\s+api|'
            . 'api\s+marak|memadam(?:kan)?\s+(?:api|kebakaran)/i',
            $combined
        ) === 1;

        $hasBruneiMarker = preg_match(
            '/brunei|bandar\s+seri\s+begawan|brunei[\s-]*muara|'
            . 'belait|tutong|temburong|seria|kuala\s+belait|'
            . 'jabatan\s+bomba\s+dan\s+penyelamat|'
            . 'fire\s+and\s+rescue\s+department|\bfrd\b|\bjbp\b|'
            . 'jerudong|gadong|berakas|rimba|lambak|kilanas|'
            . 'sengkurong|mulaut|telanai|lumut|panaga|tanjong\s+bunut|'
            . 'kiulap|mentiri|sungai\s+kebun|pengkalan\s+batu|'
            . 'tungku|katok|labi|bangar|kampong\s+ayer/i',
            $combined
        ) === 1;

        $isNotIncident = preg_match(
            '/fire\s*balls?|black\s+magic|fire\s+drill|latihan\s+kebakaran|'
            . 'fire\s+safety|safety\s+awareness|kesedaran\s+keselamatan|'
            . 'training|exercise|taklimat|ceramah|rebrand|rebranding|'
            . 'anniversary|competition|demonstration/i',
            $combined
        ) === 1;

        $hasForeignLocation = preg_match(
            '/hong\s+kong|south\s+korea|korea\s+selatan|los\s+angeles|'
            . 'california|new\s+york|united\s+states|amerika\s+syarikat|'
            . 'united\s+kingdom|australia|indonesia|malaysia|sarawak|'
            . 'sabah|singapore|china|jepun|japan|thailand|filipina|'
            . 'philippines|france|perancis|italy|spain|russia|ukraine|'
            . 'iran|israel|gaza|pakistan|bangladesh|brazil/i',
            $combined
        ) === 1;

        return $hasIncidentTerm
            && $hasBruneiMarker
            && !$isNotIncident
            && !$hasForeignLocation;
    }

    private function extractImageFromMarkup(string $markup): string
    {
        $markup = html_entity_decode(
            $markup,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $patterns = [
            '/<media:(?:content|thumbnail)\b[^>]*\burl=["\']([^"\']+)["\']/i',
            '/<enclosure\b[^>]*\burl=["\']([^"\']+)["\'][^>]*>/i',
            '/<img\b[^>]*\b(?:data-lazy-src|data-src|src)=["\']([^"\']+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $markup, $match)) {
                $image = $this->normaliseImageUrl($match[1]);

                if ($image !== '') {
                    return $image;
                }
            }
        }

        return '';
    }

    private function imageFromEnclosure($enclosure): string
    {
        if (is_string($enclosure)) {
            return $this->normaliseImageUrl($enclosure);
        }

        if (!is_array($enclosure)) {
            return '';
        }

        foreach (['link', 'url'] as $key) {
            if (!empty($enclosure[$key])) {
                return $this->normaliseImageUrl(
                    (string) $enclosure[$key]
                );
            }
        }

        return '';
    }

    private function normaliseImageUrl(string $url): string
    {
        $url = trim(html_entity_decode(
            $url,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            return '';
        }

        return $url;
    }

    private function removeDuplicates(array $articles): array
    {
        $unique = [];

        foreach ($articles as $article) {
            $key = strtolower(trim($article['title']));

            if ($key !== '' && !isset($unique[$key])) {
                $unique[$key] = $article;
            }
        }

        return array_values($unique);
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function limitText(string $value, int $limit): string
    {
        if (
            function_exists('mb_strlen')
            && function_exists('mb_substr')
        ) {
            return mb_strlen($value) > $limit
                ? rtrim(mb_substr($value, 0, $limit - 3)) . '...'
                : $value;
        }

        return strlen($value) > $limit
            ? rtrim(substr($value, 0, $limit - 3)) . '...'
            : $value;
    }
}
