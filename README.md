![QUIQQER Blog](bin/images/Readme.jpg)
#QUIQQER Feeds

The QUIQQER Feeds package allows you to create customized XML Feeds for your website.

Packagename:

    quiqqer/feed


Features
--------

- Feed management
- Feed types included:
  - RSS
  - Atom
  - Google Sitemaps
- `feed.xml` API for developers to expand the available feed types (docs: https://dev.quiqqer.com/quiqqer/package-feed/-/wikis/feed-xml-api)
- Share your feeds in your websites header
- Display your feeds with the special Feeds brick (in conjunction with `quiqqer/bricks`)

Developer API
-------------

Other modules can add virtual entries to generated feeds through the
`quiqqerFeedCollectItems` event.

Example `events.xml` entry:

```xml
<event on="quiqqerFeedCollectItems" fire="\Vendor\Package\EventHandler::onQuiqqerFeedCollectItems" />
```

Handler example:

```php
public static function onQuiqqerFeedCollectItems(
    \QUI\Feed\Feed $Feed,
    \QUI\Feed\Interfaces\FeedTypeInterface $FeedType,
    \QUI\Feed\FeedItemCollection $Collection
): void {
    $Collection->add([
        'title' => 'Example',
        'description' => 'Example feed entry',
        'language' => $Feed->getAttribute('lang'),
        'date' => time(),
        'e_date' => time(),
        'link' => 'https://www.example.com/example',
        'permalink' => 'https://www.example.com/example'
    ]);
}
```

The feed package sorts and deduplicates collected items after all modules have
added their entries. Pagination and page count are based on the same collected
items, so modules only need to add entries to the collection.

#### Bricks
- Feedlist
  - Displays the available feeds as icons or as list

#### Sitetypes
- Feedlist
  - Displays the feeds as detailed list

#### Crons
- Build feeds: This cron will rebuild the feeds and their content.

Installation
------------

You can install this module via composer:
```
composer require "quiqqer/feed" "dev-master"
```


Contribute
----------

- Source Code: https://dev.quiqqer.com/quiqqer/package-feed/tree/master
- Issue Tracker: https://dev.quiqqer.com/quiqqer/package-feed/issues


Support
-------

You can contact us by mail support@pcsg.de,
if you have encountered an error or want to express a wish or feature request.
We will try to fulfill your wishes and will redirect them to the according developers.


License
-------
GPL-3.0+

