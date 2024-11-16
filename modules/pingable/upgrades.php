<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: add_edit_pingback
     * Adds the edit_pingback permission.
     *
     * Versions: 2016.04 => 2017.01
     */
    function add_edit_pingback(
    ): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                tables:"permissions",
                conds:array(
                    "id" => "edit_pingback",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                table:"permissions",
                data:array(
                    "id" => "edit_pingback",
                    "name" => "Edit Pingbacks",
                    "group_id" => 0
                )
            );
    }

    add_edit_pingback();
