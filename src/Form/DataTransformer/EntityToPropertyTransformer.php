<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Form\DataTransformer;

use Doctrine\DBAL\Connection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for single selection mode (multiple = false).
 */
class EntityToPropertyTransformer implements DataTransformerInterface
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
     * Transforms an entity row (array) or identifier into an array formatted for the select view: [id => text].
     *
     * @param mixed $value
     * @return array<string|int, string>
     */
    public function transform(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        if (\is_array($value)) {
            $text = $this->textColumn === null
                ? (string) ($value[$this->primaryKey] ?? '')
                : (string) ($value[$this->textColumn] ?? '');

            if ($this->rowExists($value)) {
                $id = (string) $value[$this->primaryKey];
            } else {
                $id = $this->newTagPrefix . $text;
                $text .= $this->newTagText;
            }

            return [$id => $text];
        }

        // If a scalar ID was passed directly
        if (\is_scalar($value) && $this->connection !== null && $this->tableName !== null) {
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select('*')
                ->from($this->tableName)
                ->where($queryBuilder->expr()->eq($this->primaryKey, ':id'))
                ->setParameter('id', (string) $value);

            $row = $queryBuilder->executeQuery()->fetchAssociative();
            if ($row !== false) {
                $text = $this->textColumn === null
                    ? (string) $row[$this->primaryKey]
                    : (string) ($row[$this->textColumn] ?? '');
                return [(string) $row[$this->primaryKey] => $text];
            }
        }

        return [];
    }

    /**
     * Transforms a single selected ID back into a database row (array).
     *
     * @param mixed $value
     * @return array|null
     * @throws TransformationFailedException
     */
    public function reverseTransform(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!\is_scalar($value)) {
            throw new TransformationFailedException('Expected a scalar value for reverse transformation.');
        }

        $strValue = (string) $value;

        // Check if this is a newly added tag
        $tagPrefixLength = \strlen($this->newTagPrefix);
        if ($tagPrefixLength > 0 && str_starts_with($strValue, $this->newTagPrefix)) {
            $cleanValue = substr($strValue, $tagPrefixLength);
            return $this->textColumn !== null ? [$this->textColumn => $cleanValue] : [$this->primaryKey => $cleanValue];
        }

        if ($this->connection === null || $this->tableName === null) {
            return [$this->primaryKey => $strValue];
        }

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where($queryBuilder->expr()->eq($this->primaryKey, ':id'))
            ->setParameter('id', $strValue);

        $row = $queryBuilder->executeQuery()->fetchAssociative();

        if ($row === false) {
            throw new TransformationFailedException(
                sprintf('The choice "%s" does not exist in table "%s".', $strValue, $this->tableName)
            );
        }

        return $row;
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
