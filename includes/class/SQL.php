<?php
    /**
     * Class: SQL
     * Contains the database settings and functions for interacting with the SQL database.
     */

    # File: Query
    # See Also:
    #     <Query>
    require_once INCLUDES_DIR."/class/Query.php";

    # File: QueryBuilder
    # See Also:
    #     <QueryBuilder>
    require_once INCLUDES_DIR."/class/QueryBuilder.php";

    class SQL {
        # Array: $debug
        # Holds debug information for SQL queries.
        public $debug = array();

        # Integer: $queries
        # Number of queries it takes to load the page.
        public $queries = 0;

        # Variable: $db
        # Holds the currently running database.
        public $db;

        # Variable: $error
        # Holds an error message from the last attempted query.
        public $error = "";

        # Boolean: $silence_errors
        # Ignore errors?
        public $silence_errors = false;

        /**
         * Function: __construct
         * The class constructor is private so there is only one connection.
         *
         * Parameters:
         *     $settings - Settings to load instead of the config.
         */
        private function __construct($settings = array()) {
            if (!UPGRADING and !INSTALLING and !isset(Config::current()->sql))
                error(__("Error"), __("Database configuration is not set. Please run the upgrader."));

            $database = (!UPGRADING) ? oneof(@Config::current()->sql, array()) : Config::get("sql") ;

            if (is_array($settings))
                fallback($database, $settings);
            elseif ($settings === true)
                $this->silence_errors = true;

            if (!empty($database))
                foreach ($database as $setting => $value)
                    $this->$setting = $value;

            $this->connected = false;

            # We really don't need PDO anymore, since we have the two we supported with it hardcoded (kinda).
            # Keeping this here for when/if we decide to add support for more database engines, like Postgres and MSSQL.
            # if (class_exists("PDO") and (in_array("mysql", PDO::getAvailableDrivers()) or in_array("sqlite", PDO::getAvailableDrivers())))
            #     return $this->method = "pdo";

            if (isset($this->adapter)) {
                if ($this->adapter == "mysql" and class_exists("MySQLi"))
                    $this->method = "mysqli";
                elseif ($this->adapter == "mysql" and function_exists("mysql_connect"))
                    $this->method = "mysql";
                elseif (class_exists("PDO") and
                        ($this->adapter == "sqlite" and in_array("sqlite", PDO::getAvailableDrivers()) or
                         $this->adapter == "pgsql" and in_array("pgsql", PDO::getAvailableDrivers())))
                    $this->method = "pdo";
            } else
                if (class_exists("MySQLi"))
                    $this->method = "mysqli";
                elseif (function_exists("mysql_connect"))
                    $this->method = "mysql";
                elseif (class_exists("PDO") and in_array("mysql", PDO::getAvailableDrivers()))
                    $this->method = "pdo";
        }

        /**
         * Function: set
         * Sets a variable's value.
         *
         * Parameters:
         *     $setting - The setting name.
         *     $value - The new value. Can be boolean, numeric, an array, a string, etc.
         *     $overwrite - If the setting exists and is the same value, should it be overwritten?
         */
        public function set($setting, $value, $overwrite = true) {
            if (isset($this->$setting) and $this->$setting == $value and !$overwrite and !UPGRADING)
                return false;

            if (!UPGRADING)
                $config = Config::current();

            $database = (!UPGRADING) ? fallback($config->sql, array()) : Config::get("sql") ;

            # Add the setting
            $database[$setting] = $this->$setting = $value;

            return (!UPGRADING) ? $config->set("sql", $database) : Config::set("sql", $database) ;
        }

        /**
         * Function: connect
         * Connects to the SQL database.
         *
         * Parameters:
         *     $checking - Return a boolean of whether or not it could connect, instead of showing an error.
         */
        public function connect($checking = false) {
            if ($this->connected)
                return true;

            if (!isset($this->database))
                self::__construct();

            if (UPGRADING)
                $checking = true;

            switch($this->method) {
                case "pdo":
                    try {
                        if (empty($this->database))
                            throw new PDOException("No database specified.");

                        if ($this->adapter == "sqlite") {
                            $this->db = new PDO("sqlite:".$this->database, null, null, array(PDO::ATTR_PERSISTENT => true));
                            $this->db->sqliteCreateFunction("YEAR", array($this, "year_from_datetime"), 1);
                            $this->db->sqliteCreateFunction("MONTH", array($this, "month_from_datetime"), 1);
                            $this->db->sqliteCreateFunction("DAY", array($this, "day_from_datetime"), 1);
                            $this->db->sqliteCreateFunction("HOUR", array($this, "hour_from_datetime"), 1);
                            $this->db->sqliteCreateFunction("MINUTE", array($this, "minute_from_datetime"), 1);
                            $this->db->sqliteCreateFunction("SECOND", array($this, "second_from_datetime"), 1);
                        } else
                            $this->db = new PDO($this->adapter.":host=".$this->host.";".((isset($this->port)) ? "port=".$this->port.";" : "")."dbname=".$this->database,
                                                $this->username,
                                                $this->password,
                                                array(PDO::ATTR_PERSISTENT => true));

                        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    } catch (PDOException $error) {
                        $this->error = $error->getMessage();
                        return ($checking) ? false : error(__("Database Error"), $this->error) ;
                    }
                    break;
                case "mysqli":
                    $this->db = @new MySQLi($this->host, $this->username, $this->password, $this->database);
                    $this->error = mysqli_connect_error();

                    if (mysqli_connect_errno())
                        return ($checking) ? false : error(__("Database Error"), $this->error) ;

                    break;
                case "mysql":
                    $this->db = @mysql_connect($this->host, $this->username, $this->password);
                    $this->error = mysql_error();

                    if (!$this->db or !@mysql_select_db($this->database))
                        return ($checking) ? false : error(__("Database Error"), $this->error) ;

                    break;
            }

            if ($this->adapter == "mysql")
                new Query($this, "SET NAMES 'utf8'"); # Note: This doesn't increase the query debug/count.

            return $this->connected = true;
        }

        /**
         * Function: query
         * Executes a query and increases <SQL->$queries>.
         * If the query results in an error, it will die and show the error.
         *
         * Parameters:
         *     $query - Query to execute.
         *     $params - An associative array of parameters used in the query.
         *     $throw_exceptions - Should an exception be thrown if the query fails?
         */
        public function query($query, $params = array(), $throw_exceptions = false) {
            if (!$this->connected)
                return false;

            # Ensure that every param in $params exists in the query.
            # If it doesn't, remove it from $params.
            foreach ($params as $name => $val)
                if (!strpos($query, $name))
                    unset($params[$name]);

            $query = str_replace("__", $this->prefix, $query);

            if ($this->adapter == "sqlite")
                $query = str_ireplace(" DEFAULT CHARSET=utf8", "", str_ireplace("AUTO_INCREMENT", "AUTOINCREMENT", $query));

            if ($this->adapter == "pgsql")
                $query = str_ireplace(array("CREATE TABLE IF NOT EXISTS",
                                            "INTEGER PRIMARY KEY AUTO_INCREMENT",
                                            ") DEFAULT CHARSET=utf8",
                                            "TINYINT",
                                            "DATETIME",
                                            "DEFAULT '0000-00-00 00:00:00'",
                                            "LONGTEXT",
                                            "REPLACE INTO"),
                                      array("CREATE TABLE",
                                            "SERIAL PRIMARY KEY",
                                            ")",
                                            "SMALLINT",
                                            "TIMESTAMP",
                                            "",
                                            "TEXT",
                                            "INSERT INTO"),
                                      $query);

            $query = new Query($this, $query, $params, $throw_exceptions);

            return (!$query->query and UPGRADING) ? false : $query ;
        }

        /**
         * Function: count
         * Performs a counting query and returns the number of matching rows.
         *
         * Parameters:
         *     $tables - An array (or string) of tables to count results on.
         *     $conds - An array (or string) of conditions to match.
         *     $params - An associative array of parameters used in the query.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function count($tables, $conds = null, $params = array(), $throw_exceptions = false) {
            $query = $this->query(QueryBuilder::build_count($tables, $conds, $params), $params, $throw_exceptions);
            return ($query->query) ? $query->fetchColumn() : false ;
        }

        /**
         * Function: select
         * Performs a SELECT with given criteria and returns the query result object.
         *
         * Parameters:
         *     $tables - An array (or string) of tables to grab results from.
         *     $fields - Fields to select.
         *     $conds - An array (or string) of conditions to match.
         *     $order - ORDER BY statement. Can be an array.
         *     $params - An associative array of parameters used in the query.
         *     $limit - Limit for results.
         *     $offset - Offset for the select statement.
         *     $group - GROUP BY statement. Can be an array.
         *     $left_join - An array of additional LEFT JOINs.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function select($tables, $fields = "*", $conds = null, $order = null, $params = array(), $limit = null, $offset = null, $group = null, $left_join = array(), $throw_exceptions = false) {
            return $this->query(QueryBuilder::build_select($tables, $fields, $conds, $order, $limit, $offset, $group, $left_join, $params), $params, $throw_exceptions);
        }

        /**
         * Function: insert
         * Performs an INSERT with given data.
         *
         * Parameters:
         *     $table - Table to insert to.
         *     $data - An associative array of data to insert.
         *     $params - An associative array of parameters used in the query.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function insert($table, $data, $params = array(), $throw_exceptions = false) {
            return $this->query(QueryBuilder::build_insert($table, $data, $params), $params, $throw_exceptions);
        }

        /**
         * Function: replace
         * Performs either an INSERT or an UPDATE depending on
         * whether a row exists with the specified keys matching
         * their values in the data.
         *
         * Parameters:
         *     $table - Table to update or insert into.
         *     $keys - Columns to match on.
         *     $data - Data for the insert and value matches for the keys.
         *     $params - An associative array of parameters to be used in the query.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function replace($table, $keys, $data, $params = array(), $throw_exceptions = false) {
            $match = array();
            foreach ((array) $keys as $key)
                $match[$key] = $data[$key];

            if ($this->count($table, $match, $params))
                $this->update($table, $match, $data, $params, $throw_exceptions);
            else
                $this->insert($table, $data, $params, $throw_exceptions);
        }

        /**
         * Function: update
         * Performs an UDATE with given criteria and data.
         *
         * Parameters:
         *     $table - Table to update.
         *     $conds - Rows to update.
         *     $data - An associative array of data to update.
         *     $params - An associative array of parameters used in the query.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function update($table, $conds, $data, $params = array(), $throw_exceptions = false) {
            return $this->query(QueryBuilder::build_update($table, $conds, $data, $params), $params, $throw_exceptions);
        }

        /**
         * Function: delete
         * Performs a DELETE with given criteria.
         *
         * Parameters:
         *     $table - Table to delete from.
         *     $conds - Rows to delete..
         *     $params - An associative array of parameters used in the query.
         *     $throw_exceptions - Should exceptions be thrown on error?
         */
        public function delete($table, $conds, $params = array(), $throw_exceptions = false) {
            return $this->query(QueryBuilder::build_delete($table, $conds, $params), $params, $throw_exceptions);
        }

        /**
         * Function: latest
         * Returns the last inserted sequential value.
         * Both function arguments are only relevant for PostgreSQL.
         *
         * Parameters:
         *     $table - Table to get the latest value from.
         *     $seq - Name of the sequence.
         */
        public function latest($table, $seq = "id_seq") {
            if (!isset($this->db))
                $this->connect();

            switch($this->method) {
                case "pdo":
                    return $this->db->lastInsertId($this->prefix.$table."_".$seq);
                    break;
                case "mysqli":
                    return $this->db->insert_id;
                    break;
                case "mysql":
                    return @mysql_insert_id();
                    break;
            }
        }

        /**
         * Function: escape
         * Escapes a string, escaping things like $1 and C:\foo\bar so that they don't get borked by the preg_replace.
         *
         * This also handles calling the SQL connection method's "escape_string" functions.
         *
         * Parameters:
         *     $string - String to escape.
         *     $quotes - Auto-wrap the string in quotes (@'@)?
         */
        public function escape($string, $quotes = true) {
            if (!isset($this->db))
                $this->connect();

            switch($this->method) {
                case "pdo":
                    $string = ltrim(rtrim($this->db->quote($string), "'"), "'");
                    break;
                case "mysqli":
                    $string = $this->db->escape_string($string);
                    break;
                case "mysql":
                    $string = mysql_real_escape_string($string);
                    break;
            }

            # I don't think this ever worked how it intended.
            # I've tested PDO, MySQLi, and MySQL and they all
            # properly escape with this disabled, but get double
            # escaped with this uncommented:
            # $string = str_replace('\\', '\\\\', $string);
            $string = str_replace('$', '\$', $string);

            if ($quotes and !is_numeric($string))
                $string = "'".$string."'";

            return $string;
        }

        /**
         * Function: year_from_datetime
         * Returns the year of a datetime.
         *
         * Parameters:
         *     $datetime - DATETIME value.
         */
        public function year_from_datetime($datetime) {
            return when("Y", $datetime);
        }

        /**
         * Function: month_from_datetime
         * Returns the month of a datetime.
         *
         * Parameters:
         *     $datetime - DATETIME value.
         */
        public function month_from_datetime($datetime) {
            return when("m", $datetime);
        }

        /**
         * Function: day_from_datetime
         * Returns the day of a datetime.
         *
         * Parameters:
         *     $datetime - DATETIME value.
         */
        public function day_from_datetime($datetime) {
            return when("d", $datetime);
        }

        /**
         * Function: hour_from_datetime
         * Returns the hour of a datetime.
         *
         * Parameters:
         *     $datetime - DATETIME value.
         */
        public function hour_from_datetime($datetime) {
            return when("g", $datetime);
        }

        /**
         * Function: minute_from_datetime
         * Returns the minute of a datetime.
         *
         * Parameters:
         *     $datetime - DATETIME value.
         */
        public function minute_from_datetime($datetime) {
            return when("i", $datetime);
        }

        /**
         * Function: second_from_datetime
         * Returns the second of a datetime.
         *
         * Parameters:
         *     $datetime - DATETIME value.
         */
        public function second_from_datetime($datetime) {
            return when("s", $datetime);
        }

        /**
         * Function: current
         * Returns a singleton reference to the current connection.
         */
        public static function & current($settings = false) {
            if ($settings) {
                static $loaded_settings = null;
                return $loaded_settings = new self($settings);
            } else {
                static $instance = null;
                return $instance = (empty($instance)) ? new self() : $instance ;
            }
        }
    }
