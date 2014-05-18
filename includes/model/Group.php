<?php
    /**
     * Class: Group
     * The Group model.
     *
     * See Also:
     *     <Model>
     */
    class Group extends Model {
        public $has_many = "users";

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($group_id = null, $options = array()) {
            $options["left_join"][] = array("table" => "permissions",
                                            "where" => "group_id = groups.id");
            $options["select"][] = "groups.*";
            $options["select"][] = "permissions.id AS permissions";

            parent::grab($this, $group_id, $options);

            $this->permissions = (array) oneof(@$this->permissions, array());

            if ($this->no_results)
                return false;

            Trigger::current()->filter($this, "group");
        }

        /**
         * Function: find
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            $options["left_join"][] = array("table" => "permissions",
                                            "where" => "group_id = groups.id");
            $options["select"][] = "groups.*";
            $options["select"][] = "permissions.id AS permissions";

            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: can
         * Checks if the group can perform the specified actions.
         *
         * Parameters:
         *     *$permissions - However many permissions to check for.
         *                     If the last argument is <true>, it will act as "and", otherwise it will act as "or".
         *
         * Returns:
         *     @true@ or @false@
         */
        public function can() {
            if ($this->no_results)
                return false;

            $actions = func_get_args();

            if (end($actions) !== true) {# OR comparison
                foreach ($actions as $action)
                    if (in_array($action, $this->permissions))
                        return true;

                return false;
            } else { # AND comparison
                array_pop($actions);

                foreach ($actions as $action)
                    if (!in_array($action, $this->permissions))
                        return false;

                return true;
            }
        }

        /**
         * Function: add
         * Adds a group to the database with the passed Name and Permissions array.
         *
         * Calls the @add_group@ trigger with the inserted group.
         *
         * Parameters:
         *     $name - The group's name
         *     $permissions - An array of the permissions (IDs).
         *
         * Returns:
         *     The newly created <Group>.
         *
         * See Also:
         *     <update>
         */
        static function add($name, $permissions) {
            $sql = SQL::current();
            $trigger = Trigger::current();

            $trigger->filter($name, "before_group_add_name");
            $trigger->filter($permissions, "before_group_add_permissions");

            $sql->insert("groups", array("name" => $name));

            $group_id = $sql->latest("groups");

            foreach ($permissions as $id)
                $sql->insert("permissions",
                             array("id" => $id,
                                   "name" => $sql->select("permissions", "name", array("id" => $id))->fetchColumn(),
                                   "group_id" => $group_id));

            $group = new self($group_id);

            $trigger->call("add_group", $group);

            return $group;
        }

        /**
         * Function: update
         * Updates a group with the given name and permissions.
         *
         * Calls the @update_group@ trigger with the updated object and the old object.
         *
         * Parameters:
         *     $name - The new Name to set.
         *     $permissions - An array of the new permissions to set.
         */
        public function update($name, $permissions) {
            if ($this->no_results)
                return false;

            $sql = SQL::current();
            $trigger = Trigger::current();

            $trigger->filter($name, "before_group_update_name");
            $trigger->filter($permissions, "before_group_update_permissions");

            $old = clone $this;

            $this->name        = $name;
            $this->permissions = $permissions;

            $sql->update("groups",
                         array("id" => $this->id),
                         array("name" => $name));

            # Update their permissions
            $sql->delete("permissions", array("group_id" => $this->id));
            foreach ($permissions as $id) {
                $name = $sql->select("permissions",
                                     "name",
                                     array("id" => $id, "group_id" => 0),
                                     null,
                                     array(),
                                     1)->fetchColumn();
                $sql->insert("permissions",
                             array("id" => $id,
                                   "name" => $name,
                                   "group_id" => $this->id));
            }
 
            $trigger->call("update_group", $this, $old);
        }

        /**
         * Function: delete
         * Deletes a given group. Calls the @delete_group@ trigger and passes the <Group> as an argument.
         *
         * Parameters:
         *     $id - The group to delete.
         */
        static function delete($id) {
            parent::destroy(get_class(), $id);
        }

        /**
         * Function: add_permission
         * Adds a permission to the Groups table.
         *
         * Parameters:
         *     $id - The ID for the permission, like "can_do_something".
         *     $name - The name for the permission, like "Can Do Something". Defaults to the camelized ID while keeping spaces.
         */
        static function add_permission($id, $name = null) {
            $sql = SQL::current();

            if ($sql->count("permissions", array("id" => $id, "group_id" => 0)))
                return; # Permission already exists.

            fallback($name, camelize($id, true));
            $sql->insert("permissions", array("id" => $id, "name" => $name, "group_id" => 0));
        }

        /**
         * Function: remove_permission
         * Removes a permission from the Groups table.
         *
         * Parameters:
         *     $id - The ID of the permission to remove.
         */
        static function remove_permission($id) {
            SQL::current()->delete("permissions", array("id" => $id));
        }

        /**
         * Function: size
         * Returns the amount of users in the.
         */
        public function size() {
            if ($this->no_results)
                return false;

            return (isset($this->size)) ? $this->size :
                   $this->size = SQL::current()->count("users",
                                                       array("group_id" => $this->id)) ;
        }

        /**
         * Function: members
         * Returns all the members of the group.
         * 
         * !! DEPRECATED AFTER 2.0 !!
         */
        public function members() {
            if ($this->no_results)
                return false;

            return User::find(array("where" => array("group_id" => $this->id)));
        }
    }
