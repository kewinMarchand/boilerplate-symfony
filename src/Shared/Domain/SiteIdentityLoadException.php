<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final class SiteIdentityLoadException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Impossible de charger l’identité du site.', 0, $previous);
    }
}
