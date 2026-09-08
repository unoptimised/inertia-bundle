<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Unoptimised\InertiaBundle\EventListener\InertiaListener;
use Unoptimised\InertiaBundle\Service\Inertia;
use Unoptimised\InertiaBundle\Service\InertiaInterface;
use Unoptimised\InertiaBundle\Twig\InertiaExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('unoptimised_inertia.service', Inertia::class)
        ->args([
            param('inertia.version'),
            param('inertia.root_view'),
            service('request_stack'),
            service('twig'),
            service('serializer')->nullOnInvalid(),
        ]);

    $services->alias(Inertia::class, 'unoptimised_inertia.service');
    $services->alias(InertiaInterface::class, 'unoptimised_inertia.service');

    // BC: the service was registered under this id before 2.0.
    $services->alias('unoptimised_inertia_service', 'unoptimised_inertia.service');

    $services->set('unoptimised_inertia.event_listener', InertiaListener::class)
        ->args([
            service('unoptimised_inertia.service'),
        ])
        ->tag('kernel.event_listener', [
            'event' => 'kernel.request',
            'method' => 'onKernelRequest',
            'priority' => 50,
        ])
        ->tag('kernel.event_listener', [
            'event' => 'kernel.response',
            'method' => 'onKernelResponse',
        ]);

    $services->set('unoptimised_inertia.twig_extension', InertiaExtension::class)
        ->tag('twig.extension');
};