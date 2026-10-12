<?php

declare(strict_types=1);

namespace Reputify\Exception;

/** Ein Webhook, dessen Signatur oder Zeitstempel nicht passt — nicht verarbeiten. */
class WebhookException extends ReputifyException {}
