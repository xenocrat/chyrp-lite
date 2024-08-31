<?php
    /**
     * Class: Model
     * The basis for the Models system.
     */
    class Model {
        # Array: $caches
        # Caches every loaded model into a clone of the object.
        private static $caches = array();

        # Array: $data
        # Stores dynamic attributes for the loaded model.
        private $data = array();

        # Boolean: $no_results
        # Did the query for this model return no results?
        public $no_results = false;

        # Array: $belongs_to
        # An array of models that this Model belongs to.
        # This model should have a [modelname]_id column.
        public $belongs_to = array();

        # Array: $has_many
        # An array of models that belong to this Model.
        # They should have a [thismodel]_id column.
        public $has_many = array();

        # Array: $has_one
        # An array of models that this model has only one of.
        # The models should have a [thismodel]_id column.
        public $has_one = array();

        /**
         * Function: __get
         * Handles model relationships, deferred and dynamic attributes.
         *
         * Returns:
         *     @mixed@
         */
        public function &__get($name): mixed {
            $trigger = Trigger::current();
            $model_name = strtolower(get_class($this));

            if (isset($this->data[$name]))
                return $this->data[$name];

            if ($trigger->exists($model_name."_".$name."_attr")) {
                $filtered = null;
                $trigger->filter($filtered, $model_name."_".$name."_attr", $this);
                $this->data[$name] = $filtered;
                return $this->data[$name];
            }

            $this->belongs_to = (array) $this->belongs_to;
            $this->has_many   = (array) $this->has_many;
            $this->has_one    = (array) $this->has_one;

            if (
                in_array($name, $this->belongs_to) or
                isset($this->belongs_to[$name])
            ) {
                if (isset($this->belongs_to[$name])) {
                    $opts =& $this->belongs_to[$name];

                    $model = isset($opts["model"]) ?
                        $opts["model"] :
                        $name ;

                    $match = isset($opts["by"]) ?
                        $opts["by"] :
                        strtolower($name) ;

                    fallback(
                        $opts["where"],
                        array("id" => $this->data[$match."_id"])
                    );

                    $opts["where"] = (array) $opts["where"];
                } else {
                    $model = $name;
                    $opts = array(
                        "where" => array("id" => $this->data[$name."_id"])
                    );
                }

                $this->data[$name] = new $model(null, $opts);
                return $this->data[$name];

            } elseif (
                in_array($name, $this->has_many) or
                isset($this->has_many[$name])
            ) {
                if (isset($this->has_many[$name])) {
                    $opts =& $this->has_many[$name];

                    $model = isset($opts["model"]) ?
                        $opts["model"] :
                        depluralize($name) ;

                    $match = isset($opts["by"]) ?
                        $opts["by"] :
                        strtolower($name) ;

                    fallback(
                        $opts["where"],
                        array($match."_id" => $this->data["id"])
                    );

                    $opts["where"] = (array) $opts["where"];
                } else {
                    $model = depluralize($name);
                    $match = ($model_name == "visitor") ? "user" : $model_name ;
                    $opts = array(
                        "where" => array($match."_id" => $this->data["id"])
                    );
                }

                $this->data[$name] = call_user_func(array($model, "find"), $opts);
                return $this->data[$name];

            } elseif (
                in_array($name, $this->has_one) or
                isset($this->has_one[$name])
            ) {
                if (isset($this->has_one[$name])) {
                    $opts =& $this->has_one[$name];

                    $model = isset($opts["model"]) ?
                        $opts["model"] :
                        depluralize($name) ;

                    $match = isset($opts["by"]) ?
                        $opts["by"] :
                        strtolower($name) ;

                    fallback(
                        $opts["where"],
                        array($match."_id" => $this->data["id"])
                    );

                    $opts["where"] = (array) $opts["where"];
                } else {
                    $model = depluralize($name);

                    $match = ($model_name == "visitor") ?
                        "user" :
                        $model_name ;

                    $opts = array(
                        "where" => array($match."_id" => $this->data["id"])
                    );
                }

                $this->data[$name] = new $model(null, $opts);
                return $this->data[$name];
            }

            $this->data[$name] = null;
            return $this->data[$name];
        }

        /**
         * Function __set
         * Handles dynamic attributes.
         */
        public function __set($name, $value): void {
            $this->data[$name] = $value;
        }

        /**
         * Function: __isset
         * Handles model relationships, deferred and dynamic attributes.
         */
        public function __isset($name): bool {
            $trigger = Trigger::current();
            $model_name = strtolower(get_class($this));

            if ($trigger->exists($model_name."_".$name."_attr"))
                return true;

            if (isset($this->data[$name]))
                return true;

            $this->belongs_to = (array) $this->belongs_to;
            $this->has_many   = (array) $this->has_many;
            $this->has_one    = (array) $this->has_one;

            if (
                in_array($name, $this->belongs_to) or
                isset($this->belongs_to[$name])
            )
                return true;

            if (
                in_array($name, $this->has_many) or
                isset($this->has_many[$name])
            )
                return true;

            if (
                in_array($name, $this->has_one) or
                isset($this->has_one[$name])
            )
                return true;

            return false;
        }

        /**
         * Function: grab
         * Grabs a single model from the database.
         *
         * Parameters:
         *     $model - The instantiated model class to pass the object to (e.g. Post).
         *     $id - The ID of the model to grab. Can be null.
         *     $options - An array of options, mostly SQL things.
         *
         * Options:
         *     select - What to grab from the table.
         *     from - Which table(s) to grab from?
         *     left_join - A @LEFT JOIN@ associative array.
         *     where - A string or array of conditions.
         *     params - An array of parameters to pass to the SQL driver.
         *     group - A string or array of "GROUP BY" conditions.
         *     order - What to order the SQL result by.
         *     offset - Offset for SQL query.
         *     read_from - An array to read from instead of performing another query.
         *     ignore_dupes - An array of columns in which duplicate values will be retained.
         */
        protected static function grab($model, $id, $options = array()): void {
            $model_name = strtolower(get_class($model));

            if ($model_name == "visitor")
                $model_name = "user";

            if (!isset($id) and isset($options["where"]["id"]))
                $id = $options["where"]["id"];

            $cache_id = (isset($id) and !is_numeric($id)) ?
                serialize($id) :
                $id ;

            # Return cached results if available.
            if (empty($options["read_from"])) {
                if (
                    isset($cache_id) and
                    isset(self::$caches[$model_name][$cache_id])
                ) {
                    foreach (self::$caches[$model_name][$cache_id] as $attr => $val)
                        $model->$attr = $val;

                    return;
                }
            }

            fallback($options["select"], "*");
            fallback(
                $options["from"],
                ($model_name == "visitor" ? "users" : pluralize($model_name))
            );
            fallback($options["left_join"], array());
            fallback($options["where"], array());
            fallback($options["params"], array());
            fallback($options["group"], array());
            fallback($options["order"], "id DESC");
            fallback($options["offset"], null);
            fallback($options["read_from"], array());
            fallback($options["ignore_dupes"], array());

            $options["where"] = (array) $options["where"];
            $options["from"] = (array) $options["from"];
            $options["select"] = (array) $options["select"];

            if (is_numeric($id)) {
                $options["where"]["id"] = $id;
            } elseif (is_array($id)) {
                $options["where"] = array_merge($options["where"], $id);
            }

            $sql = SQL::current();
            $trigger = Trigger::current();

            $trigger->filter($options, $model_name."_grab");

            if (!empty($options["read_from"])) {
                $read = $options["read_from"];
            } else {
                $query = $sql->select(
                    tables:$options["from"],
                    fields:$options["select"],
                    conds:$options["where"],
                    order:$options["order"],
                    params:$options["params"],
                    offset:$options["offset"],
                    group:$options["group"],
                    left_join:$options["left_join"]
                );
                $all = $query->fetchAll();

                if (count($all) == 1) {
                    $read = $all[0];
                } else {
                    $merged = array();

                    foreach ($all as $index => $row) {
                        foreach ($row as $column => $val)
                            $merged[$row["id"]][$column][] = $val;
                    }

                    foreach ($all as $index => &$row)
                        $row = $merged[$row["id"]];

                    if (count($all)) {
                        $keys = array_keys($all);
                        $read = $all[$keys[0]];

                        foreach ($read as $name => &$column) {
                            if (!in_array($name, $options["ignore_dupes"]))
                                $column = array_unique($column);

                            if (count($column) == 1)
                                $column = $column[0];
                        }
                    } else {
                        $read = false;
                    }
                }
            }

            if (!$read or !count($read)) {
                $model->no_results = true;
                return;
            } else {
                $model->no_results = false;
            }

            foreach ($read as $key => $val) {
                if (!is_int($key))
                    $model->$key = $val;
            }

            if (isset($query) and isset($query->queryString))
                $model->queryString = $query->queryString;

            if (isset($model->updated_at))
                $model->updated = (
                    !empty($model->updated_at) and
                    !is_datetime_zero($model->updated_at)
                );

            # Clone the object and cache it.
            $clone = clone $model;
            self::$caches[$model_name][$read["id"]] = $clone;

            if (isset($id) and !is_numeric($id))
                self::$caches[$model_name][$cache_id] = $clone;
        }

        /**
         * Function: search
         * Returns an array of model objects that are found by the $options array.
         *
         * Parameters:
         *     $options - An array of options, mostly SQL things.
         *     $options_for_object - An array of options for the instantiation of the model.
         *
         * Options:
         *     select - What to grab from the table.
         *     from - Which table(s) to grab from?
         *     left_join - A @LEFT JOIN@ associative array.
         *     where - A string or array of conditions.
         *     params - An array of parameters to pass to the SQL driver.
         *     group - A string or array of "GROUP BY" conditions.
         *     order - What to order the SQL result by.
         *     offset - Offset for SQL query.
         *     limit - Limit for SQL query.
         *     placeholders - Return an array of arrays instead of an array of objects?
         *     ignore_dupes - An array of columns in which duplicate values will be retained.
         *
         * See Also:
         *     <Model.grab>
         */
        protected static function search(
            $model,
            $options = array(),
            $options_for_object = array()
        ): array {
            $trigger = Trigger::current();
            $model_name = strtolower($model);

            fallback($options["select"], "*");
            fallback($options["from"], pluralize(strtolower($model)));
            fallback($options["left_join"], array());
            fallback($options["where"], null);
            fallback($options["params"], array());
            fallback($options["group"], array());
            fallback($options["order"], "id DESC");
            fallback($options["offset"], null);
            fallback($options["limit"], null);
            fallback($options["placeholders"], false);
            fallback($options["ignore_dupes"], array());

            $options["where"]  = (array) $options["where"];
            $options["from"]   = (array) $options["from"];
            $options["select"] = (array) $options["select"];

            $trigger->filter($options, pluralize(strtolower($model_name))."_get");

            $grab = SQL::current()->select(
                tables:$options["from"],
                fields:$options["select"],
                conds:$options["where"],
                order:$options["order"],
                params:$options["params"],
                limit:$options["limit"],
                offset:$options["offset"],
                group:$options["group"],
                left_join:$options["left_join"]
            )->fetchAll();

            $results = array();
            $rows = array();

            foreach ($grab as $row) {
                foreach ($row as $column => $val)
                    $rows[$row["id"]][$column][] = $val;
            }

            foreach ($rows as &$row) {
                foreach ($row as $name => &$column) {
                    if (!in_array($name, $options["ignore_dupes"]))
                        $column = array_unique($column);

                    if (count($column) == 1)
                        $column = $column[0];
                }
            }

            foreach ($rows as $result) {
                if ($options["placeholders"]) {
                    $results[] = $result;
                    continue;
                }

                $options_for_object["read_from"] = $result;
                $result = new $model(null, $options_for_object);
                $results[] = $result;
            }

            return ($options["placeholders"]) ?
                array($results, $model_name) :
                $results ;
        }

        /**
         * Function: delete
         * Deletes a given object.
         *
         * Parameters:
         *     $model - The model name.
         *     $id - The ID of the object to delete.
         *     $options_for_object - An array of options for the instantiation of the model.
         */
        protected static function destroy(
            $model,
            $id,
            $options_for_object = array()
        ): void {
            $model = strtolower($model);
            $trigger = Trigger::current();

            if ($trigger->exists("delete_".$model))
                $trigger->call("delete_".$model, new $model($id, $options_for_object));

            SQL::current()->delete(
                table:pluralize($model),
                conds:array("id" => $id)
            );
        }

        /**
         * Function: deletable
         * Checks if the <User> can delete the object.
         */
        public function deletable($user = null): bool {
            if ($this->no_results)
                return false;

            $name = strtolower(get_class($this));

            fallback($user, Visitor::current());
            return $user->group->can("delete_".$name);
        }

        /**
         * Function: editable
         * Checks if the <User> can edit the object.
         */
        public function editable($user = null): bool {
            if ($this->no_results)
                return false;

            $name = strtolower(get_class($this));

            fallback($user, Visitor::current());
            return $user->group->can("edit_".$name);
        }

        /**
         * Function: edit_link
         * Outputs an edit link for the model, if the visitor's <Group.can> edit_[model].
         *
         * Parameters:
         *     $text - The text to show for the link.
         *     $before - If the link can be shown, show this before it.
         *     $after - If the link can be shown, show this after it.
         *     $classes - Extra CSS classes for the link, space-delimited.
         */
        public function edit_link(
            $text = null,
            $before = null,
            $after = null,
            $classes = ""
        ): void {
            if (!$this->editable())
                return;

            fallback($text, __("Edit"));

            $name = strtolower(get_class($this));

            $url = url(
                "edit_".$name."/id/".$this->id,
                AdminController::current()
            );

            $classes = $classes.' '.$name.'_edit_link edit_link';

            echo $before.'<a href="'.$url.'" class="'.fix(trim($classes), true).
                 '" id="'.$name.'_edit_'.$this->id.'">'.$text.'</a>'.$after;
        }

        /**
         * Function: delete_link
         * Outputs a delete link for the post, if the <User.can> delete_[model].
         *
         * Parameters:
         *     $text - The text to show for the link.
         *     $before - If the link can be shown, show this before it.
         *     $after - If the link can be shown, show this after it.
         *     $classes - Extra CSS classes for the link, space-delimited.
         */
        public function delete_link(
            $text = null,
            $before = null,
            $after = null,
            $classes = ""
        ): void {
            if (!$this->deletable())
                return;

            fallback($text, __("Delete"));

            $name = strtolower(get_class($this));

            $url = url(
                "delete_".$name."/id/".$this->id,
                AdminController::current()
            );

            $classes = $classes.' '.$name.'_delete_link delete_link';

            echo $before.'<a href="'.$url.'" class="'.fix(trim($classes), true).
                 '" id="'.$name.'_delete_'.$this->id.'">'.$text.'</a>'.$after;
        }

        /**
         * Function: etag
         * Generates an Etag for the object.
         */
        public function etag(): string|false {
            if ($this->no_results)
                return false;

            $array = array(get_class($this), $this->id);

            if (isset($this->created_at))
                $array[] = $this->created_at;

            if (isset($this->updated_at))
                $array[] = $this->updated_at;

            return (isset($this->updated_at) ? '"' : 'W/"').token($array).'"';
        }
    }
