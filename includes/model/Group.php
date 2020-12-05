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
         *
         * See Also:
         *     <Model::grab>
         */
        public function __construct($group_id = null, $options = array()) {
            $options["left_join"][] = array("table" => "permissions",
                                            "where" => "group_id = groups.id");
            $options["select"][] = "groups.*";
            $options["select"][] = "permissions.id AS permissions";

            parent::grab($this, $group_id, $options);

            $this->permissions = (array) oneof($this->permissions, array());

            if ($this->no_results)
                return false;

            Trigger::current()->filter($this, "group");
        }

        /**
         * Function: find
         *
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
         *     $permissions - Permissions to check, as separate args.
         *
         * Returns:
         *     @true@ or @false@
         *
         * Notes:
         *     If the last arg is <true>, logic is "and", otherwise "or".
         */
        public function can() {
            if ($this->no_results)
                return false;

            $actions = func_get_args();

            if (end($actions) !== true) { # OR comparison
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
         * Adds a group to the database.
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
            $name = strip_tags($name);

            $trigger->filter($name, "before_group_add_name");
            $trigger->filter($permissions, "before_group_add_permissions");

            $sql->insert("groups", array("name" => $name));

            $group_id = $sql->latest("groups");

            # Grab valid permissions.
            $results = $sql->select("permissions",
                                    "id, name",
                                    array("group_id" => 0))->fetchAll();

            $valid_permissions = array();

            foreach ($results as $permission)
                $valid_permissions[$permission["id"]] = $permission["name"];

            $permissions = array_intersect(array_keys($valid_permissions), $permissions);

            # Insert the permissions for the new group.
            foreach ($permissions as $id)
                $sql->insert("permissions",
                             array("id" => $id,
                                   "name" => $valid_permissions[$id],
                                   "group_id" => $group_id));

            $group = new self($group_id);

            $trigger->call("add_group", $group);

            return $group;
        }

        /**
         * Function: update
         * Updates a group with the given name and permissions.
         *
         * Parameters:
         *     $name - The new Name to set.
         *     $permissions - An array of the new permissions to set (IDs).
         *
         * Returns:
         *     The updated <Group>.
         */
        public function update($name, $permissions) {
            if ($this->no_results)
                return false;

            $sql = SQL::current();
            $trigger = Trigger::current();
            $name = strip_tags($name);

            $trigger->filter($name, "before_group_update_name");
            $trigger->filter($permissions, "before_group_update_permissions");

            # Grab valid permissions.
            $results = $sql->select("permissions",
                                    "id, name",
                                    array("group_id" => 0))->fetchAll();

            $valid_permissions = array();

            foreach ($results as $permission)
                $valid_permissions[$permission["id"]] = $permission["name"];

            $permissions = array_intersect(array_keys($valid_permissions), $permissions);

            $sql->update("groups",
                         array("id" => $this->id),
                         array("name" => $name));

            # Delete the old permissions for this group.
            $sql->delete("permissions", array("group_id" => $this->id));

            # Insert the new permissions for this group.
            foreach ($permissions as $id)
                $sql->insert("permissions",
                             array("id" => $id,
                                   "name" => $valid_permissions[$id],
                                   "group_id" => $this->id));
 
            $group = new self(null,
                              array("read_from" => array("id" => $this->id,
                                                         "name" => $name,
                                                         "permissions" => $permissions)));

            $trigger->call("update_group", $group, $this);

            return $group;
        }

        /**
         * Function: delete
         * Deletes a given group and its permissions.
         *
         * See Also:
         *     <Model::destroy>
         */
        static function delete($group_id) {
            if (!empty($group_id))
                SQL::current()->delete("permissions", array("group_id" => $group_id));

            parent::destroy(get_class(), $group_id);
        }

        /**
         * Function: add_permission
         * Adds a permission to the Groups table.
         *
         * Parameters:
         *     $id - The ID for the permission, e.g "can_do_something".
         *     $name - The name for the permission, e.g. "Can Do Something".
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
         * Function: list_permissions
         * Returns an array of all permissions in the Groups table.
         *
         * Parameters:
         *     $group_id - List enabled permissions for this group ID.
         */
        static function list_permissions($group_id = 0) {
            $permissions = SQL::current()->select("permissions",
                                                  "*",
                                                  array("group_id" => $group_id))->fetchAll();

            $names = array("change_settings"   => __("Change Settings"),
                           "toggle_extensions" => __("Toggle Extensions"),
                           "view_site"         => __("View Site"),
                           "view_private"      => __("View Private Posts"),
                           "view_scheduled"    => __("View Scheduled Posts"),
                           "view_draft"        => __("View Drafts"),
                           "view_own_draft"    => __("View Own Drafts"),
                           "add_post"          => __("Add Posts"),
                           "add_draft"         => __("Add Drafts"),
                           "edit_post"         => __("Edit Posts"),
                           "edit_draft"        => __("Edit Drafts"),
                           "edit_own_post"     => __("Edit Own Posts"),
                           "edit_own_draft"    => __("Edit Own Drafts"),
                           "delete_post"       => __("Delete Posts"),
                           "delete_draft"      => __("Delete Drafts"),
                           "delete_own_post"   => __("Delete Own Posts"),
                           "delete_own_draft"  => __("Delete Own Drafts"),
                           "view_page"         => __("View Pages"),
                           "add_page"          => __("Add Pages"),
                           "edit_page"         => __("Edit Pages"),
                           "delete_page"       => __("Delete Pages"),
                           "add_user"          => __("Add Users"),
                           "edit_user"         => __("Edit Users"),
                           "delete_user"       => __("Delete Users"),
                           "add_group"         => __("Add Groups"),
                           "edit_group"        => __("Edit Groups"),
                           "delete_group"      => __("Delete Groups"),
                           "export_content"    => __("Export Content"));

            Trigger::current()->filter($names, "list_permissions");

            foreach ($permissions as &$permission)
                if (array_key_exists($permission["id"], $names))
                    $permission["name"] = $names[$permission["id"]];

            return $permissions;
        }

        /**
         * Function: size
         * Returns the number of users in the group.
         */
        public function size() {
            if ($this->no_results)
                return false;

            return (isset($this->size)) ?
                $this->size :
                $this->size = SQL::current()->count("users",
                                                    array("group_id" => $this->id)) ;
        }
    }
