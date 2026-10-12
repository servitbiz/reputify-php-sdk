<?php

declare(strict_types=1);

namespace Reputify\Resources;

use Reputify\Page;

/** Recht: `projects:read`. */
final class Projects extends Resource
{
    /** @param array{q?: string, page?: int, per_page?: int} $query */
    public function list(array $query = []): Page
    {
        return $this->client->page('v1/projects', $query);
    }

    /** @return array<string, mixed> */
    public function get(int|string $id): array
    {
        return $this->client->request('GET', 'v1/projects/'.self::id($id)) ?? [];
    }

    /**
     * Score, Schnitt, Anzahl, Plattformen — dieselben Zahlen wie Siegel und
     * Profil. `group` rechnet je Bewertungsgruppe (nur hier, nicht im Widget).
     *
     * @return array<string, mixed>
     */
    public function profile(int|string $id, ?int $group = null): array
    {
        return $this->client->request('GET', 'v1/projects/'.self::id($id).'/reputify-profile', ['group' => $group]) ?? [];
    }

    /**
     * Score-Verlauf (ab Pro). Datumsangaben als `Y-m-d` oder DateTime.
     *
     * @return array<string, mixed>
     */
    public function scoreHistory(int|string $id, string|\DateTimeInterface|null $from = null, string|\DateTimeInterface|null $to = null): array
    {
        return $this->client->request('GET', 'v1/projects/'.self::id($id).'/score', ['from' => $from, 'to' => $to]) ?? [];
    }
}
