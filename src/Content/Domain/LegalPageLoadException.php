<?php

declare(strict_types=1);

namespace App\Content\Domain;

final class LegalPageLoadException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Impossible de charger cette page pour le moment. Réessayez dans quelques minutes.', 0, $previous);
    }
}
