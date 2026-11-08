<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final class PageMetasLoadException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Impossible de charger les métadonnées des pages.', 0, $previous);
    }
}
