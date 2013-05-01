# Mollom client implementation guidelines

## Entities
* Mollom session
  * `entity_type VARCHAR(32), entity_id INT`
      * Or whatever is suitable for your app; required to map a `contentId` or `captchaId` to a locally stored entity.
  * `content_id VARCHAR(32) NULL`
      * Text analysis only.
      * Note: Certain database engines/drivers care for case-sensitivity (e.g., Postgres); for maximum compatibility use `content_id` instead of `contentId`.
  * `captcha_id VARCHAR(32) NULL`
      * Only exists when a post involved a CAPTCHA in any way.
      * Note: Certain database engines/drivers care for case-sensitivity (e.g., Postgres); for maximum compatibility use `captcha_id` instead of `captchaId`.
  * `created INT(11)/DATE NOT NULL DEFAULT NOW()`
      * Used to prune out obsolete session data older than 6 months.

## Terminology
* CMP: [Content Moderation Platform](http://mollom.com/moderation)
* Form flow: Basic assumed form workflow stages:
  1. Form construction (initial; no user input)
  1. Form validation (user input, possibly throwing errors that cause the form to be re-rendered)
  1. Form submission (only reached if there are no validation errors)

## Form elements
* Content ID, commonly `contentId` or `mollom[contentId]`.
* CAPTCHA ID, commonly `captchaId` or `mollom[captchaId]`.
* Honeypot field
  * Wrap the text input element in a container with a CSS class that gets `display: none;` applied.
  * Use an _"attractive"_ name for the input element; e.g., `name="mollom-homepage"` or `name="mollom[homepage]"`.
* Mollom privacy policy link

        By submitting this form, you accept the <a href="//mollom.com/web-service-privacy-policy" target="_blank" rel="nofollow">Mollom privacy policy</a>.


## Form flows

### Text analysis
1. On form construction:
   * --
1. On form validation:
   1. Check (unsure) CAPTCHA, if a CAPTCHA ID was submitted.
        * Regardless of whether the `solution` is empty.
   1. Check content (using submitted content ID, if any).
   1. Output new content ID as hidden form input value.
   1. React to `spamClassification`:
        * `spam`: Throw form validation error (blocking submission).
        * `unsure`: Retrieve + output CAPTCHA associated with `contentId` + throw form validation error (blocking submission).
        * `ham`: Accept post
            * Remove the CAPTCHA ID from the form's HTML markup to not re-trigger the CAPTCHA validation again.
1. On form submission:
   1. Store MollomSession data locally, including entity type + ID mapping.
   1. If CMP integration is enabled, mark content as stored + provide meta/context info:
        * `stored=1`
        * `url=http://example.com/articles/123#comment-456`
        * `contextUrl=http://example.com/articles/123`
        * `contextTitle=Article title`

### CAPTCHA-only (discouraged)
1. On form construction:
   1. Retrieve a new CAPTCHA.
   1. Start local session to store CAPTCHA ID + URL with `solved=0` + `url=http://...`
   1. **Disable page caching** + ensure to send proper HTTP headers to notify reverse-proxies.
   1. Output CAPTCHA + CAPTCHA ID as hidden form input + `solution` text form input element.
1. On form validation:
   1. Check CAPTCHA `solution` using submitted CAPTCHA ID.
   1. Output new CAPTCHA ID as hidden form input value.
   1. React to `solved`:
        * `0`: Throw form validation error (blocking submission) + output CAPTCHA again.
        * `1`: Accept post.
1. On form submission:
   1. Store MollomSession data locally, including entity type + ID mapping.

