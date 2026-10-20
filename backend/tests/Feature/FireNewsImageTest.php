<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FireNewsImageTest extends TestCase
{
    public function test_feed_article_uses_the_publishers_open_graph_image(): void
    {
        $articleUrl = 'https://borneobulletin.com.bn/brunei-fire-update/';
        $imageUrl = 'https://borneobulletin.com.bn/wp-content/uploads/fire-update.jpg';
        $publishedAt = now('UTC')->format(DATE_RSS);
        $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><item>
<title>Brunei fire update</title>
<link>{$articleUrl}</link>
<pubDate>{$publishedAt}</pubDate>
<description><![CDATA[]]></description>
<content:encoded xmlns:content="http://purl.org/rss/1.0/modules/content/"><![CDATA[]]></content:encoded>
</item></channel></rss>
XML;
        $articleHtml = <<<HTML
<!doctype html><html><head>
<meta property="og:title" content="Brunei fire update">
<meta property="og:image" content="{$imageUrl}">
</head><body><article itemprop="articleBody"><p>A fire in Brunei was extinguished.</p></article></body></html>
HTML;

        Http::fake(function (Request $request) use ($articleUrl, $articleHtml, $rss) {
            if ($request->url() === $articleUrl) {
                return Http::response($articleHtml, 200, ['Content-Type' => 'text/html']);
            }

            if (str_contains($request->url(), 'borneobulletin.com.bn/?feed=rss2')) {
                return Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']);
            }

            if (str_contains($request->url(), '/wp-json/wp/v2/posts')) {
                return Http::response([], 200, ['Content-Type' => 'application/json']);
            }

            return Http::response('', 404);
        });

        $response = $this->get('/api/fire-news?region=brunei');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Brunei fire update');

        $proxiedImage = (string) $response->json('0.image');
        $this->assertStringContainsString('/api/fire-news/image?', $proxiedImage);
        parse_str((string) parse_url($proxiedImage, PHP_URL_QUERY), $query);
        $this->assertSame($imageUrl, $query['url'] ?? null);

        Http::assertSent(
            fn (Request $request): bool => $request->url() === $articleUrl
        );
    }

    public function test_publisher_image_is_served_through_the_news_proxy(): void
    {
        $remoteUrl = 'https://borneobulletin.com.bn/wp-content/uploads/news.jpg';

        Http::fake([
            $remoteUrl => Http::response(
                'jpeg-image-bytes',
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $this->get('/api/fire-news/image?' . http_build_query([
            'url' => $remoteUrl,
        ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->assertContent('jpeg-image-bytes');

        Http::assertSent(
            fn (Request $request): bool => $request->url() === $remoteUrl
        );
    }

    public function test_google_news_article_resolves_to_the_publisher_thumbnail(): void
    {
        $articleUrl = 'https://news.google.com/rss/articles/example?oc=5';
        $publisherUrl = 'https://borneobulletin.com.bn/brunei-fire-update/';
        $imageUrl = 'https://borneobulletin.com.bn/wp-content/uploads/fire-update.jpg';
        $publishedAt = now('UTC')->format(DATE_RSS);
        $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><item>
<title>Brunei fire update - Borneo Bulletin</title>
<link>{$articleUrl}</link>
<pubDate>{$publishedAt}</pubDate>
<description><![CDATA[]]></description>
<source url="https://borneobulletin.com.bn">Borneo Bulletin</source>
</item></channel></rss>
XML;
        $articleHtml = <<<HTML
<!doctype html><html><head>
<meta property="og:title" content="Brunei fire update &raquo; Borneo Bulletin Online">
<meta property="og:image" content="{$imageUrl}">
</head><body></body></html>
HTML;

        Http::fake(function (Request $request) use ($publisherUrl, $articleHtml, $rss) {
            if ($request->url() === $publisherUrl) {
                return Http::response($articleHtml, 200, ['Content-Type' => 'text/html']);
            }

            if (str_starts_with($request->url(), 'https://news.google.com/rss/search?')) {
                return Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']);
            }

            if (str_contains($request->url(), '/wp-json/wp/v2/posts')) {
                return Http::response([], 200, ['Content-Type' => 'application/json']);
            }

            return Http::response('', 404);
        });

        $response = $this->get('/api/fire-news?region=brunei');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Brunei fire update')
            ->assertJsonPath('0.url', $publisherUrl);

        parse_str(
            (string) parse_url((string) $response->json('0.image'), PHP_URL_QUERY),
            $query
        );
        $this->assertSame($imageUrl, $query['url'] ?? null);
    }

    public function test_news_proxy_rejects_an_unapproved_image_host(): void
    {
        Http::fake();

        $this->get('/api/fire-news/image?' . http_build_query([
            'url' => 'https://example.test/news.jpg',
        ]))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_generated_news_thumbnail_can_be_embedded_cross_origin(): void
    {
        $response = $this->get('/fire-news/thumbnail?' . http_build_query([
            'title' => 'Brunei fire update',
            'source' => 'Borneo Bulletin',
        ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');

        $this->assertStringContainsString(
            'Brunei fire update',
            $response->getContent()
        );
    }
}
