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
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            $options["from"] = "categorize";
            return parent::search(get_class(), $options, $options_for_object);
        }

        static function add($name, $clean, $show_on_home) {
            $sql = SQL::current();

            $sql->insert("categorize",
                         array("name" => $name,
                               "clean" => $clean,
                               "show_on_home" => $show_on_home));

            return new self($sql->latest("categorize"));
        }

        public function update($name, $clean, $show_on_home) {
            $url = url("category/".$clean, MainController::current());

            # Update all values of this category.
            foreach (array("name", "clean", "show_on_home", "url") as $attr)
                $this->$attr = $$attr;

            SQL::current()->update("categorize",
                                   array("id" => $this->id),
                                   array("name" => $name,
                                         "clean" => $clean,
                                         "show_on_home" => $show_on_home));
        }

        static function delete($category_id) {
            $sql = SQL::current();

            $sql->delete("categorize",
                         array("id" => $category_id));

            $sql->delete("post_attributes",
                         array("name" => "category_id",
                               "value" => $category_id));
        }

        /**
         * Function: check_clean
         * Checks if a given clean URL is already being used as another category's URL.
         *
         * Parameters:
         *     $clean - The clean URL to check.
         *
         * Returns:
         *     The unique version of the passed clean URL.
         *     If it's not used, it's the same as $clean. If it is, a number is appended.
         */
        static function check_clean($clean) {
            $count = SQL::current()->count("categorize", array("clean" => $clean));
            return (!$count or empty($clean)) ? $clean : $clean."-".($count + 1) ;
        }

        static function install() {
            SQL::current()->query("CREATE TABLE IF NOT EXISTS __categorize (
                                      id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                      name  VARCHAR(128) NOT NULL,
                                      clean VARCHAR(128) NOT NULL UNIQUE,
                                      show_on_home BOOLEAN DEFAULT '1'
                                  ) DEFAULT CHARSET=UTF8");
        }

        static function uninstall() {
            $sql = SQL::current();

            $sql->query("DROP TABLE __categorize");
            $sql->delete("post_attributes", array("name" => "category_id"));
        }
    }
