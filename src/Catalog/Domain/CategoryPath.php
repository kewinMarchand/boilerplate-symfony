<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Chemin d'une catégorie dans l'arbre, validé à la construction : seul resolve() transforme un chemin
 * d'URL en catégorie, donc un slug inconnu ou une profondeur excessive devient un « introuvable » en un
 * seul endroit. La racine est le chemin vide.
 */
final readonly class CategoryPath
{
    public const int MAX_DEPTH = 3;

    /**
     * @param list<Category> $categories de la première catégorie sous la racine à la catégorie désignée
     */
    private function __construct(
        private Category $root,
        public array $categories,
    ) {
    }

    public static function root(CategoryTree $tree): self
    {
        return new self($tree->root, []);
    }

    public static function resolve(CategoryTree $tree, string $path): ?self
    {
        $slugs = '' === $path ? [] : explode('/', $path);
        if (\count($slugs) > self::MAX_DEPTH) {
            return null;
        }

        $categories = [];
        $current = $tree->root;
        foreach ($slugs as $slug) {
            $next = array_find($current->children, static fn (Category $child): bool => $child->slug === $slug);
            if (null === $next) {
                return null;
            }
            $categories[] = $next;
            $current = $next;
        }

        return new self($tree->root, $categories);
    }

    /**
     * @return list<self> chaque catégorie de l'arbre, parents avant enfants, racine exclue
     */
    public static function all(CategoryTree $tree): array
    {
        return self::root($tree)->descendants();
    }

    public function category(): Category
    {
        return [] === $this->categories ? $this->root : $this->categories[array_key_last($this->categories)];
    }

    public function isRoot(): bool
    {
        return [] === $this->categories;
    }

    /**
     * Valeur du paramètre d'URL, par exemple « plantes-interieur/feuillages ».
     */
    public function value(): string
    {
        return implode('/', array_map(static fn (Category $category): string => $category->slug, $this->categories));
    }

    /**
     * Valeur du paramètre d'URL dans chaque langue où chaque catégorie traversée a un slug, par langue (sélecteur de langue,
     * hreflang, sitemap). Racine : chemin vide dans chaque langue connue d'aucune catégorie, donc aucune.
     *
     * @return array<string, string>
     */
    public function alternates(): array
    {
        if ($this->isRoot()) {
            return [];
        }

        $paths = [];
        foreach (array_keys($this->categories[0]->alternates) as $locale) {
            $slugs = array_map(static fn (Category $category): ?string => $category->alternates[$locale] ?? null, $this->categories);
            if (!\in_array(null, $slugs, true)) {
                $paths[$locale] = implode('/', $slugs);
            }
        }

        return $paths;
    }

    public function child(Category $child): self
    {
        return new self($this->root, [...$this->categories, $child]);
    }

    public function parent(): ?self
    {
        return $this->isRoot() ? null : new self($this->root, \array_slice($this->categories, 0, -1));
    }

    /**
     * @return list<self> le chemin de chaque catégorie traversée, de la première à celle-ci
     */
    public function steps(): array
    {
        if ($this->isRoot()) {
            return [];
        }

        return array_map(
            fn (int $depth): self => new self($this->root, \array_slice($this->categories, 0, $depth)),
            range(1, \count($this->categories)),
        );
    }

    /**
     * @return list<self>
     */
    private function descendants(): array
    {
        $paths = [];
        foreach ($this->category()->children as $child) {
            $childPath = $this->child($child);
            $paths = [...$paths, $childPath, ...$childPath->descendants()];
        }

        return $paths;
    }
}
