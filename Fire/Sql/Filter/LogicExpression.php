<?php
/**
 *    __  _____   ___   __          __
 *   / / / /   | <  /  / /   ____ _/ /_  _____
 *  / / / / /| | / /  / /   / __ `/ __ `/ ___/
 * / /_/ / ___ |/ /  / /___/ /_/ / /_/ (__  )
 * `____/_/  |_/_/  /_____/`__,_/_.___/____/
 *
 * @package FireSQL
 * @author UA1 Labs Developers https://ua1.us
 * @copyright Copyright (c) UA1 Labs
 */

namespace Fire\Sql\Filter;

/**
 * This class is meant to provide a bases for creating logic expresssions for
 * \Fire\Sql\Filter.
 */
class LogicExpression {

    /**
     * The type of expression AND, OR, WHERE.
     * @var string
     */
    public $expression;

    /**
     * The property for which we are applying the expression to.
     * @var string
     */
    public $prop;

    /**
     * The value you are comparing to.
     * @var mixed
     */
    public $val;

    /**
     * The comparison type you would like to use.
     * @var string
     */
    public $comparison;

    /**
     * The constructor.
     * @param string $propertyName
     */
    public function __construct($propertyName)
    {
        $this->prop = $propertyName;
    }

    /**
     * Sets the comparison of this expression to be EqualTo
     * @param mixed $value
     * @return void
     */
    public function eq($value)
    {
        $this->comparison = '=';
        $this->val = $value;
    }

    /**
     * Sets the comparison of this expression to be NotEqualTo
     * @param mixed $value
     * @return void
     */
    public function not($value)
    {
        $this->comparison = '<>';
        $this->val = $value;
    }

    /**
     * Sets the comparison of this expression to be GreaterThan
     * @param mixed $value
     * @return void
     */
    public function gt($value)
    {
        $this->comparison = '>';
        $this->val = $value;
    }

    /**
     * Sets the comparison of this expression to be LessThan
     * @param mixed $value
     * @return void
     */
    public function lt($value)
    {
        $this->comparison = '<';
        $this->val = $value;
    }

    /**
     * Sets the comparison of this expression to be GreatThanOrEqualTo
     * @param mixed $value
     * @return void
     */
    public function gteq($value)
    {
        $this->comparison = '>=';
        $this->val = $value;
    }

    /**
     * Sets the comparison of this expression to be LessThanOrEqualTo
     * @param mixed $value
     * @return void
     */
    public function lteq($value)
    {
        $this->comparison = '<=';
        $this->val = $value;
    }

}
