<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Tests\Form\DataTransformer;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use SaifulFeroz\Select2TableBundle\Form\DataTransformer\EntityToPropertyTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;

class EntityToPropertyTransformerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('
            CREATE TABLE tbl_countries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL
            )
        ');

        $this->connection->insert('tbl_countries', ['id' => 1, 'name' => 'Canada']);
        $this->connection->insert('tbl_countries', ['id' => 2, 'name' => 'Australia']);
    }

    public function testTransformEmptyValueReturnsEmptyArray(): void
    {
        $transformer = new EntityToPropertyTransformer($this->connection, 'tbl_countries', 'name');

        $this->assertSame([], $transformer->transform(null));
        $this->assertSame([], $transformer->transform([]));
        $this->assertSame([], $transformer->transform(''));
    }

    public function testTransformArrayWithoutConnectionUsesTagFormatting(): void
    {
        $transformer = new EntityToPropertyTransformer(null, 'tbl_countries', 'name', 'id', '__', ' (NEW)');

        $data = ['id' => 12, 'name' => 'Canada'];
        $result = $transformer->transform($data);

        // Without DB connection, rowExists returns false, so treated as new tag
        $this->assertSame(['__Canada' => 'Canada (NEW)'], $result);
    }

    public function testTransformArrayWithExistingRow(): void
    {
        $transformer = new EntityToPropertyTransformer($this->connection, 'tbl_countries', 'name', 'id');

        $data = ['id' => 1, 'name' => 'Canada'];
        $res = $transformer->transform($data);

        $this->assertSame(['1' => 'Canada'], $res);
    }

    public function testReverseTransformNullReturnsNull(): void
    {
        $transformer = new EntityToPropertyTransformer($this->connection, 'tbl_countries', 'name');

        $this->assertNull($transformer->reverseTransform(null));
        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testReverseTransformNewTag(): void
    {
        $transformer = new EntityToPropertyTransformer($this->connection, 'tbl_countries', 'name', 'id', '__', ' (NEW)');

        $result = $transformer->reverseTransform('__Technology');

        $this->assertSame(['name' => 'Technology'], $result);
    }

    public function testReverseTransformExistingRowFromDatabase(): void
    {
        $transformer = new EntityToPropertyTransformer($this->connection, 'tbl_countries', 'name', 'id');
        $row = $transformer->reverseTransform('2');

        $this->assertNotNull($row);
        $this->assertSame('2', (string) $row['id']);
        $this->assertSame('Australia', $row['name']);
    }

    public function testReverseTransformNonExistingChoiceThrowsException(): void
    {
        $transformer = new EntityToPropertyTransformer($this->connection, 'tbl_countries', 'name', 'id');

        $this->expectException(TransformationFailedException::class);
        $transformer->reverseTransform('999');
    }
}
