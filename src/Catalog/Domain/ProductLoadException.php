<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

final class ProductLoadException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Impossible de charger ce produit pour le moment.', 0, $previous);
    }
}
