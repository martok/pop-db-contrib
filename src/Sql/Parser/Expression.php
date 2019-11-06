<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Db\Sql\Parser;

/**
 * Predicate expression parser class
 *
 * @category   Pop
 * @package    Pop\Db
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.5.0
 */
class Expression
{

    /**
     * Allowed operators
     * @var array
     */
    protected static $operators = [
        '>=', '<=', '!=', '=', '>', '<',
        'NOT LIKE', 'LIKE', 'NOT BETWEEN', 'BETWEEN',
        'NOT IN', 'IN', 'IS NOT NULL', 'IS NULL'
    ];

    /**
     * Method to parse a predicate string expression into its components
     *
     * @param  string $expression
     * @return array
     */
    public static function parse($expression)
    {
        $column   = null;
        $operator = null;
        $value    = null;

        if (stripos($expression, ' NULL') !== false) {
            $column   = self::stripIdQuotes(trim(substr($expression, 0, strpos($expression, ' '))));
            $operator = (stripos($expression, ' IS NOT NULL') !== false) ? 'IS NOT NULL' : 'IS NULL';
        } else if (stripos($expression, ' IN ') !== false) {
            $column   = self::stripIdQuotes(trim(substr($expression, 0, strpos($expression, ' '))));
            $operator = (stripos($expression, ' NOT IN ') !== false) ? 'NOT IN' : 'IN';
            $values   = substr($expression, (strpos($expression, '(') + 1));
            $values   = substr($values, strpos($values, ')'));
            $values   = array_map(function($value) {
                return \Pop\Db\Sql\Parser\Expression::stripQuotes(trim($value));
            }, explode(',', $values));
            $value    = $values;
        } else if (stripos($expression, ' BETWEEN ') !== false) {
            $column   = self::stripIdQuotes(trim(substr($expression, 0, strpos($expression, ' '))));
            $operator = (stripos($expression, ' NOT BETWEEN ') !== false) ? 'NOT BETWEEN' : 'BETWEEN';
            $value1   = substr($expression, (strpos($expression, ' ') + 1));
            $value1   = trim(substr($value1, 0, strpos($value1, ' ')));
            $value2   = trim(substr($expression, (stripos($expression, ' AND ') + 5)));
            $value    = [self::stripQuotes($value1), self::stripQuotes($value2)];
        } else if (stripos($expression, ' LIKE ') !== false) {
            $column   = self::stripIdQuotes(trim(substr($expression, 0, strpos($expression, ' '))));
            $operator = (stripos($expression, ' NOT LIKE ') !== false) ? 'NOT LIKE' : 'LIKE';
            $value    = self::stripQuotes(trim(substr($expression, (stripos($expression, ' LIKE ') + 6))));
        } else {
            [$column, $operator, $value] = array_map('trim', explode(' ', $expression));
            $value = self::stripQuotes($value);
        }

        if (!in_array($operator, self::$operators)) {
            throw new Exception("Error: The operator '" . $operator . "' is not allowed.");
        }

        return [
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value
        ];
    }

    /**
     * Method to parse predicate string expressions into its components
     *
     * @param  array $expressions
     * @return array
     */
    public static function parseExpressions(array $expressions)
    {
        $components = [];

        foreach ($expressions as $expression) {
            $components[] = self::parse($expression);
        }

        return $components;
    }

    /**
     * Convert to expression to shorthand value
     *
     * @param  string $expression
     * @return array
     */
    public static function convertExpressionToShorthand($expression)
    {
        [$column, $operator, $value] = self::parse($expression);

        switch ($operator) {
            case '>=':
            case '<=':
            case '!=':
            case '>':
            case '<':
                $column .= $operator;
                break;
            case 'LIKE':
                if (substr($value, 0, 1) == '%') {
                    $column = '%' . $column;
                    $value  = substr($value, 1);
                }
                if (substr($value, -1) == '%') {
                    $column .= '%';
                    $value   = substr($value, 0, -1);
                }
                break;
            case 'NOT LIKE':
                if (substr($value, 0, 1) == '%') {
                    $column = '-%' . $column;
                    $value  = substr($value, 1);
                }
                if (substr($value, -1) == '%') {
                    $column .= '%-';
                    $value   = substr($value, 0, -1);
                }
                break;
                break;
            case 'IS NOT NULL':
                $column .= '-';
                break;
        }

        return [$column => $value];
    }

    /**
     * Convert to expression to shorthand value
     *
     * @param  array $expressions
     * @return array
     */
    public static function convertExpressionsToShorthand(array $expressions)
    {
        $conditions = [];

        foreach ($expressions as $expression) {
            $conditions[] = self::convertExpressionToShorthand($expression);
        }

        return $conditions;
    }

    /**
     * Method to parse the shorthand columns to create expressions and their parameters
     *
     * @param  array  $columns
     * @param  string $placeholder
     * @return array
     */
    public static function parseShorthand($columns, $placeholder = null)
    {
        $expressions  = [];
        $params = [];
        $i      = 1;

        foreach ($columns as $column => $value) {
            ['column' => $parsedColumn, 'operator' => $operator] = Operator::parse($column);

            if ($placeholder == ':') {
                $pHolder = $placeholder . $parsedColumn;
            } else if ($placeholder == '$') {
                $pHolder = $placeholder . $i;
            } else {
                $pHolder = $placeholder;
            }

            // IS NULL/IS NOT NULL
            if (null === $value) {
                if ($placeholder == ':') {
                    $expressions[$parsedColumn] = $parsedColumn . ' IS ' . (($operator == 'NOT') ? 'NOT ' : '') . 'NULL';
                } else {
                    $expressions[] = $parsedColumn . ' IS ' . (($operator == 'NOT') ? 'NOT ' : '') . 'NULL';
                }
                if ($placeholder == ':') {
                    $params[$parsedColumn] = $value;
                } else {
                    $params[] = $value;
                }
                $i++;
            // IN/NOT IN
            } else if (is_array($value)) {
                $p = [];
                if ($placeholder == ':') {
                    $pHolders = [];
                    foreach ($value as $j => $val) {
                        $ph         = $pHolder . ($j + 1);
                        $pHolders[] = $ph;
                        $p[]        = $val;
                    }
                } else if ($placeholder == '$') {
                    $pHolders = [];
                    foreach ($value as $val) {
                        $pHolders[] = $placeholder . $i++;
                        $p[]        = $val;
                    }
                } else {
                    $pHolders = array_fill(0, count($value), $pHolder);
                    $p        = $value;
                    $i++;
                }
                if (null !== $placeholder) {
                    if ($placeholder == ':') {
                        $expressions[$parsedColumn] = $parsedColumn . (($operator == 'NOT') ? ' NOT ' : ' ') . 'IN (' .
                            implode(', ', $pHolders) . ')';
                    } else {
                        $expressions[] = $parsedColumn . (($operator == 'NOT') ? ' NOT ' : ' ') . 'IN (' .
                            implode(', ', $pHolders) . ')';
                    }
                } else {
                    $expressions[] = $parsedColumn . (($operator == 'NOT') ? ' NOT ' : ' ') . 'IN (' .
                        implode(', ', array_map('Pop\Db\Sql\Parser\Expression::quote', $value)) . ')';
                }
                if ($placeholder == ':') {
                    $params[$parsedColumn] = $p;
                } else {
                    $params[] = $p;
                }
            // BETWEEN/NOT BETWEEN
            } else if (is_string($value) && (substr($value, 0, 1) == '(') && (substr($value, -1) == ')') &&
                (strpos($value, ',') !== false)) {
                $values = substr($value, (strpos($value, '(') + 1));
                $values = substr($values, 0, strpos($values, ')'));
                $p      = [];

                [$value1, $value2] = array_map('trim', explode(',', $values));

                if ($placeholder == ':') {
                    $pHolder2 = $pHolder . 2;
                    $pHolder .= 1;
                    $p[substr($pHolder, 1)]  = $value1;
                    $p[substr($pHolder2, 1)] = $value2;
                } else if ($placeholder == '$') {
                    $pHolder2 = $placeholder . ++$i;
                    $p        = $values;
                } else {
                    $pHolder2 = $pHolder;
                    $p        = $values;
                }
                $p = substr($value, (strpos($value, '(') + 1));
                $p = substr($p, 0, strpos($p, ')'));
                $p = array_map('trim', explode(',', $p));

                if (null !== $placeholder) {
                    if ($placeholder == ':') {
                        $expressions[$parsedColumn] = $parsedColumn . (($operator == 'NOT') ? ' NOT ' : ' ') .
                            'BETWEEN ' . $pHolder . ' AND ' . $pHolder2;
                    } else {
                        $expressions[] = $parsedColumn . (($operator == 'NOT') ? ' NOT ' : ' ') .
                            'BETWEEN ' . $pHolder . ' AND ' . $pHolder2;
                    }
                } else {
                    $expressions[] = $parsedColumn . (($operator == 'NOT') ? ' NOT ' : ' ') .
                        'BETWEEN ' . self::quote($value1) . ' AND ' . self::quote($value2);
                }
                if ($placeholder == ':') {
                    $params[$parsedColumn] = $p;
                } else {
                    $params[] = $p;
                }
                $i++;
            // LIKE/NOT LIKE or Standard Operators
            } else  {
                if (null !== $placeholder) {
                    if ($placeholder == ':') {
                        $expressions[$parsedColumn] = $parsedColumn . ' ' . $operator . ' ' . $pHolder;
                    } else {
                        $expressions[] = $parsedColumn . ' ' . $operator . ' ' . $pHolder;
                    }
                } else {
                    $expressions[] = $parsedColumn . ' ' . $operator . ' ' . self::quote($value);
                }
                if ($placeholder == ':') {
                    $params[$parsedColumn] = $value;
                } else {
                    $params[] = $value;
                }
                $i++;
            }
        }

        return ['expressions' => $expressions, 'params' => $params];
    }

    /**
     * Strip ID quotes
     *
     * @param  string $identifier
     * @return string
     */
    public static function stripIdQuotes($identifier)
    {
        if (((substr($identifier, 0, 1) == '"') && (substr($identifier, -1) == '"')) ||
            ((substr($identifier, 0, 1) == '`') && (substr($identifier, -1) == '`')) ||
            ((substr($identifier, 0, 1) == '[') && (substr($identifier, -1) == ']'))) {
            $identifier = substr($identifier, 1);
            $identifier = substr($identifier, 0, -1);
        }

        return $identifier;
    }

    /**
     * Strip quotes
     *
     * @param  string $value
     * @return string
     */
    public static function stripQuotes($value)
    {
        if (((substr($value, 0, 1) == '"') && (substr($value, -1) == '"')) ||
            ((substr($value, 0, 1) == "'") && (substr($value, -1) == "'"))) {
            $value = substr($value, 1);
            $value = substr($value, 0, -1);
        }

        return $value;
    }



    /**
     * Quote the value (if it is not a numeric value)
     *
     * @param  string $value
     * @return string
     */
    public static function quote($value)
    {
        if (($value == '') ||
            (($value != '?') && (substr($value, 0, 1) != ':') && (preg_match('/^\$\d*\d$/', $value) == 0) &&
                !is_int($value) && !is_float($value) && (preg_match('/^\d*$/', $value) == 0))) {
            $value = "'" . $value . "'";
        }
        return $value;
    }

}