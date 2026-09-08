<?php

namespace Unoptimised\InertiaBundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Unoptimised\InertiaBundle\DependencyInjection\InertiaExtension;

class UnoptimisedInertiaBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new InertiaExtension();
    }
}
