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

        $fieldName = (string) $request->query->get('field_name', '');
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

        // Columns searched by the LIKE filter. "property" takes precedence and may
        // be a list; "text_property" is used as a fallback for both search and label.
        $properties = $fieldOptions['property'] ?? $fieldOptions['text_property'] ?? 'name';
        $properties = \is_array($properties) ? array_values($properties) : [$properties];
        foreach ($properties as $prop) {
            $this->validateIdentifier((string) $prop);
        }

        $primaryKey = (string) ($fieldOptions['primary_key'] ?? 'id');
        $this->validateIdentifier($primaryKey);

        // The label column: explicit "text_property" wins, otherwise the first
        // searched column, so setting only "property" still yields a sane label.
        $textProperty = (string) ($fieldOptions['text_property'] ?? $properties[0]);
        $this->validateIdentifier($textProperty);

        // Deterministic ordering is required: LIMIT/OFFSET without ORDER BY has no
        // defined row order, which makes paginated scrolling drop and repeat rows.
        $orderBy = (string) ($fieldOptions['order_by'] ?? $textProperty);
        $this->validateIdentifier($orderBy);

        $term = mb_strtolower((string) $request->query->get('q', ''));
        $maxResults = max(1, (int) ($fieldOptions['page_limit'] ?? 10));
        $page = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * $maxResults;

        $callback = $fieldOptions['callback'] ?? null;
        if ($callback !== null && !\is_callable($callback)) {
            $callback = null;
        }

        // A single filter closure is applied identically to both queries so the
        // "more" flag can never disagree with the rows actually returned.
        $applyFilter = function (QueryBuilder $qb) use ($term, $properties, $callback, $request): void {
            if ($term !== '' && $properties !== []) {
                $conditions = [];
                foreach ($properties as $idx => $prop) {
                    $conditions[] = $qb->expr()->like(
                        'LOWER(' . $this->quote((string) $prop) . ')',
                        ':term_' . $idx
                    );
                }

                $qb->where(\count($conditions) === 1 ? $conditions[0] : $qb->expr()->or(...$conditions));

                foreach ($properties as $idx => $prop) {
                    $qb->setParameter('term_' . $idx, '%' . $term . '%');
                }
            }

            if ($callback !== null) {
                $callback($qb, $request);
            }
        };

        // 1. Total count query
        $countQb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->quote($table));
        $applyFilter($countQb);

        $count = (int) $countQb->executeQuery()->fetchOne();

        // 2. Paginated data query
        $dataQb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->quote($table))
            ->orderBy($this->quote($orderBy), 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($maxResults);
        $applyFilter($dataQb);

        $rows = $dataQb->executeQuery()->fetchAllAssociative();

        // The "html" column is rendered unescaped by the client, so it is only sent
        // when the field explicitly opts in via render_html. Fields that never render
        // HTML therefore cannot leak an injectable payload to the browser at all.
        $renderHtml = (bool) ($fieldOptions['render_html'] ?? false);

        $results = array_map(function (array $row) use ($primaryKey, $textProperty, $renderHtml) {
            $item = [
                'id' => $row[$primaryKey] ?? null,
                'text' => $row[$textProperty] ?? ($row[$primaryKey] ?? ''),
            ];

            if ($renderHtml && isset($row['html'])) {
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

    /**
     * Quotes an already validated identifier, preserving qualified "schema.table" names
     * so reserved words such as "order" can safely be used as column or table names.
     */
    private function quote(string $identifier): string
    {
        if ($this->connection === null) {
            return $identifier;
        }

        return implode('.', array_map(
            $this->connection->quoteIdentifier(...),
            explode('.', $identifier)
        ));
    }
}
