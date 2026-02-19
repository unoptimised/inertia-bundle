<?php

namespace DependencyInjection;

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
                ->scalarNode('root_view')->defaultValue('@Inertia/Inertia')->end()
                ->scalarNode('version')->defaultNull()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}