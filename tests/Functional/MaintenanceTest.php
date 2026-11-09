<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Pages de maintenance statiques (MAINTENANCE=1), écrites au build de l'image par app:maintenance-pages et servies par Caddy
 * sans PHP : une par langue livrée, choisie par le préfixe de l'adresse, sinon DEFAULT_LOCALE (docker/Caddyfile).
 */
final class MaintenanceTest extends KernelTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/maintenance-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:maintenance-pages'));
        $tester->execute(['--dir' => $this->dir]);
        $tester->assertCommandIsSuccessful();
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pages(): iterable
    {
        yield 'français' => ['fr', 'Site en maintenance'];
        yield 'anglais' => ['en', 'Site under maintenance'];
    }

    #[DataProvider('pages')]
    public function testWritesOnePagePerShippedLanguage(string $locale, string $title): void
    {
        $page = new Crawler((string) file_get_contents($this->dir.'/maintenance.'.$locale.'.html'));

        self::assertSame($locale, $page->filter('html')->attr('lang'));
        self::assertSame($title, $page->filter('h1')->text());
        self::assertSame($title, $page->filter('title')->text());
        self::assertCount(0, $page->filter('script'));
    }

    public function testWritesNothingElse(): void
    {
        self::assertSame(['maintenance.en.html', 'maintenance.fr.html'], array_map(basename(...), glob($this->dir.'/*') ?: []));
    }
}
