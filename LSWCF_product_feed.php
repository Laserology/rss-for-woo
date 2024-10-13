<?php /*
    Plugin Name: RSS feed for Woo
    Plugin URI: https://github.com/Laserology/woocommerce-product-feed/
    Description: Free public XML/RSS feed for your woo store.
    License: GPL v2 or later
    Version: 1.3
    Author: Laserology, vladjpuscasu
    Author URI: https://laserology.net/
    Requires Plugins: woocommerce
    Text Domain: rss-for-woo-main
*/

// To-do: Cache feed & re-generate only when new items are added.

// Add quick link to view the feed generated.
function LSWCF_setup_view_feed_link( $links ) {
	// Build and escape the URL.
	$url = esc_url(
	    get_site_url() . "?feed=products"
	);
	// Create the link.
	$settings_link = "<a href='$url'>" . __( 'View feed' ) . '</a>';
	// Adds the link to the end of the array.
	array_push(
		$links,
		$settings_link
	);
	return $links;
}

// Add custom feed
function LSWCF_product_feed() {
	add_feed('products', 'LSWCF_product_feed_callback');
}

function LSWCF_product_feed_callback() {
	$products = get_posts([
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	]);

    // Begin XML file.
	$output = '<?xml version="1.0"?>' . PHP_EOL;
	$output .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL;
	$output .= "\t" . '<channel>' . PHP_EOL;
	$output .= "\t\t" . '<title>' . wp_strip_all_tags(get_bloginfo( 'name' )) . '</title>' . PHP_EOL;
	$output .= "\t\t" . '<description>' . wp_strip_all_tags(get_bloginfo( 'description' )) . '</description>' . PHP_EOL;
	$output .= "\t\t" . '<link>' . esc_url(get_site_url()) . '</link>' . PHP_EOL;

    // Loop over all products.
    foreach ( $products as $product ) {
        $product_obj = wc_get_product( $product->ID );

        $strip_linkto = '';
        $strip_region = '';
        $strip_color = '';
        $strip_title = '';
        $description = '';
        $strip_sku = '';
        $currency = '';
        $price = '';
        $stock = '';
        $GPID = '';

        if ( $product_obj->is_type( 'variable' ) ) { // Run through variable product type.
            foreach ( $product_obj->get_available_variations() as $variation ) {
                $variation_obj = new WC_Product_Variation( $variation['variation_id'] );

                $currency = GetCurrency( $variation_obj->get_attribute( 'pa_region' ) );

                // Sanitize the descriptions
                $short_description = wp_strip_all_tags($product->post_excerpt);
                $long_description = wp_strip_all_tags($product->post_content);
                $description = strlen($short_description) > 0 ? $short_description : $long_description;

                $stock = $variation_obj->get_stock_status() == 'instock' ? 'In stock' : 'Out of stock';

                // Sanitize user product data
                $strip_region = wp_strip_all_tags($variation_obj->get_attribute( 'pa_region' ));
                $strip_color = wp_strip_all_tags($variation_obj->get_attribute( 'pa_colour' ));
                $strip_linkto = wp_get_attachment_url( $variation_obj->get_image_id() );
                $strip_title = wp_strip_all_tags($product->post_title);
                $strip_sku = wp_strip_all_tags($product_obj->get_sku());
                $price = $variation_obj->get_price() .  $currency;
                $id = wp_strip_all_tags( $product->ID );

                $GPID = wp_strip_all_tags($product_obj->get_meta( 'google-product-id' ));
            }
        }
        else { // Run througn static product type.
            $currency = GetCurrency( $product_obj->get_attribute( 'pa_region' ) );

            // Sanitize the descriptions
            $short_description = wp_strip_all_tags($product->post_excerpt);
            $long_description = wp_strip_all_tags($product->post_content);
            $description = strlen($short_description) > 0 ? $short_description : $long_description;

            $stock = $product_obj->get_stock_status() == 'instock' ? 'In stock' : 'Out of stock';

            // Sanitize user product data
            $strip_region = wp_strip_all_tags($product_obj->get_attribute( 'pa_region' ));
            $strip_color = wp_strip_all_tags($product_obj->get_attribute( 'pa_colour' ));
            $strip_linkto = wp_get_attachment_url( $product_obj->get_image_id() );
            $strip_title = wp_strip_all_tags($product->post_title);
            $strip_sku = wp_strip_all_tags($product_obj->get_sku());
            $price = $product_obj->get_price() .  $currency;
            $id = wp_strip_all_tags( $product->ID );

            $GPID = wp_strip_all_tags($product_obj->get_meta( 'google-product-id' ));
        }

        // Begin new product.
        $output .= "\t\t" . '<item>' . PHP_EOL;

        // Output product stripped data as XML.
        $output .= "\t\t\t" . '<g:title>' . $strip_title . '</g:title>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:description><![CDATA[' . $description . ']]></g:description>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:sku>' . $strip_sku . '</g:sku>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:image_link>' . $strip_linkto . '</g:image_link>' . PHP_EOL;

        if (strlen($strip_color) > 0) {
            $output .= "\t\t\t" . '<color>' . $strip_color . '</color>' . PHP_EOL;
        }

        $output .= "\t\t\t" . '<g:price>' . $price . '</g:price>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:availability>' . $stock . '</g:availability>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:condition>New</g:condition>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:item_group_id>' . $strip_sku . '</g:item_group_id>' . PHP_EOL;

        // Conditional to avoid printing un-used fields.
        if (strlen($strip_region) > 0) {
            $output .= "\t\t\t" . '<g:id>' . $id . '-' . $strip_region . '</g:id>' . PHP_EOL;
            $output .= "\t\t\t" . '<additional_variant_attribute><label>Region</label><value>' . $strip_region . '</value></additional_variant_attribute>' . PHP_EOL;
            $output .= "\t\t\t" . '<g:link>' . esc_url(get_permalink( $id )) . '?attribute_pa_region=' . $strip_region . '</g:link>' . PHP_EOL;
            $output .= "\t\t\t" . '<g:region>' . $strip_region . '</g:region>' . PHP_EOL;
        }
        else {
            $output .= "\t\t\t" . '<g:id>' . wp_strip_all_tags( $id ) . '</g:id>' . PHP_EOL;
            $output .= "\t\t\t" . '<g:link>' . esc_url(get_permalink( $id )) . '</g:link>' . PHP_EOL;
        }

        // Adds a google product tag if present - this helps SEO.
        if (strlen($GPID) > 0) {
            $output .= "\t\t\t" . '<g:google_product_category>' . $GPID . '</g:google_product_category>' . PHP_EOL;
        }

        // End of product.
        $output .= "\t\t" . '</item>' . PHP_EOL;
    }

    // Echo footer for RSS feed & return data.
	$output .= "\t" . '</channel>' . PHP_EOL;
	$output .= '</rss>';
	header( 'Content-Type: application/xml; charset=utf-8' );
	echo $output;
	exit;
}

function GetCurrency($currency) {
    switch ($currency) {
        case 'US':
            return ' USD';
        case 'CA':
            return ' CAD';
        case 'EU':
            return ' EUR';
        case 'GB':
            return ' GBP';
        default:
            // Default to USD if it doesn't exist in the list
            return ' USD';
    }
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'LSWCF_setup_view_feed_link' );
add_action( 'init', 'LSWCF_product_feed' );
