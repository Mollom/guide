<article id="comment-<?php print $comment->id; ?>" class="comment" data-mollom-contentid="<?php print $mollom->contentId; ?>">
<?php if ($comment->title): ?>
<h1><?php print $comment->title; ?></h1>
<?php endif; ?>
<footer>
<?php print $comment->by; ?>
</footer>
<div class="content">
<?php print $comment->body; ?>
</div>
</article>
