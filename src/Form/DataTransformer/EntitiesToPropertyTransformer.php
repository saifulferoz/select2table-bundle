<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Form\DataTransformer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for multiple selection mode (multiple = true).
 */
class EntitiesToPropertyTransformer implements DataTransformerInterface
{
    protected PropertyAccessor $accessor;

    public function __construct(
        protected ?Connection $connection,
        protected ?string $tableName,
        protected ?string $textColumn = null,
        protected string $primaryKey = 'id',
        protected string $newTagPrefix = '__',
        protected string $newTagText = ' (NEW)'
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Transforms a collection of rows or IDs into an array for the view: [id => text, ...].
     *
     * @param mixed $value
     * @return array<string|int, string>
     */
    public function transform(mixed $value): array
    {
        if (empty($value) || (!\is_array($value) && !$value instanceof \Traversable)) {
            return [];
        }

        $data = [];

        foreach ($value as $item) {
            if (\is_array($item)) {
                $text = $this->textColumn === null
                    ? (string) ($item[$this->primaryKey] ?? '')
                    : (string) ($item[$this->textColumn] ?? '');

                if ($this->rowExists($item)) {
                    $id = (string) $item[$this->primaryKey];
                } else {
                    $id = $this->newTagPrefix . $text;
                    $text .= $this->newTagText;
                }

                $data[$id] = $text;
            } elseif (\is_scalar($item) && $this->connection !== null && $this->tableName !== null) {
                // Scalar ID passed
                $queryBuilder = $this->connection->createQueryBuilder();
                $queryBuilder
                    ->select('*')
                    ->from($this->tableName)
                    ->where($queryBuilder->expr()->eq($this->primaryKey, ':id'))
                    ->setParameter('id', (string) $item);

                $row = $queryBuilder->executeQuery()->fetchAssociative();
                if ($row !== false) {
                    $text = $this->textColumn === null
                        ? (string) $row[$this->primaryKey]
                        : (string) ($row[$this->textColumn] ?? '');
                    $data[(string) $row[$this->primaryKey]] = $text;
                }
            }
        }

        return $data;
    }

    /**
     * Transforms an array of submitted IDs into database rows.
     *
     * @param mixed $value
     * @return array<int, array>
     * @throws TransformationFailedException
     */
    public function reverseTransform(mixed $value): array
    {
        if (!\is_array($value) || empty($value)) {
            return [];
        }

        $newRows = [];
        $ids = [];
        $tagPrefixLength = \strlen($this->newTagPrefix);

        foreach ($value as $val) {
            $strVal = (string) $val;
            if ($tagPrefixLength > 0 && str_starts_with($strVal, $this->newTagPrefix)) {
                $cleanValue = substr($strVal, $tagPrefixLength);
                $newRows[] = $this->textColumn !== null
                    ? [$this->textColumn => $cleanValue]
                    : [$this->primaryKey => $cleanValue];
            } else {
                $ids[] = $strVal;
            }
        }

        if (empty($ids)) {
            return $newRows;
        }

        if ($this->connection === null || $this->tableName === null) {
            $mapped = array_map(fn($id) => [$this->primaryKey => $id], $ids);
            return array_merge($mapped, $newRows);
        }

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where($queryBuilder->expr()->in($this->primaryKey, ':ids'))
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        if (\count($rows) !== \count($ids)) {
            throw new TransformationFailedException('One or more selected IDs are invalid or could not be found.');
        }

        return array_merge($rows, $newRows);
    }

    protected function rowExists(array $row): bool
    {
        if (!isset($row[$this->primaryKey]) || $this->connection === null || $this->tableName === null) {
            return false;
        }

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('1')
            ->from($this->tableName)
            ->where($queryBuilder->expr()->eq($this->primaryKey, ':id'))
            ->setParameter('id', $row[$this->primaryKey]);

        return (bool) $queryBuilder->executeQuery()->fetchOne();
    }
}
