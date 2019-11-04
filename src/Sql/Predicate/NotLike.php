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
namespace Pop\Db\Sql\Predicate;

use Pop\Db\Sql\AbstractSql;

/**
 * Not Like predicate class
 *
 * @category   Pop
 * @package    Pop\Db
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.5.0
 */
class NotLike extends AbstractPredicate
{

    /**
     * Constructor
     *
     * Instantiate the NOT LIKE predicate set object
     *
     * @param  array  $values
     * @param  string $conjunction
     */
    public function __construct(array $values, $conjunction = 'AND')
    {
        $this->format = '%1 NOT LIKE %2';
        parent::__construct($values, $conjunction);
    }

    /**
     * Render the predicate string
     *
     *
     * @param  AbstractSql $sql
     * @throws Exception
     * @return string
     */
    public function render(AbstractSql $sql)
    {
        if (count($this->values) != 2) {
            throw new Exception('Error: The values array must have 2 values in it.');
        }

        [$column, $value] = $this->values;

        return '(' . str_replace(['%1', '%2'], [$sql->quoteId($column), $sql->quote($value)], $this->format) . ')';
    }

}