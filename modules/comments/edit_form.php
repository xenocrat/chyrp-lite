<form id="comment_edit_<?php echo $comment->id; ?>" class="inline_edit comment_edit" action="<?php echo $config->chyrp_url."/admin/?action=update_comment"; ?>" method="post" accept-charset="utf-8">
    <p>
        <label for="body"><?php echo __("Body", "comments"); ?></label>
        <textarea name="body" rows="8" cols="40" class="wide"><?php echo fix($comment->body, false, false); ?></textarea>
    </p>
    <a id="more_options_link_<?php echo $comment->id; ?>" href="javascript:void(0)" class="more_options_link"><?php echo __("More Options &darr;"); ?></a>
    <div id="more_options_<?php echo $comment->id; ?>" class="more_options" style="display: none">
        <p>
            <label for="author"><?php echo __("Author"); ?></label>
            <input class="text" type="text" name="author" value="<?php echo fix($comment->author); ?>" id="author" />
        </p>
        <p>
            <label for="author_url"><?php echo __("Author URL", "comments"); ?></label>
            <input class="text" type="text" name="author_url" value="<?php echo fix($comment->author_url); ?>" id="author_url" />
        </p>
        <p>
            <label for="author_email"><?php echo __("Author E-Mail", "comments"); ?></label>
            <input class="text" type="text" name="author_email" value="<?php echo fix($comment->author_email); ?>" id="author_email" />
        </p>
        <p>
            <label for="status"><?php echo __("Status"); ?></label>
            <select name="status" id="status">
                <option value="approved"<?php selected($comment->status, "approved"); ?>><?php echo __("Approved", "comments"); ?></option>
                <option value="denied"<?php selected($comment->status, "denied"); ?>><?php echo __("Denied", "comments"); ?></option>
                <option value="spam"<?php selected($comment->status, "spam"); ?>><?php echo __("Spam", "comments"); ?></option>
            </select>
        </p>
        <p>
            <label for="created_at"><?php echo __("Timestamp"); ?></label>
            <input class="text" type="text" name="created_at" value="<?php echo when("F jS, Y H:i:s", $comment->created_at); ?>" id="created_at" />
        </p>
        <div class="clear"></div>
    </div>
    <br />
    <input type="hidden" name="id" value="<?php echo fix($comment->id); ?>" id="id" />
    <input type="hidden" name="ajax" value="true" id="ajax" />
    <div class="buttons">
        <button><?php echo __("Update"); ?></button> <?php echo __("or"); ?>
        <a href="javascript:void(0)" id="comment_cancel_edit_<?php echo $comment->id; ?>" class="cancel"><?php echo __("Cancel"); ?></a>
    </div>
</form>
