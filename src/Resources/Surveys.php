<?php

declare(strict_types=1);

namespace Reputify\Resources;

use Reputify\Page;

/**
 * Rechte: `surveys:read`, `surveys:write`.
 *
 * Ueber die API angelegte Umfragen nehmen Einladungen aus fremden Systemen
 * an (`access_external`) und beginnen immer als Entwurf — eingeschaltet
 * wird im Dashboard.
 */
final class Surveys extends Resource
{
    /** @param array{active?: bool, page?: int, per_page?: int} $query */
    public function list(int|string $projectId, array $query = []): Page
    {
        return $this->client->page('v1/projects/'.self::id($projectId).'/surveys', $query);
    }

    /** @return array<string, mixed> */
    public function get(int|string $projectId, int|string $surveyId): array
    {
        return $this->client->request('GET', $this->path($projectId, $surveyId)) ?? [];
    }

    /**
     * @param  array{name: string, description?: string, delay_hours?: int, allow_text?: bool, allow_audio?: bool, allow_video?: bool, star_threshold_action?: string, review_group_id?: int}  $attributes
     * @return array<string, mixed>
     */
    public function create(int|string $projectId, array $attributes): array
    {
        return $this->client->request('POST', 'v1/projects/'.self::id($projectId).'/surveys', [], $attributes) ?? [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(int|string $projectId, int|string $surveyId, array $attributes): array
    {
        return $this->client->request('PATCH', $this->path($projectId, $surveyId), [], $attributes) ?? [];
    }

    /** @return array<string, mixed> Trichter: eingeladen, geoeffnet, bewertet … */
    public function insights(int|string $projectId, int|string $surveyId, string|\DateTimeInterface|null $from = null, string|\DateTimeInterface|null $to = null): array
    {
        return $this->client->request('GET', $this->path($projectId, $surveyId).'/insights', ['from' => $from, 'to' => $to]) ?? [];
    }

    /** @param array{status?: string, order_ref?: string, page?: int} $query */
    public function recipients(int|string $projectId, int|string $surveyId, array $query = []): Page
    {
        return $this->client->page($this->path($projectId, $surveyId).'/recipients', $query);
    }

    private function path(int|string $projectId, int|string $surveyId): string
    {
        return 'v1/projects/'.self::id($projectId).'/surveys/'.self::id($surveyId);
    }
}
