<?php

namespace Feroz\Select2TableBundle\Form\DataTransformer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for multiple mode (i.e., multiple = true)
 *
 * Class EntitiesToPropertyTransformer
 * @package Feroz\Select2TableBundle\Form\DataTransformer
 */
class EntitiesToPropertyTransformer implements DataTransformerInterface
{
    /** @var Connection */
    protected Connection $connection;
    /** @var  string */
    protected string $tableName;
    /** @var  string */
    protected string $textColumn;
    /** @var  string */
    protected string $primaryKey;
    /** @var  string */
    protected string $newTagPrefix;
    /** @var string */
    protected string $newTagText;
    /** @var PropertyAccessor */
    protected PropertyAccessor $accessor;

    /**
     * @param Connection $connection
     * @param string $tableName
     * @param string|null $textColumn
     * @param string $primaryKey
     * @param string $newTagPrefix
     */
    public function __construct(
        Connection $connection,
        string $tableName,
        string $textColumn = null,
        string $primaryKey = 'id',
        string $newTagPrefix = '__',
        $newTagText = ' (NEW)'
    ) {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->textColumn = $textColumn;
        $this->primaryKey = $primaryKey;
        $this->newTagPrefix = $newTagPrefix;
        $this->newTagText = $newTagText;
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Transform initial rows to array
     *
     * @param mixed $rows
     * @return array
     */
    public function transform($values): array
    {
        if (empty($values)) {
            return [];
        }

        $data = [];

        foreach ($values as $row) {
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
        }

        return $data;
    }

    /**
     * Transform array to a collection of rows
     *
     * @param array $values
     * @return array
     */
    public function reverseTransform($values): array
    {
        if (!is_array($values) || empty($values)) {
            return [];
        }

        // add new tag entries
        $newRows = [];
        $tagPrefixLength = strlen($this->newTagPrefix);
        foreach ($values as $key => $value) {
            $cleanValue = substr($value, $tagPrefixLength);
            $valuePrefix = substr($value, 0, $tagPrefixLength);
            if ($valuePrefix == $this->newTagPrefix) {
                $newRows[] = [$this->textColumn => $cleanValue];
                unset($values[$key]);
            }
        }

        // get multiple rows with one query
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where($queryBuilder->expr()->in($this->primaryKey, ':ids'))
            ->setParameter('ids', $values, ArrayParameterType::STRING);

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        // this will happen if the form submits invalid data
        if (count($rows) != count($values)) {
            throw new TransformationFailedException('One or more id values are invalid');
        }

        return array_merge($rows, $newRows);
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
