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
         *
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
         *
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
         *
         * Returns:
         *     The newly created <View>.
         */
        static function add($post_id, $user_id, $created_at = null) {
            $sql = SQL::current();

            $sql->insert("views",
                         array("post_id"    => $post_id,
                               "user_id"    => $user_id,
                               "created_at" => oneof($created_at, datetime())));

            return new self($sql->latest("views"));
        }

        /**
         * Function: delete
         * Deletes a view from the database.
         *
         * See Also:
         *     <Model::destroy>
         */
        static function delete($view_id) {
            parent::destroy(get_class(), $view_id);
        }

        /**
         * Function: install
         * Creates the database table.
         */
        static function install() {
            SQL::current()->create("views",
                                   array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
                                         "post_id INTEGER NOT NULL",
                                         "user_id INTEGER DEFAULT 0",
                                         "created_at DATETIME DEFAULT NULL"));
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        static function uninstall() {
            SQL::current()->drop("views");
        }
    }
