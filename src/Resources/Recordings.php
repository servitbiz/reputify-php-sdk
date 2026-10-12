<?php

declare(strict_types=1);

namespace Reputify\Resources;

use Reputify\Page;

/**
 * Video- und Tonbewertungen. Rechte: `recordings:read`, `recordings:write`.
 */
final class Recordings extends Resource
{
    /** @param array{status?: string, featured?: bool, kind?: 'video'|'audio', per_page?: int, page?: int} $query */
    public function list(int|string $projectId, array $query = []): Page
    {
        return $this->client->page('v1/projects/'.self::id($projectId).'/recordings', $query);
    }

    /** @return array<string, mixed> */
    public function get(int|string $projectId, int|string $recordingId): array
    {
        return $this->client->request('GET', $this->path($projectId, $recordingId)) ?? [];
    }

    /** @return array<string, mixed> */
    public function feature(int|string $projectId, int|string $recordingId, bool $featured = true): array
    {
        return $this->client->request('PATCH', $this->path($projectId, $recordingId), [], ['is_featured' => $featured]) ?? [];
    }

    /** @return array<string, mixed> */
    public function exports(int|string $projectId, int|string $recordingId): array
    {
        return $this->client->request('GET', $this->path($projectId, $recordingId).'/exports') ?? [];
    }

    /**
     * Social-Export (ab Agency Light).
     *
     * @param  array{format: string, has_subtitles?: bool, has_logo_watermark?: bool, clip_start_seconds?: int, clip_end_seconds?: int}  $options
     * @return array<string, mixed>
     */
    public function createExport(int|string $projectId, int|string $recordingId, array $options): array
    {
        return $this->client->request('POST', $this->path($projectId, $recordingId).'/exports', [], $options) ?? [];
    }

    /** @return array<string, mixed> Die Hilfe beim Bewerten (Karten). */
    public function prompts(int|string $projectId, ?string $language = null, int|string|null $survey = null): array
    {
        return $this->client->request('GET', 'v1/projects/'.self::id($projectId).'/recording-prompts', ['language' => $language, 'survey' => $survey]) ?? [];
    }

    private function path(int|string $projectId, int|string $recordingId): string
    {
        return 'v1/projects/'.self::id($projectId).'/recordings/'.self::id($recordingId);
    }
}
