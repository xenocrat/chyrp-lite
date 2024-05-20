<?php
    /**
     * Class: SQL
     * Contains the database settings and functions for interacting with the SQL database.
     */

    # File: Query
    #
    # See Also:
    #     <Query>
    require_once INCLUDES_DIR.DIR."class".DIR."Query.php";

    # File: QueryBuilder
    #
    # See Also:
    #     <QueryBuilder>
    require_once INCLUDES_DIR.DIR."class".DIR."QueryBuilder.php";

    class SQL {
        # Array: $debug
        # Holds debug information for SQL queries.
        public $debug = array();

        # Integer: $queries
        # Number of queries it takes to load the page.
        public $queries = 0;

        # Variable: $db
        # Holds the currently running database instance.
        public $db;

        # Variable: $error
        # Holds an error message from the last attempted query.
        public $error = null;

        # String: $host
        # Holds the host setting.
        public $host = "";

        # String: $port
        # Holds the port setting.
        public $port = "";

        # String: $username
        # Holds the username setting.
        public $username = "";

        # String: $password
        # Holds the password setting.
        public $password = "";

        # String: $database
        # Holds the database setting.
        public $database = "";

        # String: $prefix
        # Holds the prefix setting.
        public $prefix = "";

        # String: $adapter
        # Holds the adapter setting.
        public $adapter = "";

        # Boolean: $connected
        # Has a connection to the database been established?
        private $connected = false;

        /**
         * Function: __construct
         * The class constructor is private so there is only one connection.
         *
         * Parameters:
         *     $settings - An array of settings (optional).
         */
        private function __construct($settings = array()) {
            if (class_exists("Config"))
                fallback($settings, Config::current()->sql);

            foreach ($settings as $setting => $value) {
                switch ($setting) {
                    case "host":
                    case "port":
                    case "username":
                    case "password":
                    case "database":
                    case "prefix":
                    case "adapter":
                        $this->$setting = fallback($value, "");
                        break;
                }
            }
        }

        /**
         * Function: connect
         * Connects to the SQL database.
         *
         * Parameters:
         *     $checking - Return a boolean for failure, instead of triggering an error?
         */
        public function connect($checking = false): bool {
            if ($this->connected)
                return true;

            try {
                if (!in_array($this->adapter, PDO::getAvailableDrivers()))
                    throw new PDOException(
                        __("PDO driver is unavailable for this database.")
                    );

                if ($this->adapter == "sqlite")
                    $this->db = new PDO(
                        "sqlite:".$this->database
                    );
                else
                    $this->db = new PDO(
                        $this->adapter.":host=".$this->host.";".
                        ((!empty($this->port)) ? "port=".$this->port.";" : "").
                        "dbname=".$this->database.
                        (($this->adapter == "mysql") ? ";charset=utf8mb4" : ""),
                        $this->username,
                        $this->password
                    );

                $this->db->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

                $this->db->setAttribute(
                    PDO::ATTR_CASE,
                    PDO::CASE_NATURAL
                );

                $this->db->setAttribute(
                    PDO::ATTR_ORACLE_NULLS,
                    PDO::NULL_NATURAL
                );

                $this->db->setAttribute(
                    PDO::ATTR_DEFAULT_FETCH_MODE,
                    PDO::FETCH_ASSOC
                );

            } catch (PDOException $error) {
                $this->error = $error->getMessage();

                if ($checking)
                    return false;

                trigger_error(
                    _f("Database error: %s", fix($this->error, false, true)),
                    E_USER_ERROR
                );
            }

            if ($this->adapter == "mysql") {
                # This is not added to the query debug/count.
                new Query(
                    $this,
                    "SET SESSION sql_mode = 'ANSI,STRICT_TRANS_TABLES'"
                );
            }

            return $this->connected = true;
        }

        /**
         * Function: query
         * Executes a query and increases <SQL->$queries>.
         *
         * Parameters:
         *     $query - Query to execute.
         *     $params - An associative array of query parameters.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function query(
            $query,
            $params = array(),
            $throw_exceptions = false
        ): Query|false {
            if (!$this->connected)
                return false;

            # Reset the error message.
            $this->error = null;

            # Unset parameters that do not exist in the query.
            foreach ($params as $name => $val) {
                if (!strpos($query, $name))
                    unset($params[$name]);
            }

            # Add the table prefix to the query.
            $query = str_replace("__", $this->prefix, $query);
            $query = new Query($this, $query, $params, $throw_exceptions);

            return $query;
        }

        /**
         * Function: count
         * Performs a counting query and returns the number of matching rows.
         *
         * Parameters:
         *     $tables - An array (or string) of tables to count results on.
         *     $conds - Rows to count. Supply @false@ to count all rows.
         *     $params - An associative array of query parameters.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function count(
            $tables,
            $conds = null,
            $params = array(),
            $throw_exceptions = false
        ): mixed {
            $build = QueryBuilder::build_count(
                $this, $tables, $conds, $params
            );
            $query = $this->query(
                $build, $params, $throw_exceptions
            );

            return isset($query->query) ?
                $query->fetchColumn() :
                false ;
        }

        /**
         * Function: select
         * Performs a SELECT with given criteria and returns the query result object.
         *
         * Parameters:
         *     $tables - An array (or string) of tables to grab results from.
         *     $fields - Fields to select.
         *     $conds - Rows to select. Supply @false@ to select all rows.
         *     $order - ORDER BY statement. Can be an array.
         *     $params - An associative array of query parameters.
         *     $limit - Limit for results.
         *     $offset - Offset for the select statement.
         *     $group - GROUP BY statement. Can be an array.
         *     $left_join - An array of additional LEFT JOINs.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function select(
            $tables,
            $fields = "*",
            $conds = null,
            $order = null,
            $params = array(),
            $limit = null,
            $offset = null,
            $group = null,
            $left_join = array(),
            $throw_exceptions = false
        ): Query|false {
            $build = QueryBuilder::build_select(
                $this,
                $tables,
                $fields,
                $conds,
                $order,
                $limit,
                $offset,
                $group,
                $left_join,
                $params
            );
            return $this->query(
                $build, $params, $throw_exceptions
            );
        }

        /**
         * Function: insert
         * Performs an INSERT with given data.
         *
         * Parameters:
         *     $table - Table to insert to.
         *     $data - An associative array of data to insert.
         *     $params - An associative array of query parameters.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function insert(
            $table,
            $data,
            $params = array(),
            $throw_exceptions = false
        ): Query|false {
            $build = QueryBuilder::build_insert(
                $this, $table, $data, $params
            );
            return $this->query(
                $build, $params, $throw_exceptions
            );
        }

        /**
         * Function: replace
         * Performs either an INSERT or an UPDATE depending
         * on whether a row exists with the specified keys.
         *
         * Parameters:
         *     $table - Table to update or insert into.
         *     $keys - Columns to match on.
         *     $data - Data for the insert and value matches for the keys.
         *     $params - An associative array of query parameters.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function replace(
            $table,
            $keys,
            $data,
            $params = array(),
            $throw_exceptions = false
        ): Query|false {
            $match = array();

            foreach ((array) $keys as $key)
                $match[$key] = $data[$key];

            if ($this->count($table, $match, $params))
                return $this->update(
                    $table, $match, $data, $params, $throw_exceptions
                );

            return $this->insert(
                $table, $data, $params, $throw_exceptions
            );
        }

        /**
         * Function: update
         * Performs an UDATE with given criteria and data.
         *
         * Parameters:
         *     $table - Table to update.
         *     $conds - Rows to update. Supply @false@ to update all rows.
         *     $data - An associative array of data to update.
         *     $params - An associative array of query parameters.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function update(
            $table,
            $conds,
            $data,
            $params = array(),
            $throw_exceptions = false
        ): Query|false {
            $build = QueryBuilder::build_update(
                $this, $table, $conds, $data, $params
            );
            return $this->query(
                $build, $params, $throw_exceptions
            );
        }

        /**
         * Function: delete
         * Performs a DELETE with given criteria.
         *
         * Parameters:
         *     $table - Table to delete from.
         *     $conds - Rows to delete. Supply @false@ to delete all rows.
         *     $params - An associative array of query parameters.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function delete(
            $table,
            $conds,
            $params = array(),
            $throw_exceptions = false
        ): Query|false {
            $build = QueryBuilder::build_delete(
                $this, $table, $conds, $params
            );
            return $this->query(
                $build, $params, $throw_exceptions
            );
        }

        /**
         * Function: drop
         * Performs a DROP TABLE with given criteria.
         *
         * Parameters:
         *     $table - Table to drop.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function drop(
            $table,
            $throw_exceptions = false
        ): Query|false {
            $build = QueryBuilder::build_drop(
                $this, $table
            );
            return $this->query(
                $build, array(), $throw_exceptions
            );
        }

        /**
         * Function: create
         * Performs a CREATE TABLE with given criteria.
         *
         * Parameters:
         *     $table - Table to create.
         *     $cols - An array of column declarations.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function create(
            $table,
            $cols,
            $throw_exceptions = false
        ): Query|false {
            $build = QueryBuilder::build_create(
                $this, $table, $cols
            );
            return $this->query(
                $build, array(), $throw_exceptions
            );
        }

        /**
         * Function: latest
         * Returns the last inserted sequential value.
         *
         * Parameters:
         *     $table - Table to get the latest value from.
         *     $seq - Name of the sequence.
         */
        public function latest(
            $table,
            $seq = "id_seq"
        ): string|false {
            if (!isset($this->db))
                $this->connect();

            return $this->db->lastInsertId(
                $this->prefix.$table."_".$seq
            );
        }

        /**
         * Function: escape
         * Escapes a string for Query construction.
         *
         * Parameters:
         *     $string - String to escape.
         *     $quotes - Wrap the string in single quotes?
         */
        public function escape(
            $string,
            $quotes = true
        ): string {
            if (!isset($this->db))
                $this->connect();

            $string = $this->db->quote($string);

            if (!$quotes)
                $string = trim($string, "'");

            return $string;
        }

        /**
         * Function: current
         * Returns a singleton reference to the current connection.
         */
        public static function & current($settings = false): self {
            if ($settings) {
                $loaded = new self($settings);
                return $loaded;
            } else {
                static $instance = null;
                $instance = (empty($instance)) ? new self() : $instance ;
                return $instance;
            }
        }
    }
