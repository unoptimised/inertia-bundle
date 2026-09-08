<?php

namespace Unoptimised\InertiaBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;
use Unoptimised\InertiaBundle\Service\Inertia;

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
        $json = json_encode($page, Inertia::JSON_FLAGS);
        $attribute = htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new Markup('<div id="app" data-page="'.$attribute.'"></div>', 'UTF-8');
    }
}
