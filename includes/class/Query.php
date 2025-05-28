<?php
    /**
     * Class: Query
     * Handles a SQL query.
     */
    class Query {
        # Object: $query
        # Holds the prepared query.
        public $query;

        # Boolean: $result
        # The result of execution.
        public $result;

        # String: $queryString
        # Logs a representation of the query statement.
        public $queryString = "";

        # Object: $sql
        # Holds the current <SQL> instance.
        private $sql;

        # Array: $params
        # Holds the query parameters.
        private $params = array();

        # Boolean: $throw_exceptions
        # Throw exceptions instead of calling error()?
        private $throw_exceptions = false;

        /**
         * Function: __construct
         * Creates a query based on the <SQL.interface>.
         *
         * Parameters:
         *     $sql - <SQL> instance.
         *     $query - Query to execute.
         *     $params - An associative array of parameters used in the query.
         *     $throw_exceptions - Throw exceptions instead of calling error()?
         */
        public function __construct(
            $sql,
            $query,
            $params = array(),
            $throw_exceptions = false
        ) {
            $this->sql = $sql;

            # Don't count config setting queries.
            $count = !preg_match("/^SET /", strtoupper($query));

            if ($count)
                ++$this->sql->queries;

            $this->params = $params;
            $this->throw_exceptions = $throw_exceptions;
            $this->queryString = $query;

            foreach ($params as $name => $val)
                $this->queryString = preg_replace(
                    "/{$name}([^a-zA-Z0-9_]|$)/",
                    "[".serialize($val)."]"."$1",
                    $this->queryString
                );

            if ($count and DEBUG) {
                $trace = debug_backtrace();
                $target = $trace[$index = 0];

                # Getting a trace from these files doesn't help much.
                while (
                    match_any(
                        array("/SQL\.php/", "/Model\.php/", "/\/model\//"),
                        $target["file"]
                    )
                ) {
                    if (isset($trace[$index + 1]["file"]))
                        $target = $trace[$index++];
                    else
                        break;
                }

                $this->sql->debug[] = array(
                    "number" => $this->sql->queries,
                    "file" => str_replace(MAIN_DIR.DIR, "", $target["file"]),
                    "line" => $target["line"],
                    "query" => $this->queryString,
                    "time" => timer_stop()
                );
            }

            try {
                $this->query = $this->sql->db->prepare($query);
                $this->result = $this->query->execute($params);

                if (!$this->result)
                    throw new PDOException(
                        __("PDO failed to execute the prepared statement.")
                    );

            } catch (PDOException $e) {
                $this->exception_handler($e);
            }
        }

        /**
         * Function: fetchColumn
         * Fetches a column of the current row.
         *
         * Parameters:
         *     $column - The offset of the column to grab. Default 0.
         */
        public function fetchColumn(
            $column = 0
        ): mixed {
            return $this->query->fetchColumn($column);
        }

        /**
         * Function: fetch
         * Returns the current row as an array.
         */
        public function fetch(
            $mode = PDO::FETCH_DEFAULT
        ): mixed {
            return $this->query->fetch($mode);
        }

        /**
         * Function: fetchObject
         * Returns the current row as an object.
         */
        public function fetchObject(
        ): object|false {
            return $this->query->fetchObject();
        }

        /**
         * Function: fetchAll
         * Returns an array of every result.
         */
        public function fetchAll(
            $mode = PDO::FETCH_DEFAULT
        ): array {
            return $this->query->fetchAll($mode);
        }

        /**
         * Function: grab
         * Grabs all of the given column out of the full result of a query.
         *
         * Parameters:
         *     $column - Name of the column to grab.
         *
         * Returns:
         *     An array of all of the values of that column in the result.
         */
         public function grab(
            $column
        ): array {
            $all = $this->fetchAll();
            $result = array();

            foreach ($all as $row)
                $result[] = $row[$column];

            return $result;
         }

        /**
         * Function: exception_handler
         * Handles exceptions thrown by failed queries.
         */
        public function exception_handler(
            $e
        ): void {
            $this->sql->error = $e->getMessage();

            # Trigger an error if throws were not requested.
            if (!$this->throw_exceptions) {
                $message = (DEBUG) ?
                    fix($this->sql->error, false, true).
                    "\n".
                    "\n".
                    "<h2>".__("Query String")."</h2>".
                    "\n".
                    "<pre>".
                    fix(print_r($this->queryString, true), false, true).
                    "</pre>".
                    "\n".
                    "\n".
                    "<h2>".__("Parameters")."</h2>".
                    "\n".
                    "<pre>".
                    fix(print_r($this->params, true), false, true).
                    "</pre>"
                    :
                    fix($this->sql->error, false, true)
                    ;

                trigger_error(
                    _f("Database error: %s", $message),
                    E_USER_WARNING
                );
            }

            # Otherwise we chain the exception.
            throw new RuntimeException($this->sql->error, $e->getCode(), $e);
        }
    }
