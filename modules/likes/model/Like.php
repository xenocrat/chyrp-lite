<?php
    /**
     * Class: Like
     * The model for the Likes SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Like extends Model {
        public $belongs_to = array("post", "user");

        /**
         * Function: __construct
         *
         * See Also:
         *     <Model::grab>
         */
        public function __construct(
            $like_id,
            $options = array()
        ) {
            parent::grab($this, $like_id, $options);

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
         * Adds a like to the database.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was liked.
         *     $user_id - The ID of the user who liked the post.
         *     $timestamp - The datetime when the like was added.
         *     $session_hash - The session hash of the visitor.
         *
         * Returns:
         *     The newly created <Like>.
         */
        public static function add(
            $post_id,
            $user_id,
            $timestamp,
            $session_hash
        ): self {
            $sql = SQL::current();

            $sql->insert(
                table:"likes",
                data:array(
                    "post_id"      => $post_id,
                    "user_id"      => $user_id,
                    "timestamp"    => $timestamp,
                    "session_hash" => $session_hash
                )
            );

            $new = new self($sql->latest("likes"));
            Trigger::current()->call("add_like", $new);
            return $new;
        }

        /**
         * Function: delete
         * Deletes a like from the database.
         *
         * See Also:
         *     <Model::destroy>
         */
        public static function delete(
            $like_id
        ): void {
            parent::destroy(self::class, $like_id);
        }

        /**
         * Function: deletable
         * Checks if the <User> can delete the like.
         */
        public function deletable(
            $user = null
        ): bool {
            if ($this->no_results)
                return false;

            $post = new Post($this->post_id);
            return $post->deletable($user);
        }

        /**
         * Function: editable
         * Checks if the <User> can edit the like.
         */
        public function editable(
            $user = null
        ): bool {
            if ($this->no_results)
                return false;

            $post = new Post($this->post_id);
            return $post->editable($user);
        }

        /**
         * Function: create
         * Creates a like and sets it in the visitor's session values.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was liked.
         */
        public static function create(
            $post_id
        ): void {
            if (self::exists($post_id))
                return;

            $new = self::add(
                post_id:$post_id,
                user_id:Visitor::current()->id,
                timestamp:datetime(),
                session_hash:self::session_hash()
            );

            $_SESSION['likes'][$post_id] = $new->id;
            Trigger::current()->call("like_post", $post_id);
        }

        /**
         * Function: remove
         * Removes a like and unsets it in the visitor's session values.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was unliked.
         *
         * Notes:
         *     Guests' likes are removable until the session is destroyed.
         */
        public static function remove(
            $post_id
        ): void {
            if (!self::exists($post_id))
                return;

            self::delete($_SESSION['likes'][$post_id]);

            unset($_SESSION['likes'][$post_id]);
            Trigger::current()->call("unlike_post", $post_id);
        }

        /**
         * Function: exists
         * Determines if the visitor has liked a post.
         */
        public static function exists(
            $post_id
        ): bool {
            static $results;
            fallback($_SESSION['likes'], array());

            if (logged_in() and !isset($results)) {
                $results = SQL::current()->select(
                    tables:"likes",
                    fields:array("id", "post_id"),
                    conds:array("user_id" => Visitor::current()->id),
                    order:"post_id ASC"
                )->fetchAll();

                foreach ($results as $result) {
                    $this_id = $result["id"];
                    $this_post_id = $result["post_id"];
                    $_SESSION['likes'][$this_post_id] = $this_id;
                }
            }

            return isset($_SESSION['likes'][$post_id]);
        }

        /**
         * Function: session_hash
         * Returns a hash generated from the visitor's ID and IP address.
         */
        private static function session_hash(
        ): string {
            return md5(session_id());
        }

        /**
         * Function: install
         * Creates the database table.
         */
        public static function install(
        ): void {
            SQL::current()->create(
                table:"likes",
                cols:array(
                    "id INTEGER PRIMARY KEY AUTO_INCREMENT",
                    "post_id INTEGER NOT NULL",
                    "user_id INTEGER NOT NULL",
                    "timestamp DATETIME DEFAULT NULL",
                    "session_hash VARCHAR(32) NOT NULL"
                )
            );
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        public static function uninstall(
        ): void {
            SQL::current()->drop("likes");
        }
    }
