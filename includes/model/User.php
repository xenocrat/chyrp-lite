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
         * See Also:
         *     <Model::grab>
         */
        public function __construct($user_id, $options = array()) {
            parent::grab($this, $user_id, $options);

            if ($this->no_results)
                return false;

            Trigger::current()->filter($this, "user");
        }

        /**
         * Function: find
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            fallback($options["order"], "id ASC");
            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: authenticate
         * Checks to see if a given Login and Password match a user in the database.
         *
         * Parameters:
         *     $login - The Login to check.
         *     $password - The matching Password to check.
         *
         * Returns:
         *     @true@ or @false@
         */
        static function authenticate($login, $password) {
            $check = new self(array("login" => $login));

            if ($check->no_results)
                return false;
            else {
                if (self::checkPassword($password, $check->password))
                    return true;
                elseif ($password == $check->password) {
                    # Password is stored in clear text, perhaps from an import.
                    # Accept it and update the stored value with a secure hash.
                    $check->update(null, self::hashPassword($password));
                    return true;
                }
            }

            return false;
        }

        /**
         * Function: add
         * Adds a user to the database with the passed username, password, and email.
         *
         * Calls the @add_user@ trigger with the inserted user.
         *
         * Parameters:
         *     $login - The Login for the new user.
         *     $password - The Password for the new user. Don't hash this, it's done in the function.
         *     $email - The email for the new user.
         *     $full_name - The full name of the user.
         *     $website - The user's website.
         *     $group - The user's group (defaults to the configured default group).
         *     $joined_at - Join date (defaults to now).
         *     $hash_password - Hash the password automatically? (defaults to true)
         *
         * Returns:
         *     The newly created <User>.
         *
         * See Also:
         *     <update>
         */
        static function add($login,
                            $password,
                            $email,
                            $full_name = "",
                            $website = "",
                            $group_ = null,
                            $approved = true,
                            $joined_at = null,
                            $hash_password = true) {
            $config = Config::current();
            $sql = SQL::current();
            $trigger = Trigger::current();

            if (empty($group))
                $group_id = $config->default_group;
            else
                $group_id = ($group instanceof Group) ? $group->id : $group ;
            
            $new_values = array("login"     => strip_tags($login),
                                "password"  => ($hash_password ? self::hashPassword($password) : $password),
                                "email"     => strip_tags($email),
                                "full_name" => strip_tags($full_name),
                                "website"   => strip_tags($website),
                                "group_id"  => $group_id,
                                "approved"  => oneof($approved, true),
                                "joined_at" => oneof($joined_at, datetime()));

            $trigger->filter($new_values, "before_add_user");

            $sql->insert("users", $new_values);

            $user = new self($sql->latest("users"));

            $trigger->call("add_user", $user);

            return $user;
        }

        /**
         * Function: update
         * Updates the user with the given login, password, full name, email, website, and <Group> ID.
         *
         * Passes all of the arguments to the update_user trigger.
         *
         * Parameters:
         *     $login - The new Login to set.
         *     $password - The new Password to set, already encoded.
         *     $full_name - The new Full Name to set.
         *     $email - The new email to set.
         *     $website - The new Website to set.
         *     $group_id - The new <Group> to set.
         *
         * See Also:
         *     <add>
         */
        public function update($login     = null,
                               $password  = null,
                               $email     = null,
                               $full_name = null,
                               $website   = null,
                               $group_id  = null,
                               $joined_at = null) {
            if ($this->no_results)
                return false;

            $sql = SQL::current();
            $trigger = Trigger::current();

            $old = clone $this;

            foreach (array("login", "password", "email", "full_name", "website", "group_id", "joined_at") as $attr)
                $this->$attr = $$attr = ($$attr !== null ? $$attr : $this->$attr);

            $new_values = array("login"     => strip_tags($login),
                                "password"  => $password,
                                "email"     => strip_tags($email),
                                "full_name" => strip_tags($full_name),
                                "website"   => strip_tags($website),
                                "group_id"  => $group_id,
                                "joined_at" => $joined_at);

            $trigger->filter($new_values, "before_update_user");

            $sql->update("users",
                         array("id" => $this->id),
                         $new_values);

            $trigger->call("update_user", $this, $old);
        }

        /**
         * Function: delete
         * Deletes a given user. Calls the @delete_user@ trigger and passes the <User> as an argument.
         *
         * Parameters:
         *     $id - The user to delete.
         */
        static function delete($id) {
            parent::destroy(get_class(), $id);
        }

        /**
         * Function: hashPassword
         * Creates a secure hash of a user's password.
         *
         * Parameters:
         *     $password - The unhashed password.
         * 
         * Returns:
         *     The securely hashed password to be stored in the database.
         *
         * Notes:
         *     Base64 encodes 6 bits per character so we generate 16*6/8=12 bytes for the salt.
         */
        static function hashPassword($password) {
            $salt = (function_exists("openssl_random_pseudo_bytes")) ?
                base64_encode(openssl_random_pseudo_bytes(12)) : random(16);

            $param = '$6$'.$salt; # Selecting SHA-512 (>=PHP 5.3.2)
            return crypt($password,$param);
        }

        /**
         * Function: checkPassword
         * Checks a given password against the stored hash.
         *
         * Parameters:
         *     $password - The unhashed password given during a login attempt.
         *     $storedHash - The stored hash for the user.
         * 
         * Returns:
         *     @true@ or @false@
         */
        static function checkPassword($password, $storedHash) {
            return (crypt($password, $storedHash) == $storedHash);
        }

    }
