<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Content\Domain\LegalPage;
use App\Content\Domain\LegalPageLoadException;
use App\Content\Domain\LegalPageRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalControllerTest extends WebTestCase
{
    public function testRendersTheApiTitleIntroductionAndSectionsWithTheirHtml(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/donnees-personnelles');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Données personnelles');
        self::assertSelectorCount(7, '[data-testid="legal-section"]');
        $rights = $crawler->filter('[data-testid="legal-section"]')->eq(4);
        self::assertSame('Vos droits', $rights->filter('h2')->text());
        self::assertCount(5, $rights->filter('li'));
        $complaint = $crawler->filter('[data-testid="legal-section"]')->eq(5);
        self::assertSame('https://www.cnil.fr/fr/plaintes', $complaint->selectLink('Commission nationale de l\'informatique et des libertés (CNIL)')->attr('href'));
        self::assertSelectorNotExists('[data-testid="legal-error"]');
    }

    public function testRendersTheIntroductionBeforeTheSections(): void
    {
        $client = self::createClient();
        $client->request('GET', '/mentions-legales');

        self::assertSelectorTextContains('.prose > .legal-html', 'valeurs d\'exemple');
        self::assertSelectorCount(4, '[data-testid="legal-section"]');
    }

    public function testKeepsTheTitleAndShowsTheErrorWhenThePageCannotBeLoaded(): void
    {
        $client = self::createClient();
        self::getContainer()->set(LegalPageRepository::class, new class implements LegalPageRepository {
            public function find(string $slug): ?LegalPage
            {
                throw new LegalPageLoadException();
            }
        });
        $client->request('GET', '/mentions-legales');

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextSame('h1', 'Mentions légales');
        self::assertSelectorTextContains('[data-testid="legal-error"]', 'Impossible de charger cette page pour le moment. Réessayez dans quelques minutes.');
        self::assertSelectorNotExists('[data-testid="legal-section"]');
    }

    public function testAnswers404WhenTheApiDoesNotKnowThePage(): void
    {
        $client = self::createClient();
        self::getContainer()->set(LegalPageRepository::class, new class implements LegalPageRepository {
            public function find(string $slug): ?LegalPage
            {
                return null;
            }
        });
        $client->request('GET', '/accessibilite');

        self::assertResponseStatusCodeSame(404);
    }
}
