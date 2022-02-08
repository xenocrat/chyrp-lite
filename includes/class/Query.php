<?php
    /**
     * Class: Query
     * Handles a SQL query.
     */
    class Query {
        # Variable: $query
        # Holds the prepared query.
        public $query;

        # Boolean: $result
        # The result of execution.
        public $result;

        # Variable: $queryString
        # Holds the query statement.
        public $queryString = "";

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
        public function __construct($sql, $query, $params = array(), $throw_exceptions = false) {
            $this->sql = $sql;

            # Don't count config setting queries.
            $count = !preg_match("/^SET /", strtoupper($query));

            if ($count)
                ++$this->sql->queries;

            $this->db =& $this->sql->db;

            $this->params = $params;
            $this->throw_exceptions = (XML_RPC) ? true : $throw_exceptions ;
            $this->queryString = $query;

            if ($count and DEBUG) {
                $trace = debug_backtrace();
                $target = $trace[$index = 0];

                # Getting a trace from these files doesn't help much.
                while (match_any(array("/SQL\.php/", "/Model\.php/", "/\/model\//"), $target["file"])) {
                    if (isset($trace[$index + 1]["file"]))
                        $target = $trace[$index++];
                    else
                        break;
                }

                $logQuery = $query;

                foreach ($params as $name => $val)
                    $logQuery = preg_replace("/{$name}([^a-zA-Z0-9_]|$)/",
                                 str_replace("\\", "\\\\", $this->sql->escape($val))."\\1", $logQuery);

                $this->sql->debug[] = array("number" => $this->sql->queries,
                                            "file" => str_replace(MAIN_DIR."/", "", $target["file"]),
                                            "line" => $target["line"],
                                            "query" => $logQuery,
                                            "time" => timer_stop());
            }

            try {
                $this->query = $this->db->prepare($query);
                $this->result = $this->query->execute($params);
                $this->query->setFetchMode(PDO::FETCH_ASSOC);
                $this->queryString = $query;

                foreach ($params as $name => $val)
                    $this->queryString = preg_replace("/{$name}([^a-zA-Z0-9_]|$)/",
                                          str_replace(array("\\", "\$"),
                                                      array("\\\\", "\\\$"),
                                                      $this->sql->escape($val))."\\1",
                                                      $this->queryString);

                if (!$this->result)
                    throw new PDOException(__("PDO failed to execute the prepared statement."));

            } catch (PDOException $e) {
                return $this->exception_handler($e);
            }
        }

        /**
         * Function: fetchColumn
         * Fetches a column of the current row.
         *
         * Parameters:
         *     $column - The offset of the column to grab. Default 0.
         */
        public function fetchColumn($column = 0) {
            return $this->query->fetchColumn($column);
        }

        /**
         * Function: fetch
         * Returns the current row as an array.
         */
        public function fetch() {
            return $this->query->fetch();
        }

        /**
         * Function: fetchObject
         * Returns the current row as an object.
         */
        public function fetchObject() {
            return $this->query->fetchObject();
        }

        /**
         * Function: fetchAll
         * Returns an array of every result.
         */
        public function fetchAll($style = PDO::FETCH_ASSOC) { # Can be PDO::FETCH_DEFAULT in PHP 8.0.7+
            return $this->query->fetchAll($style);
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
         public function grab($column) {
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
        public function exception_handler($e) {
            $this->sql->error = $e->getMessage();

            # Trigger an error if throws were not requested.
            if (!$this->throw_exceptions) {
                $message = (DEBUG) ?
                    fix($this->sql->error, false, true).
                    "\n\n<h2>".__("Query String")."</h2>\n".
                    "<pre>".fix(print_r($this->queryString, true), false, true)."</pre>".
                    "\n\n<h2>".__("Parameters")."</h2>\n".
                    "<pre>".fix(print_r($this->params, true), false, true)."</pre>" :
                    fix($this->sql->error, false, true) ;

                return trigger_error(_f("Database error: %s", $message), E_USER_WARNING);
            }

            # Otherwise we chain the exception.
            throw new Exception($this->sql->error, $e->getCode(), $e);
        }
    }
