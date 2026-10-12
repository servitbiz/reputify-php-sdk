<?php

declare(strict_types=1);

namespace Reputify\Exception;

/** Gemeinsame Wurzel — ein `catch (ReputifyException)` faengt alles aus diesem Paket. */
class ReputifyException extends \RuntimeException {}
