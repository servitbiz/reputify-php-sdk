<?php

declare(strict_types=1);

namespace Reputify\Exception;

/** 409 — z. B. `survey_closed` (die Umfrage nimmt nichts an) oder `reply_on_platform` (Antwort geht nur auf der Plattform). */
class ConflictException extends ApiException {}
