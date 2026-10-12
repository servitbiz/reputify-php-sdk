<?php

declare(strict_types=1);

namespace Reputify\Resources;

/** Recht: `awards:read`. */
final class Awards extends Resource
{
    /** @return array<string, mixed> */
    public function list(int|string $projectId, bool $validOnly = false): array
    {
        return $this->client->request('GET', 'v1/projects/'.self::id($projectId).'/awards', ['valid' => $validOnly ?: null]) ?? [];
    }
}
