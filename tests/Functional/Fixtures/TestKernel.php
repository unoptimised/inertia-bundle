<?php

declare(strict_types=1);

namespace Unoptimised\InertiaBundle\Tests\Functional\Fixtures;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Unoptimised\InertiaBundle\Service\Inertia;
use Unoptimised\InertiaBundle\Service\InertiaInterface;
use Unoptimised\InertiaBundle\UnoptimisedInertiaBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private readonly string $configHash;

    public function __construct(private readonly array $inertiaConfig = [])
    {
        $this->configHash = substr(md5(serialize($inertiaConfig)), 0, 8);

        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new UnoptimisedInertiaBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/inertia-bundle-tests/'.$this->configHash;
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'inertia-bundle-tests',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'serializer' => true,
            'property_info' => ['enabled' => true],
            'router' => ['utf8' => true],
        ]);

        $container->extension('twig', [
            'strict_variables' => true,
        ]);

        $container->extension('inertia', $this->inertiaConfig);

        $services = $container->services();

        // Keep the test output clean — the debug logger writes to stderr otherwise.
        $services->set('logger', NullLogger::class);

        // Public handles so the tests can reach otherwise-private services.
        $services->alias('test.inertia', InertiaInterface::class)->public();
        $services->alias('test.inertia_legacy_id', 'unoptimised_inertia_service')->public();
        $services->alias('test.request_stack', 'request_stack')->public();

        $services->set('test.controller', TestController::class)
            ->args([service(Inertia::class)])
            ->public()
            ->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('page', '/page')
            ->controller(['test.controller', 'page']);

        $routes->add('redirect', '/redirect')
            ->controller(['test.controller', 'redirect']);

        $routes->add('delete', '/delete')
            ->methods(['DELETE'])
            ->controller(['test.controller', 'redirect']);

        $routes->add('autowired', '/autowired')
            ->controller(['test.controller', 'autowired']);
    }
}