<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SaifulFeroz\Select2TableBundle\Form\Type\Select2TableType;
use SaifulFeroz\Select2TableBundle\Service\AutocompleteService;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class AutocompleteServiceTest extends TestCase
{
    private Connection $connection;
    private FormFactoryInterface $formFactory;
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('
            CREATE TABLE tbl_countries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(10) NULL
            )
        ');

        $this->connection->insert('tbl_countries', ['id' => 1, 'name' => 'United States', 'code' => 'US']);
        $this->connection->insert('tbl_countries', ['id' => 2, 'name' => 'United Kingdom', 'code' => 'GB']);
        $this->connection->insert('tbl_countries', ['id' => 3, 'name' => 'Canada', 'code' => 'CA']);

        $this->router = new class implements RouterInterface {
            public function setContext(\Symfony\Component\Routing\RequestContext $context): void {}
            public function getContext(): \Symfony\Component\Routing\RequestContext { return new \Symfony\Component\Routing\RequestContext(); }
            public function getRouteCollection(): \Symfony\Component\Routing\RouteCollection { return new \Symfony\Component\Routing\RouteCollection(); }
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string { return '/ajax/' . $name; }
            public function match(string $pathinfo): array { return []; }
        };

        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addType(new Select2TableType($this->connection, $this->router, [
                'minimum_input_length' => 1,
                'page_limit' => 10,
                'scroll' => false,
                'allow_clear' => false,
                'delay' => 250,
                'language' => 'en',
                'theme' => 'default',
                'cache' => true,
                'cache_timeout' => 60000,
                'primary_key' => 'id',
                'allow_add' => [
                    'enabled' => false,
                    'new_tag_text' => ' (NEW)',
                    'new_tag_prefix' => '__',
                    'tag_separators' => '[",", " "]',
                ],
            ]))
            ->getFormFactory();
    }

    public function testGetAutocompleteResultsWithSingleProperty(): void
    {
        $form = $this->formFactory->createBuilder(FormType::class)
            ->add('country', Select2TableType::class, [
                'table_name' => 'tbl_countries',
                'property' => 'name',
                'primary_key' => 'id',
                'text_property' => 'name',
                'page_limit' => 10,
            ])
            ->getForm();

        $service = new AutocompleteService($this->formFactory, $this->connection);

        $request = new Request([
            'field_name' => 'country',
            'q' => 'united',
            'page' => 1,
        ]);

        $response = $service->getAutocompleteResults($request, $form);

        $this->assertFalse($response['more']);
        $this->assertCount(2, $response['results']);
        $this->assertSame('1', (string) $response['results'][0]['id']);
        $this->assertSame('United States', $response['results'][0]['text']);
        $this->assertSame('2', (string) $response['results'][1]['id']);
        $this->assertSame('United Kingdom', $response['results'][1]['text']);
    }

    public function testGetAutocompleteResultsWithMultipleProperties(): void
    {
        $form = $this->formFactory->createBuilder(FormType::class)
            ->add('country', Select2TableType::class, [
                'table_name' => 'tbl_countries',
                'property' => ['name', 'code'],
                'primary_key' => 'id',
                'text_property' => 'name',
                'page_limit' => 10,
            ])
            ->getForm();

        $service = new AutocompleteService($this->formFactory, $this->connection);

        $request = new Request([
            'field_name' => 'country',
            'q' => 'ca',
            'page' => 1,
        ]);

        $response = $service->getAutocompleteResults($request, $form);

        $this->assertFalse($response['more']);
        $this->assertCount(1, $response['results']);
        $this->assertSame('3', (string) $response['results'][0]['id']);
        $this->assertSame('Canada', $response['results'][0]['text']);
    }

    public function testValidateIdentifierThrowsOnInvalidSqlIdentifier(): void
    {
        $form = $this->formFactory->createBuilder(FormType::class)
            ->add('country', Select2TableType::class, [
                'table_name' => 'tbl_countries; DROP TABLE users;',
                'property' => 'name',
                'primary_key' => 'id',
                'text_property' => 'name',
                'page_limit' => 10,
            ])
            ->getForm();

        $service = new AutocompleteService($this->formFactory, $this->connection);

        $request = new Request([
            'field_name' => 'country',
            'q' => 'test',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid database identifier');
        $service->getAutocompleteResults($request, $form);
    }
}
