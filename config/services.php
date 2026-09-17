<?php

declare(strict_types=1);

use SaifulFeroz\Select2TableBundle\Form\Type\Select2TableType;
use SaifulFeroz\Select2TableBundle\Service\AutocompleteService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire(false)
            ->autoconfigure(false)
            ->private();

    $services->set(Select2TableType::class)
        ->args([
            service('doctrine.dbal.default_connection')->nullOnInvalid(),
            service('router'),
            param('select2_table.config'),
        ])
        ->tag('form.type');

    $services->set(AutocompleteService::class)
        ->args([
            service('form.factory'),
            service('doctrine.dbal.default_connection')->nullOnInvalid(),
        ])
        ->public();

    // Backward compatibility aliases.
    $services->alias('select2table.autocomplete_service', AutocompleteService::class)->public();
    $services->alias('feroz_select2table.autocomplete_service', AutocompleteService::class)->public();
    $services->alias('feroz_select2table.select2table_type', Select2TableType::class)->public();
};
