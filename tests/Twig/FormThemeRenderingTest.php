<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Tests\Twig;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SaifulFeroz\Select2TableBundle\Form\Type\Select2TableType;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * Renders the shipped form theme end to end. Nothing else in the suite proves the
 * template is valid Twig or that it produces a usable <select> element.
 */
class FormThemeRenderingTest extends TestCase
{
    private const THEME = 'form/fields.html.twig';

    private Connection $connection;
    private FormFactoryInterface $formFactory;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE tbl_countries (id INTEGER PRIMARY KEY, name VARCHAR(255))');
        $this->connection->insert('tbl_countries', ['id' => 1, 'name' => 'Canada']);

        $router = new class implements RouterInterface {
            public function setContext(RequestContext $context): void {}
            public function getContext(): RequestContext { return new RequestContext(); }
            public function getRouteCollection(): RouteCollection { return new RouteCollection(); }
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/ajax/' . $name . ($parameters ? '?' . http_build_query($parameters) : '');
            }
            public function match(string $pathinfo): array { return []; }
        };

        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addType(new Select2TableType($this->connection, $router, []))
            ->getFormFactory();

        $this->twig = new Environment(new FilesystemLoader([
            \dirname(__DIR__, 2) . '/templates',
            \dirname(__DIR__, 2) . '/vendor/symfony/twig-bridge/Resources/views/Form',
        ]));
        $this->twig->addExtension(new FormExtension());
        // The theme uses the "trans" filter for placeholder and tag labels.
        $this->twig->addExtension(new TranslationExtension(new IdentityTranslator()));

        $engine = new TwigRendererEngine([self::THEME, 'form_div_layout.html.twig'], $this->twig);
        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => fn () => new FormRenderer($engine),
        ]));
    }

    private function render(array $options, string $name = 'country'): string
    {
        $form = $this->formFactory->createBuilder(FormType::class)
            ->add($name, Select2TableType::class, $options)
            ->getForm();

        return $this->twig->createTemplate('{{ form_widget(form.' . $name . ') }}')
            ->render(['form' => $form->createView()]);
    }

    public function testWidgetRendersSelectElementWithAjaxAttributes(): void
    {
        $html = $this->render([
            'table_name' => 'tbl_countries',
            'text_property' => 'name',
            'remote_route' => 'country_search',
            'page_limit' => 15,
            'minimum_input_length' => 2,
        ]);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('select2table', $html);
        $this->assertStringContainsString('data-ajax--url="/ajax/country_search?page_limit=15"', $html);
        $this->assertStringContainsString('data-minimum-input-length="2"', $html);
    }

    /**
     * Regression: schema details must never be whitelisted into the view, so they
     * cannot appear in the rendered markup either.
     */
    public function testRenderedMarkupDoesNotContainSchemaDetails(): void
    {
        $html = $this->render([
            'table_name' => 'tbl_countries',
            'text_property' => 'name',
            'primary_key' => 'id',
            'order_by' => 'name',
            'remote_route' => 'country_search',
        ]);

        $this->assertStringNotContainsString('tbl_countries', $html);
        $this->assertStringNotContainsString('order_by', $html);
    }

    public function testMultipleRendersMultipleAttribute(): void
    {
        $html = $this->render([
            'table_name' => 'tbl_countries',
            'text_property' => 'name',
            'remote_route' => 'country_search',
            'multiple' => true,
        ]);

        $this->assertStringContainsString('multiple="multiple"', $html);
    }

    public function testLegacyBlockDelegatesToCurrentWidget(): void
    {
        $template = $this->twig->load(self::THEME);

        $this->assertTrue($template->hasBlock('saifulferoz_select2table_widget'));
        $this->assertTrue($template->hasBlock('feroz_select2table_widget'));
    }
}
