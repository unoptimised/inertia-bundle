<?php

namespace Twig;

use Twig\Extension\AbstractExtension;

class InertiaExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('inertia', [$this, 'renderInertia'], ['is_safe' => ['html']]),
        ];
    }

    public function renderInertia(array $page): Markup
    {
        return new Markup('<div id="app" data-page="'.htmlspecialchars(json_encode($page)).'"></div>', 'UTF-8');
    }
}