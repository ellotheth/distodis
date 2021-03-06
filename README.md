# distodis

Send [Discourse](https://discourse.org) posts to a
[Discord](https://discordapp.com/) channel.

- Posts show up in pretty embedded fields that include the author, a link, and the first 80 characters of the post
- Private messages and system messages are ignored
- Discourse gets meaningful HTTP status codes in response to events
- The hook lives in `/docroot`, so you don't have to sully your web root with `git` files
- Change one line of code to add more hooks

![sample](https://i.imgur.com/xWiCVfK.png)

### Requirements

- PHP 5.5+
- Your own web server

### Install

```
$ git clone https://github.com/ellotheth/distodis
```

### Setup

You'll want to check out the
[Discord](https://support.discordapp.com/hc/en-us/articles/228383668-Intro-to-Webhooks)
and [Discourse](https://meta.discourse.org/t/setting-up-webhooks/49045) webhook
documentation first.

#### One hook

1. Copy `config.php.dist` to `config.php`.
1. Add the values for your Discourse and Discord servers.
1. Copy `docroot/index.php.dist` to `docroot/index.php`.
1. Point your Discourse webhook at `docroot/index.php`.

#### Multiple hooks

1. Copy `config.php.dist` to as many config files as you want hooks. The
   filename doesn't matter (I like `config.<hook identifier>.php`).
1. Add the values for all your Discourse/Discord servers.
1. Copy `docroot/index.php.dist` to as many index files as you want hooks. I
   use `<hook identifier>`.php.
1. Modify each index file and replace the existing `require '../config.php'`
   line with the appropriate config filename. For example,
   `docroot/mycoolserver.php` might look like this:

     ```php
     <?php

     require '../config.mycoolserver.php';
     require '../hook.php';
     ```

1. Point each Discourse webhook at the appropriate index file.
