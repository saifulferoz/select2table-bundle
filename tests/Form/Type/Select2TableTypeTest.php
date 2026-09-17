<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Tests\Form\Type;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SaifulFeroz\Select2TableBundle\Form\Type\Select2TableType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class Select2TableTypeTest extends TestCase
{
    private Connection $connection;
    private RouterInterface $router;
    private Select2TableType $type;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->router = new class implements RouterInterface {
            public function setContext(\Symfony\Component\Routing\RequestContext $context): void {}
            public function getContext(): \Symfony\Component\Routing\RequestContext { return new \Symfony\Component\Routing\RequestContext(); }
            public function getRouteCollection(): \Symfony\Component\Routing\RouteCollection { return new \Symfony\Component\Routing\RouteCollection(); }
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string {
                return '/ajax/' . $name . '?' . http_build_query($parameters);
            }
            public function match(string $pathinfo): array { return []; }
        };

        $this->type = new Select2TableType($this->connection, $this->router, [
            'minimum_input_length' => 2,
            'page_limit' => 15,
            'scroll' => true,
            'allow_clear' => true,
            'delay' => 300,
            'language' => 'en',
            'theme' => 'default',
            'cache' => true,
            'cache_timeout' => 5000,
            'primary_key' => 'id',
            'allow_add' => [
                'enabled' => false,
                'new_tag_text' => ' (NEW)',
                'new_tag_prefix' => '__',
                'tag_separators' => '[",", " "]',
            ],
        ]);

        $this->formFactory = Forms::createFormFactory();
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('saifulferoz_select2table', $this->type->getBlockPrefix());
    }

    public function testConfigureOptionsResolvesDefaults(): void
    {
        $resolver = new OptionsResolver();
        $this->type->configureOptions($resolver);

        $options = $resolver->resolve([
            'table_name' => 'tbl_users',
            'text_property' => 'username',
        ]);

        $this->assertSame('tbl_users', $options['table_name']);
        $this->assertSame('username', $options['text_property']);
        $this->assertSame(2, $options['minimum_input_length']);
        $this->assertSame(15, $options['page_limit']);
        $this->assertTrue($options['scroll']);
        $this->assertTrue($options['allow_clear']);
        $this->assertSame('id', $options['primary_key']);
    }

    public function testFinishViewPopulatesViewVariables(): void
    {
        $view = new FormView();
        $form = $this->formFactory->create(TextType::class);

        $options = [
            'remote_path' => null,
            'remote_route' => 'users',
            'remote_params' => [],
            'page_limit' => 15,
            'multiple' => true,
            'placeholder' => 'Select user',
            'primary_key' => 'id',
            'autostart' => true,
            'query_parameters' => ['type' => 'admin'],
            'width' => '100%',
            'render_html' => true,
            'class_type' => null,
            'req_params' => [],
            'minimum_input_length' => 2,
            'scroll' => true,
            'allow_clear' => true,
            'delay' => 300,
            'language' => 'en',
            'theme' => 'default',
            'cache' => true,
            'cache_timeout' => 5000,
            'allow_add' => [
                'enabled' => false,
                'new_tag_text' => ' (NEW)',
                'new_tag_prefix' => '__',
                'tag_separators' => '[",", " "]',
            ],
        ];

        $view->vars['full_name'] = 'user_select';

        $this->type->finishView($view, $form, $options);

        $this->assertSame('/ajax/users?page_limit=15', $view->vars['remote_path']);
        $this->assertTrue($view->vars['multiple']);
        $this->assertSame('user_select[]', $view->vars['full_name']);
        $this->assertSame('100%', $view->vars['width']);
        $this->assertTrue($view->vars['render_html']);
    }

    /**
     * Regression: internal schema details must not be published to the template,
     * where they would end up exposed in rendered HTML or a profiler dump.
     */
    public function testFinishViewDoesNotExposeSchemaDetails(): void
    {
        $view = new FormView();
        $form = $this->createMock(FormInterface::class);

        $options = [
            'remote_path' => '/ajax/users',
            'remote_route' => null,
            'remote_params' => [],
            'table_name' => 'tbl_users',
            'text_property' => 'username',
            'primary_key' => 'uuid',
            'order_by' => 'created_at',
            'page_limit' => 15,
            'multiple' => false,
            'placeholder' => false,
            'autostart' => true,
            'query_parameters' => [],
            'width' => null,
            'render_html' => false,
            'class_type' => null,
            'req_params' => [],
            'minimum_input_length' => 2,
            'scroll' => false,
            'allow_clear' => false,
            'delay' => 300,
            'language' => 'en',
            'theme' => 'default',
            'cache' => true,
            'cache_timeout' => 5000,
            'allow_add' => [
                'enabled' => false,
                'new_tag_text' => ' (NEW)',
                'new_tag_prefix' => '__',
                'tag_separators' => '[",", " "]',
            ],
        ];

        $view->vars['full_name'] = 'user_select';

        $this->type->finishView($view, $form, $options);

        foreach (['table_name', 'text_property', 'primary_key', 'order_by'] as $leaked) {
            $this->assertArrayNotHasKey($leaked, $view->vars, sprintf('"%s" must not reach the view.', $leaked));
        }

        // Presentation options are still published.
        $this->assertSame(2, $view->vars['minimum_input_length']);
        $this->assertSame(15, $view->vars['page_limit']);
    }
}
