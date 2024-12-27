<?php

namespace Feroz\Select2TableBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Form\FormFactoryInterface;
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
    public function getAutocompleteResults(Request $request, $type): array
    {
        $form = $this->formFactory->create($type);
        $fieldOptions = $form->get($request->get('field_name'))->getConfig()->getOptions();
        $field = $fieldOptions['property'];
        $table = $fieldOptions['table_name'];
        $term = $request->get('q');
        $maxResults = $fieldOptions['page_limit'];
        $offset = ($request->get('page', 1) - 1) * $maxResults;

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('COUNT(*)')
            ->from($table)
            ->where($queryBuilder->expr()->like($field, ':term'))
            ->setParameter('term', '%'.$term.'%');
        $count = $queryBuilder->executeQuery()->fetchOne();

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->like($field, ':term'))
            ->setFirstResult($offset)
            ->setMaxResults($maxResults)
            ->setParameter('term', '%'.$term.'%');
        $paginationResults = $queryBuilder->executeQuery()->fetchAllAssociative();

        // Format Results
        return [
            'results' => array_map(function ($row) use ($fieldOptions) {
                return [
                    'id' => $row[$fieldOptions['primary_key']],
                    'text' => $row[$fieldOptions['property']],
                ];
            }, $paginationResults),
            'more' => $count > ($offset + $maxResults),
        ];
    }
}
