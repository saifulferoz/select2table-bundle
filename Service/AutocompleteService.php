<?php

namespace Feroz\Select2TableBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;

class AutocompleteService
{

    public function __construct(private FormFactoryInterface $formFactory, private Connection $connection)
    {
    }

    /**
     * @param Request $request
     * @param string|FormTypeInterface $type
     *
     * @return array
     */
    public function getAutocompleteResults(Request $request, string $type, ?FormInterface $form = null): array
    {
        if (null == $form) {
            $form = $this->formFactory->create($type);
        }
        $fieldOptions = $form->get($request->get('field_name'))->getConfig()->getOptions();
        $field = $fieldOptions['property'];
        $table = $fieldOptions['table_name'];
        $term = strtolower($request->get('q'));
        $maxResults = $fieldOptions['page_limit'];
        $offset = ($request->get('page', 1) - 1) * $maxResults;
        $queryBuilder = $this->connection->createQueryBuilder();
        $conditions = [];
        foreach ($field as $f) {
            $conditions[] = $queryBuilder->expr()->like('lower('.$f.')', ':term');
        }
        $queryBuilder
            ->select('COUNT(*)')
            ->from($table)
            ->where($queryBuilder->expr()->orX(...$conditions))
            ->setParameter('term', '%'.$term.'%');
        if (isset($fieldOptions['callback']) && is_callable($fieldOptions['callback'])) {
            $callback = $fieldOptions['callback'];
            $callback($queryBuilder, $request);
        }
        $count = $queryBuilder->executeQuery()->fetchOne();

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->orX(...$conditions))
            ->setFirstResult($offset)
            ->setMaxResults($maxResults)
            ->setParameter('term', '%'.$term.'%');
        if (isset($fieldOptions['callback']) && is_callable($fieldOptions['callback'])) {
            $callback = $fieldOptions['callback'];
            $callback($queryBuilder, $request);
        }
        $paginationResults = $queryBuilder->executeQuery()->fetchAllAssociative();

        // Format Results
        return [
            'results' => array_map(function ($row) use ($fieldOptions) {
                return [
                    'id' => $row[$fieldOptions['primary_key']],
                    'text' => $row[$fieldOptions['text_property']],
                ];
            }, $paginationResults),
            'more' => $count > ($offset + $maxResults),
        ];
    }
}
