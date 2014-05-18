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
        public static function build_select($tables,
                                            $fields,
                                            $conds,
                                            $order = null,
                                            $limit = null,
                                            $offset = null,
                                            $group = null,
                                            $left_join = array(),
                                            &$params = array()) {
            $query = "SELECT ".self::build_select_header($fields, $tables)."\n".
                     "FROM ".self::build_from($tables)."\n";

            foreach ($left_join as $join)
                $query.= "LEFT JOIN __".$join["table"]." ON ".self::build_where($join["where"], $join["table"], $params)."\n";

            $query.= ($conds ? "WHERE ".self::build_where($conds, $tables, $params)."\n" : "").
                     ($group ? "GROUP BY ".self::build_group($group, $tables)."\n" : "").
                     ($order ? "ORDER BY ".self::build_order($order, $tables)."\n" : "").
                     self::build_limits($offset, $limit);

            return $query;
        }

        /**
         * Function: build_insert
         * Creates a full insert query.
         *
         * Parameters:
         *     $table - Table to insert into.
         *     $data - Data to insert.
         *     &$params - An associative array of parameters used in the query.
         *
         * Returns:
         *     An @INSERT@ query string.
         */
        public static function build_insert($table, $data, &$params = array()) {
            if (empty($params))
                foreach ($data as $key => $val)
                    $params[":".str_replace(array("(", ")", "."), "_", $key)] = $val;

            return "INSERT INTO __$table\n".
                   self::build_insert_header($data)."\n".
                   "VALUES\n".
                   "(".implode(", ", array_keys($params)).")\n";
        }

        /**
         * Function: build_update
         * Creates a full update query.
         *
         * Parameters:
         *     $table - Table to update.
         *     $conds - Conditions to update rows by.
         *     $data - Data to update.
         *     &$params - An associative array of parameters used in the query.
         *
         * Returns:
         *     An @UPDATE@ query string.
         */
        public static function build_update($table, $conds, $data, &$params = array()) {
            return "UPDATE __$table\n".
                   "SET ".self::build_update_values($data, $params)."\n".
                   ($conds ? "WHERE ".self::build_where($conds, $table, $params) : "");
        }

        /**
         * Function: build_delete
         * Creates a full delete query.
         *
         * Parameters:
         *     $table - Table to delete from.
         *     $conds - Conditions to delete by.
         *     &$params - An associative array of parameters used in the query.
         *
         * Returns:
         *     A @DELETE@ query string.
         */
        public static function build_delete($table, $conds, &$params = array()) {
            return "DELETE FROM __$table\n".
                   ($conds ? "WHERE ".self::build_where($conds, $table, $params) : "");
        }

        /**
         * Function: build_update_values
         * Creates an update data part.
         *
         * Parameters:
         *     $data - Data to update.
         *     &$params - An associative array of parameters used in the query.
         */
        public static function build_update_values($data, &$params = array()) {
            $set = self::build_conditions($data, $params, null, true);
            return implode(",\n    ", $set);
        }

        /**
         * Function: build_insert_header
         * Creates an insert header.
         *
         * Parameters:
         *     $data - Data to insert.
         */
        public static function build_insert_header($data) {
            $set = array();

            foreach (array_keys($data) as $field)
                array_push($set, self::safecol($field));

            return "(".implode(", ", $set).")";
        }

        /**
         * Function: build_limits
         * Creates the LIMIT part of a query.
         *
         * Parameters:
         *     $offset - Offset of the result.
         *     $limit - Limit of the result.
         */
        public static function build_limits($offset, $limit) {
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
         *     $tables - Tables to select from.
         */
        public static function build_from($tables) {
            if (!is_array($tables))
                $tables = array($tables);

            foreach ($tables as &$table)
                if (substr($table, 0, 2) != "__")
                    $table = "__".$table;

            return implode(",\n     ", $tables);
        }

        /**
         * Function: build_count
         * Creates a SELECT COUNT(1) query.
         *
         * Parameters:
         *     $tables - Tables to tablefy with.
         *     $conds - Conditions to select by.
         *     &$params - An associative array of parameters used in the query.
         */
        public static function build_count($tables, $conds, &$params = array()) {
            return "SELECT COUNT(1) AS count\n".
                   "FROM ".self::build_from($tables)."\n".
                   ($conds ? "WHERE ".self::build_where($conds, $tables, $params) : "");
        }

        /**
         * Function: build_select_header
         * Creates a SELECT fields header.
         *
         * Parameters:
         *     $fields - Columns to select.
         *     $tables - Tables to tablefy with.
         */
        public static function build_select_header($fields, $tables = null) {
            if (!is_array($fields))
                $fields = array($fields);

            $tables = (array) $tables;

            foreach ($fields as &$field) {
                self::tablefy($field, $tables);
                $field = self::safecol($field);
            }

            return implode(",\n       ", $fields);
        }

        /**
         * Function: build_where
         * Creates a WHERE query.
         */
        public static function build_where($conds, $tables = null, &$params = array()) {
            $conds = (array) $conds;
            $tables = (array) $tables;

            $conditions = self::build_conditions($conds, $params, $tables);

            return (empty($conditions)) ? "" : "(".implode(")\n  AND (", array_filter($conditions)).")";
        }

        /**
         * Function: build_group
         * Creates a GROUP BY argument.
         *
         * Parameters:
         *     $order - Columns to group by.
         *     $tables - Tables to tablefy with.
         */
        public static function build_group($by, $tables = null) {
            $by = (array) $by;
            $tables = (array) $tables;

            foreach ($by as &$column) {
                self::tablefy($column, $tables);
                $column = self::safecol($column);
            }

            return implode(",\n         ", array_unique(array_filter($by)));
        }

        /**
         * Function: build_order
         * Creates an ORDER BY argument.
         *
         * Parameters:
         *     $order - Columns to order by.
         *     $tables - Tables to tablefy with.
         */
        public static function build_order($order, $tables = null) {
            $tables = (array) $tables;

            if (!is_array($order))
                $order = comma_sep($order);

            foreach ($order as &$by) {
                self::tablefy($by, $tables);
                $by = self::safecol($by);
            }

            return implode(",\n         ", $order);
        }

        /**
         * Function: build_list
         * Returns ('one', 'two', '', 1, 0) from array("one", "two", null, true, false)
         */
        public static function build_list($vals, $params = array()) {
            $return = array();

            foreach ($vals as $val) {
                if (is_object($val)) # Useful catch, e.g. empty SimpleXML objects.
                    $val = "";

                $return[] = (isset($params[$val])) ? $val : SQL::current()->escape($val) ;
            }

            return "(".join(", ", $return).")";
        }

        /**
         * Function: safecol
         * Wraps a column in proper escaping if it is a SQL keyword.
         *
         * Doesn't check every keyword, just the common/sensible ones.
         *
         * ...Okay, it only does two. "order" and "group".
         *
         * Parameters:
         *     $name - Name of the column.
         */
        public static function safecol($name) {
            return preg_replace("/(([^a-zA-Z0-9_]|^)(order|group)([^a-zA-Z0-9_]|$))/i",
                                (SQL::current()->adapter == "mysql") ? "\\2`\\3`\\4" : '\\2"\\3"\\4',
                                $name);
        }

        /**
         * Function: build_conditions
         * Builds an associative array of SQL values into PDO-esque paramized query strings.
         *
         * Parameters:
         *     $conds - Conditions.
         *     &$params - Parameters array to fill.
         *     $tables - If specified, conditions will be tablefied with these tables.
         *     $insert - Is this an insert/update query?
         */
        public static function build_conditions($conds, &$params, $tables = null, $insert = false) {
            $conditions = array();

            foreach ($conds as $key => $val) {
                if (is_int($key)) # Full expression
                    $cond = $val;
                else { # Key => Val expression
                    if (is_string($val) and strlen($val) and $val[0] == ":")
                        $cond = self::safecol($key)." = ".$val;
                    else {
                        if (is_bool($val))
                            $val = (int) $val;

                        if (substr($key, -4) == " not") { # Negation
                            $key = self::safecol(substr($key, 0, -4));
                            $param = str_replace(array("(", ")", "."), "_", $key);
                            if (is_array($val))
                                $cond = $key." NOT IN ".self::build_list($val, $params);
                            elseif ($val === null)
                                $cond = $key." IS NOT NULL";
                            else {
                                $cond = $key." != :".$param;
                                $params[":".$param] = $val;
                            }
                        } elseif (substr($key, -5) == " like" and is_array($val)) { # multiple LIKE
                            $key = self::safecol(substr($key, 0, -5));

                            $likes = array();
                            foreach ($val as $index => $match) {
                                $param = str_replace(array("(", ")", "."), "_", $key)."_".$index;
                                $likes[] = $key." LIKE :".$param;
                                $params[":".$param] = $match;
                            }

                            $cond = "(".implode(" OR ", $likes).")";
                        } elseif (substr($key, -9) == " like all" and is_array($val)) { # multiple LIKE
                            $key = self::safecol(substr($key, 0, -9));

                            $likes = array();
                            foreach ($val as $index => $match) {
                                $param = str_replace(array("(", ")", "."), "_", $key)."_".$index;
                                $likes[] = $key." LIKE :".$param;
                                $params[":".$param] = $match;
                            }

                            $cond = "(".implode(" AND ", $likes).")";
                        } elseif (substr($key, -9) == " not like" and is_array($val)) { # multiple NOT LIKE
                            $key = self::safecol(substr($key, 0, -9));

                            $likes = array();
                            foreach ($val as $index => $match) {
                                $param = str_replace(array("(", ")", "."), "_", $key)."_".$index;
                                $likes[] = $key." NOT LIKE :".$param;
                                $params[":".$param] = $match;
                            }

                            $cond = "(".implode(" AND ", $likes).")";
                        } elseif (substr($key, -5) == " like") { # LIKE
                            $key = self::safecol(substr($key, 0, -5));
                            $param = str_replace(array("(", ")", "."), "_", $key);
                            $cond = $key." LIKE :".$param;
                            $params[":".$param] = $val;
                        } elseif (substr($key, -9) == " not like") { # NOT LIKE
                            $key = self::safecol(substr($key, 0, -9));
                            $param = str_replace(array("(", ")", "."), "_", $key);
                            $cond = $key." NOT LIKE :".$param;
                            $params[":".$param] = $val;
                        } elseif (substr_count($key, " ")) { # Custom operation, e.g. array("foo >" => $bar)
                            list($param,) = explode(" ", $key);
                            $param = str_replace(array("(", ")", "."), "_", $param);
                            $cond = self::safecol($key)." :".$param;
                            $params[":".$param] = $val;
                        } else { # Equation
                            if (is_array($val))
                                $cond = self::safecol($key)." IN ".self::build_list($val, $params);
                            elseif ($val === null and $insert)
                                $cond = self::safecol($key)." = ''";
                            elseif ($val === null)
                                $cond = self::safecol($key)." IS NULL";
                            else {
                                $param = str_replace(array("(", ")", "."), "_", $key);
                                $cond = self::safecol($key)." = :".$param;
                                $params[":".$param] = $val;
                            }
                        }
                    }
                }

                if ($tables)
                    self::tablefy($cond, $tables);

                $conditions[] = $cond;
            }

            return $conditions;
        }

        /**
         * Function: tablefy
         * Automatically prepends tables and table prefixes to a field if it doesn't already have them.
         *
         * Parameters:
         *     &$field - The field to "tablefy".
         *     $tables - An array of tables. The first one will be used for prepending.
         */
        public static function tablefy(&$field, $tables) {
            if (!preg_match_all("/(\(|[\s]+|^)(?!__)([a-z0-9_\.\*]+)(\)|[\s]+|$)/", $field, $matches))
                return $field = str_replace("`", "", $field); # Method for bypassing the prefixer.

            foreach ($matches[0] as $index => $full) {
                $before = $matches[1][$index];
                $name   = $matches[2][$index];
                $after  = $matches[3][$index];

                if (is_numeric($name))
                    continue;

                # Does it not already have a table specified?
                if (!substr_count($full, ".")) {
                                           # Don't replace things that are already either prefixed or paramized.
                    $field = preg_replace("/([^\.:'\"_]|^)".preg_quote($full, "/")."/",
                                          "\\1".$before."__".$tables[0].".".$name.$after,
                                          $field,
                                          1);
                } else {
                    # Okay, it does, but is the table prefixed?
                    if (substr($full, 0, 2) != "__") {
                                               # Don't replace things that are already either prefixed or paramized.
                        $field = preg_replace("/([^\.:'\"_]|^)".preg_quote($full, "/")."/",
                                              "\\1".$before."__".$name.$after,
                                              $field,
                                              1);
                    }
                }
            }

            $field = preg_replace("/AS ([^ ]+)\./i", "AS ", $field);
        }
    }


