<?php
    $config = Config::current();
    $trigger = Trigger::current();

    $split_locale = explode("_", $config->locale);

    fallback($comments, Comment::find(array("limit" => 20,
                                            "where" => array("status != 'spam'",
                                                             "status != 'denied'"))));

    fallback($title, _f("Comments at &#8220;%s&#8221;", array(fix($config->name)), "comments"));

    $latest_timestamp = 0;
    foreach ($comments as $comment)
        if (strtotime($comment->created_at) > $latest_timestamp)
            $latest_timestamp = strtotime($comment->created_at);

    echo "<".'?xml version="1.0" encoding="utf-8"?'.">\r";
?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title><?php echo $title; ?></title>
    <id><?php echo fix(self_url()); ?></id>
    <updated><?php echo date("c", $latest_timestamp); ?></updated>
    <link href="<?php echo fix(self_url(), true); ?>" rel="self" type="application/atom+xml" />
    <generator uri="http://chyrp.net/" version="<?php echo CHYRP_VERSION; ?>">Chyrp</generator>
<?php
    foreach ($comments as $comment) {
        $trigger->call("feed_comment", $comment);

        $updated = ($comment->updated) ? $comment->updated_at : $comment->created_at ;

        $tagged = substr(strstr(url("id/".$comment->post->id)."#comment_".$comment->id, "//"), 2);
        $tagged = str_replace("#", "/", $tagged);
        $tagged = preg_replace("/(".preg_quote(parse_url($comment->post->url(), PHP_URL_HOST)).")/", "\\1,".when("Y-m-d", $updated).":", $tagged, 1);
?>
    <entry xml:base="<?php echo $comment->post->url()."#comment_".$comment->id; ?>">
        <title type="html"><?php echo fix($comment->post->title()); ?></title>
        <id>tag:<?php echo $tagged; ?></id>
        <updated><?php echo when("c", $updated); ?></updated>
        <published><?php echo when("c", $comment->created_at); ?></published>
        <link href="<?php echo $comment->post->url()."#comment_".$comment->id; ?>" />
        <author>
            <name><?php echo fix($comment->author); ?></name>
<?php if (!empty($comment->author_url)): ?>
            <uri><?php echo fix($comment->author_url); ?></uri>
<?php endif; ?>
        </author>
        <content type="html">
            <?php echo fix($comment->body); ?>
        </content>
<?php $trigger->call("comments_feed_item", $comment->id); ?>
    </entry>
<?php
    }
?></feed>
