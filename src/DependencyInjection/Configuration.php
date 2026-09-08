<?php

namespace Unoptimised\InertiaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('inertia');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('root_view')->defaultValue('@UnoptimisedInertia/inertia.html.twig')->end()
                ->scalarNode('version')->defaultNull()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
