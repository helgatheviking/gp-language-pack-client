# GP Language Pack Client

Client integration package for GP Language Pack Server.

This library hooks into WordPress translation update checks and adds language pack updates from a custom GlotPress server.

## Requirements

- PHP 8.0+
- WordPress (plugin or theme context)

## Install

From your plugin or theme project:

```bash
composer require helgatheviking/gp-language-pack-client
```

## Usage

```php
<?php

use HelgaTheViking\GPLanguagePack\Client;

new Client(
    'https://your-server.com', // Base URL for GP Language Pack Server
    'your-project-slug',       // GlotPress project slug/path
    'your-textdomain',         // Plugin or theme text domain
    'plugin'                   // Optional: plugin|theme (default plugin)
);
```

## Notes

- The client only surfaces updates for locales available on the site.
- Responses are cached to reduce remote requests.
- Updates are injected into native WordPress translation update flows.
