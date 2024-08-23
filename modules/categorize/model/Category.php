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
                return;

            $this->url = url(
                "category/".urlencode($this->clean),
                MainController::current()
            );
        }

        /**
         * Function: find
         *
         * See Also:
         *     <Model::search>
         */
        public static function find(
            $options = array(),
            $options_for_object = array()
        ): array {
            $options["from"] = "categorize";
            return parent::search(
                self::class,
                $options,
                $options_for_object
            );
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
        public static function add($name, $clean, $show_on_home): self {
            $sql = SQL::current();

            $sql->insert(
                table:"categorize",
                data:array(
                    "name"         => strip_tags($name),
                    "clean"        => $clean,
                    "show_on_home" => $show_on_home
                )
            );

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
        public function update($name, $clean, $show_on_home): self|false {
            if ($this->no_results)
                return false;

            $new_values = array(
                "name"         => strip_tags($name),
                "clean"        => $clean,
                "show_on_home" => $show_on_home
            );

            SQL::current()->update(
                table:"categorize",
                conds:array("id" => $this->id),
                data:$new_values
            );

            $category = new self(
                null,
                array(
                    "read_from" => array_merge(
                        $new_values,
                        array("id" => $this->id)
                    )
                )
            );

            Trigger::current()->call("update_category", $category, $this);
            return $category;
        }

        /**
         * Function: delete
         * Deletes a category from the database.
         */
        public static function delete($category_id): void {
            $trigger = Trigger::current();
            $sql = SQL::current();

            if ($trigger->exists("delete_category")) {
                $category = new self($category_id);
                $trigger->call("delete_category", $category);
            }

            $sql->delete(
                table:"categorize",
                conds:array("id" => $category_id)
            );

            $sql->delete(
                table:"post_attributes",
                conds:array(
                    "name" => "category_id",
                    "value" => $category_id
                )
            );
        }

        /**
         * Function: deletable
         * Checks if the <User> can delete the category.
         */
        public function deletable($user = null): bool {
            if ($this->no_results)
                return false;

            fallback($user, Visitor::current());
            return $user->group->can("manage_categorize");
        }

        /**
         * Function: editable
         * Checks if the <User> can edit the category.
         */
        public function editable($user = null): bool {
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
        public static function check_clean($clean): string {
            if (empty($clean))
                return $clean;

            $count = 1;
            $unique = substr($clean, 0, 128);

            while (
                SQL::current()->count(
                    tables:"categorize",
                    conds:array("clean" => $unique)
                )
            ) {
                $count++;
                $unique = substr($clean, 0, (127 - strlen($count))).
                          "-".
                          $count;
            }

            return $unique;
        }

        /**
         * Function: install
         * Creates the database table.
         */
        public static function install(): void {
            SQL::current()->create(
                table:"categorize",
                cols:array(
                    "id INTEGER PRIMARY KEY AUTO_INCREMENT",
                    "name  VARCHAR(128) NOT NULL",
                    "clean VARCHAR(128) NOT NULL UNIQUE",
                    "show_on_home BOOLEAN DEFAULT '1'"
                )
            );
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        public static function uninstall(): void {
            $sql = SQL::current();

            $sql->drop("categorize");
            $sql->delete(
                table:"post_attributes",
                conds:array("name" => "category_id")
            );
        }
    }
