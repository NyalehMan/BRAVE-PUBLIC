<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('brave:secure-report-photos', function () {
    $publicDisk = Storage::disk('public');
    $privateDisk = Storage::disk('local');
    $moved = 0;

    foreach ($publicDisk->files('public_reports') as $path) {
        $sourceSize = $publicDisk->size($path);
        $sourceChecksum = hash('sha256', $publicDisk->get($path));

        if (! $privateDisk->exists($path)) {
            $stream = $publicDisk->readStream($path);

            if ($stream === false) {
                $this->error("Unable to read {$path}.");

                continue;
            }

            try {
                if (! $privateDisk->put($path, $stream)) {
                    $this->error("Unable to secure {$path}.");

                    continue;
                }
            } finally {
                fclose($stream);
            }
        }

        if (
            $privateDisk->size($path) !== $sourceSize
            || hash('sha256', $privateDisk->get($path)) !== $sourceChecksum
        ) {
            $this->error("Copy verification failed for {$path}; the public copy was retained.");

            continue;
        }

        if (! $publicDisk->delete($path)) {
            $this->error("The private copy is safe, but the public copy of {$path} could not be removed.");

            continue;
        }

        $moved++;
    }

    $this->info("Secured {$moved} public report photo(s).");
})->purpose('Move legacy public-report evidence into private storage');
