<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\PageMetaTranslation;
use PHPUnit\Framework\TestCase;

final class PageMetaTranslationTest extends TestCase
{
    public function testIndexesTheMetadataByPage(): void
    {
        $metas = PageMetaTranslation::pageMetas(json_decode((string) file_get_contents(__DIR__.'/../../../../Fixtures/api/page-metas.json'), true, flags: \JSON_THROW_ON_ERROR));

        self::assertCount(8, $metas);
        self::assertSame('', $metas['home']->title);
        self::assertStringStartsWith('Conseils et actualités', $metas['blog']->description);
    }

    public function testAcceptsAnEmptyList(): void
    {
        self::assertSame([], PageMetaTranslation::pageMetas([]));
    }
}
