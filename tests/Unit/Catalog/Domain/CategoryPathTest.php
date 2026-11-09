<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryPath;
use App\Catalog\Domain\CategoryTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CategoryPathTest extends TestCase
{
    private static function tree(): CategoryTree
    {
        return new CategoryTree(new Category('', 'Catalogue', [
            new Category('interieur', 'Intérieur', [
                new Category('feuillages', 'Feuillages', [new Category('monstera', 'Monstera')]),
            ]),
            new Category('aquatiques', 'Aquatiques'),
        ]));
    }

    public function testEmptyPathIsTheRoot(): void
    {
        $path = CategoryPath::resolve(self::tree(), '');

        self::assertNotNull($path);
        self::assertTrue($path->isRoot());
        self::assertSame('Catalogue', $path->category()->name);
        self::assertSame('', $path->value());
        self::assertSame([], $path->steps());
        self::assertNull($path->parent());
    }

    public function testResolvesANestedPath(): void
    {
        $path = CategoryPath::resolve(self::tree(), 'interieur/feuillages/monstera');

        self::assertNotNull($path);
        self::assertSame('Monstera', $path->category()->name);
        self::assertSame('interieur/feuillages/monstera', $path->value());
        self::assertSame(['interieur', 'interieur/feuillages', 'interieur/feuillages/monstera'], array_map(
            static fn (CategoryPath $step): string => $step->value(),
            $path->steps(),
        ));
        self::assertSame('interieur/feuillages', $path->parent()?->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownPaths(): iterable
    {
        yield 'slug inconnu' => ['inconnu'];
        yield 'enfant inconnu' => ['interieur/inconnu'];
        yield 'enfant rattaché au mauvais parent' => ['aquatiques/feuillages'];
        yield 'plus profond que trois niveaux' => ['interieur/feuillages/monstera/variegata'];
    }

    #[DataProvider('unknownPaths')]
    public function testUnknownPathIsNotResolved(string $path): void
    {
        self::assertNull(CategoryPath::resolve(self::tree(), $path));
    }

    public function testChildExtendsThePath(): void
    {
        $root = CategoryPath::root(self::tree());

        self::assertSame('aquatiques', $root->child(new Category('aquatiques', 'Aquatiques'))->value());
    }

    public function testListsEveryCategoryParentsFirst(): void
    {
        self::assertSame(['interieur', 'interieur/feuillages', 'interieur/feuillages/monstera', 'aquatiques'], array_map(
            static fn (CategoryPath $path): string => $path->value(),
            CategoryPath::all(self::tree()),
        ));
    }

    public function testAlternatesFollowEachCategoryInEachLanguage(): void
    {
        $tree = new CategoryTree(new Category('', 'Catalogue', [
            new Category('interieur', 'Intérieur', [
                new Category('feuillages', 'Feuillages', [], ['fr' => 'feuillages', 'en' => 'foliage']),
                new Category('cactus', 'Cactus', [], ['fr' => 'cactus']),
            ], ['fr' => 'interieur', 'en' => 'indoor']),
        ]));

        self::assertSame(['fr' => 'interieur/feuillages', 'en' => 'indoor/foliage'], CategoryPath::resolve($tree, 'interieur/feuillages')?->alternates());
        self::assertSame(['fr' => 'interieur/cactus'], CategoryPath::resolve($tree, 'interieur/cactus')?->alternates());
        self::assertSame([], CategoryPath::resolve($tree, '')?->alternates());
    }
}
