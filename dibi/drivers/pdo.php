<?php

/**
 * dibi - tiny'n'smart database abstraction layer
 * ----------------------------------------------
 *
 * Copyright (c) 2005, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the "dibi license" that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://php7.org/dibi/
 *
 * @copyright  Copyright (c) 2005, 2007 David Grudl
 * @license    http://php7.org/dibi/license  dibi license
 * @link       http://php7.org/dibi/
 * @package    dibi
 */


/**
 * The dibi driver for PDO
 *
 * Connection options:
 *   - 'dsn' - driver specific DSN
 *   - 'username' (or 'user')
 *   - 'password' (or 'pass')
 *   - 'options' - driver specific options array
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2005, 2007 David Grudl
 * @package    dibi
 * @version    $Revision$ $Date$
 */
class DibiPdoDriver extends DibiDriver
{
    /**
     * Describes how convert some datatypes to SQL command
     * @var array
     */
    public $formats = array(
        'TRUE'     => "1",
        'FALSE'    => "0",
        'date'     => "'Y-m-d'",
        'datetime' => "'Y-m-d H:i:s'",
    );


    /**
     * Creates object and (optionally) connects to a database
     *
     * @param array  connect configuration
     * @throws DibiException
     */
    public function __construct(array $config)
    {
        self::alias($config, 'username', 'user');
        self::alias($config, 'password', 'pass');
        self::alias($config, 'dsn');
        self::alias($config, 'options');
        parent::__construct($config);
    }



    /**
     * Connects to a database
     *
     * @throws DibiException
     * @return resource
     */
    protected function doConnect()
    {
        if (!extension_loaded('pdo')) {
            throw new DibiException("PHP extension 'pdo' is not loaded");
        }

        $config = $this->getConfig();
        $connection = new PDO($config['dsn'], $config['username'], $config['password'], $config['options']);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $connection;
    }



    /**
     * Disconnects from a database
     *
     * @return void
     */
    protected function doDisconnect()
    {
    }



    /**
     * Internal: Executes the SQL query
     *
     * @param string       SQL statement.
     * @return DibiResult  Result set object
     * @throws DibiDatabaseException
     */
    protected function doQuery($sql)
    {
        $res = $this->getConnection()->query($sql);
        return $res instanceof PDOStatement ? new DibiPdoResult($res, TRUE) : NULL;
    }



    /**
     * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query
     *
     * @return int       number of rows or FALSE on error
     */
    public function affectedRows()
    {
        throw new BadMethodCallException(__METHOD__ . ' is not implemented');
    }



    /**
     * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query
     *
     * @return int|FALSE  int on success or FALSE on failure
     */
    public function insertId()
    {
        return $this->getConnection()->lastInsertId();
    }



    /**
     * Begins a transaction (if supported).
     * @return void
     */
    public function begin()
    {
        $this->getConnection()->beginTransaction();
        dibi::notify('begin', $this);
    }



    /**
     * Commits statements in a transaction.
     * @return void
     */
    public function commit()
    {
        $this->getConnection()->commit();
        dibi::notify('commit', $this);
    }



    /**
     * Rollback changes in a transaction.
     * @return void
     */
    public function rollback()
    {
        $this->getConnection()->rollBack();
        dibi::notify('rollback', $this);
    }



    /**
     * Escapes the string
     *
     * @param string     unescaped string
     * @param bool       quote string?
     * @return string    escaped and optionally quoted string
     */
    public function escape($value, $appendQuotes = TRUE)
    {
        if (!$appendQuotes) {
            throw new BadMethodCallException('Escaping without qoutes is not supported by PDO');
        }
        return $this->getConnection()->quote($value);
    }



    /**
     * Delimites identifier (table's or column's name, etc.)
     *
     * @param string     identifier
     * @return string    delimited identifier
     */
    public function delimite($value)
    {
        // quoting is not supported by PDO
        return $value;
    }



    /**
     * Gets a information of the current database.
     *
     * @return DibiReflection
     */
    public function getDibiReflection()
    {
        throw new BadMethodCallException(__METHOD__ . ' is not implemented');
    }



    /**
     * Injects LIMIT/OFFSET to the SQL query
     *
     * @param string &$sql  The SQL query that will be modified.
     * @param int $limit
     * @param int $offset
     * @return void
     */
    public function applyLimit(&$sql, $limit, $offset = 0)
    {
        throw new BadMethodCallException(__METHOD__ . ' is not implemented');
    }

} // class DibiPdoDriver









/**
 * The dibi result-set class for PDOStatement
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2005, 2007 David Grudl
 * @package    dibi
 * @version    $Revision$ $Date$
 */
class DibiPdoResult extends DibiResult
{
    private $row = 0;


    /**
     * Returns the number of rows in a result set
     *
     * @return int
     */
    protected function doRowCount()
    {
        return $this->resource->rowCount();
    }



    /**
     * Fetches the row at current position and moves the internal cursor to the next position
     * internal usage only
     *
     * @return array|FALSE  array on success, FALSE if no next record
     */
    protected function doFetch()
    {
        return $this->resource->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT, $this->row++);
    }



    /**
     * Moves cursor position without fetching row
     *
     * @param  int      the 0-based cursor pos to seek to
     * @return void
     * @throws DibiException
     */
    protected function doSeek($row)
    {
        $this->row = $row;
    }



    /**
     * Frees the resources allocated for this result set
     *
     * @return void
     */
    protected function doFree()
    {
    }



    /** this is experimental */
    protected function buildMeta()
    {
        $count = $this->resource->columnCount();
        $this->meta = $this->convert = array();
        for ($index = 0; $index < $count; $index++) {
            $meta = $this->resource->getColumnMeta($index);
            // TODO:
            $meta['type'] = dibi::FIELD_UNKNOWN;
            $name = $meta['name'];
            $this->meta[$name] =  $meta;
            $this->convert[$name] = $meta['type'];
        }
    }


} // class DibiPdoResult
