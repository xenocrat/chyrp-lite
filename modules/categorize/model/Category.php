<?php
    /**
     * Class: Category
     * The model for the Categorize SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Category extends Model {
        /**
         * Function: __construct
         *
         * See Also:
         *     <Model::grab>
         */
        public function __construct($category_id, $options = array()) {
            $options["from"] = "categorize";
            parent::grab($this, $category_id, $options);

            if ($this->no_results)
                return false;

            $this->url = url("category/".$this->clean, MainController::current());
        }

        /**
         * Function: find
         *
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            $options["from"] = "categorize";
            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: add
         * Adds a category to the database.
         *
         * Parameters:
         *     $name - The display name for this category.
         *     $clean - The unique slug for this category.
         *     $show_on_home - Show in the categories list?
         *
         * Returns:
         *     The newly created <Category>.
         *
         * See Also:
         *     <update>
         */
        static function add($name, $clean, $show_on_home) {
            $sql = SQL::current();

            $sql->insert("categorize",
                         array("name"         => strip_tags($name),
                               "clean"        => $clean,
                               "show_on_home" => $show_on_home));

            $new = new self($sql->latest("categorize"));
            Trigger::current()->call("add_category", $new);
            return $new;
        }

        /**
         * Function: update
         * Updates a category with the given attributes.
         *
         * Parameters:
         *     $name - The display name for this category.
         *     $clean - The unique slug for this category.
         *     $show_on_home - Show in the categories list?
         *
         * Returns:
         *     The updated <Category>.
         */
        public function update($name, $clean, $show_on_home) {
            if ($this->no_results)
                return false;

            $new_values = array("name"         => strip_tags($name),
                                "clean"        => $clean,
                                "show_on_home" => $show_on_home);

            SQL::current()->update("categorize",
                                   array("id" => $this->id),
                                   $new_values);

            $category = new self(null,
                                 array("read_from" => array_merge($new_values,
                                                      array("id" => $this->id))));

            Trigger::current()->call("update_category", $category, $this);

            return $category;
        }

        /**
         * Function: delete
         * Deletes a category from the database.
         */
        static function delete($category_id) {
            $trigger = Trigger::current();
            $sql = SQL::current();

            if ($trigger->exists("delete_category")) {
                $category = new self($category_id);
                $trigger->call("delete_category", $category);
            }

            $sql->delete("categorize",
                         array("id" => $category_id));

            $sql->delete("post_attributes",
                         array("name" => "category_id",
                               "value" => $category_id));
        }

        /**
         * Function: deletable
         * Checks if the <User> can delete the category.
         */
        public function deletable($user = null) {
            if ($this->no_results)
                return false;

            fallback($user, Visitor::current());
            return $user->group->can("manage_categorize");
        }

        /**
         * Function: editable
         * Checks if the <User> can edit the category.
         */
        public function editable($user = null) {
            if ($this->no_results)
                return false;

            fallback($user, Visitor::current());
            return $user->group->can("manage_categorize");
        }

        /**
         * Function: check_clean
         * Checks if a given slug is already being used as another category's slug.
         *
         * Parameters:
         *     $clean - The slug to check.
         *
         * Returns:
         *     The unique version of the slug.
         *     If it's not used, it's the same as $clean. If it is, a number is appended.
         */
        static function check_clean($clean) {
            if (empty($clean))
                return $clean;

            $count = 1;
            $unique = substr($clean, 0, 128);

            while (SQL::current()->count("categorize", array("clean" => $unique))) {
                $count++;
                $unique = substr($clean, 0, (127 - strlen($count)))."-".$count;
            }

            return $unique;
        }

        /**
         * Function: install
         * Creates the database table.
         */
        static function install() {
            SQL::current()->create("categorize",
                                   array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
                                         "name  VARCHAR(128) NOT NULL",
                                         "clean VARCHAR(128) NOT NULL UNIQUE",
                                         "show_on_home BOOLEAN DEFAULT '1'"));
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        static function uninstall() {
            $sql = SQL::current();

            $sql->drop("categorize");
            $sql->delete("post_attributes", array("name" => "category_id"));
        }
    }
