<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

final readonly class Category
{
    /**
     * @param list<Category>        $children
     * @param array<string, string> $alternates slug de la catégorie dans chaque langue activée, vide sur un site monolingue
     */
    public function __construct(
        public string $slug,
        public string $name,
        public array $children = [],
        public array $alternates = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function leafSlugs(): array
    {
        if ([] === $this->children) {
            return [$this->slug];
        }

        return array_merge(...array_map(static fn (self $child): array => $child->leafSlugs(), $this->children));
    }
}
