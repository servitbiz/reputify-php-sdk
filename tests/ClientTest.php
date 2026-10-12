<?php

declare(strict_types=1);

namespace Reputify\Tests;

use PHPUnit\Framework\TestCase;
use Reputify\Exception\ConflictException;
use Reputify\Exception\ConnectionException;
use Reputify\Exception\PermissionException;
use Reputify\Exception\RateLimitException;
use Reputify\Exception\ValidationException;
use Reputify\OAuth;
use Reputify\Reputify;
use Reputify\Resources\Embeds;
use Reputify\TokenSet;

final class ClientTest extends TestCase
{
    /** @var list<float> */
    private array $slept = [];

    private function client(FakeTransport $t, string|TokenSet $token = 'rpf_live_test', array $options = []): Reputify
    {
        return new Reputify($token, $options + ['transport' => $t, 'sleep' => function (float $s) { $this->slept[] = $s; }]);
    }

    public function test_it_sends_the_bearer_token_and_builds_the_query(): void
    {
        $t = new FakeTransport([FakeTransport::json(200, ['data' => [], 'meta' => ['page' => 1], 'links' => ['next' => null]])]);

        $this->client($t)->reviews->list(12, ['min_rating' => 4, 'has_text' => true, 'date_from' => new \DateTimeImmutable('2026-01-02'), 'sentiment' => null]);

        $r = $t->requests[0];
        $this->assertSame('GET', $r['method']);
        $this->assertSame('https://api.reputify.io/v1/projects/12/reviews?min_rating=4&has_text=1&date_from=2026-01-02', $r['url']);
        $this->assertSame('Bearer rpf_live_test', $r['headers']['Authorization']);
        $this->assertNull($r['body']);
    }

    public function test_single_items_come_back_as_they_are(): void
    {
        $t = new FakeTransport([FakeTransport::json(200, ['id' => 7, 'rating' => 4.0, 'reply' => ['text' => 'Danke']])]);

        $review = $this->client($t)->reviews->reply(12, 7, 'Danke');

        $this->assertSame(4.0, $review['rating']);
        $this->assertSame('PUT', $t->requests[0]['method']);
        $this->assertSame('{"text":"Danke"}', $t->requests[0]['body']);
        $this->assertSame('application/json', $t->requests[0]['headers']['Content-Type']);
    }

    public function test_auto_paging_follows_links_next(): void
    {
        $t = new FakeTransport([
            FakeTransport::json(200, ['data' => [['id' => 1], ['id' => 2]], 'meta' => ['page' => 1, 'total' => 3], 'links' => ['next' => 'x?page=2']]),
            FakeTransport::json(200, ['data' => [['id' => 3]], 'meta' => ['page' => 2, 'total' => 3], 'links' => ['next' => null]]),
        ]);

        $ids = array_map(fn ($r) => $r['id'], iterator_to_array($this->client($t)->projects->list(['per_page' => 2])->autoPaging(), false));

        $this->assertSame([1, 2, 3], $ids);
        $this->assertStringContainsString('page=2&per_page=2', $t->requests[1]['url']);
    }

    public function test_recordings_page_without_links_by_total(): void
    {
        $t = new FakeTransport([
            FakeTransport::json(200, ['data' => [['id' => 1]], 'meta' => ['current_page' => 1, 'per_page' => 1, 'total' => 2]]),
            FakeTransport::json(200, ['data' => [['id' => 2]], 'meta' => ['current_page' => 2, 'per_page' => 1, 'total' => 2]]),
        ]);

        $all = iterator_to_array($this->client($t)->recordings->list(5, ['per_page' => 1])->autoPaging(), false);

        $this->assertCount(2, $all);
        $this->assertCount(2, $t->requests);
    }

    public function test_errors_become_typed_exceptions(): void
    {
        $t = new FakeTransport([
            FakeTransport::json(403, ['error' => 'insufficient_scope', 'message' => 'Insufficient permissions.', 'required' => ['reviews:reply']]),
            FakeTransport::json(409, ['error' => 'survey_closed']),
            FakeTransport::json(422, ['message' => 'The text field must be at least 2 characters.', 'errors' => ['text' => ['too short']]]),
        ]);
        $c = $this->client($t);

        try {
            $c->reviews->reply(1, 2, 'Hallo');
            $this->fail();
        } catch (PermissionException $e) {
            $this->assertSame('insufficient_scope', $e->errorCode);
            $this->assertSame(['reviews:reply'], $e->required());
            $this->assertFalse($e->isPlanRequired());
        }

        try {
            $c->invites->create(['name' => 'Anna', 'email' => 'anna@example.at']);
            $this->fail();
        } catch (ConflictException $e) {
            $this->assertSame('survey_closed', $e->errorCode);
        }

        try {
            $c->reviews->reply(1, 2, 'x');
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertSame('validation_failed', $e->errorCode);
            $this->assertSame(['text' => ['too short']], $e->errors());
        }
    }

    public function test_429_waits_for_retry_after_and_then_gives_up(): void
    {
        $limited = FakeTransport::json(429, ['error' => 'rate_limited', 'message' => 'Too many requests.'], ['Retry-After' => '3']);
        $t = new FakeTransport([$limited, $limited, $limited]);

        try {
            $this->client($t)->projects->get(1);
            $this->fail();
        } catch (RateLimitException $e) {
            $this->assertSame(3, $e->retryAfter());
        }

        $this->assertSame([3.0, 3.0], $this->slept);
        $this->assertCount(3, $t->requests);
    }

    public function test_server_errors_are_retried_for_get_but_not_for_post(): void
    {
        $t = new FakeTransport([FakeTransport::json(503, null), FakeTransport::json(200, ['id' => 1])]);
        $this->assertSame(['id' => 1], $this->client($t)->projects->get(1));

        $t = new FakeTransport([new ConnectionException('timeout')]);
        $this->expectException(ConnectionException::class);
        $this->client($t)->invites->create(['name' => 'A', 'email' => 'a@example.at']);
    }

    public function test_an_expiring_oauth_token_is_refreshed_and_handed_back_before_use(): void
    {
        $api = new FakeTransport([FakeTransport::json(200, ['project' => ['id' => 9]])]);
        $auth = new FakeTransport([FakeTransport::json(200, ['access_token' => 'rpf_live_new', 'refresh_token' => 'rpf_rt_2', 'expires_in' => 3600, 'scope' => 'widgets:read'])]);
        $saved = null;

        $client = $this->client($api, new TokenSet('rpf_live_old', 'rpf_rt_1', time() + 10), [
            'oauth' => new OAuth('rpc_x', 'https://shop.example/cb', ['transport' => $auth]),
            'on_refresh' => function (TokenSet $t) use (&$saved) { $saved = $t; },
        ]);

        $client->site->get();

        $this->assertSame('rpf_rt_2', $saved?->refreshToken);
        $this->assertStringContainsString('grant_type=refresh_token&refresh_token=rpf_rt_1&client_id=rpc_x', (string) $auth->requests[0]['body']);
        $this->assertSame('Bearer rpf_live_new', $api->requests[0]['headers']['Authorization']);
    }

    public function test_a_401_refreshes_once(): void
    {
        $api = new FakeTransport([FakeTransport::json(401, ['error' => 'unauthorized', 'message' => 'Invalid or expired token.']), FakeTransport::json(200, ['ok' => true])]);
        $auth = new FakeTransport([FakeTransport::json(200, ['access_token' => 'new', 'refresh_token' => 'rpf_rt_2', 'expires_in' => 3600])]);

        $client = $this->client($api, new TokenSet('old', 'rpf_rt_1', time() + 3000), ['oauth' => new OAuth('rpc_x', 'https://x/cb', ['transport' => $auth])]);

        $this->assertSame(['ok' => true], $client->request('GET', 'v1/site'));
        $this->assertSame('new', $client->tokens()?->accessToken);
    }

    public function test_authorize_url_and_pkce(): void
    {
        $pkce = OAuth::pkce();
        $this->assertSame(OAuth::challenge($pkce['verifier']), $pkce['challenge']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43,128}$/', $pkce['verifier']);

        // RFC 7636, Anhang B
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', OAuth::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));

        $url = (new OAuth('rpc_x', 'https://shop.example/cb'))->authorizeUrl(['widgets:read', 'invites:write'], 'st', 'ch');
        $this->assertStringStartsWith('https://app.reputify.io/oauth/authorize?response_type=code&client_id=rpc_x', $url);
        $this->assertStringContainsString('scope=widgets%3Aread%20invites%3Awrite', $url);
        $this->assertStringContainsString('code_challenge_method=S256&resource=https%3A%2F%2Fapi.reputify.io', $url);
    }

    public function test_render_url(): void
    {
        $this->assertSame('https://cdn.reputify.io/w/v1/pk_abc/1a.svg?theme=dark', Embeds::renderUrl('pk_abc', '1a', 'svg', ['theme' => 'dark', 'loc' => null]));
    }
}
