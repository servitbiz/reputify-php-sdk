<?php

declare(strict_types=1);

namespace Reputify\Resources;

use Reputify\Page;

/**
 * Interne Rueckmeldungen (keine Bewertungen). Recht: `feedback:read`.
 */
final class Feedback extends Resource
{
    /** @param array{survey?: int, with_message?: bool, handled?: bool, page?: int} $query */
    public function list(int|string $projectId, array $query = []): Page
    {
        return $this->client->page('v1/projects/'.self::id($projectId).'/feedback', $query);
    }
}
