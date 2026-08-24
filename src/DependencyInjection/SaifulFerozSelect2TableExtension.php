<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class SaifulFerozSelect2TableExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('saifulferoz_select2_table.config', $config);
        $container->setParameter('feroz_select2_table.config', $config);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.xml');
    }

    public function getAlias(): string
    {
        return 'saifulferoz_select2_table';
    }
}
