<?php

declare(strict_types=1);

namespace Reputify\Resources;

/** Recht: `analysis:read`. */
final class Analysis extends Resource
{
    /**
     * Verteilung positiv/neutral/negativ.
     *
     * @return array<string, mixed>
     */
    public function sentiment(int|string $projectId): array
    {
        return $this->get($projectId, 'sentiment');
    }

    /** @return array<string, mixed> Die Themen, die Gaeste nennen, mit Zitaten. */
    public function findings(int|string $projectId, ?string $locale = null): array
    {
        return $this->get($projectId, 'findings', ['locale' => $locale]);
    }

    /** @return array<string, mixed> */
    public function summary(int|string $projectId): array
    {
        return $this->get($projectId, 'summary');
    }

    /** @return array<string, mixed> */
    public function figures(int|string $projectId): array
    {
        return $this->get($projectId, 'figures');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(int|string $projectId, string $what, array $query = []): array
    {
        return $this->client->request('GET', 'v1/projects/'.self::id($projectId).'/analysis/'.$what, $query) ?? [];
    }
}
