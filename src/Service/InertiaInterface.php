<?php

namespace Unoptimised\InertiaBundle\Service;

use Symfony\Component\HttpFoundation\Response;

interface InertiaInterface
{
    public function share(string|array $key, mixed $value = null): void;
    public function getSharedProps(): array;
    public function getShared(string $key): mixed;
    public function viewData(string $key, $value = null): void;
    public function getViewData(string $key): mixed;
    public function getVersion(): ?string;
    public function setVersion(?string $version): void;
    public function render(string $component, array $props = [], array $viewData = []): Response;
}
