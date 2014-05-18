<?php
    /**
     * Class: Query
     * Handles a query based on the <SQL.method>.
     */
    class Query {
        # Variable: $query
        # Holds the current query.
        public $query;

        /**
         * Function: __construct
         * Creates a query based on the <SQL.interface>.
         *
         * Parameters:
         *     $sql - <SQL> instance.
         *     $query - Query to execute.
         *     $params - An associative array of parameters used in the query.
         *     $throw_exceptions - Throw exceptions on error?
         */
        public function __construct($sql, $query, $params = array(), $throw_exceptions = false) {
            if (DEBUG)
                global $time_start;
            
            $this->sql = $sql;

            # Don't count config setting queries.
            $count = !preg_match("/^SET /", strtoupper($query));

            if ($count)
                ++$this->sql->queries;

            $this->db =& $this->sql->db;

            $this->params = $params;
            $this->throw_exceptions = $throw_exceptions;
            $this->queryString = $query;

            if ($count and defined('DEBUG') and DEBUG) {
                $trace = debug_backtrace();
                $target = $trace[$index = 0];

                # Getting a traceback from these files doesn't help much.
                while (match(array("/SQL\.php/", "/Model\.php/", "/\/model\//"), $target["file"]))
                    if (isset($trace[$index + 1]["file"]))
                        $target = $trace[$index++];
                    else
                        break;

                $logQuery = $query;
                foreach ($params as $name => $val)
                    $logQuery = preg_replace("/{$name}([^a-zA-Z0-9_]|$)/", str_replace("\\", "\\\\", $this->sql->escape($val))."\\1", $logQuery);

                $this->sql->debug[] = array("number" => $this->sql->queries,
                                            "file" => str_replace(MAIN_DIR."/", "", $target["file"]),
                                            "line" => $target["line"],
                                            "query" => $logQuery,
                                            "time" => timer_stop());
            }

            switch($this->sql->method) {
                case "pdo":
                    try {
                        $this->query = $this->db->prepare($query);
                        $result = $this->query->execute($params);
                        $this->query->setFetchMode(PDO::FETCH_ASSOC);

                        $this->queryString = $query;
                        foreach ($params as $name => $val)
                            $this->queryString = preg_replace("/{$name}([^a-zA-Z0-9_]|$)/",
                                                              str_replace(array("\\", "\$"),
                                                                          array("\\\\", "\\\$"),
                                                                          $this->sql->escape($val))."\\1",
                                                              $this->queryString);

                        if (!$result)
                            throw new PDOException;
                    } catch (PDOException $error) {
                        if (!empty($error->errorInfo[1]) and $error->errorInfo[1] == 17)
                            return new self($sql, $query, $params, $throw_exceptions);

                        return $this->handle($error);
                    }
                    break;
                case "mysqli":
                    foreach ($params as $name => $val)
                        $query = preg_replace("/{$name}([^a-zA-Z0-9_]|$)/",
                                              str_replace(array("\\", "\$"),
                                                          array("\\\\", "\\\$"),
                                                          $this->sql->escape($val))."\\1",
                                              $query);

                    $this->queryString = $query;

                    try {
                        if (!$this->query = $this->db->query($query))
                            throw new Exception($this->db->error);
                    } catch (Exception $error) {
                        return $this->handle($error);
                    }
                    break;
                case "mysql":
                    foreach ($params as $name => $val)
                        $query = preg_replace("/{$name}([^a-zA-Z0-9_]|$)/",
                                              str_replace(array("\\", "\$"),
                                                          array("\\\\", "\\\$"),
                                                          $this->sql->escape($val))."\\1",
                                              $query);

                    $this->queryString = $query;

                    try {
                        if (!$this->query = @mysql_query($query))
                            throw new Exception(mysql_error());
                    } catch (Exception $error) {
                        return $this->handle($error);
                    }

                    break;
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
            switch($this->sql->method) {
                case "pdo":
                    return $this->query->fetchColumn($column);
                case "mysqli":
                    $result = $this->query->fetch_array();
                    return $result[$column];
                case "mysql":
                    $result = mysql_fetch_array($this->query);
                    return $result[$column];
            }
        }

        /**
         * Function: fetch
         * Returns the current row as an array.
         */
        public function fetch() {
            switch($this->sql->method) {
                case "pdo":
                    return $this->query->fetch();
                case "mysqli":
                    return $this->query->fetch_array();
                case "mysql":
                    return mysql_fetch_array($this->query);
            }
        }

        /**
         * Function: fetchObject
         * Returns the current row as an object.
         */
        public function fetchObject() {
            switch($this->sql->method) {
                case "pdo":
                    return $this->query->fetchObject();
                case "mysqli":
                    return $this->query->fetch_object();
                case "mysql":
                    return mysql_fetch_object($this->query);
            }
        }

        /**
         * Function: fetchAll
         * Returns an array of every result.
         */
        public function fetchAll($style = null) {
            switch($this->sql->method) {
                case "pdo":
                    return $this->query->fetchAll($style);
                case "mysqli":
                    $results = array();

                    while ($row = $this->query->fetch_assoc())
                        $results[] = $row;

                    return $results;
                case "mysql":
                    $results = array();

                    while ($row = mysql_fetch_assoc($this->query))
                        $results[] = $row;

                    return $results;
            }
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
         * Function: handle
         * Handles exceptions thrown by failed queries.
         */
        public function handle($error) {
            $this->sql->error = $error;

            if (UPGRADING or $this->sql->silence_errors) return false;

            $message = $error->getMessage();

            $message.= "\n\n<pre>".print_r($this->queryString, true)."\n\n<pre>".print_r($this->params, true)."</pre>\n\n<pre>".$error->getTraceAsString()."</pre>";

            if (XML_RPC or $this->throw_exceptions)
                throw new Exception($message);

            error(__("Database Error"), $message);
        }
    }
