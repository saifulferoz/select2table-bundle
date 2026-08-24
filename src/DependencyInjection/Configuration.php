<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('saifulferoz_select2_table');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->integerNode('minimum_input_length')->min(0)->defaultValue(1)->end()
                ->booleanNode('scroll')->defaultFalse()->end()
                ->integerNode('page_limit')->min(1)->defaultValue(10)->end()
                ->booleanNode('allow_clear')->defaultFalse()->end()
                ->arrayNode('allow_add')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('new_tag_text')->defaultValue(' (NEW)')->end()
                        ->scalarNode('new_tag_prefix')->defaultValue('__')->end()
                        ->scalarNode('tag_separators')->defaultValue('[",", " "]')->end()
                    ->end()
                ->end()
                ->integerNode('delay')->min(0)->defaultValue(250)->end()
                ->scalarNode('language')->defaultValue('en')->end()
                ->scalarNode('theme')->defaultValue('default')->end()
                ->booleanNode('cache')->defaultTrue()->end()
                ->integerNode('cache_timeout')->min(0)->defaultValue(60000)->end()
                ->scalarNode('width')->defaultNull()->end()
                ->booleanNode('render_html')->defaultFalse()->end()
                ->scalarNode('table_name')->defaultNull()->end()
                ->scalarNode('text_property')->defaultNull()->end()
                ->scalarNode('primary_key')->defaultValue('id')->end()
            ->end();

        return $treeBuilder;
    }
}
