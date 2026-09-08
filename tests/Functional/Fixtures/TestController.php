<?php

declare(strict_types=1);

namespace Unoptimised\InertiaBundle\Tests\Functional\Fixtures;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Unoptimised\InertiaBundle\Service\InertiaInterface;

class TestController
{
    public function __construct(private readonly InertiaInterface $inertia)
    {
    }

    public function page(): Response
    {
        return $this->inertia->render('Page', ['ok' => true]);
    }

    public function redirect(): Response
    {
        return new RedirectResponse('/page');
    }

    /**
     * Resolved through the controller argument locator rather than the constructor,
     * which exercises a different DI code path.
     */
    public function autowired(InertiaInterface $inertia): Response
    {
        return $inertia->render('Autowired');
    }
}