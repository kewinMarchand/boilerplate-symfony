<?php

declare(strict_types=1);

namespace App\Content\Domain;

final class SlidesLoadException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Impossible de charger le carrousel pour le moment.', 0, $previous);
    }
}
