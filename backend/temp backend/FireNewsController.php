<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FireNewsController extends Controller
{
    public function index()
    {
        $news = Cache::remember('fire_news_latest_v6', now()->addMinutes(10), function () {
            return $this->fetchNews();
        });

        return response()
            ->json($news)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    private function fetchNews(): array
    {
        $feeds = [
            [
                'source' => 'Borneo Bulletin',
                'url' => 'https://borneobulletin.com.bn/?feed=rss2&s=fire',
            ],
            [
                'source' => 'Borneo Bulletin',
                'url' => 'https://borneobulletin.com.bn/?feed=rss2&s=bomba',
            ],
            [
                'source' => 'Borneo Bulletin',
                'url' => 'https://borneobulletin.com.bn/?feed=rss2&s=kebakaran',
            ],
            [
                'source' => 'Google News',
                'url' => 'https://news.google.com/rss/search?q=Brunei%20fire%20OR%20bomba%20OR%20kebakaran&hl=en-BN&gl=BN&ceid=BN:en',
            ],
        ];

        $news = collect();

        foreach ($feeds as $feed) {
            try {
                $response = Http::withoutVerifying()
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0',
                    ])
                    ->get($feed['url']);

                if (!$response->successful()) {
                    continue;
                }

                $xml = @simplexml_load_string(
                    $response->body(),
                    'SimpleXMLElement',
                    LIBXML_NOCDATA
                );

                if (!$xml || !isset($xml->channel->item)) {
                    continue;
                }

                foreach ($xml->channel->item as $item) {
                    $title = $this->cleanText((string) $item->title);
                    $description = $this->cleanText((string) $item->description);
                    $url = trim((string) $item->link);
                    $publishedAt = trim((string) $item->pubDate);

                    if (!$title || !$url) {
                        continue;
                    }

                    $combined = Str::lower($title . ' ' . $description . ' ' . $url);

                    if ($this->isBlockedNews($combined)) {
                        continue;
                    }

                    $sourceName = $feed['source'];

                    if (isset($item->source)) {
                        $rssSource = $this->cleanText((string) $item->source);

                        if ($rssSource) {
                            $sourceName = $rssSource;
                        }
                    }

                    $news->push([
                        'title' => $title,
                        'description' => $description ?: 'Click to read full article.',
                        'url' => $url,
                        'image' => $this->extractImageFromItem($item) ?: '/images/news-placeholder.jpg',
                        'source' => $sourceName,
                        'published_at' => $publishedAt ? date('Y-m-d H:i:s', strtotime($publishedAt)) : null,
                    ]);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $news
            ->unique('url')
            ->sortByDesc('published_at')
            ->take(6)
            ->values()
            ->toArray();
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode(trim(strip_tags($text)));
        $cleaned = preg_replace('/\s+/', ' ', $text);

        return $cleaned ?: '';
    }

    private function extractImageFromItem($item): ?string
    {
        $media = $item->children('media', true);

        if (isset($media->content)) {
            $attributes = $media->content->attributes();

            if (isset($attributes['url'])) {
                return trim((string) $attributes['url']);
            }
        }

        if (isset($media->thumbnail)) {
            $attributes = $media->thumbnail->attributes();

            if (isset($attributes['url'])) {
                return trim((string) $attributes['url']);
            }
        }

        if (isset($item->enclosure)) {
            $attributes = $item->enclosure->attributes();

            if (isset($attributes['url'])) {
                $imageUrl = trim((string) $attributes['url']);

                if ($imageUrl) {
                    return $imageUrl;
                }
            }
        }

        $description = (string) $item->description;

        if ($description) {
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches);

            if (!empty($matches[1])) {
                return html_entity_decode(trim($matches[1]));
            }
        }

        return null;
    }

    private function isBlockedNews(string $combined): bool
    {
        return Str::contains($combined, [
            'ceasefire',
            'gaza',
            'ukraine',
            'israel',
            'california',
            'canada',
            'los angeles',
            'los-angeles',
            'san diego',
            'san-diego',
            'white house',
            'white-house',
            'secret service',
            'secret-service',
            'pope',
            'italy',
            'gunmen',
            'opening fire',
            'opening-fire',
            'open fire',
            'open-fire',
        ]);
    }
}