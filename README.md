# RSS Product feed for Woo!

Creates a Woo product feed url in the XML format that can be used to upload a catalog stream to your Google Merchant Center catalog or Facebook Shop catalog. This includes regional variations and color variations of products.

You can visit our github page [here!](https://github.com/Laserology/rss-for-woo/)

## Install
Here is how you install the plugin:
- Click 'Code'
- Click 'Download as .ZIP'
- Go to your wordpress plugins page
- Click 'Add new plugin'
- Click 'Upload'
- Upload the file you downloaded from here
- Activate
- Done.

## Usage
To take advantage of this plugin, simply append ``/?feed=products`` to your url, like so:
``https://laserology.net/?feed=products``

You can also find the feed link from the plugin listing, as shown here:
![A picture showing a "View feed" link on a plugin listing](https://github.com/Laserology/rss-for-woo/blob/main/.wordpress-org/Screenshot_20240911_162859.png?raw=true)

### Note
For every product, you should add an extra field called 'google-product-id', and it should be a number indentifying the product's category. [You can reference this page here.](https://productcategory.net/)
To add the property, search how to enable the "custom fields" menu from the screen options.

## This is a fork!
This plugin originated from [here](https://github.com/vladjpuscasu/woocommerce_xml_product_feed), i have forked it and made it much more user friendly and plan to keep it up to date for the forseeable future.

## License
This project is licensed under the GPLv2 license.
