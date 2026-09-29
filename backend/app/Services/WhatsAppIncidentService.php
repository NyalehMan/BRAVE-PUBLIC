<?php

namespace App\Services;

use App\Models\PublicIncidentReport;
use App\Models\WhatsAppInboundMessage;
use App\Models\WhatsAppReportDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppIncidentService
{
    private const DRAFT_LIFETIME_HOURS = 24;

    private const SUPPORTED_MESSAGE_TYPES = [
        'text',
        'location',
        'image',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{messages_processed: int, reports_created: array<int, int>}
     */
    public function process(array $payload): array
    {
        $messagesProcessed = 0;
        $reportsCreated = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = is_array($change['value'] ?? null)
                    ? $change['value']
                    : [];

                foreach ($value['messages'] ?? [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $outcome = $this->processMessage($value, $message);

                    if (($outcome['processed'] ?? false) === true) {
                        $messagesProcessed++;
                    }

                    if (isset($outcome['report_id'])) {
                        $reportsCreated[] = (int) $outcome['report_id'];
                    }
                }
            }
        }

        return [
            'messages_processed' => $messagesProcessed,
            'reports_created' => array_values(array_unique($reportsCreated)),
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function processMessage(array $value, array $message): array
    {
        $messageId = trim((string) ($message['id'] ?? ''));
        $sender = trim((string) ($message['from'] ?? ''));
        $messageType = trim((string) ($message['type'] ?? ''));

        if ($messageId === '' || $sender === '' || $messageType === '') {
            return ['processed' => false];
        }

        $phoneNumberId = trim((string) (
            $value['metadata']['phone_number_id']
                ?? config('services.whatsapp.phone_number_id', '')
        ));
        $reporterName = $this->reporterName($value, $sender);

        $outcome = DB::transaction(function () use (
            $messageId,
            $sender,
            $messageType,
            $phoneNumberId,
            $reporterName,
            $message
        ): array {
            $inbound = WhatsAppInboundMessage::query()
                ->where('meta_message_id', $messageId)
                ->lockForUpdate()
                ->first();

            if ($inbound?->processed_at !== null) {
                return [
                    'processed' => false,
                    'duplicate' => true,
                ];
            }

            if ($inbound === null) {
                $inbound = WhatsAppInboundMessage::query()->create([
                    'meta_message_id' => $messageId,
                    'sender_wa_id' => $sender,
                    'message_type' => Str::limit($messageType, 30, ''),
                ]);
            }

            if (! in_array($messageType, self::SUPPORTED_MESSAGE_TYPES, true)) {
                $inbound->update([
                    'processed_at' => now(),
                    'processing_error' => null,
                ]);

                return [
                    'processed' => true,
                    'reply' => $this->instructions(),
                ];
            }

            $draft = WhatsAppReportDraft::query()
                ->where('sender_wa_id', $sender)
                ->lockForUpdate()
                ->first();

            if ($draft === null) {
                $draft = new WhatsAppReportDraft([
                    'sender_wa_id' => $sender,
                    'started_at' => now(),
                ]);
            } elseif (
                $draft->last_activity_at !== null
                && $draft->last_activity_at->lt(
                    now()->subHours(self::DRAFT_LIFETIME_HOURS)
                )
            ) {
                $this->discardDraftPhoto($draft);
                $this->resetDraft($draft);
            }

            $draft->reporter_name = $reporterName;
            $draft->phone_number_id = $phoneNumberId !== ''
                ? $phoneNumberId
                : null;
            $draft->last_activity_at = now();

            $command = null;

            if ($messageType === 'text') {
                $command = $this->applyText(
                    $draft,
                    (string) ($message['text']['body'] ?? '')
                );
            } elseif ($messageType === 'location') {
                $this->applyLocation($draft, $message['location'] ?? []);
            } elseif ($messageType === 'image') {
                $command = $this->applyText(
                    $draft,
                    (string) ($message['image']['caption'] ?? '')
                );
                $this->applyImage($draft, $message['image'] ?? []);
            }

            if ($command === 'cancel') {
                $this->discardDraftPhoto($draft);
                $draft->delete();
                $inbound->update([
                    'processed_at' => now(),
                    'processing_error' => null,
                ]);

                return [
                    'processed' => true,
                    'reply' => 'Your BRAVE report draft was cancelled.',
                ];
            }

            $draft->save();

            if (! $this->isComplete($draft)) {
                $inbound->update([
                    'processed_at' => now(),
                    'processing_error' => null,
                ]);

                return [
                    'processed' => true,
                    'reply' => $command === 'help'
                        ? $this->instructions()
                        : $this->missingDetailsReply($draft),
                ];
            }

            $report = $this->createReport($draft);
            $draftStartedAt = $draft->started_at ?? $draft->created_at ?? now();

            WhatsAppInboundMessage::query()
                ->where('sender_wa_id', $sender)
                ->whereNull('public_incident_report_id')
                ->where('created_at', '>=', $draftStartedAt)
                ->update([
                    'public_incident_report_id' => $report->id,
                ]);

            $inbound->update([
                'public_incident_report_id' => $report->id,
                'processed_at' => now(),
                'processing_error' => null,
            ]);
            $draft->delete();

            return [
                'processed' => true,
                'report_id' => $report->id,
                'reply' => "BRAVE report #{$report->id} was received and is pending operator verification. If anyone is in immediate danger, contact emergency services now.",
            ];
        });

        $this->sendReply(
            $phoneNumberId,
            $sender,
            (string) ($outcome['reply'] ?? '')
        );

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function reporterName(array $value, string $sender): string
    {
        foreach ($value['contacts'] ?? [] as $contact) {
            if (
                (string) ($contact['wa_id'] ?? '') === $sender
                && trim((string) ($contact['profile']['name'] ?? '')) !== ''
            ) {
                return Str::limit(
                    trim((string) $contact['profile']['name']),
                    150,
                    ''
                );
            }
        }

        return 'WhatsApp Reporter';
    }

    private function resetDraft(WhatsAppReportDraft $draft): void
    {
        $draft->fill([
            'reporter_name' => null,
            'phone_number_id' => null,
            'incident_type' => null,
            'severity_level' => null,
            'district' => null,
            'description' => null,
            'latitude' => null,
            'longitude' => null,
            'photo_path' => null,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    private function applyText(
        WhatsAppReportDraft $draft,
        string $body
    ): ?string {
        $body = trim($body);

        if ($body === '') {
            return null;
        }

        $normalised = Str::lower($body);

        if (in_array($normalised, ['cancel', 'cancel report'], true)) {
            return 'cancel';
        }

        if (in_array($normalised, ['new', 'new report', 'restart'], true)) {
            $this->discardDraftPhoto($draft);
            $this->resetDraft($draft);

            return 'help';
        }

        if (in_array($normalised, ['hi', 'hello', 'help', 'report', 'start'], true)) {
            return 'help';
        }

        $fields = [];
        $descriptionLines = [];

        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            if (preg_match(
                '/^\s*(?:type|category|incident(?:\s+type)?)\s*[:\-]\s*(.+)$/iu',
                $line,
                $matches
            ) === 1) {
                $fields['incident_type'] = trim($matches[1]);

                continue;
            }

            if (preg_match(
                '/^\s*(?:district|area)\s*[:\-]\s*(.+)$/iu',
                $line,
                $matches
            ) === 1) {
                $fields['district'] = trim($matches[1]);

                continue;
            }

            if (preg_match(
                '/^\s*(?:urgency|severity)\s*[:\-]\s*(.+)$/iu',
                $line,
                $matches
            ) === 1) {
                $fields['severity_level'] = trim($matches[1]);

                continue;
            }

            if (preg_match(
                '/^\s*(?:description|details|message)\s*[:\-]\s*(.+)$/iu',
                $line,
                $matches
            ) === 1) {
                $descriptionLines[] = trim($matches[1]);

                continue;
            }

            if (trim($line) !== '') {
                $descriptionLines[] = trim($line);
            }
        }

        $description = trim(implode(PHP_EOL, $descriptionLines));

        if ($description === '' && $fields === []) {
            $description = $body;
        }

        if ($description !== '') {
            $draft->description = Str::limit(
                trim(implode(PHP_EOL, array_filter([
                    $draft->description,
                    $description,
                ]))),
                5000,
                ''
            );
        }

        if (trim((string) ($fields['incident_type'] ?? '')) !== '') {
            $explicitType = trim((string) $fields['incident_type']);
            $inferredType = $this->incidentType($explicitType, null);
            $draft->incident_type = $inferredType !== 'Other'
                ? $inferredType
                : Str::limit(Str::headline($explicitType), 100, '');
        } else {
            $draft->incident_type = $this->incidentType(
                $description,
                $draft->incident_type
            );
        }

        $district = $this->district(
            (string) ($fields['district'] ?? $description)
        );

        if ($district !== null) {
            $draft->district = $district;
        }

        $severity = $this->severity(
            (string) ($fields['severity_level'] ?? $description)
        );

        if ($severity !== null) {
            $draft->severity_level = $severity;
        }

        return null;
    }

    /**
     * @param  mixed  $location
     */
    private function applyLocation(
        WhatsAppReportDraft $draft,
        $location
    ): void {
        if (! is_array($location)) {
            return;
        }

        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;

        if (
            ! is_numeric($latitude)
            || ! is_numeric($longitude)
            || (float) $latitude < -90
            || (float) $latitude > 90
            || (float) $longitude < -180
            || (float) $longitude > 180
        ) {
            return;
        }

        $draft->latitude = (float) $latitude;
        $draft->longitude = (float) $longitude;

        $locationLabel = trim(implode(' ', array_filter([
            $location['name'] ?? null,
            $location['address'] ?? null,
        ])));
        $district = $this->district($locationLabel)
            ?? $this->nearestDistrict(
                (float) $latitude,
                (float) $longitude
            );

        if ($district !== null) {
            $draft->district = $district;
        }
    }

    /**
     * @param  mixed  $image
     */
    private function applyImage(
        WhatsAppReportDraft $draft,
        $image
    ): void {
        if (! is_array($image)) {
            return;
        }

        $mediaId = trim((string) ($image['id'] ?? ''));

        if ($mediaId === '') {
            return;
        }

        $path = $this->downloadImage($mediaId);

        if ($path === null) {
            return;
        }

        $this->discardDraftPhoto($draft);
        $draft->photo_path = $path;
    }

    private function downloadImage(string $mediaId): ?string
    {
        $accessToken = trim((string) config(
            'services.whatsapp.access_token',
            ''
        ));

        if ($accessToken === '') {
            return null;
        }

        try {
            $version = trim((string) config(
                'services.whatsapp.graph_api_version',
                'v23.0'
            ));
            $metadataUrl = 'https://graph.facebook.com/'
                .rawurlencode($version)
                .'/'.rawurlencode($mediaId);
            $metadata = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->get($metadataUrl)
                ->throw()
                ->json();
            $downloadUrl = trim((string) ($metadata['url'] ?? ''));
            $mimeType = Str::lower(trim((string) (
                $metadata['mime_type'] ?? ''
            )));
            $declaredSize = (int) ($metadata['file_size'] ?? 0);
            $maximumBytes = (int) config(
                'services.whatsapp.media_max_bytes',
                15 * 1024 * 1024
            );

            if (
                ! $this->isTrustedMediaUrl($downloadUrl)
                || ! in_array(
                    $mimeType,
                    ['image/jpeg', 'image/png', 'image/webp'],
                    true
                )
                || ($declaredSize > 0 && $declaredSize > $maximumBytes)
            ) {
                return null;
            }

            $response = Http::withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(10)
                ->get($downloadUrl)
                ->throw();
            $bytes = $response->body();

            if (
                $bytes === ''
                || strlen($bytes) > $maximumBytes
                || @getimagesizefromstring($bytes) === false
            ) {
                return null;
            }

            $extension = match ($mimeType) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $path = 'public_reports/whatsapp-'
                .Str::uuid()
                .'.'.$extension;

            return Storage::disk(
                config('filesystems.report_photos_disk', 'local')
            )->put($path, $bytes)
                ? $path
                : null;
        } catch (Throwable $exception) {
            Log::warning('WhatsApp incident image could not be downloaded.', [
                'media_id_hash' => hash('sha256', $mediaId),
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function isTrustedMediaUrl(string $url): bool
    {
        if ($url === '' || ! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        foreach (['facebook.com', 'fbcdn.net', 'fbsbx.com'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    private function discardDraftPhoto(WhatsAppReportDraft $draft): void
    {
        if ($draft->photo_path === null || $draft->photo_path === '') {
            return;
        }

        Storage::disk(
            config('filesystems.report_photos_disk', 'local')
        )->delete($draft->photo_path);
    }

    private function isComplete(WhatsAppReportDraft $draft): bool
    {
        return trim((string) $draft->description) !== ''
            && $draft->latitude !== null
            && $draft->longitude !== null;
    }

    private function createReport(
        WhatsAppReportDraft $draft
    ): PublicIncidentReport {
        $description = trim(implode(PHP_EOL, array_filter([
            'Submitted through WhatsApp.',
            $draft->severity_level !== null
                ? 'Observed urgency: '.$draft->severity_level
                : null,
            '',
            $draft->description,
        ], fn ($line): bool => $line !== null)));

        return PublicIncidentReport::query()->create([
            'reporter_ic_no' => 'Not provided',
            'reporter_full_name' => Str::limit(
                trim((string) $draft->reporter_name) !== ''
                    ? $draft->reporter_name.' (WhatsApp)'
                    : 'WhatsApp Reporter',
                150,
                ''
            ),
            'district' => $draft->district
                ?? 'Location shared via WhatsApp',
            'incident_type' => $draft->incident_type ?? 'Other',
            'description' => Str::limit($description, 5000, ''),
            'latitude' => $draft->latitude,
            'longitude' => $draft->longitude,
            'photo_path' => $draft->photo_path,
            'status' => 'PENDING',
        ]);
    }

    private function incidentType(
        string $text,
        ?string $existing
    ): string {
        $normalised = Str::lower($text);
        $patterns = [
            'Wildfire' => ['wildfire', 'forest fire', 'bush fire', 'grass fire'],
            'Car Fire' => ['car fire', 'vehicle fire', 'burning car'],
            'Electrical Fire' => ['electrical fire', 'power fire', 'cable fire'],
            'House Fire' => ['house fire', 'home fire', 'building fire'],
            'Smoke' => ['smoke', 'haze'],
            'Rescue' => ['rescue', 'trapped', 'stuck'],
            'Flood' => ['flood', 'flooding', 'flash flood'],
            'Road Accident' => ['road accident', 'car accident', 'crash', 'collision'],
            'Fire' => ['fire', 'flame', 'burning'],
        ];

        foreach ($patterns as $incidentType => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalised, $needle)) {
                    return $incidentType;
                }
            }
        }

        if ($existing !== null && trim($existing) !== '') {
            return $existing;
        }

        return 'Other';
    }

    private function severity(string $text): ?string
    {
        $normalised = Str::lower($text);

        if (preg_match('/\b(critical|life[ -]?threatening|explosion)\b/u', $normalised)) {
            return 'Critical';
        }

        if (preg_match('/\b(high|urgent|large|spreading|trapped)\b/u', $normalised)) {
            return 'High';
        }

        if (preg_match('/\b(medium|moderate)\b/u', $normalised)) {
            return 'Medium';
        }

        if (preg_match('/\b(low|small|minor)\b/u', $normalised)) {
            return 'Low';
        }

        return null;
    }

    private function district(string $text): ?string
    {
        $normalised = Str::lower($text);

        if (preg_match('/\b(temburong|bangar)\b/u', $normalised)) {
            return 'Temburong';
        }

        if (preg_match('/\b(belait|seria|kuala belait)\b/u', $normalised)) {
            return 'Belait';
        }

        if (preg_match('/\b(tutong)\b/u', $normalised)) {
            return 'Tutong';
        }

        if (preg_match('/\b(brunei[ -]?muara|bandar|muara|bsb)\b/u', $normalised)) {
            return 'Brunei Muara';
        }

        return null;
    }

    private function nearestDistrict(
        float $latitude,
        float $longitude
    ): ?string {
        if (
            $latitude < 4.0
            || $latitude > 5.2
            || $longitude < 113.6
            || $longitude > 115.4
        ) {
            return null;
        }

        $districts = [
            'Brunei Muara' => [4.9031, 114.9398],
            'Tutong' => [4.8066, 114.6596],
            'Belait' => [4.5835, 114.1816],
            'Temburong' => [4.57, 115.12],
        ];
        $nearest = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($districts as $district => [$districtLat, $districtLng]) {
            $distance = (($latitude - $districtLat) ** 2)
                + (($longitude - $districtLng) ** 2);

            if ($distance < $nearestDistance) {
                $nearest = $district;
                $nearestDistance = $distance;
            }
        }

        return $nearest;
    }

    private function missingDetailsReply(
        WhatsAppReportDraft $draft
    ): string {
        if (trim((string) $draft->description) === '') {
            return 'Location saved. Now describe what happened. You can also include lines such as "Type: House Fire" and "Urgency: High".';
        }

        if ($draft->latitude === null || $draft->longitude === null) {
            return 'Details saved. Please share the incident location using WhatsApp Attach > Location. A photo is optional.';
        }

        return $this->instructions();
    }

    private function instructions(): string
    {
        return 'To report an incident, send a description, optionally send a photo, then share the incident location using WhatsApp Attach > Location. Send CANCEL to discard the draft.';
    }

    private function sendReply(
        string $phoneNumberId,
        string $recipient,
        string $body
    ): void {
        $accessToken = trim((string) config(
            'services.whatsapp.access_token',
            ''
        ));

        if (
            ! config('services.whatsapp.send_replies', true)
            || $accessToken === ''
            || $phoneNumberId === ''
            || $recipient === ''
            || $body === ''
        ) {
            return;
        }

        try {
            $version = trim((string) config(
                'services.whatsapp.graph_api_version',
                'v23.0'
            ));
            $url = 'https://graph.facebook.com/'
                .rawurlencode($version)
                .'/'.rawurlencode($phoneNumberId)
                .'/messages';

            Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $recipient,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $body,
                    ],
                ])
                ->throw();
        } catch (Throwable $exception) {
            Log::warning('WhatsApp incident acknowledgement could not be sent.', [
                'recipient_hash' => hash('sha256', $recipient),
                'exception' => $exception::class,
            ]);
        }
    }
}
