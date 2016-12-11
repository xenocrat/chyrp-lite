<?php
    /**
     * Class: View
     * The model for the Views SQL table.
     *
     * See Also:
     *     <Model>
     */
    class View extends Model {
        public $belongs_to = "post";

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($view_id, $options = array()) {
            parent::grab($this, $view_id, $options);

            if ($this->no_results)
                return false;
        }

        /**
         * Function: find
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: add
         * Adds a view to the database.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was viewed.
         *     $user_id - The ID of the user who viewed the post.
         *     $created_at - The new view's @created_at@ timestamp.
         */
        static function add($post_id, $user_id, $created_at = null) {
            $sql = SQL::current();

            $sql->insert("views",
                         array("post_id"    => (int) $post_id,
                               "user_id"    => (int) $user_id,
                               "created_at" => oneof($created_at, datetime())));

            return new self($sql->latest("views"));
        }

        static function delete($view_id) {
            SQL::current()->delete("views", array("id" => $view_id));
        }

        static function install() {
            SQL::current()->query("CREATE TABLE IF NOT EXISTS __views (
                                       id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                       post_id INTEGER NOT NULL,
                                       user_id INTEGER DEFAULT 0,
                                       created_at DATETIME DEFAULT NULL
                                   ) DEFAULT CHARSET=utf8");
        }

        static function uninstall() {
            SQL::current()->query("DROP TABLE __views");
        }
    }
