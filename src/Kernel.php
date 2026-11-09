<?php

declare(strict_types=1);

namespace App;

use App\Shared\UI\Locale\LocalizedUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /**
     * Le générateur d'URL du routeur préfixe les liens d'une page dans une autre langue que la langue par défaut.
     */
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('router.default')->addMethodCall('setOption', ['generator_class', LocalizedUrlGenerator::class]);
    }
}
