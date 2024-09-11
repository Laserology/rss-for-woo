<?php /*
    Plugin Name: RSS feed for Woo
    Plugin URI: https://github.com/Laserology/woocommerce-product-feed/
    Description: Free public XML/RSS feed for your woo store.
    License: GPL v2 or later
    Version: 1.0
    Author: Laserology, vladjpuscasu
    Author URI: https://laserology.net/
*/

// Add quick link to view the feed generated.
function setup_view_feed_link( $links ) {
	// Build and escape the URL.
	$url = esc_url(
	    get_site_url() . "?feed=wc_product_feed"
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
function wc_product_feed() {
    add_feed('wc_product_feed', 'wc_product_feed_callback');
}

function wc_product_feed_callback() {
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    );
    $products = get_posts( $args );


    $output = '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
    $output .= '<channel>';
    
    foreach ( $products as $product ) {

        $product_obj = wc_get_product( $product->ID );

        if ( $product_obj->is_type( 'variable' ) ) {
            foreach ( $product_obj->get_available_variations() as $variation ) {
                $variation_obj = new WC_Product_Variation( $variation['variation_id'] );
                
                //Use this section if you use region/currency variations on your products
        
                $currency = $variation_obj->get_attribute( 'pa_region' );
                
                switch ($currency) {
                    case 'US':
                        $currency = 'USD';
                        break;
                    case 'CA':
                        $currency = 'CAD';
                        break;
                    case 'EU':
                        $currency = 'EUR';
                        break;
                    case 'GB':
                        $currency = 'GBP';
                        break;
                    case 'ZZ':
                        $currency = '';
                        break;
                }
                $short_description = wp_strip_all_tags($product->post_excerpt);
                $long_description = wp_strip_all_tags($product->post_content);
                $description = $short_description !== '' ? $short_description : $long_description;

                $output .= '<item>';
                $output .= '<g:item_group_id>' . $product_obj->get_sku() . '</g:item_group_id>';
                $output .= '<g:id>' . $variation_obj->get_sku() . '-' . $variation_obj->get_attribute( 'pa_region' ) . '</g:id>';
                $output .= '<g:title>' . $product->post_title . '</g:title>';
                $output .= '<g:description><![CDATA[' . $description . ']]></g:description>';
                $output .= '<g:link>' . get_permalink( $product->ID ) . '?attribute_pa_region=' . $variation_obj->get_attribute( 'pa_region' ) . '</g:link>';

                $stock = $variation_obj->get_stock_status();
                $stock_status = $stock == 'instock' ? 'In stock' : 'Out of stock';

                $output .= '<g:image_link>' . $variation['image']['thumb_src'] . '</g:image_link>';
                $output .= '<color>' . $variation_obj->get_attribute( 'pa_colour' ) . '</color>';
                $output .= '<g:region>' . $variation_obj->get_attribute( 'pa_region' ) . '</g:region>';
                $output .= '<g:price>' . $variation_obj->get_price() . ' ' . $currency . '</g:price>';
                $output .= '<additional_variant_attribute><label>Region</label><value>' . $variation_obj->get_attribute( 'pa_region' ) . '</value></additional_variant_attribute>';
                $output .= '<g:availability>' . $stock_status . '</g:availability>';
                $output .= '<g:sku>' . $variation_obj->get_sku() . '</g:sku>';
                $output .= '<g:condition>New</g:condition>';
                $output .= '<g:google_product_category>223</g:google_product_category>';
                $output .= '</item>';
               
            }
        } 
    }

    $output .= '</channel>';
    $output .= '</rss>';
    header( 'Content-Type: application/xml; charset=utf-8' );
    echo $output;
    exit;
}

add_filter( 'plugin_action_links_rss-for-woo-main/wc_product_feed.php', 'setup_view_feed_link' );
add_action( 'init', 'wc_product_feed' );
