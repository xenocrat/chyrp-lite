<?php
    /**
     * Class: QueryBuilder
     * A generic SQL query builder.
     */
    class QueryBuilder {
        /**
         * Function: build_select
         * Creates a full SELECT query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $tables - Tables to select from.
         *     $fields - Columns to select.
         *     $order - What to order by.
         *     $limit - Limit of the result.
         *     $offset - Starting point for the result.
         *     $group - What to group by.
         *     $left_join - Any @LEFT JOIN@s to add.
         *     &$params - An associative array of parameters used in the query.
         *
         * Returns:
         *     A @SELECT@ query string.
         */
        public static function build_select(
            $sql,
            $tables,
            $fields,
            $conds,
            $order = null,
            $limit = null,
            $offset = null,
            $group = null,
            $left_join = array(),
            &$params = array()
        ): string {
            $query = "SELECT ".self::build_select_header($sql, $fields, $tables)."\n".
                     "FROM ".self::build_from($sql, $tables)."\n";

            foreach ($left_join as $join)
                $query.= "LEFT JOIN \"__".$join["table"].
                         "\" ON ".
                         self::build_where($sql, $join["where"], $join["table"], $params).
                         "\n";

            if ($conds)
                $query.= "WHERE ".self::build_where($sql, $conds, $tables, $params)."\n";

            if ($group)
                $query.= "GROUP BY ".self::build_group($sql, $group, $tables)."\n";

            if ($order)
                $query.= "ORDER BY ".self::build_order($sql, $order, $tables)."\n";

            if (empty($left_join))
                $query.= self::build_limits($sql, $offset, $limit);

            return $query;
        }

        /**
         * Function: build_insert
         * Creates a full insert query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $table - Table to insert into.
         *     $data - Data to insert.
         *     &$params - An associative array of parameters used in the query.
         *
         * Returns:
         *     An @INSERT@ query string.
         */
        public static function build_insert(
            $sql,
            $table,
            $data,
            &$params = array()
        ): string {
            if (empty($params))
                foreach ($data as $key => $val) {
                    if (is_object($val) and !$val instanceof Stringable)
                        $val = null;

                    if (is_bool($val))
                        $val = (int) $val;

                    if (is_float($val))
                        $val = (string) $val;

                    if (
                        $key == "updated_at" and
                        is_datetime_zero($val)
                    )
                        $val = SQL_DATETIME_ZERO;

                    $param = self::paramify($key, $params);
                    $params[$param] = $val;
                }

            return "INSERT INTO \"__$table\"\n".
                   self::build_insert_header($sql, $data)."\n".
                   "VALUES\n".
                   "(".implode(", ", array_keys($params)).")\n";
        }

        /**
         * Function: build_update
         * Creates a full update query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $table - Table to update.
         *     $conds - Conditions to update rows by.
         *     $data - Data to update.
         *     &$params - An associative array of parameters used in the query.
         *
         * Returns:
         *     An @UPDATE@ query string.
         */
        public static function build_update(
            $sql,
            $table,
            $conds,
            $data,
            &$params = array()
        ): string {
            $query = "UPDATE \"__$table\"\n".
                     "SET ".self::build_update_values($sql, $data, $params)."\n";

            if ($conds)
                $query.= "WHERE ".self::build_where($sql, $conds, $table, $params);

            return $query;
        }

        /**
         * Function: build_delete
         * Creates a full delete query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $table - Table to delete from.
         *     $conds - Conditions to delete by.
         *     &$params - An associative array of parameters used in the query.
         *
         * Returns:
         *     A @DELETE@ query string.
         */
        public static function build_delete(
            $sql,
            $table,
            $conds,
            &$params = array()
        ): string {
            $query = "DELETE FROM \"__$table\"\n";

            if ($conds)
                $query.= "WHERE ".self::build_where($sql, $conds, $table, $params);

            return $query;
        }

        /**
         * Function: build_drop
         * Creates a full drop table query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $table - Table to drop.
         *
         * Returns:
         *     A @DROP TABLE@ query string.
         */
        public static function build_drop(
            $sql,
            $table
        ): string {
            return "DROP TABLE IF EXISTS \"__$table\"";
        }

        /**
         * Function: build_create
         * Creates a full create table query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $table - Table to create.
         *     $cols - An array of column declarations.
         *
         * Returns:
         *     A @CREATE TABLE@ query string.
         */
        public static function build_create(
            $sql,
            $table,
            $cols
        ): string {
            $query = "CREATE TABLE IF NOT EXISTS \"__$table\" (\n  ".
                     implode(",\n  ", $cols)."\n)";

            switch ($sql->adapter) {
                case "sqlite":
                    $query = str_ireplace(
                        "AUTO_INCREMENT",
                        "AUTOINCREMENT", 
                        $query
                    );
                    break;

                case "mysql":
                    $query = str_ireplace(
                        "AUTOINCREMENT",
                        "AUTO_INCREMENT",
                        $query
                    );
                    $query.= " DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_general_ci";
                    break;

                case "pgsql":
                    $query = str_ireplace(
                        array(
                            "LONGTEXT",
                            "DATETIME"
                        ),
                        array(
                            "TEXT",
                            "TIMESTAMP"
                        ),
                        $query
                    );
                    $query = preg_replace(
                        "/INTEGER( (PRIMARY )?KEY)? AUTO_?INCREMENT/i",
                        "SERIAL$1",
                        $query
                    );
                    break;
            }

            return $query;
        }

        /**
         * Function: build_update_values
         * Creates an update data part.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $data - Data to update.
         *     &$params - An associative array of parameters used in the query.
         */
        public static function build_update_values(
            $sql,
            $data,
            &$params = array()
        ): string {
            $set = self::build_conditions($sql, $data, $params, null, true);
            return implode(",\n    ", $set);
        }

        /**
         * Function: build_insert_header
         * Creates an insert header.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $data - Data to insert.
         */
        public static function build_insert_header(
            $sql,
            $data
        ): string {
            $set = array();

            foreach (array_keys($data) as $field)
                array_push($set, self::safecol($sql, $field));

            return "(".implode(", ", $set).")";
        }

        /**
         * Function: build_limits
         * Creates the LIMIT part of a query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $offset - Offset of the result.
         *     $limit - Limit of the result.
         */
        public static function build_limits(
            $sql,
            $offset,
            $limit
        ): string {
            if ($limit === null)
                return "";

            if ($offset !== null)
                return "LIMIT ".$offset.", ".$limit;

            return "LIMIT ".$limit;
        }

        /**
         * Function: build_from
         * Creates a FROM header for select queries.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $tables - Tables to select from.
         */
        public static function build_from(
            $sql,
            $tables
        ): string {
            if (!is_array($tables))
                $tables = array($tables);

            foreach ($tables as &$table) {
                if (substr($table, 0, 2) != "__")
                    $table = "__".$table;

                $table = "\"".$table."\"";
            }

            return implode(",\n     ", $tables);
        }

        /**
         * Function: build_count
         * Creates a SELECT COUNT(1) query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $tables - Tables to tablefy with.
         *     $conds - Conditions to select by.
         *     &$params - An associative array of parameters used in the query.
         */
        public static function build_count(
            $sql,
            $tables,
            $conds,
            &$params = array()
        ): string {
            $query = "SELECT COUNT(1) AS count\n".
                     "FROM ".self::build_from($sql, $tables)."\n";

            if ($conds)
                $query.= "WHERE ".self::build_where($sql, $conds, $tables, $params);

            return $query;
        }

        /**
         * Function: build_select_header
         * Creates a SELECT fields header.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $fields - Columns to select.
         *     $tables - Tables to tablefy with.
         */
        public static function build_select_header(
            $sql,
            $fields,
            $tables = null
        ): string {
            if (!is_array($fields))
                $fields = array($fields);

            $tables = (array) $tables;

            foreach ($fields as &$field) {
                self::tablefy($sql, $field, $tables);
                $field = self::safecol($sql, $field);
            }

            return implode(",\n       ", $fields);
        }

        /**
         * Function: build_where
         * Creates a WHERE query.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $conds - Conditions to select by.
         *     $tables - Tables to tablefy with.
         *     &$params - An associative array of parameters used in the query.
         */
        public static function build_where(
            $sql,
            $conds,
            $tables = null,
            &$params = array()
        ): string {
            $conds = (array) $conds;
            $tables = (array) $tables;

            $conditions = self::build_conditions($sql, $conds, $params, $tables);

            return empty($conditions) ?
                "" :
                "(".implode(")\n  AND (", array_filter($conditions)).")" ;
        }

        /**
         * Function: build_group
         * Creates a GROUP BY argument.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $order - Columns to group by.
         *     $tables - Tables to tablefy with.
         */
        public static function build_group(
            $sql,
            $by,
            $tables = null
        ): string {
            $by = (array) $by;
            $tables = (array) $tables;

            foreach ($by as &$column) {
                self::tablefy($sql, $column, $tables);
                $column = self::safecol($sql, $column);
            }

            return implode(
                ",\n         ",
                array_unique(array_filter($by))
            );
        }

        /**
         * Function: build_order
         * Creates an ORDER BY argument.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $order - Columns to order by.
         *     $tables - Tables to tablefy with.
         */
        public static function build_order(
            $sql,
            $order,
            $tables = null
        ): string {
            $tables = (array) $tables;

            if (!is_array($order)) {
                $parts = array_map("trim", explode(",", $order));
                $order = array_diff(array_unique($parts), array(""));
            }

            foreach ($order as &$by) {
                self::tablefy($sql, $by, $tables);
                $by = self::safecol($sql, $by);
            }

            return implode(",\n         ", $order);
        }

        /**
         * Function: build_list
         * Creates a list of values.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $vals - An array of values.
         *     &$params - An associative array of parameters used in the query.
         */
        public static function build_list(
            $sql,
            $vals,
            $params = array()
        ): string {
            $return = array();

            foreach ($vals as $val) {
                if (is_object($val) and !$val instanceof Stringable)
                    $val = null;

                switch (gettype($val)) {
                    case "NULL":
                        $return[] = "NULL";
                        break;
                    case "boolean":
                        $return[] = (string) (int) $val;
                        break;
                    case "double":
                        $return[] = $sql->escape($val);
                        break;
                    case "integer":
                        $return[] = (string) $val;
                        break;
                    case "object":
                        $return[] = $sql->escape($val);
                        break;
                    case "string":
                        $return[] = isset($params[$val]) ?
                            $val :
                            $sql->escape($val) ;
                        break;
                }
            }

            return "(".implode(", ", $return).")";
        }

        /**
         * Function: build_conditions
         * Builds an associative array of SQL values into parameterized query strings.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $conds - Conditions.
         *     &$params - Parameters array to fill.
         *     $tables - If specified, conditions will be tablefied with these tables.
         *     $insert - Is this an insert/update query?
         */
        public static function build_conditions(
            $sql,
            $conds,
            &$params,
            $tables = null,
            $insert = false
        ): array {
            $conditions = array();

            # PostgreSQL: cast to text to enable LIKE operator.
            $text = ($sql->adapter == "pgsql") ? "::text" : "" ;

            # ESCAPE clause for LIKE.
            $escape = " ESCAPE '|'";

            foreach ($conds as $key => $val) {
                if (is_int($key)) {
                    # Full expression.
                    $cond = $val;
                } else {
                    # Key => Val expression.
                    if (is_string($val) and str_starts_with($val, ":")) {
                        $cond = self::safecol($sql, $key)." = ".$val;
                    } else {
                        if (is_object($val) and !$val instanceof Stringable)
                            $val = null;

                        if (is_bool($val))
                            $val = (int) $val;

                        if (is_float($val))
                            $val = (string) $val;

                        $uck = strtoupper($key);

                        if (substr($uck, -4) == " NOT") {
                            # Negation.
                            $key = self::safecol($sql, substr($key, 0, -4));
                            $param = self::paramify($key, $params);

                            if (is_array($val)) {
                                $cond = $key." NOT IN ".
                                        self::build_list($sql, $val, $params);
                            } elseif ($val === null) {
                                $cond = $key." IS NOT NULL";
                            } else {
                                $cond = $key." != ".$param;
                                $params[$param] = $val;
                            }
                        } elseif (substr($uck, -9) == " LIKE ALL" and is_array($val)) {
                            # multiple LIKE (AND).
                            $key = self::safecol($sql, substr($key, 0, -9));
                            $likes = array();

                            foreach ($val as $index => $match) {
                                $param = self::paramify($key."_".$index, $params);
                                $likes[] = $key.$text." LIKE ".$param.$escape;
                                $params[$param] = $match;
                            }

                            $cond = "(".implode(" AND ", $likes).")";
                        } elseif (substr($uck, -9) == " NOT LIKE" and is_array($val)) {
                            # multiple NOT LIKE.
                            $key = self::safecol($sql, substr($key, 0, -9));
                            $likes = array();

                            foreach ($val as $index => $match) {
                                $param = self::paramify($key."_".$index, $params);
                                $likes[] = $key.$text." NOT LIKE ".$param.$escape;
                                $params[$param] = $match;
                            }

                            $cond = "(".implode(" AND ", $likes).")";
                        } elseif (substr($uck, -5) == " LIKE" and is_array($val)) {
                            # multiple LIKE (OR).
                            $key = self::safecol($sql, substr($key, 0, -5));
                            $likes = array();

                            foreach ($val as $index => $match) {
                                $param = self::paramify($key."_".$index, $params);
                                $likes[] = $key.$text." LIKE ".$param.$escape;
                                $params[$param] = $match;
                            }

                            $cond = "(".implode(" OR ", $likes).")";
                        } elseif (substr($uck, -9) == " NOT LIKE") {
                            # NOT LIKE.
                            $key = self::safecol($sql, substr($key, 0, -9));
                            $param = self::paramify($key, $params);
                            $cond = $key.$text." NOT LIKE ".$param.$escape;
                            $params[$param] = $val;
                        } elseif (substr($uck, -5) == " LIKE") {
                            # LIKE.
                            $key = self::safecol($sql, substr($key, 0, -5));
                            $param = self::paramify($key, $params);
                            $cond = $key.$text." LIKE ".$param.$escape;
                            $params[$param] = $val;
                        } elseif (substr_count($key, " ")) {
                            # Custom operation, e.g. array("foo >" => $bar).
                            list($param,) = explode(" ", $key);
                            $param = self::paramify($param, $params);
                            $cond = self::safecol($sql, $key)." ".$param;
                            $params[$param] = $val;
                        } else {
                            # Equation.
                            if (is_array($val)) {
                                $cond = self::safecol($sql, $key)." IN ".
                                        self::build_list($sql, $val, $params);
                            } elseif ($val === null and $insert) {
                                $cond = self::safecol($sql, $key)." = ''";
                            } elseif ($val === null) {
                                $cond = self::safecol($sql, $key)." IS NULL";
                            } else {
                                $param = self::paramify($key, $params);
                                $cond = self::safecol($sql, $key)." = ".$param;

                                if ($insert) {
                                    if (
                                        $key == "updated_at" and
                                        is_datetime_zero($val)
                                    )
                                        $val = SQL_DATETIME_ZERO;
                                }

                                $params[$param] = $val;
                            }
                        }
                    }
                }

                if ($tables)
                    self::tablefy($sql, $cond, $tables);

                $conditions[] = $cond;
            }

            return $conditions;
        }

        /**
         * Function: paramify
         * Returns a unique parameter name for a parameterized query.
         *
         * Parameters:
         *     $name - The parameter name to be made unique.
         *     &$params - Parameters to compare for clashes.
         */
        public static function paramify(
            $name,
            &$params
        ): string {
            $name = str_replace(
                array("(", ")", "."),
                "_",
                $name
            );

            $count = 1;
            $unique = ":".$name;

            while (isset($params[$unique])) {
                $count++;
                $unique = ":".$name."_".$count;
            }

            return $unique;
        }

        /**
         * Function: tablefy
         * Prepends table names and prefixes to a field if it doesn't already have them.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     &$field - The field to "tablefy".
         *     $tables - An array of tables. The first one will be used for prepending.
         */
        public static function tablefy(
            $sql,
            &$field,
            $tables
        ): void {
            if (
                !preg_match_all(
                    "/(\(|[\s]+|^)(?!__)([a-z0-9_\.\*]+)(\)|[\s]+|$)/",
                    $field,
                    $matches
                )
            )
                return;

            foreach ($matches[0] as $index => $full) {
                $before = $matches[1][$index];
                $name   = $matches[2][$index];
                $after  = $matches[3][$index];

                if (is_numeric($name))
                    continue;

                # Does it not already have a table specified?
                if (!substr_count($full, ".")) {
                    # Don't replace things that are already either prefixed or parameterized.
                    $field = preg_replace(
                        "/([^\.:'\"_]|^)".preg_quote($full, "/")."/",
                        "\\1".$before."\"__".$tables[0]."\".".$name.$after,
                        $field,
                        1
                    );
                } else {
                    $field = preg_replace(
                        "/([^\.:'\"_]|^)".preg_quote($full, "/")."/",
                        "\\1".$before."\"__".str_replace(".", "\".", $name).$after,
                        $field,
                        1
                    );
                }
            }

            $field = preg_replace("/AS ([^ ]+)\./i", "AS ", $field);
        }

        /**
         * Function: safecol
         * Encloses a column name in quotes if it is a subset of SQL keywords.
         *
         * Parameters:
         *     $sql - The SQL instance calling this method.
         *     $name - Name of the column.
         */
        public static function safecol(
            $sql,
            $name
        ): string|array|null {
            $keywords = array(
                "between", "case", "check", "collate", "constraint", "create",
                "default", "delete", "distinct", "drop", "except", "from",
                "groups?", "having", "insert", "intersect", "into", "join",
                "left", "like", "limit", "null", "offset", "order", "primary",
                "references", "select", "set", "table", "transaction", "union",
                "unique", "update", "using", "values", "when", "where", "with"
            );

            $pattern = implode("|", $keywords);
            return preg_replace(
                "/(([^a-zA-Z0-9_]|^)($pattern)([^a-zA-Z0-9_]|$))/i",
                '\\2"\\3"\\4',
                $name
            );
        }
    }
