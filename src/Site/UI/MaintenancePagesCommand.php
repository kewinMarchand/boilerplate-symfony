<?php

declare(strict_types=1);

namespace App\Site\UI;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Pages de maintenance statiques, une par langue livrée (fichier translations/messages.<langue>.yaml), écrites au build de
 * l'image : Caddy les sert avec MAINTENANCE=1 sans passer par PHP, qui peut être en panne ou en cours de déploiement.
 */
#[AsCommand(name: 'app:maintenance-pages', description: 'Écrit public/maintenance.<langue>.html pour chaque langue livrée')]
final readonly class MaintenancePagesCommand
{
    public function __construct(
        private Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Dossier de sortie')] string $dir = 'public'): int
    {
        $output = str_starts_with($dir, '/') ? $dir : $this->projectDir.'/'.$dir;
        foreach (glob($this->projectDir.'/translations/messages.*.yaml') ?: [] as $file) {
            $locale = explode('.', basename($file))[1];
            file_put_contents($output.'/maintenance.'.$locale.'.html', $this->twig->render('maintenance.html.twig', ['locale' => $locale]));
            $io->writeln(\sprintf('maintenance.%s.html', $locale));
        }

        return Command::SUCCESS;
    }
}
