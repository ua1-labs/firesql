<?php

/**
 *    __  _____   ___   __          __
 *   / / / /   | <  /  / /   ____ _/ /_  _____
 *  / / / / /| | / /  / /   / __ `/ __ `/ ___/
 * / /_/ / ___ |/ /  / /___/ /_/ / /_/ (__  )
 * `____/_/  |_/_/  /_____/`__,_/_.___/____/
 *
 * @package FireStudio
 * @subpackage FireSQL
 * @author UA1 Labs Developers https://ua1.us
 * @copyright Copyright (c) UA1 Labs
 */

namespace Fire\Bug\Panel;

use Fire\Bug\Panel;

class FireSqlPanel extends Panel
{

    const ID = 'firesql';
    const NAME = 'FireSQL';
    const TEMPLATE = __DIR__ . '/../../../view/panels/firesql.phtml';

    private $_statements;

    public function __construct()
    {
        $this->_statements = [];
        parent::__construct(self::ID, self::NAME, self::TEMPLATE);
    }

    public function addSqlStatement($statement)
    {
        $this->_statements[] = $statement;
    }

    public function getSqlStatements()
    {
        return $this->_statements;
    }

}
