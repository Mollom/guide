<html>
<head>
<link rel="stylesheet" href="<?php print $base_path; ?>/assets/style.css">
</head>
<body>
<?php if ($errors): ?>
  <div class="messages error">
  <?php print $errors; ?>
  </div>
<?php endif; ?>
<?php if ($warnings): ?>
  <div class="messages warning">
  <?php print $warnings; ?>
  </div>
<?php endif; ?>
<?php if ($status): ?>
  <div class="messages status">
  <?php print $status; ?>
  </div>
<?php endif; ?>
<?php print $comments; ?>

<?php print $comment_form; ?>

<?php if ($log): ?>
<details>
<summary>Request log</summary>

<?php print $log; ?>
<details>
<?php endif; ?>
</body>
</html>
