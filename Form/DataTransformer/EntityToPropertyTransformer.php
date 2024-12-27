<?php

namespace Feroz\Select2TableBundle\Form\DataTransformer;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for single mode (i.e., multiple = false)
 *
 * Class EntityToPropertyTransformer
 *
 * @package Feroz\Select2TableBundle\Form\DataTransformer
 */
class EntityToPropertyTransformer implements DataTransformerInterface
{
    protected PropertyAccessor $accessor;

    /**
     * @param Connection $connection
     * @param string $tableName
     * @param string|null $textColumn
     * @param string $primaryKey
     * @param string $newTagPrefix
     * @param string $newTagText
     */
    public function __construct(
        protected Connection $connection,
        protected string $tableName,
        protected ?string $textColumn = null,
        protected string $primaryKey = 'id',
        protected string $newTagPrefix = '__',
        protected string $newTagText = ' (NEW)'
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Transform row to array
     *
     * @param mixed $row
     * @return array
     */
    public function transform($row): array
    {
        $data = [];
        if (empty($row)) {
            return $data;
        }

        $text = is_null($this->textColumn)
            ? (string)$row
            : $row[$this->textColumn];

        if ($this->rowExists($row)) {
            $value = (string)$row[$this->primaryKey];
        } else {
            $value = $this->newTagPrefix.$text;
            $text = $text.$this->newTagText;
        }

        $data[$value] = $text;

        return $data;
    }

    /**
     * Transform single id value to a row
     *
     * @param string $value
     * @return array|null
     */
    public function reverseTransform($value): ?array
    {
        if (empty($value)) {
            return null;
        }

        // Add a potential new tag entry
        $tagPrefixLength = strlen($this->newTagPrefix);
        $cleanValue = substr($value, $tagPrefixLength);
        $valuePrefix = substr($value, 0, $tagPrefixLength);
        if ($valuePrefix == $this->newTagPrefix) {
            // In that case, we have a new entry
            return [$this->textColumn => $cleanValue];
        } else {
            // We do not search for a new entry, as it does not exist yet, by definition
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select('*')
                ->from($this->tableName)
                ->where($queryBuilder->expr()->eq($this->primaryKey, ':id'))
                ->setParameter('id', $value);

            $row = $queryBuilder->executeQuery()->fetchAssociative();

            if (!$row) {
                throw new TransformationFailedException(
                    sprintf('The choice "%s" does not exist or is not unique', $value)
                );
            }

            return $row;
        }
    }

    /**
     * Check if a row exists in the table
     *
     * @param array $row
     * @return bool
     */
    protected function rowExists(array $row): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('1')
            ->from($this->tableName)
            ->where($queryBuilder->expr()->eq($this->primaryKey, ':id'))
            ->setParameter('id', $row[$this->primaryKey]);

        return (bool)$queryBuilder->executeQuery()->fetchOne();
    }
}
