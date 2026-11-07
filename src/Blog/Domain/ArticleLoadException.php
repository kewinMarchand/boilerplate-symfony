<?php

declare(strict_types=1);

namespace App\Blog\Domain;

final class ArticleLoadException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Impossible de charger cet article pour le moment.', 0, $previous);
    }
}
