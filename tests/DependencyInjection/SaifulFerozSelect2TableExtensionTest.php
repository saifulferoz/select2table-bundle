<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SaifulFeroz\Select2TableBundle\DependencyInjection\SaifulFerozSelect2TableExtension;
use SaifulFeroz\Select2TableBundle\Form\Type\Select2TableType;
use SaifulFeroz\Select2TableBundle\Service\AutocompleteService;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SaifulFerozSelect2TableExtensionTest extends TestCase
{
    public function testLoadDefaultServicesAndParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new SaifulFerozSelect2TableExtension();

        $extension->load([], $container);

        $this->assertTrue($container->hasParameter('saifulferoz_select2_table.config'));
        $this->assertTrue($container->hasParameter('feroz_select2_table.config'));

        $this->assertTrue($container->hasDefinition(Select2TableType::class));
        $this->assertTrue($container->hasDefinition(AutocompleteService::class));

        // Aliases
        $this->assertTrue($container->hasAlias('feroz_select2table.select2table_type'));
        $this->assertTrue($container->hasAlias('feroz_select2table.autocomplete_service'));
        $this->assertTrue($container->hasAlias('saifulferoz_select2table.autocomplete_service'));
    }
}
