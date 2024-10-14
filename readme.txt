=== RSS feed for Woo ===
Contributors: laserology
Tags: woo, product feed, google marketplace, rss, rss feed
Requires at least: 4.7
Tested up to: 6.6.2
Stable tag: 1.3.4
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A very simple wordpress plugin to dynamically generate an XML RSS feed for your woo store.

== Description ==

RSS Product feed for Woo!

Creates a Woo product feed url in the XML format that can be used to upload a catalog stream to your Google Merchant Center catalog or Facebook Shop catalog. This includes regional variations and color variations of products.

You can visit our github page [here!](https://github.com/Laserology/rss-for-woo/)

== Frequently Asked Questions ==

= How do i install from source? =

- Go to [this link](https://github.com/Laserology/rss-for-woo/)
- Click 'Code'
- Click 'Download as .ZIP'
- Go to your wordpress plugins page
- Click 'Add new plugin'
- Click 'Upload'
- Upload the file you downloaded from here
- Activate
- Done.

= How do i use the plugin? =

To take advantage of this plugin, simply append ``/?feed=products`` to your url, like so:
``https://laserology.net/?feed=products``

1. You can also find the feed link from the plugin listing:

### Note
For every product, you should add an extra field called 'google-product-id', and it should be a number indentifying the product's category. [You can reference this page here.](https://productcategory.net/)
To add the property, search how to enable the "custom fields" menu from the screen options.

== Changelog ==
= 1.3.4 =
* Add brand and mpn tags

= 1.3.3 =
* General improvements to image loading
* fixed a critical bug preventing variations from being properly displayed

= 1.3.2 =
* Fix translations

= 1.3.1 =
* Quick hotfix to address minor bugs

= 1.3 =
* Fix code formatting
* Add static product capability

= 1.2.2 =
* Skip to latest public release version

= 1.0 =
* Initial release.
