<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FireNewsController extends Controller
{
    public const VERSION = 'bb-local-haze-20260910-3';
    private const BRUNEI_MAX_ARTICLE_AGE_DAYS = 120;
    private const BORNEO_MAX_ARTICLE_AGE_DAYS = 90;
    private const MAX_DISPLAY_DATE_SPREAD_DAYS = 45;
    private const MIN_ARTICLE_COUNT = 4;
    private const MAX_ARTICLE_COUNT = 8;
    private const MAX_DETAIL_LOOKUPS = 12;

    private array $pendingLocationChecks = [];
    private array $sourceDiagnostics = [];
    private bool $collectLocationChecks = true;

    public function index(Request $request): JsonResponse
    {
        $diagnostics = (string) $request->query('diagnostics', '0') === '1';
        $region = $this->normaliseRegion((string) $request->query('region', 'brunei'));
        try {
            $articles = $this->fetchAllSourcesInParallel($region);

            $articles = $this->removeDuplicates($articles);

            $articles = $this->selectArticles($articles);

            foreach ($articles as &$article) {
                $originalImage = $this->normaliseImageUrl(
                    (string) ($article['image'] ?? '')
                );

                $proxiedImage = $this->buildImageProxyUrl(
                    $originalImage
                );

                if ($proxiedImage !== '') {
                    $article['image'] = $proxiedImage;
                } elseif ($originalImage !== '') {
                    // Normal publisher images can be loaded directly. Only
                    // Google attachment thumbnails need the Laravel proxy.
                    $article['image'] = $originalImage;
                } else {
                    $article['image'] = '/images/news-placeholder.jpg';
                }

                unset($article['_timestamp']);
            }
            unset($article);

            $status = $this->newsStatus($articles);
            $payload = $diagnostics ? [
                'version' => self::VERSION,
                'publisher' => 'Borneo Bulletin',
                'region' => $region,
                'status' => $status,
                'checked_at' => gmdate(DATE_ATOM),
                'article_count' => count($articles),
                'sources' => $this->sourceDiagnostics,
                'articles' => $articles,
            ] : $articles;

            return response()->json(
                $payload,
                200,
                [],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
                ->header(
                    'Cache-Control',
                    'no-store, no-cache, must-revalidate, max-age=0'
                )
                ->header('X-BRAVE-News-Version', self::VERSION)
                ->header('X-BRAVE-News-Status', $status)
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (Throwable $error) {
            Log::warning('BRAVE fire news request failed', [
                'error' => $error->getMessage(),
            ]);
            return response()->json($diagnostics ? [
                'version' => self::VERSION,
                'publisher' => 'Borneo Bulletin',
                'region' => $region,
                'status' => 'error',
                'sources' => $this->sourceDiagnostics,
                'articles' => [],
            ] : [], 200)
                ->header('X-BRAVE-News-Version', self::VERSION)
                ->header('X-BRAVE-News-Status', 'error')
                ->header(
                    'Cache-Control',
                    'no-store, no-cache, must-revalidate, max-age=0'
                );
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
     * Prefer the original publisher feeds because they update faster and keep
     * their publication timestamps. Google News remains a fallback and fills
     * any gaps when a publisher temporarily blocks or changes its RSS feed.
     */
    private function fetchAllSourcesInParallel(string $region): array
    {
        $this->pendingLocationChecks = [];
        $this->sourceDiagnostics = [];
        $this->collectLocationChecks = true;
        $feeds = $this->publisherFeeds($region);

        // These are discovery searches, never proof of an article's location.
        foreach ($this->googleQueriesForRegion($region) as $query) {
            $feeds[] = [
                'source' => $this->sourceLabelForRegion($region),
                'url' => $this->googleNewsUrl($query),
            ];
        }

        $responses = $this->fetchResponses(array_column($feeds, 'url'));
        $articles = [];

        foreach ($feeds as $index => $feed) {
            $response = $responses[$index] ?? null;

            if (!$this->isSuccessfulResponse($response)) {
                $this->recordSourceResult($feed['url'], $response, 'discovery');
                Log::notice('BRAVE fire news source unavailable', [
                    'source' => $feed['source'],
                    'status' => is_object($response)
                        && method_exists($response, 'status')
                            ? $response->status() : null,
                ]);
                continue;
            }

            $items = ($feed['type'] ?? 'rss') === 'wordpress'
                ? $this->articlesFromWordpressResponse($response, $region)
                : $this->articlesFromXmlResponse($response, $feed['source'], $region, false);
            $this->recordSourceResult($feed['url'], $response, 'discovery', count($items));
            array_push($articles, ...$items);
        }

        // RSS summaries (especially Google News) often omit the place name.
        // Check the publisher's article/search feed before rejecting them.
        array_push($articles, ...$this->resolveLocationChecks($region, $articles));
        $articles = $this->selectArticles($this->removeDuplicates($articles));

        if ($articles === []) {
            return [];
        }

        // Preserve the existing thumbnail and image-proxy behaviour.
        $needsImages = array_filter($articles, static function (array $article): bool {
            return empty($article['image']);
        });
        return $needsImages !== []
            ? $this->applyImageMap($articles, $this->fetchGoogleImageMapSafely($region))
            : $articles;
    }

    /**
     * Retain completed responses even if one request fails DNS or times out.
     * Waiting on the already-created promises does not send another request.
     */
    private function fetchResponses(array $urls): array
    {
        $pending = [];
        $responses = [];

        try {
            $responses = Http::pool(function (Pool $pool) use ($urls, &$pending): array {
                foreach ($urls as $key => $url) {
                    $pending[$key] = $pool->as((string) $key)
                        ->withOptions(['allow_redirects' => [
                            'max' => 2,
                            'protocols' => ['https'],
                            'on_redirect' => function ($request, $response, $uri): void {
                                $url = (string) $uri;
                                if ($this->publisherForUrl($url) === null
                                    && parse_url($url, PHP_URL_HOST) !== 'news.google.com') {
                                    throw new \RuntimeException('News redirect left the configured publishers.');
                                }
                            },
                        ]])
                        ->withHeaders([
                            'User-Agent' => 'BRAVE-Public-News/1.0',
                            'Accept' => 'application/rss+xml, application/xml, text/html, */*',
                        ])
                        ->connectTimeout(3)
                        ->timeout(8)
                        ->get($url);
                }
                return $pending;
            });
        } catch (Throwable $error) {
            // Recover successful siblings from the same pool below.
        }

        foreach ($pending as $key => $promise) {
            if (array_key_exists($key, $responses)) {
                continue;
            }
            try {
                $responses[$key] = $promise->wait();
            } catch (Throwable $error) {
                $responses[$key] = null;
            }
        }

        return $responses;
    }

    private function fetchGoogleImageMapSafely(string $region): array
    {
        try {
            $response = Http::withOptions([
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
                    $this->googleQueryForRegion($region)
                ));

            return $this->imageMapFromGoogleHtmlResponse($response);
        } catch (Throwable $error) {
            return [];
        }
    }

    private function normaliseRegion(string $region): string
    {
        $region = strtolower(trim($region));

        if (in_array($region, ['brunei', 'borneo', 'regional'], true)) {
            return $region;
        }

        return 'brunei';
    }

    private function sourceLabelForRegion(string $region): string
    {
        return 'Borneo Bulletin';
    }

    private function googleQueryForRegion(string $region): string
    {
        return $this->googleQueriesForRegion($region)[0];
    }

    private function googleQueriesForRegion(string $region): array
    {
        $age = ' when:' . $this->maxArticleAgeDays($region) . 'd';
        // Google is a discovery index. Every accepted article must resolve to
        // Borneo Bulletin's domain, irrespective of what Google returns.
        return [
            'site:borneobulletin.com.bn '
                . '(fire OR fires OR blaze OR wildfire OR firefighter OR firefighting '
                . 'OR smoke OR haze OR hazy OR "air quality" OR PSI OR PM2.5 '
                . 'OR explosion OR kebakaran OR terbakar)' . $age,
            'site:borneobulletin.com.bn '
                . '("forest fire" OR "forest fires" OR "bush fire" OR "bush fires" '
                . 'OR "grass fire" OR "grass fires" OR haze OR smoke '
                . 'OR "air quality") when:45d',
        ];
    }

    private function maxArticleAgeDays(string $region): int
    {
        return $region === 'brunei'
            ? self::BRUNEI_MAX_ARTICLE_AGE_DAYS
            : self::BORNEO_MAX_ARTICLE_AGE_DAYS;
    }

    /** The only permitted article publisher, for both geographic buttons. */
    private function publishers(): array
    {
        return [
            'borneobulletin.com.bn' => [
                'name' => 'Borneo Bulletin', 'region' => 'brunei',
                'rank' => 0, 'wordpress' => true,
            ],
        ];
    }

    private function publisherForUrl(string $url): ?array
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        foreach ($this->publishers() as $domain => $publisher) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return $publisher + ['domain' => $domain, 'host' => $host];
            }
        }
        return null;
    }

    private function publisherFeeds(string $region): array
    {
        $base = 'https://borneobulletin.com.bn';
        $feeds = [];

        foreach (['', 'fire', 'blaze', 'forest', 'haze', 'smoke'] as $search) {
            $feeds[] = [
                'source' => 'Borneo Bulletin',
                'type' => 'rss',
                'url' => $base . '/?' . http_build_query([
                    'feed' => 'rss2', 's' => $search,
                    'orderby' => 'date', 'order' => 'DESC',
                ], '', '&', PHP_QUERY_RFC3986),
            ];
        }

        // The public WordPress API can provide complete article text when a
        // publisher's RSS endpoint exposes only a shortened excerpt.
        foreach (['fire', 'blaze', 'haze'] as $search) {
            $feeds[] = [
                'source' => 'Borneo Bulletin',
                'type' => 'wordpress',
                'url' => $base . '/wp-json/wp/v2/posts?' . http_build_query([
                    'search' => $search, 'orderby' => 'date', 'order' => 'desc',
                    'per_page' => 20,
                    '_fields' => 'link,date_gmt,date,title,excerpt,content',
                ], '', '&', PHP_QUERY_RFC3986),
            ];
        }

        return $feeds;
    }

    private function articlesFromWordpressResponse($response, string $region): array
    {
        if (!$this->isSuccessfulResponse($response)) {
            return [];
        }
        try {
            $posts = $response->json();
            if (!is_array($posts)) {
                return [];
            }
            $articles = [];
            foreach ($posts as $post) {
                if (!is_array($post)
                    || !is_array($post['title'] ?? null)
                    || !is_array($post['content'] ?? null)) {
                    continue;
                }
                $content = (string) ($post['content']['rendered'] ?? '');
                $utcDate = (string) ($post['date_gmt'] ?? '');
                $date = $utcDate !== '' ? $utcDate . 'Z' : (string) ($post['date'] ?? '');
                $article = $this->makeArticle(
                    (string) ($post['title']['rendered'] ?? ''),
                    (string) ($post['excerpt']['rendered'] ?? ''),
                    (string) ($post['link'] ?? ''), $date, 'Borneo Bulletin',
                    $this->extractImageFromMarkup($content), $region, false, $content
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

    private function recordSourceResult(
        string $url, $response, string $stage, int $accepted = 0
    ): void {
        $status = is_object($response) && method_exists($response, 'status')
            ? $response->status() : null;
        $outcome = 'ok';
        if ($status === null) {
            $outcome = 'connection_failed';
        } elseif ($status === 401 || $status === 403) {
            $outcome = 'access_denied';
        } elseif ($status === 429) {
            $outcome = 'rate_limited';
        } elseif (!$this->isSuccessfulResponse($response)) {
            $outcome = 'http_error';
        }
        $this->sourceDiagnostics[] = [
            'stage' => $stage, 'url' => $url, 'http_status' => $status,
            'outcome' => $outcome, 'accepted_articles' => $accepted,
        ];
    }

    private function newsStatus(array $articles): string
    {
        if ($articles !== []) {
            return 'ok';
        }
        foreach ($this->sourceDiagnostics as $source) {
            if ($source['outcome'] === 'ok') {
                return 'no-matching-articles';
            }
        }
        return 'upstream-unavailable';
    }

    private function articlesFromPublisherResponse(
        $response,
        string $source,
        string $region = 'brunei',
        bool $trustedRegionalSource = false
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
                    $image,
                    $region,
                    $trustedRegionalSource,
                    $rawContent
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
        string $source,
        string $region = 'brunei',
        bool $trustedRegionalSource = false
    ): array {
        try {
            if (!$this->isSuccessfulResponse($response)) {
                return [];
            }

            $body = trim($response->body());

            return $body !== ''
                ? $this->parseRss(
                    $body,
                    $source,
                    $region,
                    $trustedRegionalSource
                )
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
                $imageUrl = '';

                if ($imageNode instanceof \DOMElement) {
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
        $title = strtolower($this->newsEvidence($title));
        $title = preg_replace('/\s+online\s*$/u', '', $title) ?? $title;
        $title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title) ?? $title;
        return trim($title);
    }

    private function parseRss(
        string $xmlText,
        string $source,
        string $region = 'brunei',
        bool $trustedRegionalSource = false
    ): array {
        if (function_exists('simplexml_load_string')) {
            $articles = $this->parseWithSimpleXml(
                $xmlText,
                $source,
                $region,
                $trustedRegionalSource
            );

            if ($articles !== []) {
                return $articles;
            }
        }

        return $this->parseWithoutXmlExtension(
            $xmlText,
            $source,
            $region,
            $trustedRegionalSource
        );
    }

    private function parseWithSimpleXml(
        string $xmlText,
        string $source,
        string $region,
        bool $trustedRegionalSource
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
                $image,
                $region,
                $trustedRegionalSource,
                $rawContent,
                (string) ($item->source['url'] ?? '')
            );

            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    private function parseWithoutXmlExtension(
        string $xmlText,
        string $source,
        string $region,
        bool $trustedRegionalSource
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
                ),
                $region,
                $trustedRegionalSource,
                $content,
                $this->readSourceUrl($itemXml)
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

    private function resolveLocationChecks(string $region, array $accepted): array
    {
        $acceptedKeys = [];
        foreach ($accepted as $article) {
            $acceptedKeys[$this->normaliseTitleKey($article['title'])] = true;
        }
        $candidates = array_values(array_filter(
            $this->pendingLocationChecks,
            function (array $candidate) use ($acceptedKeys): bool {
                return !isset($acceptedKeys[$this->normaliseTitleKey($candidate['title'])]);
            }
        ));
        usort($candidates, static function (array $a, array $b): int {
            return ($b['timestamp'] <=> $a['timestamp'])
                ?: ($a['publisher']['rank'] <=> $b['publisher']['rank']);
        });
        $candidates = array_slice($candidates, 0, self::MAX_DETAIL_LOOKUPS);
        $urls = [];
        foreach ($candidates as $key => &$candidate) {
            if ($this->publisherForUrl($candidate['link']) !== null) {
                $candidate['lookup_type'] = 'html';
                $urls[$key] = $candidate['link'];
            } elseif ($candidate['publisher']['wordpress']) {
                // Ask the publisher's public WordPress API for this title.
                // No guessed article slug and no reliance on Google redirects.
                $candidate['lookup_type'] = 'wordpress';
                $urls[$key] = 'https://' . $candidate['publisher']['host']
                    . '/wp-json/wp/v2/posts?' . http_build_query([
                        'search' => $candidate['title'],
                        'per_page' => 3,
                        '_fields' => 'link,date_gmt,date,title,excerpt,content',
                    ], '', '&', PHP_QUERY_RFC3986);
            }
        }
        unset($candidate);

        if ($urls === []) {
            return [];
        }

        $this->collectLocationChecks = false;
        $articles = [];
        try {
            $responses = $this->fetchResponses($urls);
            foreach ($urls as $key => $url) {
                $response = $responses[$key] ?? null;
                $this->recordSourceResult($url, $response, 'article-location-check');
                if (!$this->isSuccessfulResponse($response)) {
                    continue;
                }
                $candidate = $candidates[$key];
                try {
                    if ($candidate['lookup_type'] === 'wordpress') {
                        $posts = $response->json();
                        if (!is_array($posts)) {
                            continue;
                        }
                        foreach ($posts as $post) {
                            if (!is_array($post)
                                || $this->normaliseTitleKey((string) ($post['title']['rendered'] ?? ''))
                                    !== $this->normaliseTitleKey($candidate['title'])) {
                                continue;
                            }
                            $content = (string) ($post['content']['rendered'] ?? '');
                            $date = !empty($post['date_gmt'])
                                ? $post['date_gmt'] . 'Z'
                                : (string) ($post['date'] ?? '');
                            $article = $this->makeArticle(
                                (string) ($post['title']['rendered'] ?? ''),
                                (string) ($post['excerpt']['rendered'] ?? ''),
                                (string) ($post['link'] ?? ''), $date,
                                $candidate['source'], $this->extractImageFromMarkup($content),
                                $region, false, $content
                            );
                            if ($article !== null) {
                                $articles[] = $article;
                            }
                        }
                    } else {
                        $details = $this->articleDetailsFromHtml($response->body());
                        if ($details['title'] !== ''
                            && $this->normaliseTitleKey($details['title'])
                                !== $this->normaliseTitleKey($candidate['title'])) {
                            continue;
                        }
                        $article = $this->makeArticle(
                            $candidate['title'],
                            $details['description'] ?: $candidate['description'],
                            $candidate['link'], $candidate['date'],
                            $candidate['source'], $details['image'] ?: $candidate['image'],
                            $region, false, $details['body']
                        );
                        if ($article !== null) {
                            $articles[] = $article;
                        }
                    }
                } catch (Throwable $error) {
                    // A malformed article must not remove other publishers.
                    continue;
                }
            }
        } finally {
            $this->collectLocationChecks = true;
            $this->pendingLocationChecks = [];
        }
        return $articles;
    }

    private function articleDetailsFromHtml(string $html): array
    {
        $result = ['title' => '', 'description' => '', 'body' => '', 'image' => ''];
        if (!class_exists('DOMDocument') || strlen($html) > 3 * 1024 * 1024) {
            return $result;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return $result;
            }
            $xpath = new \DOMXPath($dom);
            $metas = $xpath->query('//head/meta[@content]');
            if ($metas !== false) {
                foreach ($metas as $meta) {
                    if (!$meta instanceof \DOMElement) {
                        continue;
                    }
                    $key = strtolower($meta->getAttribute('property') ?: $meta->getAttribute('name'));
                    $content = $meta->getAttribute('content');
                    if ($key === 'og:title') {
                        $result['title'] = $this->cleanText($content);
                    } elseif (in_array($key, ['og:description', 'description'], true)) {
                        $result['description'] = $this->newsEvidence($content);
                    } elseif ($key === 'og:image') {
                        $result['image'] = $this->normaliseImageUrl($content);
                    }
                }
            }

            // Read articleBody only from Article/NewsArticle schema objects.
            $schemas = $xpath->query('//script[@type="application/ld+json"]');
            if ($schemas !== false) {
                foreach ($schemas as $schema) {
                    $body = $this->articleBodyFromSchema(json_decode($schema->textContent, true));
                    if ($body !== '') {
                        $result['body'] .= ' ' . $body;
                    }
                }
            }

            // Inspect content paragraphs, never the whole page, navigation,
            // related news or footer (which often mention unrelated places).
            $bodies = $xpath->query(
                '//*[@itemprop="articleBody"]'
                . ' | //*[contains(concat(" ", normalize-space(@class), " "), " td-post-content ")]'
                . ' | //*[contains(concat(" ", normalize-space(@class), " "), " entry-content ")]'
            );
            if ($bodies !== false) {
                foreach ($bodies as $body) {
                    $noise = $xpath->query(
                        './/script | .//style | .//nav | .//footer | .//aside'
                        . ' | .//*[contains(@class, "related") or contains(@class, "sharedaddy")'
                        . ' or contains(@class, "author-box") or contains(@class, "newsletter")]',
                        $body
                    );
                    if ($noise !== false) {
                        $remove = [];
                        foreach ($noise as $node) {
                            $remove[] = $node;
                        }
                        foreach ($remove as $node) {
                            if ($node->parentNode !== null) {
                                $node->parentNode->removeChild($node);
                            }
                        }
                    }
                    $paragraphs = $xpath->query('.//p', $body);
                    if ($paragraphs !== false && $paragraphs->length > 0) {
                        foreach ($paragraphs as $paragraph) {
                            $result['body'] .= ' ' . $this->newsEvidence($paragraph->textContent);
                        }
                    } else {
                        $result['body'] .= ' ' . $this->newsEvidence($body->textContent);
                    }
                    break;
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return $result;
    }

    private function articleBodyFromSchema($data): string
    {
        if (!is_array($data)) {
            return '';
        }
        $types = (array) ($data['@type'] ?? []);
        if (array_intersect($types, ['NewsArticle', 'Article', 'ReportageNewsArticle', 'BlogPosting']) !== []
            && is_string($data['articleBody'] ?? null)) {
            return $this->newsEvidence($data['articleBody']);
        }
        $parts = [];
        foreach ($data as $value) {
            if (is_array($value)) {
                $parts[] = $this->articleBodyFromSchema($value);
            }
        }
        return trim(implode(' ', $parts));
    }

    private function readSourceUrl(string $xml): string
    {
        return preg_match('/<source\b[^>]*\burl=["\']([^"\']+)["\']/i', $xml, $match)
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    }

    private function makeArticle(
        string $rawTitle,
        string $rawDescription,
        string $rawLink,
        string $rawDate,
        string $source,
        string $image = '',
        string $region = 'brunei',
        bool $trustedRegionalSource = false,
        string $rawContent = '',
        string $sourceUrl = ''
    ): ?array {
        $title = $this->cleanText($rawTitle);
        $description = $this->newsEvidence($rawDescription);
        $link = trim(html_entity_decode($rawLink, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        $publisher = $this->publisherForUrl($link);
        if ($publisher === null && $host === 'news.google.com'
            && strtolower((string) parse_url($link, PHP_URL_SCHEME)) === 'https') {
            $publisher = $this->publisherForUrl($sourceUrl);
        }

        if ($title === '' || $publisher === null || trim($rawDate) === '') {
            return null;
        }

        // Source labels are not location evidence.
        foreach (array_unique([$source, $publisher['name']]) as $label) {
            $title = preg_replace(
                '/\s+[-–—|]\s+' . preg_quote($label, '/') . '(?:\s+Online)?\s*$/iu',
                '', $title
            ) ?? $title;
        }
        $source = $publisher['name'];

        try {
            $timestamp = (new \DateTimeImmutable(
                $rawDate, new \DateTimeZone('Asia/Brunei')
            ))->getTimestamp();
        } catch (Throwable $error) {
            return null;
        }

        if ($timestamp < time() - $this->maxArticleAgeDays($region) * 86400
            || $timestamp > time() + 2 * 86400) {
            return null;
        }

        // Keep full RSS content for classification; truncate only display text.
        $body = $this->newsEvidence($rawContent);
        $evidence = trim($description . ' ' . $body);
        if (!$this->hasFireNewsTerms($title . ' ' . $evidence)) {
            return null;
        }

        if (!$this->isRelevantFireNews(
            $title, $evidence, $link, $region, false, $publisher['region'] === 'brunei'
        )) {
            $outside = $this->outsideBorneoPlaces();
            if ($region === 'brunei') {
                $outside = array_merge($outside, $this->borneoPlaces(), ['Malaysia', 'Indonesia']);
            } elseif ($region === 'borneo') {
                $outside = array_merge($outside, $this->bruneiPlaces()['districts']);
            }
            if ($this->collectLocationChecks
                && !$this->containsPlace($this->normaliseLocationText($title . ' ' . $description), $outside)) {
                $key = $publisher['domain'] . '|' . $this->normaliseTitleKey($title);
                $candidate = compact('title', 'description', 'link', 'source', 'image', 'region');
                $candidate['date'] = $this->bruneiIsoDate($timestamp);
                $candidate['timestamp'] = $timestamp;
                $candidate['publisher'] = $publisher;
                $candidate['source_url'] = $sourceUrl;

                // Prefer an actual publisher URL over a Google wrapper.
                if (!isset($this->pendingLocationChecks[$key])
                    || $host !== 'news.google.com') {
                    $this->pendingLocationChecks[$key] = $candidate;
                }
            }
            return null;
        }

        return [
            'title' => $title,
            'description' => $description !== ''
                ? $this->limitText($description, 220)
                : ($body !== '' ? $this->limitText($body, 220) : 'Click to read the full article.'),
            'url' => $link,
            'image' => $this->normaliseImageUrl($image),
            'source' => $source,
            'published_at' => $this->bruneiIsoDate($timestamp),
            '_timestamp' => $timestamp,
        ];
    }

    private function bruneiIsoDate(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone('Asia/Brunei'))
            ->format(DATE_ATOM);
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

    private function newsEvidence(string $markup): string
    {
        $markup = preg_replace(
            '~<(script|style|nav|footer)\b[^>]*>.*?</\1>~is', ' ', $markup
        ) ?? $markup;
        // WordPress's syndication footer includes the publisher's country/name.
        $text = $this->cleanText($markup);
        $text = preg_replace('/\bThe post\b.*?\bappeared first on\b.*$/isu', '', $text) ?? $text;
        foreach ($this->publishers() as $publisher) {
            $text = str_ireplace($publisher['name'], '', $text);
        }
        $text = str_ireplace(['Borneo Post', 'Brunei News', 'Borneo News', 'Google News'], '', $text);
        return trim($text);
    }

    private function normaliseLocationText(string $text): string
    {
        $text = strtolower($this->newsEvidence($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
        $text = preg_replace(
            ['/\b(?:kampung|kg|kpg)\b/u', '/\bsg\b/u', '/\bjln\b/u',
             '/\btanjung\b/u', '/\bpangkalan\b/u'],
            ['kampong', 'sungai', 'jalan', 'tanjong', 'pengkalan'], $text
        ) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function containsPlace(string $normalisedText, array $places): bool
    {
        static $normalisedPlaces = [];
        $haystack = ' ' . $normalisedText . ' ';
        foreach ($places as $place) {
            $needle = $normalisedPlaces[$place]
                ?? ($normalisedPlaces[$place] = ' ' . $this->normaliseLocationText($place) . ' ');
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extend these named places as reports introduce new localities.
     * This is a matching gazetteer, not an exhaustive administrative register.
     * Mukim references: tutong.gov.bn/direktori-pmkk/ and
     * temburong.gov.bn/mukim-mukim/; village spellings also follow mora.gov.bn.
     */
    private function bruneiPlaces(): array
    {
        return [
            'districts' => [
                'Brunei', 'Brunei Darussalam', 'Brunei Muara', 'Brunei and Muara',
                'Brunei dan Muara', 'Belait', 'Tutong', 'Temburong',
                'Bandar Seri Begawan', 'Kuala Belait', 'Pekan Tutong',
            ],
            'mukims' => [
                // Brunei-Muara
                'Berakas A', 'Berakas B', 'Gadong A', 'Gadong B', 'Kianggeh',
                'Kilanas', 'Kota Batu', 'Lumapas', 'Mentiri', 'Pengkalan Batu',
                'Sengkurong', 'Serasa', 'Burong Pingai Ayer', 'Peramu', 'Saba',
                'Sungai Kebun', 'Sungai Kedayan', 'Tamoi',
                // Belait
                'Seria', 'Kuala Belait', 'Kuala Balai', 'Liang', 'Bukit Sawat',
                'Labi', 'Sukang', 'Melilas',
                // Tutong
                'Pekan Tutong', 'Keriam', 'Kiudang', 'Lamunin', 'Rambai',
                'Tanjong Maya', 'Telisai', 'Ukong',
                // Temburong
                'Bangar', 'Batu Apoi', 'Bokok', 'Labu', 'Amo',
            ],
            'kampongs' => [
                'Jerudong', 'Rimba', 'Lambak', 'Lambak Kanan', 'Lambak Kiri',
                'Mulaut', 'Telanai', 'Tanjong Bunut', 'Bunut', 'Kiulap',
                'Kiarong', 'Beribi', 'Tungku', 'Katok', 'Mata Mata', 'Madang',
                'Manggis', 'Salambigar', 'Sungai Hanching', 'Sungai Tilong',
                'Sungai Akar', 'Sungai Orok', 'Tanah Jambu', 'Salambangan',
                'Batu Marang', 'Kapok', 'Meragang', 'Sabun', 'Pelumpong',
                'Mabohai', 'Kumbang Pasang', 'Pusar Ulak', 'Serusop', 'Pulaie',
                'Anggerek Desa', 'Delima Satu', 'Delima Dua', 'Burong Pingai',
                'Burong Pinggai', 'Madewa', 'Bengkurong', 'Sinarubai', 'Tasek Meradun',
                'Bebatik Kilanas', 'Jangsak', 'Ban', 'Tanjong Nangka', 'Kulapis',
                'Lugu', 'Katimahar', 'Masin', 'Junjongan', 'Limau Manis',
                'Panchor Murai', 'Batu Ampar', 'Bukit Panggal', 'Parit',
                'Kasat', 'Putat', 'Lupak Luas', 'Bunut Perpindahan',
                'Menunggol', 'Sungai Besar', 'Sungai Bunga', 'Sungai Matan',
                'Kampong Ayer', 'Kampong Air', 'Pelambayan', 'Pintu Malim',
                'Subok', 'Menantung', 'Sungai Buloh', 'Sungai Tampoi',
                'Lumut', 'Panaga', 'Mumong', 'Sungai Liang', 'Sungai Tali',
                'Sungai Teraban', 'Sungai Pandan', 'Sungai Duhon', 'Pandan',
                'Rasau', 'Lumut Tersusun', 'Lumut Tujuh', 'Lumut Satu',
                'Labi Satu', 'Labi Dua', 'Merangking', 'Bukit Puan', 'Labi',
                'Melilas', 'Sukang', 'Kuala Balai', 'Bukit Sawat',
                'Penanjong', 'Sengkarai', 'Kuala Tutong', 'Panchor', 'Petani',
                'Keriam', 'Sinaut', 'Sungai Kelugos', 'Sungai Damit',
                'Kupang', 'Luagan Dudok', 'Kiudang', 'Mungkom', 'Batang Mitus',
                'Bakiau', 'Pengkalan Mau', 'Lamunin', 'Layong', 'Panchong',
                'Rambai', 'Benutan', 'Meriuk', 'Tanjong Maya', 'Lubok Pulau',
                'Telisai', 'Danau', 'Bukit Beruang', 'Ukong', 'Long Mayan',
                'Batang Duri', 'Selapon', 'Sumbiling', 'Batu Apoi', 'Peliunan',
                'Batu Bejabang', 'Puni', 'Ujong Jalan', 'Belais', 'Buda Buda',
                'Labu Estate', 'Labu Estet', 'Piasau Piasau',
                'Bokok', 'Bokok Lama', 'Rataie', 'Rataeh', 'RPN Rataie',
            ],
            'landmarks' => [
                'Hassanal Bolkiah Highway', 'Lebuhraya Hassanal Bolkiah',
                'Sultan Hassanal Bolkiah Highway', 'Jalan Tutong',
                'Muara Tutong Highway', 'Tutong Muara Highway',
                'Jalan Kebangsaan', 'Jalan Pasir Berakas',
                'Raja Isteri Pengiran Anak Saleha Hospital',
                'RIPAS Hospital', 'Istana Nurul Iman',
                'Brunei Fire and Rescue Department',
                'Jabatan Bomba dan Penyelamat Brunei', 'BFRD',
            ],
        ];
    }

    private function hasBruneiPlace(string $text, bool $localPublisher): bool
    {
        $places = $this->bruneiPlaces();
        if ($this->containsPlace($text, array_merge($places['districts'], $places['landmarks']))) {
            return true;
        }

        // Many short names also exist overseas or are ordinary Malay words.
        // Require a local publisher or an explicit administrative/address form.
        $localNames = array_merge($places['mukims'], $places['kampongs'], ['Gadong', 'Berakas', 'Muara']);
        if ($localPublisher && $this->containsPlace($text, $localNames)) {
            return true;
        }

        foreach ($localNames as $name) {
            if ($this->containsPlace($text, [
                'kampong ' . $name, 'mukim ' . $name,
                'jalan ' . $name, $name . ' fire station',
            ])) {
                return true;
            }
        }
        return false;
    }

    private function borneoPlaces(): array
    {
        return [
            'Borneo', 'Sarawak', 'Sabah', 'Labuan', 'Kalimantan',
            'Kuching', 'Miri', 'Sibu', 'Bintulu', 'Limbang', 'Lawas',
            'Serian', 'Sri Aman', 'Sarikei', 'Kapit', 'Mukah', 'Lundu',
            'Kota Samarahan', 'Samarahan', 'Asajaya', 'Niah', 'Mulu',
            'Kota Kinabalu', 'Sandakan', 'Tawau', 'Lahad Datu', 'Ranau',
            'Penampang', 'Putatan', 'Tuaran', 'Kota Belud', 'Papar',
            'Keningau', 'Kudat', 'Semporna', 'Sipitang', 'Tenom',
            'Kinabatangan', 'Pontianak', 'Singkawang', 'Ketapang',
            'Sintang', 'Palangkaraya', 'Palangka Raya', 'Banjarmasin',
            'Banjarbaru', 'Samarinda', 'Balikpapan', 'Tarakan',
            'Tanjung Selor', 'Sangatta', 'Kutai', 'Sampit',
            'in Bau', 'di Bau', 'Bau district', 'daerah Bau',
            'in Betong', 'di Betong', 'Betong district',
            'Beaufort Sabah', 'Beaufort district',
        ];
    }

    private function outsideBorneoPlaces(): array
    {
        return [
            'Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Australia', 'New South Wales',
            'Hong Kong', 'South Korea', 'Korea Selatan', 'Los Angeles', 'California',
            'New York', 'United States', 'Amerika Syarikat', 'United Kingdom',
            'London', 'Singapore', 'Singapura', 'Kuala Lumpur', 'Selangor',
            'Johor', 'Penang', 'Pulau Pinang', 'Melaka', 'Malacca', 'Perak',
            'Terengganu', 'Kelantan', 'Kedah', 'Negeri Sembilan', 'Pahang',
            'Jakarta', 'Java', 'Jawa', 'Sumatra', 'Sumatera', 'Bali', 'Sulawesi',
            'China', 'Jepun', 'Japan', 'Thailand', 'Filipina', 'Philippines',
            'France', 'Perancis', 'Italy', 'Spain', 'Belgium', 'Germany',
            'Russia', 'Ukraine', 'Iran', 'Israel', 'Gaza', 'Pakistan',
            'Bangladesh', 'Brazil', 'India', 'Nepal', 'Vietnam', 'Canada',
        ];
    }

    private function hasFireNewsTerms(string $text): bool
    {
        $text = $this->normaliseLocationText($text);
        $fire = preg_match(
            '/\b(?:fires?|firefighters?|firefighting|blazes?|wildfires?|flames?|'
            . 'burning|burnt|burned|razed|smoke|haze|hazy|air quality|'
            . 'pollutant standard index|psi|pm ?2[._]?5|pm ?10|explosions?|'
            . 'kebakaran|terbakar|rentung|hangus|bomba|bfrd|short circuit|'
            . 'dijilat api|api marak)\b/u',
            $text
        ) === 1;
        $figurative = preg_match(
            '/\b(?:cease fire|ceasefire|opening fire|open fire|gunfire|under fire|'
            . 'shots? fired|fired from (?:a )?job|fired employee|fire sale|'
            . 'fireballs?|black magic)\b/u', $text
        ) === 1;
        return $fire && !$figurative;
    }

    private function isRelevantFireNews(
        string $title,
        string $description,
        string $link,
        string $region,
        bool $regionScopedSource = false,
        bool $localPublisher = false
    ): bool {
        $publisher = $this->publisherForUrl($link);
        $localPublisher = $localPublisher || ($publisher['region'] ?? '') === 'brunei';
        if (!$this->hasFireNewsTerms($title . ' ' . $description)) {
            return false;
        }

        $headline = $this->normaliseLocationText($title);
        // Give the lead more weight than passing mentions later in a story.
        $lead = $this->normaliseLocationText($this->limitText($description, 550));
        $text = $this->normaliseLocationText($title . ' ' . $description);

        $brunei = $this->hasBruneiPlace($text, $localPublisher);
        $borneo = $this->containsPlace($text, $this->borneoPlaces());
        $outside = $this->outsideBorneoPlaces();
        $targetHeadline = false;
        $targetLead = false;

        if ($region === 'brunei') {
            if (!$brunei) {
                return false;
            }
            $outside = array_merge($outside, $this->borneoPlaces(), ['Malaysia', 'Indonesia']);
            $targetHeadline = $this->hasBruneiPlace($headline, $localPublisher);
            $targetLead = $this->hasBruneiPlace($lead, $localPublisher);
        } elseif ($region === 'borneo') {
            // The Borneo UI button covers the surrounding region, excluding
            // Brunei-only stories. "Serian" must never match Brunei's "Seria".
            if (!$borneo) {
                return false;
            }
            $targetHeadline = $this->containsPlace($headline, $this->borneoPlaces());
            $targetLead = $this->containsPlace($lead, $this->borneoPlaces());
            $outside = array_merge($outside, $this->bruneiPlaces()['districts']);
        } else {
            if (!$brunei && !$borneo) {
                return false;
            }
            $targetHeadline = $this->hasBruneiPlace($headline, $localPublisher)
                || $this->containsPlace($headline, $this->borneoPlaces());
            $targetLead = $this->hasBruneiPlace($lead, $localPublisher)
                || $this->containsPlace($lead, $this->borneoPlaces());
        }

        if ($this->containsPlace($headline, $outside) && !$targetHeadline) {
            return false;
        }
        if ($this->containsPlace($text, $outside) && !$targetHeadline && !$targetLead) {
            return false;
        }
        // A publisher name, URL or Google query can never bypass this test.
        return true;
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

    private function sourceRank(string $source): int
    {
        foreach ($this->publishers() as $publisher) {
            if ($publisher['name'] === $source) {
                return $publisher['rank'];
            }
        }
        return 99;
    }

    private function selectArticles(array $articles): array
    {
        usort($articles, function (array $a, array $b): int {
            // Newest publication days first. Prefer Bulletin within the same
            // day, without moving old Bulletin stories ahead of newer news.
            $day = strcmp(
                gmdate('Y-m-d', $b['_timestamp'] + 8 * 3600),
                gmdate('Y-m-d', $a['_timestamp'] + 8 * 3600)
            );
            return $day
                ?: ($this->sourceRank($a['source']) <=> $this->sourceRank($b['source']))
                ?: ($b['_timestamp'] <=> $a['_timestamp'])
                ?: strcmp($a['title'], $b['title']);
        });
        if ($articles === []) {
            return [];
        }

        $newestTimestamp = (int) ($articles[0]['_timestamp'] ?? 0);
        $displayCutoff = $newestTimestamp
            - (self::MAX_DISPLAY_DATE_SPREAD_DAYS * 86400);

        $selected = array_values(array_filter(
            $articles,
            static fn (array $article): bool =>
                (int) ($article['_timestamp'] ?? 0) >= $displayCutoff
        ));

        // A sparse local-news cycle should not collapse the UI to one card.
        // If fewer than four stories fall inside the 45-day cluster, backfill
        // only enough of the already validated results to reach four.
        if (count($selected) < self::MIN_ARTICLE_COUNT) {
            $selectedTitles = [];

            foreach ($selected as $article) {
                $selectedTitles[$this->normaliseTitleKey($article['title'])] = true;
            }

            foreach ($articles as $article) {
                $titleKey = $this->normaliseTitleKey($article['title']);

                if (isset($selectedTitles[$titleKey])) {
                    continue;
                }

                $selected[] = $article;
                $selectedTitles[$titleKey] = true;

                if (count($selected) >= self::MIN_ARTICLE_COUNT) {
                    break;
                }
            }
        }

        return array_slice($selected, 0, self::MAX_ARTICLE_COUNT);
    }

    private function removeDuplicates(array $articles): array
    {
        $unique = [];
        $titles = [];
        $urls = [];

        foreach ($articles as $article) {
            $titleKey = $this->normaliseTitleKey($article['title']);
            $parts = parse_url($article['url']);
            $host = preg_replace('/^www\./i', '', (string) ($parts['host'] ?? ''));
            $urlKey = $host . rtrim((string) ($parts['path'] ?? ''), '/');
            // WordPress ?p= links must not all collapse to the publisher root.
            if (($parts['path'] ?? '/') === '/' && isset($parts['query'])) {
                $urlKey .= '?' . $parts['query'];
            }
            $index = $urls[$urlKey] ?? $titles[$titleKey] ?? null;
            if ($index === null) {
                $index = count($unique);
                $unique[] = $article;
            } else {
                $existing = $unique[$index];
                $direct = $this->publisherForUrl($article['url']) !== null;
                $existingDirect = $this->publisherForUrl($existing['url']) !== null;
                if (($direct && !$existingDirect)
                    || ($direct === $existingDirect
                        && $this->sourceRank($article['source']) < $this->sourceRank($existing['source']))) {
                    if (empty($article['image'])) {
                        $article['image'] = $existing['image'];
                    }
                    $unique[$index] = $article;
                } elseif (empty($unique[$index]['image']) && !empty($article['image'])) {
                    $unique[$index]['image'] = $article['image'];
                }
            }
            $titles[$titleKey] = $index;
            $urls[$urlKey] = $index;
        }
        return $unique;
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
