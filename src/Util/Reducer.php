<?php
/**
 * @from https://github.com/c9s/reducer
 *
 * $rows = [
 *     [ 'category' => 'Food', 'type' => 'pasta', 'amount' => 1, 'foo' => 10 ],
 *     [ 'category' => 'Food', 'type' => 'pasta', 'amount' => 1 ],
 *     [ 'category' => 'Food', 'type' => 'juice', 'amount' => 1 ],
 *     [ 'category' => 'Food', 'type' => 'juice', 'amount' => 1 ],
 *     [ 'category' => 'Book', 'type' => 'programming', 'amount' => 5 ],
 *     [ 'category' => 'Book', 'type' => 'programming', 'amount' => 2 ],
 *     [ 'category' => 'Book', 'type' => 'cooking', 'amount' => 6 ],
 *     [ 'category' => 'Book', 'type' => 'cooking', 'amount' => 2 ],
 * ];
 * $result = group_by($rows, ['category','type'], [
 *     'total_amount' => [
 *     'selector' => 'amount',
 *     'aggregator' => REDUCER_AGGR_SUM,
 * ], 'cnt' => REDUCER_AGGR_COUNT,]);
 * print_r($result);
 *
 * The equivaient SQL query: SELECT SUM(amount) as total_amount, COUNT(*) as cnt FROM table;
 *
 * Aggregators：
 *
 * [
 *     '{alias}' => [
 *         'selector' => '{selector}',
 *         'aggregator' => {constant | function},
 *     ],
 *     '{alias}' => {constant | function},
 * ]
 *
 * Aggregating with custom reduce function:
 *
 * $ret = group_by($rows, ['category','type'], [
 *     'amount' => function($carry, $current) {
 *         return $carry + $current;
 *     }
 * ]);
 *
 * Aggregating with selector:
 *
 * $result = group_by($rows, ['category','type'], [
 *     'total_amount' => [
 *         'selector'   => 'amount',
 *         'aggregator' => function($carry, $current) { return $carry + $current; }
 *     ],
 * ]);
 */

namespace Hail\Util;


class Reducer
{
    protected static function fold($rows, $fields, array $aggregators)
    {
        $result = [];

        if (empty($rows)) {
            return $result;
        }

        $first = $rows[0];
        foreach ($fields as $field) {
            if (isset($first[$field])) {
                $result[$field] = $first[$field];
            }
        }

        $count = \count($rows);

        foreach ($rows as $row) {
            foreach ($aggregators as $key => &$agg) {
                if (\is_array($agg)) {
                    $field = $agg['selector'];
                    $agg = $agg['aggregator'];
                } else {
                    $field = $key;
                }

                $current = $row[$field] ?? null;
                $carry = $result[$key] ?? null;

                if ($agg instanceof \Closure) {
                    $carry = $agg($carry, $current);
                    $result[$key] = $carry;

                    continue;
                }

                switch ($agg) {
                    case REDUCER_AGGR_AVG:
                    case REDUCER_AGGR_SUM:
                        if ($carry === null) {
                            $carry = 0;
                        }
                        $carry += $current;
                        $result[$key] = $carry;
                        break;
                    case REDUCER_AGGR_COUNT:
                        if ($carry === null) {
                            $result[$key] = $count;
                        }
                        break;
                    case REDUCER_AGGR_MIN:
                        if ($carry === null) {
                            $carry = $current;
                        }
                        $carry = min($carry, $current);

                        $result[$key] = $carry;
                        break;

                    case REDUCER_AGGR_MAX:
                        if ($carry === null) {
                            $carry = $current;
                        }
                        $carry = max($carry, $current);
                        $result[$key] = $carry;
                        break;
                    case REDUCER_AGGR_FIRST:
                        if ($carry === null) {
                            $result[$key] = $current;
                        }
                        break;
                    case REDUCER_AGGR_LAST:
                        $result[$key] = $current;
                        break;

                    case REDUCER_AGGR_GROUP:
                        if ($carry === null) {
                            $carry = [];
                        }
                        $carry[] = $current;
                        $result[$key] = $carry;
                        break;
                }
            }
        }
        unset($agg);

        foreach ($aggregators as $key => $agg) {
            if ($agg === REDUCER_AGGR_AVG) {
                $result[$key] /= $count;
            }
        }

        return $result;
    }

    protected static function groupItems(array $items, $field)
    {
        $tmp = [];
        foreach ($items as $item) {
            $key = $item[$field];
            if (isset($tmp[$key])) {
                $tmp[$key][] = $item;
            } else {
                $tmp[$key] = [$item];
            }
        }

        return $tmp;
    }

    protected static function groupRows($rows, $fields)
    {
        $groups = [$rows];
        foreach ($fields as $field) {
            $tmp = [];
            foreach ($groups as $group) {
                $g = self::groupItems($group, $field);
                foreach ($g as $n) {
                    $tmp[] = $n;
                }
            }
            $groups = $tmp;
        }

        return $groups;
    }

    public static function groupBy($rows, $fields, array $aggregators)
    {
        $groups = self::groupRows($rows, $fields);
        $result = [];
        foreach ($groups as $group) {
            $res = self::fold($group, $fields, $aggregators);
            $result[] = $res;
        }

        return $result;
    }
}


if (!\extension_loaded('reducer')) {
    \define('REDUCER_AGGR_SUM', 1);
    \define('REDUCER_AGGR_COUNT', 2);
    \define('REDUCER_AGGR_MIN', 3);
    \define('REDUCER_AGGR_MAX', 4);
    \define('REDUCER_AGGR_AVG', 5);
    \define('REDUCER_AGGR_FIRST', 6);
    \define('REDUCER_AGGR_LAST', 7);
    \define('REDUCER_AGGR_GROUP', 8);

    function group_by($rows, $fields, $aggregators)
    {
        return Reducer::groupBy($rows, $fields, $aggregators);
    }
}