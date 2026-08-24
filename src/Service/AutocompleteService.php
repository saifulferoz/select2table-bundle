<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class AutocompleteService
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly ?Connection $connection
    ) {
    }

    /**
     * Executes autocomplete search and returns structured pagination response.
     *
     * @param Request $request Current HTTP request containing 'q', 'field_name', 'page'
     * @param string|FormInterface $type Form type FQCN or instantiated Form
     * @param FormInterface|null $form Optional instantiated form
     * @return array{results: list<array{id: mixed, text: mixed}>, more: bool}
     */
    public function getAutocompleteResults(Request $request, string|FormInterface $type, ?FormInterface $form = null): array
    {
        if ($this->connection === null) {
            throw new \LogicException('Doctrine DBAL Connection is required for AutocompleteService.');
        }

        if ($type instanceof FormInterface) {
            $form = $type;
        } elseif ($form === null) {
            $form = $this->formFactory->create($type);
        }

        $fieldName = (string) $request->get('field_name', '');
        if (!$form->has($fieldName)) {
            throw new \InvalidArgumentException(sprintf('Field "%s" not found on form "%s".', $fieldName, $form->getName()));
        }

        $fieldOptions = $form->get($fieldName)->getConfig()->getOptions();

        $table = $fieldOptions['table_name'] ?? null;
        if (empty($table)) {
            throw new \InvalidArgumentException(sprintf('Option "table_name" is missing for field "%s".', $fieldName));
        }

        // Validate table identifier to prevent SQL injection
        $this->validateIdentifier($table);

        $properties = $fieldOptions['property'] ?? $fieldOptions['text_property'] ?? 'name';
        $properties = \is_array($properties) ? $properties : [$properties];
        foreach ($properties as $prop) {
            $this->validateIdentifier((string) $prop);
        }

        $primaryKey = (string) ($fieldOptions['primary_key'] ?? 'id');
        $this->validateIdentifier($primaryKey);

        $textProperty = (string) ($fieldOptions['text_property'] ?? $properties[0]);
        $this->validateIdentifier($textProperty);

        $term = mb_strtolower((string) $request->get('q', ''));
        $maxResults = (int) ($fieldOptions['page_limit'] ?? 10);
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $maxResults;

        // Build conditions
        $qb = $this->connection->createQueryBuilder();
        $conditions = [];
        foreach ($properties as $idx => $prop) {
            $paramName = 'term_' . $idx;
            $conditions[] = $qb->expr()->like('LOWER(' . $prop . ')', ':' . $paramName);
        }

        $orCondition = \count($conditions) === 1
            ? $conditions[0]
            : $qb->expr()->or(...$conditions);

        // 1. Total Count Query
        $countQb = $this->connection->createQueryBuilder();
        $countQb
            ->select('COUNT(*)')
            ->from($table);

        if ($term !== '' && !empty($conditions)) {
            $countQb->where($orCondition);
            foreach ($properties as $idx => $prop) {
                $countQb->setParameter('term_' . $idx, '%' . $term . '%');
            }
        }

        if (!empty($fieldOptions['callback']) && \is_callable($fieldOptions['callback'])) {
            ($fieldOptions['callback'])($countQb, $request);
        }

        $count = (int) $countQb->executeQuery()->fetchOne();

        // 2. Paginated Data Query
        $dataQb = $this->connection->createQueryBuilder();
        $dataQb
            ->select('*')
            ->from($table)
            ->setFirstResult($offset)
            ->setMaxResults($maxResults);

        if ($term !== '' && !empty($conditions)) {
            $dataQb->where($orCondition);
            foreach ($properties as $idx => $prop) {
                $dataQb->setParameter('term_' . $idx, '%' . $term . '%');
            }
        }

        if (!empty($fieldOptions['callback']) && \is_callable($fieldOptions['callback'])) {
            ($fieldOptions['callback'])($dataQb, $request);
        }

        $rows = $dataQb->executeQuery()->fetchAllAssociative();

        $results = array_map(function (array $row) use ($primaryKey, $textProperty) {
            $item = [
                'id' => $row[$primaryKey] ?? null,
                'text' => $row[$textProperty] ?? ($row[$primaryKey] ?? ''),
            ];

            if (isset($row['html'])) {
                $item['html'] = $row['html'];
            }

            return $item;
        }, $rows);

        return [
            'results' => $results,
            'more' => $count > ($offset + $maxResults),
        ];
    }

    /**
     * Ensures identifier names only contain alphanumeric characters, underscores, and dots.
     */
    private function validateIdentifier(string $identifier): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $identifier)) {
            throw new \InvalidArgumentException(sprintf('Invalid database identifier "%s".', $identifier));
        }
    }
}
