<?php

namespace Unoptimised\InertiaBundle\Service;

use LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

class Inertia implements InertiaInterface
{
    public const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;

    private array $sharedProps = [];
    private array $viewData = [];

    public function __construct(
        private ?string $version,
        private readonly string $rootView,
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        private readonly ?SerializerInterface $serializer = null,
    ) {
    }

    public function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } else {
            $this->sharedProps[$key] = $value;
        }
    }

    public function getSharedProps(): array
    {
        return $this->sharedProps;
    }

    public function getShared(string $key): mixed
    {
        return $this->sharedProps[$key] ?? null;
    }

    public function viewData(string $key, $value = null): void
    {
        $this->viewData[$key] = $value;
    }

    public function getViewData(string $key): mixed
    {
        return $this->viewData[$key] ?? null;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function render(string $component, array $props = [], array $viewData = []): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new LogicException('Cannot render an Inertia response outside of a request context.');
        }

        $viewData = array_merge($this->viewData, $viewData);
        $allProps = array_merge($this->sharedProps, $props);
        $partialComponent = $request->headers->get('X-Inertia-Partial-Component');
        $partialData = $request->headers->get('X-Inertia-Partial-Data');

        if ($partialComponent === $component && $partialData) {
            $allProps = $this->resolvePartialProps($allProps, $partialData);
        } else {
            $allProps = $this->resolveProps($allProps);
        }

        $json = $this->serialize([
            'component' => $component,
            'props'     => $allProps,
            'url'       => $request->getRequestUri(),
            'version'   => $this->version ?? '',
        ]);

        if ($request->headers->get('X-Inertia')) {
            return JsonResponse::fromJsonString(
                $json,
                Response::HTTP_OK,
                [
                    'X-Inertia' => 'true',
                    'Vary' => 'X-Inertia',
                ]
            );
        }

        $html = $this->twig->render($this->rootView, [
            'page' => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            'viewData' => $viewData,
        ]);

        return new Response($html);
    }

    private function resolveProps(array $props): array
    {
        return array_map(fn($value) => is_callable($value) ? $value() : $value, $props);
    }

    private function resolvePartialProps(array $props, ?string $partialData): array
    {
        $only = $partialData ? array_map('trim', explode(',', $partialData)) : [];

        $result = [];

        foreach ($props as $key => $value) {
            if (!empty($only)) {
                if (in_array($key, $only, true)) {
                    $result[$key] = is_callable($value) ? $value() : $value;
                }

                continue;
            }

            $result[$key] = is_callable($value) ? $value() : $value;
        }

        return $result;
    }

    private function serialize(array $page): string
    {
        if ($this->serializer) {
            return $this->serializer->serialize($page, 'json', [
                'json_encode_options' => self::JSON_FLAGS,
            ]);
        }

        return json_encode($page, self::JSON_FLAGS);
    }
}
