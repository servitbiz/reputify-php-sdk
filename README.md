# Reputify PHP SDK

PHP client for the [Reputify API](https://api.reputify.io/): reviews, scores,
surveys, invitations, embeds, video and audio reviews — plus OAuth
("Connect with Reputify") and webhook verification.

- PHP 8.1+, `ext-curl`, `ext-json`. No other dependencies.
- Retries rate limits (`429`, honouring `Retry-After`) and, for idempotent
  calls only, server and network errors.
- Typed exceptions, automatic pagination, OAuth refresh with rotating keys.

```bash
composer require reputify/reputify-php
```

## Quick start

```php
use Reputify\Reputify;

$reputify = new Reputify('rpf_live_…');   // Embed & Widgets → API & integrations

$profile = $reputify->projects->profile(12);
echo $profile['score']['value'];           // 88.8 — the same number as the seal

foreach ($reputify->reviews->list(12, ['min_rating' => 4, 'has_text' => true])->autoPaging() as $review) {
    echo $review['reviewer_name'], ': ', $review['text'], PHP_EOL;
}

$reputify->reviews->reply(12, 9182, 'Thank you, Anna!');
```

## Tokens

| Token | What it can do |
|---|---|
| `rpf_live_…` (team) | Everything the scopes on the token allow (`reviews:read`, `surveys:write`, …). |
| `rpf_inv_…` (survey) | `invites->create()` and nothing else. Safe to put in a shop backend. |
| OAuth (`TokenSet`) | What the customer granted in the connect dialog. One hour, refreshes itself. |

Never put a token into a web page. In the browser, use embeds — they have
their own public key (`pk_…`):

```php
use Reputify\Resources\Embeds;

echo '<img src="'.Embeds::renderUrl('pk_1c8a…', '1a', 'svg').'" alt="Reputify">';
```

## Invite a customer after an order

```php
$reputify = new Reputify('rpf_inv_…');

$result = $reputify->invites->create([
    'name' => 'Anna Bauer',
    'email' => 'anna@example.at',
    'order_ref' => '2026-1188',              // twice = still one invitation
    'product_items' => ['SKU-4711', ['ref' => 'SKU-12', 'name' => 'Mug']],
]);

$result['status'];                           // "invited" or "already_invited"
```

## Errors

```php
use Reputify\Exception\{ApiException, PermissionException, ConflictException, RateLimitException, ValidationException};

try {
    $reputify->reviews->reply(12, 9182, 'Thanks!');
} catch (ConflictException $e) {           // reply_on_platform: answer on Google/Trustpilot itself
} catch (PermissionException $e) {         // $e->required() lists missing scopes; $e->isPlanRequired()
} catch (ValidationException $e) {         // $e->errors()
} catch (RateLimitException $e) {          // $e->retryAfter()
} catch (ApiException $e) {                // $e->status, $e->errorCode, $e->body
}
```

Branch on `$e->errorCode`, never on the message. Network failures throw
`ConnectionException`; everything extends `ReputifyException`.

## OAuth: "Connect with Reputify"

For plugins and integrations. Nobody copies a key — the customer clicks
*Connect*, picks a project, and comes back.

```php
use Reputify\OAuth;
use Reputify\Reputify;
use Reputify\TokenSet;

// Once per installation (every site has its own redirect URI):
$client = OAuth::register('My Shop Plugin', ['https://shop.example/reputify/callback']);

$oauth = new OAuth($client['client_id'], 'https://shop.example/reputify/callback');

// 1. Send the customer off
$pkce = OAuth::pkce();
$_SESSION['reputify'] = ['verifier' => $pkce['verifier'], 'state' => $state = bin2hex(random_bytes(16))];
header('Location: '.$oauth->authorizeUrl(['widgets:read', 'invites:write'], $state, $pkce['challenge']));

// 2. On the way back (check state first!)
$tokens = $oauth->exchangeCode($_GET['code'], $_SESSION['reputify']['verifier']);
save($tokens->toArray());

// 3. Use it
$reputify = new Reputify(TokenSet::fromArray(load()), [
    'oauth' => $oauth,
    'on_refresh' => fn (TokenSet $t) => save($t->toArray()),
]);
$reputify->site->get();
```

**The refresh key rotates.** Every refresh returns a new one and spends the
old one; presenting a spent key revokes the whole connection. `on_refresh`
must persist the new pair before it returns. If two processes may refresh
at the same time, guard `on_refresh` and the request with a lock.

## Webhooks

Surveys can call your URL when a review comes in. Verify the raw body:

```php
use Reputify\Webhook;

$event = Webhook::verify(
    file_get_contents('php://input'),
    $_SERVER['HTTP_X_REPUTIFY_SIGNATURE'] ?? null,
    $secret,                                  // from the survey, tab "Nach der Abgabe"
);

if ($event['event'] === Webhook::EVENT_SUBMITTED) { … }
```

## Resources

| Property | Methods |
|---|---|
| `projects` | `list`, `get`, `profile`, `scoreHistory` |
| `reviews` | `list`, `get`, `reply`, `deleteReply` |
| `surveys` | `list`, `get`, `create`, `update`, `insights`, `recipients` |
| `invites` | `create` |
| `analysis` | `sentiment`, `findings`, `summary`, `figures` |
| `embeds` | `catalog`, `list`, `create`, `update`, `renderUrl` (static) |
| `recordings` | `list`, `get`, `feature`, `exports`, `createExport`, `prompts` |
| `awards` | `list` |
| `feedback` | `list` |
| `catalog` | `platformTypes`, `industries` |
| `site` | `get`, `surveys`, `invites`, `setIndustry`, `loginLink` |

Anything else: `$reputify->request('GET', 'v1/…', $query, $body)`.

Rate limit of the last call: `$reputify->rateLimit()`. Full reference:
<https://api.reputify.io/>.

## Custom HTTP client

Implement `Reputify\Http\Transport` (one method) to send through Guzzle,
WordPress' `wp_remote_request` or a test double, and pass it as
`['transport' => …]`.

## Development

```bash
composer install
composer test
```
