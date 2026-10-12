<?php

declare(strict_types=1);

namespace Reputify\Exception;

/** Das Netz, nicht die API: Zeitueberschreitung, DNS, TLS. */
class ConnectionException extends ReputifyException {}
