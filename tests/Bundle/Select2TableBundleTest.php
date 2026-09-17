<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Tests\Bundle;

use Feroz\Select2TableBundle\FerozSelect2TableBundle;
use PHPUnit\Framework\TestCase;
use SaifulFeroz\Select2TableBundle\Form\Type\Select2TableType;
use SaifulFeroz\Select2TableBundle\Select2TableBundle;
use SaifulFeroz\Select2TableBundle\Service\AutocompleteService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class Select2TableBundleTest extends TestCase
{
    /**
     * Symfony requires the extension alias to equal the underscored bundle name.
     * A mismatch throws during cache:clear and makes the bundle uninstallable.
     */
    public function testExtensionAliasMatchesNamingConvention(): void
    {
        $bundle = new Select2TableBundle();

        $this->assertSame('select2_table', $bundle->getContainerExtension()?->getAlias());
    }

    public function testBackwardCompatibleBundleKeepsLegacyAlias(): void
    {
        $bundle = new FerozSelect2TableBundle();

        $this->assertSame('feroz_select2_table', $bundle->getContainerExtension()?->getAlias());
    }

    public function testContainerCompilesWithDefaultConfiguration(): void
    {
        $container = $this->compile(new Select2TableBundle(), []);

        $this->assertTrue($container->hasParameter('select2_table.config'));

        $config = $container->getParameter('select2_table.config');
        $this->assertSame(1, $config['minimum_input_length']);
        $this->assertSame(10, $config['page_limit']);
        $this->assertSame('id', $config['primary_key']);
        $this->assertSame('en', $config['language']);
        $this->assertTrue($config['cache']);
        $this->assertFalse($config['render_html']);
        $this->assertNull($config['order_by']);
        $this->assertFalse($config['allow_add']['enabled']);
        $this->assertSame(' (NEW)', $config['allow_add']['new_tag_text']);
    }

    public function testContainerCompilesWithCustomConfiguration(): void
    {
        $container = $this->compile(new Select2TableBundle(), [[
            'minimum_input_length' => 3,
            'page_limit' => 25,
            'language' => 'fr',
            'theme' => 'bootstrap-5',
            'render_html' => true,
            'table_name' => 'tbl_users',
            'text_property' => 'email',
            'primary_key' => 'uuid',
            'order_by' => 'created_at',
            'allow_add' => ['enabled' => true, 'new_tag_text' => ' [NEW]'],
        ]]);

        $config = $container->getParameter('select2_table.config');
        $this->assertSame(3, $config['minimum_input_length']);
        $this->assertSame(25, $config['page_limit']);
        $this->assertSame('fr', $config['language']);
        $this->assertSame('bootstrap-5', $config['theme']);
        $this->assertTrue($config['render_html']);
        $this->assertSame('tbl_users', $config['table_name']);
        $this->assertSame('email', $config['text_property']);
        $this->assertSame('uuid', $config['primary_key']);
        $this->assertSame('created_at', $config['order_by']);
        $this->assertTrue($config['allow_add']['enabled']);
        $this->assertSame(' [NEW]', $config['allow_add']['new_tag_text']);
    }

    /**
     * Services are private, so they are inlined away once compilation finishes.
     * Inspecting before the removal pass proves they were registered and tagged.
     */
    public function testServicesAreRegisteredAndFormTypeIsTagged(): void
    {
        $spy = new class implements CompilerPassInterface {
            public bool $hasFormType = false;
            public bool $hasAutocomplete = false;
            /** @var list<string> */
            public array $formTypeTags = [];

            public function process(ContainerBuilder $container): void
            {
                $this->hasFormType = $container->hasDefinition(Select2TableType::class);
                $this->hasAutocomplete = $container->hasDefinition(AutocompleteService::class);
                $this->formTypeTags = array_keys($container->findTaggedServiceIds('form.type'));
            }
        };

        $this->compile(new Select2TableBundle(), [], $spy);

        $this->assertTrue($spy->hasFormType, 'Select2TableType should be registered.');
        $this->assertTrue($spy->hasAutocomplete, 'AutocompleteService should be registered.');
        $this->assertContains(Select2TableType::class, $spy->formTypeTags);
    }

    public function testLegacyServiceAliasesAreAvailable(): void
    {
        $container = $this->compile(new Select2TableBundle(), []);

        $this->assertTrue($container->has('select2table.autocomplete_service'));
        $this->assertTrue($container->has('feroz_select2table.autocomplete_service'));
    }

    private function compile(BundleInterface $bundle, array $config, ?CompilerPassInterface $spy = null): ContainerBuilder
    {
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $container = new ContainerBuilder();
        // AbstractBundle resolves its config loader against the kernel environment,
        // which a real kernel always provides.
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->registerExtension($extension);

        // Stand-ins for services provided by the application kernel.
        $container->setDefinition('router', new Definition(\stdClass::class))->setPublic(true);
        $container->setDefinition('form.factory', new Definition(\stdClass::class))->setPublic(true);

        foreach ($config ?: [[]] as $c) {
            $container->loadFromExtension($extension->getAlias(), $c);
        }

        if ($spy !== null) {
            $container->addCompilerPass($spy, PassConfig::TYPE_BEFORE_REMOVING);
        }

        $container->compile();

        return $container;
    }
}
