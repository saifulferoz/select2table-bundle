<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Tests\Form\DataTransformer;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use SaifulFeroz\Select2TableBundle\Form\DataTransformer\EntitiesToPropertyTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;

class EntitiesToPropertyTransformerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('
            CREATE TABLE tbl_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL
            )
        ');

        $this->connection->insert('tbl_tags', ['id' => 1, 'title' => 'PHP']);
        $this->connection->insert('tbl_tags', ['id' => 2, 'title' => 'Symfony']);
    }

    public function testTransformEmptyReturnsEmptyArray(): void
    {
        $transformer = new EntitiesToPropertyTransformer($this->connection, 'tbl_tags', 'title');

        $this->assertSame([], $transformer->transform(null));
        $this->assertSame([], $transformer->transform([]));
    }

    public function testTransformArrayWithExistingRows(): void
    {
        $transformer = new EntitiesToPropertyTransformer($this->connection, 'tbl_tags', 'title', 'id');

        $rows = [
            ['id' => 1, 'title' => 'PHP'],
            ['id' => 2, 'title' => 'Symfony'],
        ];

        $output = $transformer->transform($rows);

        $this->assertSame([
            '1' => 'PHP',
            '2' => 'Symfony',
        ], $output);
    }

    public function testReverseTransformWithNewTagsAndExistingIds(): void
    {
        $transformer = new EntitiesToPropertyTransformer($this->connection, 'tbl_tags', 'title', 'id', '__', ' (NEW)');

        $submitted = ['1', '__JavaScript'];
        $resultData = $transformer->reverseTransform($submitted);

        $this->assertCount(2, $resultData);
        $this->assertSame('1', (string) $resultData[0]['id']);
        $this->assertSame('PHP', $resultData[0]['title']);
        $this->assertSame('JavaScript', $resultData[1]['title']);
    }

    public function testReverseTransformMismatchedCountThrowsException(): void
    {
        $transformer = new EntitiesToPropertyTransformer($this->connection, 'tbl_tags', 'title', 'id');

        $this->expectException(TransformationFailedException::class);
        $transformer->reverseTransform(['1', '999']);
    }
}
