<?php

namespace Unoptimised\InertiaBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Unoptimised\InertiaBundle\Service\Inertia;

class InertiaListener
{
    public function __construct(private readonly Inertia $inertia)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->headers->get('X-Inertia')) {
            return;
        }

        if (!$request->isMethod('GET')) {
            return;
        }

        $clientVersion = $request->headers->get('X-Inertia-Version', '');
        $serverVersion = $this->inertia->getVersion() ?? '';

        if ($clientVersion !== $serverVersion) {
            $response = new Response('', 409, [
                'X-Inertia-Location' => $request->getUri(),
            ]);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$request->headers->get('X-Inertia')) {
            return;
        }

        if (
            in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'], true)
            && $response->getStatusCode() === 302
        ) {
            $response->setStatusCode(303);
        }
    }
}