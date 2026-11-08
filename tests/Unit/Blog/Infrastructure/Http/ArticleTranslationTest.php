<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Infrastructure\Http;

use App\Blog\Infrastructure\Http\ArticleTranslation;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use PHPUnit\Framework\TestCase;

final class ArticleTranslationTest extends TestCase
{
    public function testTranslatesTheApiArticlesWithTheirDate(): void
    {
        $articles = ArticleTranslation::articles(self::payload('articles'));

        self::assertCount(3, $articles);
        self::assertSame('Le mode accessibilité renforcée', $articles[0]->title);
        self::assertSame('2026-10-01', $articles[0]->publishedAt->format('Y-m-d'));
    }

    public function testTranslatesTheSectionTheFormatAndTheAudio(): void
    {
        [$latest, $withAudio, $guide] = ArticleTranslation::articles(self::payload('articles'));

        self::assertSame('Actualités', $latest->section?->name);
        self::assertNull($latest->format);
        self::assertNull($latest->audio);
        self::assertSame('', $latest->speaker);
        self::assertSame(['guide', 'Guide'], [$guide->format?->slug, $guide->format?->name]);
        self::assertSame('audio/mpeg', $withAudio->audio?->type);
        self::assertSame(4, $withAudio->audio->durationSeconds);
        self::assertSame('Présenté par Hina Teriierooiterai', $withAudio->speaker);
    }

    public function testTranslatesTheDetailWithItsMetadata(): void
    {
        $detail = ArticleTranslation::detail(self::payload('article-un-carrousel-accessible-sans-dependance'));

        self::assertSame('conseils', $detail->article->section?->slug);
        self::assertSame(['', ''], [$detail->metaTitle, $detail->metaDescription]);
    }

    public function testTranslatesTheSectionsInOrder(): void
    {
        self::assertSame(['Conseils', 'Actualités'], array_map(static fn ($section): string => $section->name, ArticleTranslation::terms(self::payload('article-sections'))));
    }

    public function testRejectsAMalformedDate(): void
    {
        $this->expectException(InvalidApiPayloadException::class);

        ArticleTranslation::articles([['id' => 1, 'title' => 'T', 'excerpt' => 'E', 'publishedAt' => '01/10/2026']]);
    }

    private static function payload(string $name): mixed
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../../../Fixtures/api/'.$name.'.json'), true, flags: \JSON_THROW_ON_ERROR);
    }
}
