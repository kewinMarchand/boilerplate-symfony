<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Objet `image` du contrat de l'API (ADR 0002 de l'API Platform) : variantes AVIF puis WebP,
 * adresses absolues prêtes à poser dans un <picture>.
 */
final readonly class Image
{
    /**
     * @param list<ImageSource> $sources
     */
    public function __construct(
        public string $alt,
        public int $width,
        public int $height,
        public array $sources,
        public string $src,
    ) {
    }
}
