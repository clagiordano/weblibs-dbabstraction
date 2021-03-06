<?php

namespace clagiordano\weblibs\dbabstraction;

use \PDO;

/**
 * Database adapter interface for PDO
 *
 * @package clagiordano\weblibs\dbabstraction
 */
class PDOAdapter implements DatabaseAdapterInterface
{
    /** @var string $dbHostname */
    protected $dbHostname;
    /** @var string $dbUsername */
    protected $dbUsername;
    /** @var string $dbPassword */
    protected $dbPassword;
    /** @var string $dbName */
    protected $dbName;
    /** @var string $dbDriver */
    protected $dbDriver;
    /** @var string $dbCharset */
    protected $dbCharset;
    /** @var array $driverOptions */
    protected $driverOptions = [];
    /** @var \PDO $dbConnection */
    protected $dbConnection;
    /** @var bool $executionStatus */
    protected $executionStatus = false;
    /** @var int $lastInsertedId */
    protected $lastInsertedId;
    /** @var \PDOStatement $resourceHandle */
    protected $resourceHandle;

    /**
     * Constructor.
     *
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbPassword
     * @param string $dbName
     * @param string $dbDriver
     * @param string $dbCharset
     * @param bool   $isPersistent
     */
    public function __construct(
        $dbHost,
        $dbUser,
        $dbPassword,
        $dbName,
        $dbDriver = 'mysql',
        $dbCharset = 'utf8',
        $isPersistent = true
    ) {
        $this->dbHostname = $dbHost;
        $this->dbUsername = $dbUser;
        $this->dbPassword = $dbPassword;
        $this->dbName = $dbName;

        $this->dbDriver = $dbDriver;
        $this->dbCharset = $dbCharset;

        $this->driverOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_PERSISTENT => $isPersistent
        ];
    }

    /**
     * Connect to a database by using constructor params
     *
     * @return PDO
     *
     * @throws \Exception
     */
    public function connect()
    {
        $dsnString = "{$this->dbDriver}:host={$this->dbHostname};dbname={$this->dbName};";
        $dsnString .= "charset={$this->dbCharset}";
        /*
         * Try to connect to database
         */
        try {
            $this->dbConnection = new \PDO(
                $dsnString,
                "{$this->dbUsername}",
                "{$this->dbPassword}",
                $this->driverOptions
            );
        } catch (\PDOException $ex) {
            // Error during database connection, check params.
            throw new \InvalidArgumentException(
                __METHOD__.': '.$ex->getMessage()
            );
        }

        return $this->dbConnection;
    }

    /**
     * Close automatically the database connection when the instance
     * of the class is destroyed.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Close explicitly the database connection.
     *
     * @return bool
     */
    public function disconnect()
    {
        if ($this->dbConnection === null) {
            return false;
        }

        $this->dbConnection = null;

        return true;
    }

    /**
     * Execute a query or a prepared statement with a params array values.
     *
     * @param string $queryString
     * @param array $queryValues in format [:placeholder => 'value']
     *
     * @return mixed
     */
    public function query($queryString, array $queryValues = [])
    {
        if (!is_string($queryString) || empty($queryString)) {
            throw new \InvalidArgumentException(
                __METHOD__.': The specified query is not valid.'
            );
        }

        $this->connect();
        $this->resourceHandle = $this->dbConnection->prepare($queryString);

        try {
            // start transaction
            $this->dbConnection->beginTransaction();

            // execute the query and return a status
            $this->executionStatus = $this->resourceHandle->execute($queryValues ?: null);

            // get last inserted id if present
            $this->lastInsertedId = $this->dbConnection->lastInsertId();

            // finally execute the query
            $this->dbConnection->commit();
        } catch (\PDOException $ex) {
            // If an error occurs, execute rollback
            $this->dbConnection->rollBack();

            // Return execution status to false
            $this->executionStatus = false;
            $this->resourceHandle->closeCursor();

            throw new \RuntimeException(
                __METHOD__.": {$ex->getMessage()}\nqueryString: {$queryString}"
            );
        }

        return $this->resourceHandle;
    }

    /**
     * Fetches the next row from a result set
     *
     * @return array|false
     */
    public function fetch()
    {
        if ($this->resourceHandle !== null) {
            if (($row = $this->resourceHandle->fetch(\PDO::FETCH_ASSOC)) === false) {
                $this->freeResult();
            }

            return $row;
        }

        return false;
    }

    /**
     * Perform a SELECT statement
     *
     * @param string $table
     * @param string $conditions
     * @param string $fields
     * @param string $order
     * @param string $limit
     * @param string $offset
     *
     * @return int number of affected rows
     */
    public function select(
        $table,
        $conditions = null,
        $fields = null,
        $order = null,
        $limit = null,
        $offset = null
    )
    {
        if (is_null($fields)) {
            $fields = "*";
        }

        $queryString = "SELECT {$fields} FROM {$table} ";

        if (!is_null($conditions)) {
            $queryString .= "WHERE {$conditions} ";
        }

        if (!is_null($order)) {
            $queryString .= "ORDER BY {$order} ";
        }

        if (!is_null($limit)) {
            $queryString .= "LIMIT {$limit} ";
        }

        if (!is_null($offset) && !is_null($limit)) {
            $queryString .= "OFFSET {$offset} ";
        }


        $queryString .= ";";

        $this->query($queryString);

        return $this->countRows();
    }

    /**
     * Perform a INSERT statement
     *
     * @param string $table
     * @param array $data
     *
     * @return int last inserted id
     */
    public function insert($table, array $data)
    {
        $nameFields = join(',', array_keys($data));
        $preparedValues = $this->prepareValues($data);
        $keyValues = join(",", array_keys($preparedValues));
        $queryString = "INSERT INTO {$table} ({$nameFields}) VALUES ({$keyValues});";

        $this->query($queryString, $preparedValues);

        return $this->getInsertId();
    }

    /**
     * Preparate values for execute
     *
     * @param array $arrayData
     * @return string
     */
    private function prepareValues($arrayData)
    {
        $arrayData = array_values($arrayData);

        $preparedValues = [];
        $vNumber = 1;
        foreach ($arrayData as $value) {
            $preparedValues[":value{$vNumber}"] = "$value";
            $vNumber++;
        }

        unset($arrayData);
        unset($vNumber);

        return $preparedValues;
    }

    /**
     * Perform a UPDATE statement
     *
     * @param string $table
     * @param array $data
     * @param string $conditions
     *
     * @return int number of affected rows
     */
    public function update($table, array $data, $conditions)
    {
        $nameFields = array_keys($data);
        $preparedValues = $this->prepareValues($data);
        $queryString = "UPDATE {$table} SET ";

        $fNumber = 0;
        foreach ($preparedValues as $key => $value) {
            unset($value);
            $queryString .= "{$nameFields[$fNumber]} = {$key}, ";
            $fNumber++;
        }

        $queryString = preg_replace('/,\ $/', ' ', $queryString);
        $queryString .= "WHERE {$conditions};";

        $this->query($queryString, $preparedValues);

        return $this->getAffectedRows();
    }

    /**
     * Perform a DELETE statement
     *
     * @param string $table
     * @param string $conditions
     *
     * @return int number of affected rows
     */
    public function delete($table, $conditions)
    {
        $queryString = "DELETE FROM {$table} WHERE {$conditions}";

        $this->query($queryString);

        return $this->getAffectedRows();
    }

    /**
     * Returns the ID of the last inserted row or sequence value
     *
     * @return int
     */
    public function getInsertId()
    {
        return (integer)$this->lastInsertedId;
    }

    /**
     * Returns the number of rows affected by the last SQL statement
     *
     * @return int the number of rows.
     */
    public function countRows()
    {
        $countRows = 0;

        if (!is_null($this->resourceHandle)) {
            $countRows = $this->resourceHandle->rowCount();
        }

        return $countRows;
    }

    /**
     * Returns the number of rows affected by the last SQL statement
     *
     * @return int the number of rows.
     */
    public function getAffectedRows()
    {
        $affectedRows = 0;

        if (!is_null($this->resourceHandle)) {
            $affectedRows = $this->resourceHandle->rowCount();
        }

        return $affectedRows;
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return bool Returns true on success, false on failure.
     */
    public function freeResult()
    {
        if ($this->resourceHandle === null) {
            return false;
        }

        $this->resourceHandle->closeCursor();

        return true;
    }

    /**
     * Returns the last query execution status.
     *
     * @return bool last execution status
     */
    public function hasExecutionStatus()
    {
        return $this->executionStatus;
    }
}
