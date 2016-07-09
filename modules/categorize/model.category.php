<?php
    class Category extends Model {
        static function getCategory($id = int) {
            $query = SQL::current()->select("categorize",
                                            "id, name, clean, show_on_home, clean AS url",
                                            "id = :id",
                                            "name ASC",
                                            array(':id' => $id), 1)->fetchObject();

            if (!empty($query))
                $query->url = url("category/".$query->url);

            return $query;
        }

        static function getCategories() {
                $query = SQL::current()->select("categorize",
                                                "id, name, clean, show_on_home, clean AS url",
                                                null,
                                                "name ASC")->fetchAll();

                foreach ($query as &$result)
                    $result["url"] = url("category/".$result["url"]);

            return $query;
        }

        static function getCategorybyClean($name = string) {
            $query = SQL::current()->select("categorize",
                                            "id, name, clean, show_on_home, clean AS url",
                                            "clean = :clean",
                                            "name ASC",
                                            array(":clean" => $name), 1)->fetchObject();

            if (!empty($query))
                $query->url = url("category/".$query->url);

            return $query;
        }

        static function getCategoryIDbyName($name = string) {
            return SQL::current()->select("categorize",
                                          "id",
                                          "name = :name",
                                          "name ASC",
                                          array(":name" => $name), 1)->fetchObject();
        }

        static function getCategoryList() {
            $query = SQL::current()->select(array('categorize',
                                                  'post_attributes',
                                                  'posts'),
                                            implode(", ",
                                                    array("__categorize.name",
                                                          "__categorize.clean",
                                                          "__categorize.show_on_home",
                                                          "count(__categorize.id) AS total",
                                                          "__categorize.clean AS url")),
                                            array("post_attributes.post_id = posts.id",
                                                  "post_attributes.name = 'category_id'",
                                                  "post_attributes.value = categorize.id"),
                                            "`__categorize.name` ASC",
                                            array(),
                                            null,
                                            null,
                                            "__categorize.name")->fetchAll();

            foreach ($query as &$result)
                $result["url"] = url("category/".$result["url"]);

            return $query;
        }

        static function addCategory($post = array()) {
            $show_on_home = (isset($post['show_on_home'])) ? 1 : 0;
            $clean = sanitize(fallback($_POST['clean'], $_POST['name']));
            $name = $post['name'];

            SQL::current()->insert("categorize",
                                   array("name" => ":name",
                                         "clean" => ":clean",
                                         "show_on_home" => ":show_on_home"),
                                   array(":name" => $name,
                                         ":clean" => $clean,
                                         ":show_on_home" => $show_on_home));
        }

        static function updateCategory($post = array()) {
            $show_on_home = (isset($post['show_on_home'])) ? 1 : 0;
            $clean = sanitize(fallback($_POST['clean'], $_POST['name']));
            $name = $post['name'];
            $id = $post['id'];

            SQL::current()->update("categorize",
                                   "`id` = :id",
                                   array("name" => ":name",
                                         "clean" => ":clean",
                                         "show_on_home" => ":show_on_home"),
                                   array(":id" => $id,
                                         ":name" => $name,
                                         ":clean" => $clean,
                                         ":show_on_home" => $show_on_home));
        }

        static function deleteCategory($id = int) {
            SQL::current()->delete("categorize",
                                   "id = :id",
                                   array(":id" => $id));

            SQL::current()->update("post_attributes",
                                   "`name` = 'category_id' AND `value` = :id",
                                   array("value" => 0),
                                   array(":id" => $id));
        }

        static function installCategorize() {
            SQL::current()->query("CREATE TABLE IF NOT EXISTS __categorize (
                                      id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                      name  VARCHAR(128) NOT NULL,
                                      clean VARCHAR(128) NOT NULL UNIQUE,
                                      show_on_home INT(1) DEFAULT 1
                                  ) DEFAULT CHARSET=UTF8");
        }

        static function uninstallCategorize() {
            if ($confirm) {
                SQL::current()->query("DROP TABLE __categorize");
                SQL::current()->delete("post_attributes", "name = 'category_id'");
            }
        }
    }
