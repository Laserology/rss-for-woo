<?php /*
    Plugin Name: RSS feed for Woo
    Plugin URI: https://github.com/Laserology/woocommerce-product-feed/
    Description: Free public XML/RSS feed for your woo store.
    License: GPL v2 or later
    Version: 1.1
    Author: Laserology, vladjpuscasu
    Author URI: https://laserology.net/
    Requires Plugins: woocommerce
*/

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
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	);
	$products = get_posts( $args );

	$output = '<?xml version="1.0"?>' . PHP_EOL;
	$output .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL;
	$output .= "\t" . '<channel>' . PHP_EOL;
	$output .= "\t\t" . '<title>' . wp_title() . '</title>' . PHP_EOL;
	$output .= "\t\t" . '<link>' . get_site_url() . '</link>' . PHP_EOL;
    
    foreach ( $products as $product ) {
        $product_obj = wc_get_product( $product->ID );

        if ( $product_obj->is_type( 'variable' ) ) {
            foreach ( $product_obj->get_available_variations() as $variation ) {
                $variation_obj = new WC_Product_Variation( $variation['variation_id'] );
                
                //Use this section if you use region/currency variations on your products
                $currency = $variation_obj->get_attribute( 'pa_region' );
                
                switch ($currency) {
                    case 'US':
                        $currency = ' USD';
                        break;
                    case 'CA':
                        $currency = ' CAD';
                        break;
                    case 'EU':
                        $currency = ' EUR';
                        break;
                    case 'GB':
                        $currency = ' GBP';
                        break;
                    case 'ZZ':
                        $currency = ' ';
                        break;
                    default:
                        // Default to USD if it doesn't exist in the list
                        $currency = ' USD';
                        break;
                }

                // Sanitize the descriptions
                $short_description = wp_strip_all_tags($product->post_excerpt);
                $long_description = wp_strip_all_tags($product->post_content);
                $description = strlen($short_description) > 0 ? $short_description : $long_description;

                $stock_status = $stock == 'instock' ? 'In stock' : 'Out of stock';
                $stock = $variation_obj->get_stock_status();

                // Sanitize user product data
                $strip_region = wp_strip_all_tags($variation_obj->get_attribute( 'pa_region' ));
                $strip_color = wp_strip_all_tags($variation_obj->get_attribute( 'pa_colour' ));
                $strip_title = wp_strip_all_tags($product->post_title);
                $strip_sku = wp_strip_all_tags($product_obj->get_sku());

                $output .= "\t\t" . '<item>' . PHP_EOL;
                $output .= "\t\t\t" . '<g:title>' . $strip_title . '</g:title>' . PHP_EOL;
                $output .= "\t\t\t" . '<g:description><![CDATA[' . $description . ']]></g:description>' . PHP_EOL;
                $output .= "\t\t\t" . '<g:sku>' . $strip_sku . '</g:sku>' . PHP_EOL;
                $output .= "\t\t\t" . '<g:image_link>' . $variation['image']['thumb_src'] . '</g:image_link>' . PHP_EOL;

                if (strlen($strip_color) > 0) {
                    $output .= "\t\t\t" . '<color>' . $strip_color . '</color>' . PHP_EOL;
                }

                $output .= "\t\t\t" . '<g:price>' . $variation_obj->get_price() .  $currency . '</g:price>' . PHP_EOL;
                $output .= "\t\t\t" . '<g:availability>' . $stock_status . '</g:availability>' . PHP_EOL;
                $output .= "\t\t\t" . '<g:condition>New</g:condition>' . PHP_EOL;

                $output .= "\t\t\t" . '<g:item_group_id>' . $strip_sku . '</g:item_group_id>' . PHP_EOL;

                // Conditional to avoid printing un-used fields.
                if (strlen($strip_region) > 0) {
                    $output .= "\t\t\t" . '<g:id>' . $product->ID . '-' . $strip_region . '</g:id>' . PHP_EOL;
                    $output .= "\t\t\t" . '<additional_variant_attribute><label>Region</label><value>' . $strip_region . '</value></additional_variant_attribute>' . PHP_EOL;
                    $output .= "\t\t\t" . '<g:link>' . get_permalink( $product->ID ) . '?attribute_pa_region=' . $strip_region . '</g:link>' . PHP_EOL;
                    $output .= "\t\t\t" . '<g:region>' . $strip_region . '</g:region>' . PHP_EOL;
                }
                else {
                    $output .= "\t\t\t" . '<g:id>' . $product->ID . '</g:id>' . PHP_EOL;
                    $output .= "\t\t\t" . '<g:link>' . get_permalink( $product->ID ) . '</g:link>' . PHP_EOL;
                }

                $GPID = wp_strip_all_tags($product_obj->get_meta( 'google-product-id' ));
                if (strlen($GPID) > 0) {
                    $output .= "\t\t\t" . '<g:google_product_category>' . $GPID . '</g:google_product_category>' . PHP_EOL;
                }

                $output .= "\t\t" . '</item>' . PHP_EOL;
            }
        } 
    }

	$output .= "\t" . '</channel>' . PHP_EOL;
	$output .= '</rss>';
	header( 'Content-Type: application/xml; charset=utf-8' );
	echo $output;
	exit;
}

add_filter( 'plugin_action_links_rss-for-woo-main/LSWCF_product_feed.php', 'LSWCF_setup_view_feed_link' );
add_action( 'init', 'LSWCF_product_feed' );
