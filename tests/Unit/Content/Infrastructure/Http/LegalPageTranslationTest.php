<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Infrastructure\Http;

use App\Content\Infrastructure\Http\LegalPageTranslation;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use PHPUnit\Framework\TestCase;

final class LegalPageTranslationTest extends TestCase
{
    public function testTranslatesTheTitleTheIntroductionAndEachSectionWithItsHtml(): void
    {
        $page = LegalPageTranslation::legalPage(json_decode((string) file_get_contents(__DIR__.'/../../../../Fixtures/api/legal-page-donnees-personnelles.json'), true, flags: \JSON_THROW_ON_ERROR));

        self::assertSame('Données personnelles', $page->title);
        self::assertSame('', $page->introduction);
        self::assertCount(7, $page->sections);
        self::assertSame('Vos droits', $page->sections[4]->title);
        self::assertStringContainsString('<li>accès ;</li>', $page->sections[4]->html);
    }

    public function testRejectsAPageWithoutSections(): void
    {
        $this->expectException(InvalidApiPayloadException::class);

        LegalPageTranslation::legalPage(['slug' => 'mentions-legales', 'title' => 'T', 'introduction' => '']);
    }
}
