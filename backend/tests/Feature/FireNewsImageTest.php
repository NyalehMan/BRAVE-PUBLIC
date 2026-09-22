<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FireNewsImageTest extends TestCase
{
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
