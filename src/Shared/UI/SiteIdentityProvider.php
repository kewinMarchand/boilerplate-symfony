<?php

declare(strict_types=1);

namespace App\Shared\UI;

use App\Shared\Domain\SiteIdentity;
use App\Shared\Domain\SiteIdentityLoadException;
use App\Shared\Domain\SiteIdentityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Identité du site pour la requête en cours : un seul appel à l'API, dans la langue de l'adresse (LocaleSubscriber), et la
 * config du site (site.yaml) en repli si l'API ne répond pas, pour que la page soit toujours servie, dans la seule langue par
 * défaut du framework.
 */
final class SiteIdentityProvider
{
    private ?SiteIdentity $identity = null;

    /**
     * @param array{name: string, description: string} $site
     */
    public function __construct(
        private readonly SiteIdentityRepository $repository,
        #[Autowire('%site%')]
        private readonly array $site,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    public function current(): SiteIdentity
    {
        return $this->identity ??= $this->load(null);
    }

    /**
     * Premier appel de la requête : l'identité lue dans la langue du préfixe de l'adresse, que l'API ignore si elle n'est pas
     * activée.
     */
    public function currentIn(?string $locale): SiteIdentity
    {
        return $this->identity ??= $this->load($locale);
    }

    public function salesEnabled(): bool
    {
        return $this->current()->salesEnabled;
    }

    private function load(?string $locale): SiteIdentity
    {
        try {
            return $this->repository->current($locale);
        } catch (SiteIdentityLoadException) {
            return new SiteIdentity($this->site['name'], $this->site['description'], null, 'auto', null, null, null, true, [$this->defaultLocale]);
        }
    }
}
