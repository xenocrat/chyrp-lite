<?php
    /**
     * Class: Category
     * The model for the Categorize SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Category extends Model {
        static function getCategory($id = int) {
            $query = SQL::current()->select("categorize",
                                            "id, name, clean, show_on_home, clean AS url",
                                            "id = :id",
                                            "name ASC",
                                            array(':id' => $id), 1)->fetchObject();

            if (!empty($query))
                $query->url = url("category/".$query->url, MainController::current());

            return $query;
        }

        static function getCategorybyClean($name = string) {
            $query = SQL::current()->select("categorize",
                                            "id, name, clean, show_on_home, clean AS url",
                                            "clean = :clean",
                                            "name ASC",
                                            array(":clean" => $name), 1)->fetchObject();

            if (!empty($query))
                $query->url = url("category/".$query->url, MainController::current());

            return $query;
        }

        static function getCategoryIDbyName($name = string) {
            return SQL::current()->select("categorize",
                                          "id",
                                          "name = :name",
                                          "name ASC",
                                          array(":name" => $name), 1)->fetchObject();
        }

        static function getCategoryList($conds = null, $params = array()) {
            $sql = SQL::current();

            $query = $sql->select("categorize",
                                  "id, name, clean, show_on_home, clean AS url",
                                  $conds,
                                  "name ASC",
                                  $params)->fetchAll();

            foreach ($query as &$result) {
                $result["url"]   = url("category/".$result["url"], MainController::current());
                $result["total"] = $sql->count("post_attributes",
                                               array("name" => "category_id",
                                                     "value" => $result["id"]));
            }

            return $query;
        }

        static function addCategory($name = string, $clean = string, $show_on_home = bool) {
            SQL::current()->insert("categorize",
                                   array("name" => ":name",
                                         "clean" => ":clean",
                                         "show_on_home" => ":show_on_home"),
                                   array(":name" => $name,
                                         ":clean" => sanitize($clean, true, true),
                                         ":show_on_home" => $show_on_home));
        }

        static function updateCategory($id = int, $name = string, $clean = string, $show_on_home = bool) {
            SQL::current()->update("categorize",
                                   "`id` = :id",
                                   array("name" => ":name",
                                         "clean" => ":clean",
                                         "show_on_home" => ":show_on_home"),
                                   array(":id" => $id,
                                         ":name" => $name,
                                         ":clean" => sanitize($clean, true, true),
                                         ":show_on_home" => $show_on_home));
        }

        static function deleteCategory($id = int) {
            $sql = SQL::current();

            $sql->delete("categorize",
                         "id = :id",
                         array(":id" => $id));

            $sql->update("post_attributes",
                         "`name` = 'category_id' AND `value` = :id",
                         array("value" => 0),
                         array(":id" => $id));
        }

        static function install() {
            SQL::current()->query("CREATE TABLE IF NOT EXISTS __categorize (
                                      id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                      name  VARCHAR(128) NOT NULL,
                                      clean VARCHAR(128) NOT NULL UNIQUE,
                                      show_on_home BOOLEAN DEFAULT '1'
                                  ) DEFAULT CHARSET=UTF8");
        }

        static function uninstall() {
            $sql = SQL::current();

            $sql->query("DROP TABLE __categorize");
            $sql->delete("post_attributes", "name = 'category_id'");
        }
    }
