# Plain/barebone Mollom client implementation example for PHP

This micro-app demonstrates how to implement a basic [Mollom] client in [PHP].


## Requirements

* PHP 5.2+
* cURL extension  
  Should be bundled and shipped with PHP on all distros.
* pdo_sqlite  
  Should be bundled and shipped with PHP on all distros.  In case it's missing:

        sudo apt-get install php5-sqlite


## Installation

1. Provide the [MollomPHP] client library
   * via git:

            git submodule init
            git submodule update
   * manually: Download and extract the complete library into `./MollomPHP/`
1. Set up permissions for the [SQLite] database:

        chmod 777 ./db
        touch db/.db.sqlite
        chmod 666 db/.db.sqlite
   * Webserver needs read+write access to the .sqlite file itself.
   * Webserver needs read+write access to the containing folder. [[serverfault]] [[pantz]]
1. Hook up the directory containing `index.php` in your web browser.


## Configuration

* To use and verify the production API:
  1. Create API keys for this demo app instance in your Mollom [Site manager].
  1. Put them into `settings.ini`.
  1. Change `testing_mode` to `FALSE`.

  However, **do not test** against the production API.  Doing so would negatively affect _your_ reputation.


## Glossary

* [CMP]: Content Moderation Platform
* UX: User Experience


## Anatomy

* Code you want to look at:
  1. `index.php`  
     Main application code demonstrating and documenting the integration and implementation logic.
  1. `client/MollomExample.php`  
     Mollom client for this example app (`extends Mollom`).
  1. `client/MollomExampleTest.php`  
     Mollom testing client for this example app (`extends MollomExample`).
* Other micro-app support code, which you can mostly ignore:

        helpers.php  - Basic helper functions.
        log.php      - Logging helpers.
        /assets      - Front-end assets.
        /db          - SQLite database.
        /templates   - Template files.


## Disclaimer

**WARNING:** This micro-app is absolutely **+++ NOT SECURE +++** and only meant to demonstrate the Mollom client implementation logic.

DO NOT USE THIS SCRIPT ON A PRODUCTION SITE (or a publicly reachable server).

Usage of global variables and functions as in this script is _discouraged_.  The one and only purpose of this code is to clarify the necessary logic of a Mollom client implementation, for which additional abstractions would not be helpful.  This code focuses on the _logic_ only, not on _security_ or _cleanliness_.  Do not take this code as an example for how to write a PHP app.

However, some basic architectural patterns were derived from [Drupal] (but heavily simplified -- causing this code to be ugly and not secure).  Thus, if you like the basic concepts and you want to use them in a clean and secure fashion, have a look at [Drupal].

Also, to learn how to develop a modern, secure, and clean PHP application, see [PHP, The Right Way.](http://www.phptherightway.com)

---

## Todo

* False-positive report link
* Feedback/Report as spam
* stored/CMP integration
* CMP callback endpoints
* Audio CAPTCHA switch
* User registration form


[Mollom]: http://mollom.com
[Site manager]: http://mollom.com/site-manager
[CMP]: http://mollom.com/moderation

[MollomPHP]: https://github.com/Mollom/MollomPHP
[PHP]: http://php.net
[SQLite]: http://www.sqlite.org
[Drupal]: http://drupal.org

[serverfault]: http://serverfault.com/questions/57596/why-do-i-get-sqlite-error-unable-to-open-database-file
[pantz]: http://www.pantz.org/software/sqlite/unabletoopendbsqliteerror.html
