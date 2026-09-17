<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Select2Table bundle.
 *
 * Extends AbstractBundle so the container extension alias is derived from the
 * bundle name automatically ("select2_table"), keeping it permanently in sync
 * with Symfony's naming convention.
 */
class Select2TableBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
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
                ->scalarNode('order_by')->defaultNull()->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->setParameter('select2_table.config', $config);

        $configurator->import(__DIR__ . '/../config/services.php');
    }
}
