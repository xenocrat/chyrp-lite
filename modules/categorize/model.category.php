<?php
    class Category extends Model {
        static function getCategory($id = null) {
            # we give all of the categories if there isn't one specified
            if (!isset($id))
                return SQL::current()->select("categorize",
                    "id,name,clean,show_on_home,concat(:url,clean) AS url", NULL, "name ASC",
                    array(":url" => url("category/")))->fetchAll();
            # single entry
            return SQL::current()->select("categorize",
                "id,name,clean,show_on_home,concat(:url,clean) AS url", "id = :id", "name ASC",
                array(':id' => $id, ":url" => url("category/")), 1)->fetchObject();
        }

        # This gets used to convert the category/<foo> name back to an ID or whatever else.
        static function getCategorybyClean($name = string) {
            return SQL::current()->select("categorize", "id,name,clean,show_on_home,concat(:url,clean) AS url", "clean = :clean", "name ASC",
                array(":url" => url("category/"), ":clean" => $name), 1)->fetchObject();
        }

        static function getCategoryIDbyName($name = string) {
            return SQL::current()->select("categorize", "id", "name = :name", "name ASC",
                array(":name" => $name), 1)->fetchObject();
        }

        # This might be a nice way of showing the list of cats in the sidebar
        static function getCategoryList() {
            return SQL::current()->select(array('categorize', 'post_attributes', 'posts'),
                "__categorize.name,__categorize.clean,__categorize.show_on_home,count(__categorize.id) AS total, concat(:url,__categorize.clean) AS url",
                array("post_attributes.post_id = posts.id",
                    "post_attributes.name = 'category_id'",
                    "post_attributes.value = categorize.id"),
                "`name` ASC", array(":url" => url("category/")),
                NULL, NULL, "__categorize.name")->fetchAll();
        }

        static function addCategory($post = array()) {
            $show_on_home = (isset($post['show_on_home'])) ? 1 : 0;
            $clean = sanitize(fallback($_POST['clean'], $_POST['name']));
            $name = $post['name'];
            SQL::current()->insert("categorize",
                array("name" => ":name", "clean" => ":clean", "show_on_home" => ":show_on_home"),
                array(":name" => $name, ":clean" => $clean, ":show_on_home" => $show_on_home));
        }

        static function updateCategory($post = array()) {
            $show_on_home = (isset($post['show_on_home'])) ? 1 : 0;
            $clean = sanitize(fallback($_POST['clean'], $_POST['name']));
            $name = $post['name'];
            $id = $post['id'];
            SQL::current()->update("categorize", "`id` = :id",
                array("name" => ":name", "clean" => ":clean", "show_on_home" => ":show_on_home"),
                array(":id" => $id, ":name" => $name, ":clean" => $clean, ":show_on_home" => $show_on_home));
        }

        static function deleteCategory($id = int, $confirm = FALSE) {
            SQL::current()->delete("categorize", "id = :id", array(":id" => $id));
            SQL::current()->update("post_attributes", "`name` = 'category_id' AND `value` = :id",
                array("value" => 0),
                array(":id" => $id));
        }

        static function installCategorize() {
            SQL::current()->query("CREATE TABLE IF NOT EXISTS `__categorize` (
                id    INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name  VARCHAR(128) NOT NULL,
                clean VARCHAR(128) NOT NULL,
                show_on_home INT(1) DEFAULT 1,
                UNIQUE KEY(`clean`)
                ) DEFAULT CHARSET=UTF8");
        }

        static function uninstallCategorize() {
            if ($confirm) {
                SQL::current()->query("DROP TABLE __categorize");
                SQL::current()->delete("post_attributes", "name = 'category_id'");
            }
        }
    }
