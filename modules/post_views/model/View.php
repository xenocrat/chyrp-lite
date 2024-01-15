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
                return;
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
            return parent::search(
                self::class,
                $options,
                $options_for_object
            );
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
        public static function add($post_id, $user_id, $created_at = null): self {
            $sql = SQL::current();

            $sql->insert(
                table:"views",
                data:array(
                    "post_id"    => $post_id,
                    "user_id"    => $user_id,
                    "created_at" => oneof($created_at, datetime())
                )
            );

            return new self($sql->latest("views"));
        }

        /**
         * Function: delete
         * Deletes a view from the database.
         *
         * See Also:
         *     <Model::destroy>
         */
        public static function delete($view_id): void {
            parent::destroy(self::class, $view_id);
        }

        /**
         * Function: install
         * Creates the database table.
         */
        public static function install(): void {
            SQL::current()->create(
                table:"views",
                cols:array(
                    "id INTEGER PRIMARY KEY AUTO_INCREMENT",
                    "post_id INTEGER NOT NULL",
                    "user_id INTEGER DEFAULT 0",
                    "created_at DATETIME DEFAULT NULL"
                )
            );
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        public static function uninstall(): void {
            SQL::current()->drop("views");
        }
    }
