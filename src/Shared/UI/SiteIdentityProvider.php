<?php

declare(strict_types=1);

namespace App\Shared\UI;

use App\Shared\Domain\SiteIdentity;
use App\Shared\Domain\SiteIdentityLoadException;
use App\Shared\Domain\SiteIdentityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Identité du site pour la requête en cours : un seul appel à l'API, et la config du site (site.yaml) en repli si l'API
 * ne répond pas, pour que la page soit toujours servie.
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
    ) {
    }

    public function current(): SiteIdentity
    {
        return $this->identity ??= $this->load();
    }

    public function salesEnabled(): bool
    {
        return $this->current()->salesEnabled;
    }

    private function load(): SiteIdentity
    {
        try {
            return $this->repository->current();
        } catch (SiteIdentityLoadException) {
            return new SiteIdentity($this->site['name'], $this->site['description'], null, 'auto', null, null, null, true);
        }
    }
}
