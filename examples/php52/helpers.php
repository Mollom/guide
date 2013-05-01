<?php

/**
 * @file
 * Micro-app helpers. You can ignore this file.
 */

/**
 * Initializes the application.
 */
function init() {
  global $settings, $base_path, $base_url, $dbh;

  $base_path = dirname($_SERVER['SCRIPT_NAME']);

  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
  $port = '';
  if (!($_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443)) {
    $port = ':' . $_SERVER['SERVER_PORT'];
  }
  $base_url = $scheme . $_SERVER['SERVER_NAME'] . $port . $base_path;

  // Load Mollom PHP base class.
  $file = 'MollomPHP/mollom.class.inc';
  $path = dirname(__FILE__) . '/' . $file;
  if (!file_exists($path)) {
    trigger_error("./$file not found. Run 'git submodule init' or download and extract the MollomPHP library manually.", E_USER_ERROR);
    exit(1);
  }
  require_once $path;
  require_once dirname(__FILE__) . '/log.php';

  // Load settings.ini.
  $settings = parse_ini_file(dirname(__FILE__) . '/settings.ini', TRUE);

  // Initialize database on first run.
  if (substr($settings['db']['database'], 0, 1) != '/' && substr($settings['db']['database'], 1, 1) != ':') {
    $settings['db']['database'] = dirname(__FILE__) . '/' . $settings['db']['database'];
  }
  $dbh = new PDO($settings['db']['driver'] . ':' . $settings['db']['database']);
  if (!$dbh->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'comments'")->fetchColumn()) {
    $dbh->query("CREATE TABLE comments (
      id INTEGER PRIMARY KEY,
      title TEXT,
      body TEXT,
      name TEXT,
      mail TEXT,
      homepage TEXT,
      created INTEGER,
      ip TEXT
    )");
  }
  if (!$dbh->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'mollom'")->fetchColumn()) {
    $dbh->query("CREATE TABLE mollom (
      entity_type TEXT,
      entity_id INTEGER,
      contentId TEXT,
      captchaId TEXT,
      created INTEGER
    )");
  }
}

/**
 * Instantiates a new Mollom client (once).
 */
function mollom() {
  global $settings;
  static $instance;

  $class = 'MollomExample';
  require_once dirname(__FILE__) . "/client/$class.php";

  if (!empty($settings['mollom']['testing_mode'])) {
    $class = 'MollomExampleTest';
    require_once dirname(__FILE__) . "/client/$class.php";
  }
  // If there is no instance yet or if it is not of the desired class, create a
  // new one.
  if (!isset($instance) || !($instance instanceof $class)) {
    $instance = new $class();
  }
  return $instance;
}

/**
 * Formats a form element as HTML.
 *
 * @param string $type
 *   The form element type; e.g., 'text', 'email', or 'textarea'.
 * @param string $name
 *   The (raw) form input name; e.g., 'body' or 'mollom[contentId]'.
 * @param string $value
 *   The (raw/unsanitized) form input value; e.g., 'sun & me were here'.
 * @param string $label
 *   (optional) The label for the form element.
 * @param array $attributes
 *   (optional) An associative array of attributes to apply to the form input
 *   element; see format_attributes().
 *
 * @return string
 *   The formatted HTML form element.
 */
function format_form_element($type, $name, $value, $label = NULL, $attributes = array()) {
  $attributes['name'] = $name;
  if ($type != 'textarea') {
    $attributes['type'] = $type;
    $attributes['value'] = $value;
    $attributes = format_attributes($attributes);
    $output = "<input $attributes />";
  }
  else {
    $attributes = format_attributes($attributes);
    $output = "<$type $attributes>";
    $output .= htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $output .= "</$type>";
  }

  if (isset($label)) {
    $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $output = '<div class="form-item form-type-' . $type . '"><label>' . $label . '</label>' . $output . '</div>';
  }
  $output .= "\n";
  return $output;
}

/**
 * Formats HTML/DOM element attributes.
 *
 * @param array $attributes
 *   (optional) An associative array of attributes to format; e.g.:
 *     array(
 *      'title' => 'Universal title',
 *      'class' => array('foo', 'bar'),
 *     )
 *   Pass NULL as an attribute's value to achieve a value-less DOM element
 *   property; e.g., array('required' => NULL).
 *
 * @return string
 *   A string containing the formatted HTML element attributes.
 */
function format_attributes($attributes = array()) {
  foreach ($attributes as $attribute => &$data) {
    if ($data === NULL) {
      $data = $attribute;
    }
    else {
      $data = implode(' ', (array) $data);
      $data = $attribute . '="' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '"';
    }
  }
  return $attributes ? implode(' ', $attributes) : '';
}

/**
 * Renders a (PHP) template.
 *
 * @param string $template
 *   The (base)name of the template to render.
 * @param array $variables
 *   An associative array of template variables to provide to the template.
 *
 * @return void
 *   The template is rendered + output to stdout.
 */
function render_template($template, $variables) {
  $base_path = $GLOBALS['base_path'];
  extract($variables);
  require dirname(__FILE__) . "/templates/$template.php";
}

/**
 * Renders a list of stored comments.
 *
 * @return string
 *   A rendered list of stored comments.
 */
function render_comments() {
  global $dbh;
  $base_path = $GLOBALS['base_path'];

  $output = '';
  $result = $dbh->query('SELECT * FROM comments ORDER BY created', PDO::FETCH_OBJ);
  foreach ($result as $comment) {
    $mollom = $dbh->query("SELECT * FROM mollom WHERE entity_type = 'comment' AND entity_id = $comment->id", PDO::FETCH_OBJ)->fetch();
    if (!$mollom) {
      $mollom = (object) array('contentId' => '');
    }

    $comment->title = html($comment->title);
    $comment->body = html($comment->body);
    $comment->by = '';
    if ($comment->mail) {
      $comment->by = '<img src="//www.gravatar.com/avatar/' . md5(strtolower(trim($comment->mail))) . '?s=30"> ';
    }
    if ($comment->name) {
      $comment->by .= html($comment->name);
    }
    else {
      $comment->by .= 'Anonymous';
    }
    $comment->by .= ', on ' . date('Y-m-d H:i', $comment->created) . ':';

    ob_start();
    require dirname(__FILE__) . '/templates/comment.php';
    $output .= ob_get_contents();
    ob_end_clean();
  }
  return $output;
}

/**
 * Sanitizes a plain-text string for HTML output.
 *
 * @param string $text
 *   The plain-text string to sanitize.
 *
 * @return string
 *   The sanitized HTML string.
 */
function html($text) {
  return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * A trivial bag for flash messages.
 */
class Messages extends ArrayObject {

  public function __toString() {
    if ($this->count() == 0) {
      return '';
    }
    if ($this->count() == 1) {
      return $this->offsetGet(0);
    }
    $output = '<ul>';
    foreach ($this->getIterator() as $message) {
      $output .= '<li>' . $message . '</li>';
    }
    $output .= '</ul>';
    return $output;
  }

}

