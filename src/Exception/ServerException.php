<?php

declare(strict_types=1);

namespace Reputify\Exception;

/** 5xx — bei uns ist etwas schiefgegangen; der Client hat schon wiederholt. */
class ServerException extends ApiException {}
