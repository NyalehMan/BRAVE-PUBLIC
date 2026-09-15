<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TlsVerificationTest extends TestCase
{
    public function test_application_code_does_not_disable_tls_verification(): void
    {
        $directories = [
            app_path(),
            base_path('routes'),
        ];

        $violations = [];

        foreach ($directories as $directory) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($files as $file) {
                if (
                    !$file->isFile()
                    || $file->getExtension() !== 'php'
                ) {
                    continue;
                }

                $contents = file_get_contents(
                    $file->getPathname()
                );

                if (
                    $contents !== false
                    && (
                        str_contains(
                            $contents,
                            'withoutVerifying('
                        )
                        || preg_match(
                            "/['\"]verify['\"]\s*=>\s*false/",
                            $contents
                        ) === 1
                    )
                ) {
                    $relativePath = str_replace(
                        base_path() . DIRECTORY_SEPARATOR,
                        '',
                        $file->getPathname()
                    );

                    $violations[] = str_replace(
                        '\\',
                        '/',
                        $relativePath
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'TLS verification is disabled in: '
                . implode(', ', $violations)
        );
    }
}
