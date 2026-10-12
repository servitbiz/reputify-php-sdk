<?php

declare(strict_types=1);

namespace Reputify\Resources;

use Reputify\Page;

/**
 * Rechte: `reviews:read`, zum Antworten `reviews:reply`.
 *
 * Anlegen oder loeschen gibt es nicht — eine Bewertung kommt aus einer
 * Umfrage oder von einer Plattform, und kein Recht kann sie verbergen.
 */
final class Reviews extends Resource
{
    /**
     * @param  array{
     *     platform?: int, platform_key?: string, group?: int, product?: string,
     *     min_rating?: int, max_rating?: int,
     *     date_from?: string|\DateTimeInterface, date_to?: string|\DateTimeInterface,
     *     has_text?: bool, has_reply?: bool, sentiment?: string, sort?: string,
     *     page?: int, per_page?: int
     * }  $query
     */
    public function list(int|string $projectId, array $query = []): Page
    {
        return $this->client->page('v1/projects/'.self::id($projectId).'/reviews', $query);
    }

    /** @return array<string, mixed> */
    public function get(int|string $projectId, int|string $reviewId): array
    {
        return $this->client->request('GET', $this->path($projectId, $reviewId)) ?? [];
    }

    /**
     * Antwort setzen oder ersetzen. Bei Plattform-Bewertungen geht das nur
     * fuer ein verbundenes Google-Profil — sonst `ConflictException`
     * (`reply_on_platform`).
     *
     * @return array<string, mixed>
     */
    public function reply(int|string $projectId, int|string $reviewId, string $text): array
    {
        return $this->client->request('PUT', $this->path($projectId, $reviewId).'/reply', [], ['text' => $text]) ?? [];
    }

    public function deleteReply(int|string $projectId, int|string $reviewId): void
    {
        $this->client->request('DELETE', $this->path($projectId, $reviewId).'/reply');
    }

    private function path(int|string $projectId, int|string $reviewId): string
    {
        return 'v1/projects/'.self::id($projectId).'/reviews/'.self::id($reviewId);
    }
}
