<?php

/**
 * @file
 * Bare essential Mollom client implementation example for PHP 5.2+.
 *
 * WARNING: This micro-app is absolutely +++ NOT SECURE +++ and only meant to
 * demonstrate the Mollom client implementation logic.
 *
 * DO NOT USE THIS SCRIPT ON A PRODUCTION SITE.
 *
 * @see README.md
 */

require_once dirname(__FILE__) . '/helpers.php';
init();

// @todo Each Mollom client SHOULD periodically (~weekly/monthly) invoke
//   Mollom::verifyKeys() to update its site/API-key meta data. CMS plugins
//   usually perform this when visiting the plugin configuration page, or
//   alternatively when cron runs. When are static/hard-coded Mollom clients
//   able to do this?
#mollom()->verifyKeys();


$values = array();
$errors = $warnings = $status = '';

// Check for a form submission attempt and invoke the form processing workflow.
if (isset($_POST['form_id']) && function_exists($_POST['form_id'])) {
  $form_id = $_POST['form_id'];
  $values = $_POST;
  // Validate the submitted user input.
  $function = $form_id . '_validate';
  $errors = new Messages($function($values));

  // Only submit the form if there are no errors.
  // If there are form validation errors, the form has to be re-rendered to
  // allow the user to fix the errors.
  if (!$errors->count()) {
    $function = $form_id . '_submit';
    $status = new Messages($function($values));
  }
}
// Output a warning if testing mode is enabled.
// UX: This allows users & Mollom Support staff to identify that something is
// not quite right (on a production site).
elseif ($settings['mollom']['testing_mode']) {
  $warnings = 'Mollom Testing mode is still enabled.';
}

render_template('page', array(
  'errors' => (string) $errors,
  'warnings' => (string) $warnings,
  'status' => (string) $status,
  'comments' => render_comments(),
  'comment_form' => comment_form($values),
  'log' => mollom_log_write(),
));


/**
 * Form constructor for the comment form.
 *
 * @param array $values
 *   (optional) The validated/processed user input values, if any.
 *
 * @return string
 *   The HTML for the comment form.
 *
 * @see comment_form_validate()
 * @see comment_form_submit()
 */
function comment_form($values = array()) {
  // Apply default values.
  // In HTTP form handling, every value is a string.
  $values += array(
    'name' => '',
    'mail' => '',
    'homepage' => '',
    'title' => '',
    'body' => '',
    'mollom' => array(),
  );
  $values['mollom'] += array(
    'contentId' => '',
    'captchaId' => '',
    'homepage' => '',
  );

  $form = '';
  $form .= '<form method="POST" action="' . $_SERVER['SCRIPT_NAME'] . '"><div>' . "\n";
  $form .= format_form_element('hidden', 'form_id', 'comment_form');

  // Comment entity form elements.
  // These values will be mapped to author + post data when a post is validated
  // and checked against Mollom.
  // @see comment_form_validate()
  $form .= format_form_element('text', 'name', $values['name'], 'Your name');
  $form .= format_form_element('email', 'mail', $values['mail'], 'Your e-mail', array(
    'placeholder' => 'me@example.com',
  ));
  $form .= format_form_element('url', 'homepage', $values['homepage'], 'Your homepage', array(
    'placeholder' => 'http://example.com',
  ));
  $form .= format_form_element('text', 'title', $values['title'], 'Subject');
  $form .= format_form_element('textarea', 'body', $values['body'], 'Comment', array(
    'required' => NULL,
    'rows' => 12,
  ));

  // Mollom session form elements.
  // These values are generated and "injected" during form validation.
  // Initially, they are empty. If a POST contains a non-empty captchaId, then
  // mollom[solution] MUST be checked against Mollom (even if empty).
  // If a malicious user attempts to "just" remove the values from the HTML
  // markup, then Mollom's Content API will return 'unsure' again (or perhaps
  // even 'spam'). Thus, don't worry; just ensure to always output the IDs and
  // use them during validation.
  // @see comment_form_validate()
  $form .= format_form_element('hidden', 'mollom[contentId]', $values['mollom']['contentId']);
  $form .= format_form_element('hidden', 'mollom[captchaId]', $values['mollom']['captchaId']);

  // Mollom (unsure) CAPTCHA.
  // mollom[_captcha] is an internal value holding the CAPTCHA API response in
  // case the Content API was 'unsure'.
  // @see comment_form_validate()
  if (isset($values['mollom']['_captcha'])) {
    $form .= '<img src="' . $values['mollom']['_captcha']['url'] . '" alt="Type the characters you see in this picture." />';
    // UX: The value is always empty. A new solution has to be entered.
    $form .= format_form_element('text', 'mollom[solution]', '', 'Word verification', array(
      'autocomplete' => $GLOBALS['settings']['mollom']['testing_mode'] ? 'on' : 'off',
    ));
  }

  // Mollom Honeypot field.
  // Wrap the text input element in a container with a CSS class that gets
  // display:none applied. This makes the input field invisible for humans, but
  // spam bots will run into the trap.
  // @see style.css
  $form .= '<div class="hidden">';
  $form .= format_form_element('text', 'mollom[homepage]', $values['mollom']['homepage']);
  $form .= '</div>';

  // Mollom privacy policy link.
  // You may skip this link if your own privacy policy covers the aspect that
  // data submitted by users is sent to/checked by Mollom. Required by law.
  $form .= '<p>By submitting this form, you accept the <a href="//mollom.com/web-service-privacy-policy" target="_blank" rel="nofollow">Mollom privacy policy</a>.</p>';

  $form .= format_form_element('submit', 'op', 'Save');

  $form .= '</div></form>';
  return $form;
}

/**
 * Form validation handler for comment_form().
 *
 * @param array $input
 *   The user-submitted form values. Passed by reference.
 *
 * @return array
 *   An indexed array of form validation error messages to present to the user.
 */
function comment_form_validate(&$input) {
  $errors = array();
  // Your custom form validation goes here; e.g.:
  if (empty($input['body'])) {
    $errors[] = 'Comment field is required.';
  }

  // Mollom: Check (unsure) CAPTCHA solution.
  // Important:
  // - The CAPTCHA solution always MUST be checked first (before re-checking the
  //   content).
  // - The CAPTCHA MUST be checked, even if the solution is empty.
  // - Ensure to provide all author* parameters that are available to you.
  if (!empty($input['mollom']['captchaId'])) {
    $data = array(
      'id' => $input['mollom']['captchaId'],
      'solution' => isset($input['mollom']['solution']) ? $input['mollom']['solution'] : '',
      'authorIp' => ip_address(),
      'honeypot' => $input['mollom']['homepage'],
    );
    $result = mollom()->checkCaptcha($data);
  }

  // Mollom: Check content.
  // Important:
  // - Ensure to provide all author* parameters that are available to you.
  // - If your form contains any additional fields that allow for free-text user
  //   input, concatenate them into postBody (delimited by newlines). However,
  //   DO NOT include sensitive user input (e.g., CC numbers, passwords, etc).
  $data = array(
    'authorIp' => ip_address(),
    'authorName' => $input['name'],
    'authorMail' => $input['mail'],
    'authorUrl' => $input['homepage'],
    'postTitle' => $input['title'],
    'postBody' => $input['body'],
    'honeypot' => $input['mollom']['homepage'],
  );
  // Ensure to pass existing content ID if we have one already.
  if (isset($input['mollom']['contentId'])) {
    $data['id'] = $input['mollom']['contentId'];
  }
  $result = mollom()->checkContent($data);

  // Verify whether we received a valid response from Mollom.
  // @todo The Mollom base class internally throws an exception, but catches it.
  //   In retrospective, it would probably have been better to let it bubble up,
  //   so as to avoid these ugly array conditions.
  if (!is_array($result) || !isset($result['id'])) {
    $errors[] = 'The spam filter installed on this site is currently unavailable. Per site policy, we are unable to accept new submissions until that problem is resolved. Please try resubmitting the form in a couple of minutes.';
    // Since it's guaranteed that we have an error and the form won't submit,
    // we can return early.
    return $errors;
  }

  // Output the new contentId to include it in the next form submission attempt.
  $input['mollom']['contentId'] = $result['id'];

  // If we checked for spam, handle the spam classification result:
  if (isset($result['spamClassification'])) {
    // Spam: Discard the post.
    if ($result['spamClassification'] == 'spam') {
      $errors[] = 'Your submission has triggered the spam filter and will not be accepted.';
      // @todo False-positive report link.
    }
    // Unsure: Require to solve a CAPTCHA.
    elseif ($result['spamClassification'] == 'unsure') {
      // UX: Don't make the user believe that there's a bug or endless loop by
      // presenting a different error message, depending on whether we already
      // showed a CAPTCHA previously or not.
      if (empty($input['mollom']['captchaId'])) {
        $errors[] = 'To complete this form, please complete the word verification below.';
      }
      else {
        $errors[] = 'The word verification was not completed correctly. Please complete this new word verification and try again.';
      }
      // Retrieve a new CAPTCHA, assign the captchaId, and pass the full
      // response to the form constructor.
      // @see comment_form()
      $result = mollom()->createCaptcha(array(
        'type' => 'image',
        'contentId' => $input['mollom']['contentId'],
      ));
      $input['mollom']['captchaId'] = $result['id'];
      $input['mollom']['_captcha'] = $result;
    }
    // Ham: Accept the post.
    else {
      // Ensure the CAPTCHA validation above is not re-triggered after a
      // previous 'unsure' response.
      $input['mollom']['captchaId'] = NULL;
    }
  }

  return $errors;
}

/**
 * Form submission handler for comment_form().
 *
 * @param array $values
 *   The submitted, validated, and processed form values. Passed by reference.
 *
 * @return array
 *   An indexed array of success/status messages to present to the user.
 */
function comment_form_submit(&$values) {
  global $dbh;

  $status = array();

  // Store the comment.
  $sth = $dbh->prepare('INSERT INTO comments
           (title,  body,  name,  mail,  homepage,  created, ip)
    VALUES (:title, :body, :name, :mail, :homepage, :created, :ip)');
  $sth->execute(array(
    ':title' => $values['title'],
    ':body' => $values['body'],
    ':name' => $values['name'],
    ':mail' => $values['mail'],
    ':homepage' => $values['homepage'],
    ':created' => time(),
    ':ip' => ip_address(),
  ));
  $status[] = 'Your comment has been posted.';

  // Store the Mollom session data (and map it to the comment).
  $id = $dbh->lastInsertId();
  $sth = $dbh->prepare('INSERT INTO mollom
           (entity_type,  entity_id,  contentId,  captchaId,  created)
    VALUES (:entity_type, :entity_id, :contentId, :captchaId, :created)');
  $sth->execute(array(
    ':entity_type' => 'comment',
    ':entity_id' => $id,
    ':contentId' => $values['mollom']['contentId'],
    ':captchaId' => isset($values['mollom']['captchaId']) ? $values['mollom']['captchaId'] : '',
    ':created' => time(),
  ));

  return $status;
}


/**
 * Formats a message for end-users to report false-positives.
 *
 * @param array $values
 *   The submitted form values.
 * @param array $data
 *   The latest Mollom session data pertaining to the form submission attempt.
 *
 * @return string
 *   A message string containing a specially crafted link to Mollom's
 *   false-positive report form, supplying these parameters:
 *   - public_key: The public API key of this site.
 *   - url: The current, absolute URL of the form.
 *   At least one or both of:
 *   - contentId: The content ID of the Mollom session.
 *   - captchaId: The CAPTCHA ID of the Mollom session.
 *   If available, to speed up and simplify the false-positive report form:
 *   - authorName: The author name, if supplied.
 *   - authorMail: The author's e-mail address, if supplied.
 */
function _mollom_format_message_falsepositive($values, $data) {
  $mollom = mollom();
  $params = array(
    'public_key' => $mollom->loadConfiguration('publicKey'),
  );
  $params += array_intersect_key($values['mollom'], array_flip(array('contentId', 'captchaId')));
  $params += array_intersect_key($data, array_flip(array('authorName', 'authorMail')));

  // This should be the URL of the page containing the form.
  // NOT the general URL of your site!
  $params['url'] = $GLOBALS['base_url'];

  $report_url = '//mollom.com/false-positive?' . http_build_query($params);
  return 'If you feel this is in error, please <a href="' . $report_url . '" target="_blank">report that you are blocked</a>.';
}

/**
 * Returns the IP address of the client.
 *
 * If the app is behind a reverse proxy, we use the X-Forwarded-For header
 * instead of $_SERVER['REMOTE_ADDR'], which would be the IP address of
 * the proxy server, and not the client's. The actual header name can be
 * configured by the reverse_proxy_header variable.
 *
 * @return
 *   IP address of client machine, adjusted for reverse proxy and/or cluster
 *   environments.
 */
function ip_address() {
  global $settings;
  static $ip_address;

  if (!isset($ip_address)) {
    $ip_address = $_SERVER['REMOTE_ADDR'];

    if ($settings['reverse_proxy']) {
      $reverse_proxy_header = $settings['reverse_proxy_header'];
      if (!empty($_SERVER[$reverse_proxy_header])) {
        // If an array of known reverse proxy IPs is provided, then trust
        // the XFF header if request really comes from one of them.
        $reverse_proxy_addresses = (array) $settings['reverse_proxy_addresses'];

        // Turn XFF header into an array.
        $forwarded = explode(',', $_SERVER[$reverse_proxy_header]);

        // Trim the forwarded IPs; they may have been delimited by commas and spaces.
        $forwarded = array_map('trim', $forwarded);

        // Tack direct client IP onto end of forwarded array.
        $forwarded[] = $ip_address;

        // Eliminate all trusted IPs.
        $untrusted = array_diff($forwarded, $reverse_proxy_addresses);

        // The right-most IP is the most specific we can trust.
        $ip_address = array_pop($untrusted);
      }
    }
  }

  return $ip_address;
}

