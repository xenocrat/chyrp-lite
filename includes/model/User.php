<?php
    /**
     * Class: User
     * The User model.
     *
     * See Also:
     *     <Model>
     */
    class User extends Model {
        public $belongs_to = "group";
        public $has_many = array("posts", "pages");

        /**
         * Function: __construct
         *
         * See Also:
         *     <Model::grab>
         */
        public function __construct(
            $user_id,
            $options = array()
        ) {
            parent::grab($this, $user_id, $options);

            if ($this->no_results)
                return;

            Trigger::current()->filter($this, "user");
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
            fallback($options["order"], "id ASC");
            return parent::search(
                self::class,
                $options,
                $options_for_object
            );
        }

        /**
         * Function: authenticate
         * Checks to see if a given login and password match a user in the database.
         *
         * Parameters:
         *     $login - The Login to check.
         *     $password - The matching Password to check.
         *
         * Returns:
         *     @true@ or @false@
         */
        static function authenticate(
            $login,
            $password
        ): bool {
            $check = new self(array("login" => $login));

            if ($check->no_results)
                return false;

            if (self::check_password($password, $check->password))
                return true;

            return false;
        }

        /**
         * Function: add
         * Adds a user to the database.
         *
         * Parameters:
         *     $login - The Login for the new user.
         *     $password - The hashed password for the new user.
         *     $email - The email for the new user.
         *     $full_name - The full name of the user (optional).
         *     $website - The user's website (optional).
         *     $group_id - The user's <Group> ID (defaults to the default group).
         *     $joined_at - Join date (defaults to now).
         *
         * Returns:
         *     The newly created <User>.
         *
         * See Also:
         *     <update>
         */
        public static function add(
            $login,
            $password,
            $email,
            $full_name = "",
            $website = "",
            $group_id = null,
            $approved = true,
            $joined_at = null
        ): self {
            $config = Config::current();
            $sql = SQL::current();
            $trigger = Trigger::current();
            
            $new_values = array(
                "login"     => strip_tags($login),
                "password"  => $password,
                "email"     => strip_tags($email),
                "full_name" => strip_tags($full_name),
                "website"   => strip_tags($website),
                "group_id"  => oneof($group_id, $config->default_group),
                "approved"  => oneof($approved, true),
                "joined_at" => oneof($joined_at, datetime())
            );

            $trigger->filter($new_values, "before_add_user");
            $sql->insert(table:"users", data:$new_values);
            $user = new self($sql->latest("users"));
            $trigger->call("add_user", $user);
            return $user;
        }

        /**
         * Function: update
         * Updates a user with the given parameters.
         *
         * Parameters:
         *     $login - The new Login to set.
         *     $password - The new hashed password to set.
         *     $full_name - The new Full Name to set.
         *     $email - The new email to set.
         *     $website - The new Website to set.
         *     $group_id - The new <Group> ID to set.
         *
         * Returns:
         *     The updated <User>.
         *
         * See Also:
         *     <add>
         */
        public function update(
            $login     = null,
            $password  = null,
            $email     = null,
            $full_name = null,
            $website   = null,
            $group_id  = null,
            $approved  = null,
            $joined_at = null
        ): self|false {
            if ($this->no_results)
                return false;

            $sql = SQL::current();
            $trigger = Trigger::current();

            $new_values = array(
                "login" => (
                    isset($login) ?
                        strip_tags($login) :
                        $this->login
                ),
                "password" => (
                    isset($password) ?
                        $password :
                        $this->password
                ),
                "email" => (
                    isset($email) ?
                        strip_tags($email) :
                        $this->email
                ),
                "full_name" => (
                    isset($full_name) ?
                        strip_tags($full_name) :
                        $this->full_name
                ),
                "website" => (
                    isset($website) ?
                        strip_tags($website) :
                        $this->website
                ),
                "group_id" => oneof(
                        $group_id,
                        $this->group_id
                ),
                "approved" => oneof(
                        $approved,
                        $this->approved
                ),
                "joined_at" => oneof(
                        $joined_at,
                        $this->joined_at
                )
            );

            $trigger->filter($new_values, "before_update_user");

            $sql->update(
                table:"users",
                conds:array("id" => $this->id),
                data:$new_values
            );

            $user = new self(
                null,
                array(
                    "read_from" => array_merge(
                        $new_values,
                        array("id" => $this->id)
                    )
                )
            );

            $trigger->call("update_user", $user, $this);
            return $user;
        }

        /**
         * Function: delete
         * Deletes a given user.
         *
         * See Also:
         *     <Model::destroy>
         */
        public static function delete(
            $user_id
        ): void {
            parent::destroy(self::class, $user_id);
        }

        /**
         * Function: hash_password
         * Creates a hash of a user's password for the database.
         *
         * Parameters:
         *     $password - The unhashed password.
         * 
         * Returns:
         *     The password hashed using the SHA-512 algorithm.
         *
         * Notes:
         *     <random> uses a cryptographically secure function.
         */
        public static function hash_password(
            $password
        ): string {
            $salt = random(16);
            $prefix = '$6$rounds=50000$';
            $hash = crypt($password, $prefix.$salt);

            if (strlen($hash) < 13)
                trigger_error(
                    __("Failed to encrypt password."),
                    E_USER_WARNING
                );

            return $hash;
        }

        /**
         * Function: check_password
         * Checks a given password against the user's stored hash.
         *
         * Parameters:
         *     $password - The unhashed password given during a login attempt.
         *     $stored - The the user's stored hash value from the database.
         * 
         * Returns:
         *     @true@ or @false@
         *
         * Notes:
         *     Uses <password_verify> to mitigate timing attacks.
         */
        public static function check_password(
            $password,
            $stored
        ): bool {
            return password_verify($password, $stored);
        }
    }
