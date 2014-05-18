<?php
    define('TRACKBACK', true);
    require_once "common.php";

    if (!$config->enable_trackbacking)
        exit;

    $post = new Post($_GET['id']);
    if (empty($_POST['title']) and empty($_POST['url']) and empty($_POST['blog_name']))
        redirect($post->url());

    if (!Post::exists($_GET['id']))
        trackback_respond(true, __("Fake post ID, or nonexistent post."));

    if (!empty($_POST['url'])) {
        header('Content-Type: text/xml; charset=utf-8');

        $url = strip_tags($_POST['url']);
        $title = strip_tags($_POST['title']);
        $excerpt = strip_tags($_POST['excerpt']);
        $blog_name = strip_tags($_POST['blog_name']);

        $excerpt = truncate($excerpt, 255);
        $title = truncate($title, 250);

        $trigger->call("trackback_receive", $url, $title, $excerpt, $blog_name);
        trackback_respond();
    }
