<?php
    /**
     * Class: Pingback
     * The model for the Pingbacks SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Pingback extends Model {
        public $belongs_to = "post";

        /**
         * Function: __construct
         *
         * See Also:
         *     <Model::grab>
         */
        public function __construct($pingback_id, $options = array()) {
            parent::grab($this, $pingback_id, $options);

            if ($this->no_results)
                return;
        }

        /**
         * Function: find
         *
         * See Also:
         *     <Model::search>
         */
        static function find(
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
         * Adds a pingback to the database.
         *
         * Parameters:
         *     $post_id - The ID of our blog post that was pinged.
         *     $source - The URL of the blog post that pinged us.
         *     $title - The title of the blog post that pinged us.
         *     $created_at - The pingback creation date (optional).
         *
         * Returns:
         *     The newly created <Pingback>.
         */
        static function add($post_id, $source, $title, $created_at = null): self {
            $sql = SQL::current();

            $sql->insert(
                table:"pingbacks",
                data:array(
                    "post_id"    => $post_id,
                    "source"     => $source,
                    "title"      => strip_tags($title),
                    "created_at" => oneof($created_at, datetime())
                )
            );

            $new = new self($sql->latest("pingbacks"));
            Trigger::current()->call("add_pingback", $new);
            return $new;
        }

        /**
         * Function: update
         * Updates a pingback title.
         *
         * Parameters:
         *     $title - The title of the blog post that pinged us.
         *
         * Returns:
         *     The updated <Pingback>.
         */
        public function update($title): self|false {
            if ($this->no_results)
                return false;

            $title = strip_tags($title);

            SQL::current()->update(
                table:"pingbacks",
                conds:array("id"    => $this->id),
                data:array("title" => $title)
            );

            $pingback = new self(
                null,
                array(
                    "read_from" => array(
                        "id"         => $this->id,
                        "post_id"    => $this->post_id,
                        "source"     => $this->source,
                        "title"      => $title,
                        "created_at" => $this->created_at
                    )
                )
            );

            Trigger::current()->call("update_pingback", $pingback, $this);
            return $pingback;
        }

        /**
         * Function: delete
         * Deletes a pingback from the database.
         *
         * See Also:
         *     <Model::destroy>
         */
        static function delete($pingback_id): void {
            parent::destroy(self::class, $pingback_id);
        }

        /**
         * Function: install
         * Creates the database table.
         */
        static function install(): void {
            SQL::current()->create(
                table:"pingbacks",
                cols:array(
                    "id INTEGER PRIMARY KEY AUTO_INCREMENT",
                    "post_id INTEGER NOT NULL",
                    "source VARCHAR(2048) DEFAULT ''",
                    "title LONGTEXT",
                    "created_at DATETIME DEFAULT NULL"
                )
            );
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        static function uninstall(): void {
            SQL::current()->drop("pingbacks");
        }
    }
