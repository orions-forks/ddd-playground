<?php
namespace Leos\Infrastructure\Common\Doctrine\ORM\Repository;

use Doctrine\ORM\EntityRepository as BaseEntityRepository;

use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Adapter\DoctrineORMAdapter;

/**
 * Class EntityRepository
 * 
 * @package Leos\Infrastructure\Common\Doctrine\ORM\Repository
 */
class EntityRepository extends BaseEntityRepository
{

    const OPERATOR_GT = "gt";
    const OPERATOR_LT = "lt";
    const OPERATOR_EQ = "eq";
    const OPERATOR_LTE = "lte";
    const OPERATOR_GTE = "gte" ;
    const OPERATOR_LIKE = "like" ;
    const OPERATOR_BETWEEN = "between";


    /**
     * @param string $alias
     * @param array $criteria
     * @param array $sorting
     *
     * @return Pagerfanta
     */
    public function createPaginator(string $alias, array $criteria = [], array $sorting = []): Pagerfanta
    {
        $queryBuilder = $this->createQueryBuilder($alias);

        $this->applyCriteria($alias, $queryBuilder, $criteria);
        $this->applySorting($alias, $queryBuilder, $sorting);
        
        return $this->getPaginator($queryBuilder);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $alias
     * @param array $criteria
     * @param array $sorting
     * 
     * @return Pagerfanta
     */
    public function createAdvancedPaginator(
        QueryBuilder $queryBuilder,
        string $alias,
        array $criteria = [],
        array $sorting = []): Pagerfanta
    {
        $this->applyCriteria($alias, $queryBuilder, $criteria);
        $this->applySorting($alias, $queryBuilder, $sorting);

        return $this->getPaginator($queryBuilder);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $alias
     * @param array $keys
     * @param array $operators
     * @param array $values
     * @param array $sorting
     * @return Pagerfanta
     */
    public function createOperatorPaginator(
        QueryBuilder $queryBuilder,
        string $alias,
        array $keys = [],
        array $operators = [],
        array $values = [],
        array $sorting = []
    ): Pagerfanta
    {

        $this->applyCriteriaOperator($alias, $queryBuilder, $keys, $operators, $values);
        $this->applySorting($alias, $queryBuilder, $sorting);

        return $this->getPaginator($queryBuilder);
    }

    /**
     * @param QueryBuilder $queryBuilder
     *
     * @return Pagerfanta
     */
    protected function getPaginator(QueryBuilder $queryBuilder): Pagerfanta
    {
        // Use output walkers option in DoctrineORMAdapter should be false as it affects performance greatly (see #3775)
        return new Pagerfanta(new DoctrineORMAdapter($queryBuilder, true, false));
    }

    /**
     * @param array $objects
     *
     * @return Pagerfanta
     */
    protected function getArrayPaginator($objects): Pagerfanta
    {
        return new Pagerfanta(new ArrayAdapter($objects));
    }

    /**
     * @param string $alias
     * @param QueryBuilder $queryBuilder
     * @param array $criteria
     */
    protected function applyCriteria(string $alias, QueryBuilder $queryBuilder, array $criteria = [])
    {
        foreach ($criteria as $property => $value) {
            $name = $this->getPropertyName($alias, $property);
            if (null === $value) {
                $queryBuilder->andWhere($queryBuilder->expr()->isNull($name));
            } elseif (is_array($value)) {
                $queryBuilder->andWhere($queryBuilder->expr()->in($name, $value));
            } elseif ('' !== $value) {
                $parameter = str_replace('.', '_', $property);
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq($name, ':'.$parameter))
                    ->setParameter($parameter, $value)
                ;
            }
        }

    }

    /**
     * @param string $alias
     * @param QueryBuilder $queryBuilder
     * @param array $keys
     * @param array $operators
     * @param array $values
     * @return QueryBuilder
     */
    protected function applyCriteriaOperator(
        string $alias,
        QueryBuilder $queryBuilder,
        array $keys = [],
        array $operators = [],
        array $values = []
    )
    {
        for ($i = 0, $count = count($keys); $i < $count; $i++) {
            if (!is_null($keys[$i])) {
                $name = $this->getPropertyName($alias, $keys[$i]);
                $parameter = ':' . str_replace('.', '_', $keys[$i]) . $i;

                switch ($operators[$i]) {
                    case static::OPERATOR_GT:
                        $queryBuilder->andWhere($queryBuilder->expr()->gt($name, $parameter));
                        break;
                    case static::OPERATOR_LT:
                        $queryBuilder->andWhere($queryBuilder->expr()->lt($name, $parameter));
                        break;
                    case static::OPERATOR_GTE:
                        $queryBuilder->andWhere($queryBuilder->expr()->gte($name, $parameter));
                        break;
                    case static::OPERATOR_LTE:
                        $queryBuilder->andWhere($queryBuilder->expr()->lte($name, $parameter));
                        break;
                    case static::OPERATOR_LIKE:
                        $queryBuilder->andWhere($queryBuilder->expr()->like($name, $parameter));
                        $values[$i] = "%" . $values[$i] . "%";
                        break;
                    case static::OPERATOR_BETWEEN:
                        $queryBuilder->andWhere($queryBuilder->expr()->between($name, $values[0], $values[1]));
                        break;
                    case static::OPERATOR_EQ:
                    default:
                        if (null === $values[$i]) {
                            $queryBuilder->andWhere($queryBuilder->expr()->isNull($parameter));
                        } elseif (is_array($values[$i])) {
                            $queryBuilder->andWhere($queryBuilder->expr()->in($name, $parameter));
                        } elseif ('' !== $values[$i]) {
                            $queryBuilder->andWhere($queryBuilder->expr()->eq($name, $parameter));
                        }
                }

                $queryBuilder->setParameter($parameter, $values[$i]);
            }
        }

        return $queryBuilder;
    }

    /**
     * @param string $alias
     * @param QueryBuilder $queryBuilder
     * @param array $sorting
     * @return QueryBuilder
     */
    protected function applySorting(string $alias, QueryBuilder $queryBuilder, array $sorting = [])
    {
        foreach ($sorting as $property => $order) {
            if (!empty($order) ) {
                $queryBuilder->addOrderBy($this->getPropertyName($alias, $property), $order);
            }
        }

        return $queryBuilder;
    }

    /**
     * @param string $alias
     * @param string $name
     * @return string
     */
    protected function getPropertyName(string $alias, string $name): string
    {
        return (false === $this->startsWith($name, $alias)) ? $alias.'.'.$name : $name;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function startsWith($haystack, $needle) {
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }
}

