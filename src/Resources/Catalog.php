<?php

declare(strict_types=1);

namespace Reputify\Resources;

/** Nachschlagelisten. */
final class Catalog extends Resource
{
    /** @return array<string, mixed> Die angebundenen Plattformen. */
    public function platformTypes(): array
    {
        return $this->client->request('GET', 'v1/platform-types') ?? [];
    }

    /** @return array<string, mixed> */
    public function industries(?string $locale = null): array
    {
        return $this->client->request('GET', 'v1/industries', ['locale' => $locale]) ?? [];
    }
}
