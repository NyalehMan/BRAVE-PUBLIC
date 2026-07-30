<?php

namespace App\Console\Commands;

use App\Models\BruneiTide;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

#[Signature('tide:sync-brunei')]
#[Description('Scrape Brunei tide pages and store tide data')]
class SyncBruneiTides extends Command
{
    public function handle(): int
    {
        $stations = [
            [
                'district' => 'Brunei Muara',
                'station' => 'Bandar Seri Begawan',
                'url' => 'https://www.tide-forecast.com/locations/Bandar-Seri-Begawan/tides/latest',
            ],
            [
                'district' => 'Tutong',
                'station' => 'Tutong',
                'url' => 'https://www.tide-forecast.com/locations/Tutong/tides/latest',
            ],
            [
                'district' => 'Belait',
                'station' => 'Kuala Belait',
                'url' => 'https://www.tide-forecast.com/locations/Kuala-Belait/tides/latest',
            ],
            // [
            //     'district' => 'Temburong',
            //     'station' => 'Bangar',
            //     'url' => 'https://www.tide-forecast.com/locations/Bangar/tides/latest',
            // ],
        ];

        foreach ($stations as $station) {
            $this->info("Scraping {$station['station']}...");

           $response = Http::withOptions(['verify' => true])
    ->timeout(20)
    ->withHeaders([
        'User-Agent' => 'Mozilla/5.0 BRAVE Tide Sync',
    ])
    ->get($station['url']);

            if (! $response->successful()) {
                $this->warn("Failed to fetch {$station['station']}");
                continue;
            }

            $crawler = new Crawler($response->body());
            $text = $crawler->filter('body')->text(' ');

            preg_match_all(
                '/(High Tide|Low Tide)\s+(\d{1,2}:\d{2}\s*[AP]M).*?(\d{1,2})\s+([A-Za-z]{3}).*?([\d.]+)\s*m/i',
                $text,
                $matches,
                PREG_SET_ORDER
            );

            $saved = 0;

            foreach ($matches as $match) {
                $type = $match[1];
                $time = strtoupper(str_replace(' ', '', $match[2]));
                $day = $match[3];
                $month = $match[4];
                $height = $match[5];

                $datetime = Carbon::createFromFormat(
                    'd M Y h:iA',
                    "{$day} {$month} " . now('Asia/Brunei')->year . " {$time}",
                    'Asia/Brunei'
                );

                BruneiTide::updateOrCreate(
                    [
                        'district' => $station['district'],
                        'station' => $station['station'],
                        'tide_datetime' => $datetime,
                    ],
                    [
                        'source_url' => $station['url'],
                        'tide_height' => $height,
                        'tide_type' => $type,
                    ]
                );

                $saved++;
            }

            $this->info("Saved {$saved} rows for {$station['station']}");
        }

        return self::SUCCESS;
    }
}