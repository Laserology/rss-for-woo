<?php /*
Plugin Name: RSS feed for Woo
Plugin URI: https://github.com/Laserology/rss-for-woo/
Description: Free public XML/RSS feed for your woo store.
Version: 1.4.1
Requires at least: 7.0
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Laserology, vladjpuscasu
Author URI: https://laserology.net/
Requires Plugins: woocommerce
Text Domain: rss-feed-for-woo
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add quick link to view the feed generated.
function LSWCF_setup_view_feed_link( $links ) {
	// Build and escape the URL.
	$url = esc_url(
		get_site_url() . "?feed=products"
	);
	// Create the link.
	$settings_link = "<a href='$url'>" . __( 'Open RSS feed', 'rss-feed-for-woo' ) . '</a>';
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
	// Check for a valid transient cache, and if it exists, use it instead.
	if ( false !== ( $value = get_transient( 'LSWCF_RSS_Cache_Transient' ) ) ) {
		header( 'Content-Type: application/xml; charset=utf-8' );
		echo $value;
		exit;
	}

	$products = get_posts([
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	]);

	// Begin XML file.
	$output = '<?xml version="1.0"?>' . PHP_EOL;
	$output .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL;
	$output .= "\t" . '<channel>' . PHP_EOL;
	$output .= "\t\t" . '<title>' . esc_html( sanitize_text_field( get_bloginfo( 'name' ) ) ) . '</title>' . PHP_EOL;
	$output .= "\t\t" . '<description>' . esc_html( sanitize_text_field( get_bloginfo( 'description' ) ) ) . '</description>' . PHP_EOL;
	$output .= "\t\t" . '<link>' . esc_url( sanitize_url( get_site_url() ) ) . '</link>' . PHP_EOL;

	// Loop over all products.
	foreach ( $products as $product ) {
		$product_obj = wc_get_product( $product->ID );

		# Check if the product is visible in the first place to avoid leaking secrets and preventing uploading unwanted listings.
		if ( !$product_obj->is_visible() ) {
			# Skip this entry if it is hidden.
			continue;
		}

		// Sanitize the descriptions
		$short_description = sanitize_text_field( $product->post_excerpt );
		$long_description = sanitize_text_field( $product->post_content );

		$title =        sanitize_text_field( $product->post_title );
		$description =  strlen($short_description) > 0 ? $short_description : $long_description;
		$image_link =   sanitize_text_field( wp_get_attachment_image_src( $product_obj->get_image_id(), 'full' )[0] );
		$stock =        LSWCF_get_stock_data( $product_obj );
		$region =       sanitize_text_field( $product_obj->get_attribute( 'pa_region' ) );
		$color =        sanitize_text_field( $product_obj->get_attribute( 'pa_colour' ) );
		$price =        LSWCF_get_price_data( $product_obj, $region );
		$gpid =         sanitize_text_field( $product_obj->get_meta( 'google-product-id' ) );
		$sku =          sanitize_text_field( $product_obj->get_sku() );
		$id =           $product_obj->get_id();

		// Run through variable product type.
		// Overwrite specific variables if available from variation.
		if ( $product_obj->is_type( 'variable' ) ) {
			foreach ( $product_obj->get_available_variations() as $variation ) {
				$variation_obj = new WC_Product_Variation( $variation['variation_id'] );

				$temp_link = sanitize_text_field( wp_get_attachment_image_src( $variation_obj->get_image_id(), 'full' )[0] );

				$title =        [ $product->post_title, get_the_title($variation['variation_id']) ];
				//$description =  strlen( $description )
				$stock =        LSWCF_get_stock_data( $variation_obj );
				$region =       sanitize_text_field( $variation_obj->get_attribute( 'pa_region' ) );
				$color =        sanitize_text_field( $variation_obj->get_attribute( 'pa_colour' ) );
				$image_link =   strlen( $temp_link ) > 0 ? $temp_link : $image_link;
				$price =        LSWCF_get_price_data( $variation_obj, $region );
				$sku =          [ $product_obj->get_sku(), $variation_obj->get_sku() ];
				$id =           $variation_obj->get_id();

				// Write one product to the output.
				$output .= LSWCF_emit_single_filtered($title, $description, $sku, $image_link, $color, $price, $stock, $id, $region, $gpid, true);
			}
			continue;
		}

		// Write one product to the output.
		$output .= LSWCF_emit_single_filtered($title, $description, $sku, $image_link, $color, $price, $stock, $id, $region, $gpid, false);
	}

	// Echo footer for RSS feed & return data.
	$output .= "\t" . '</channel>' . PHP_EOL;
	$output .= '</rss>';
	header( 'Content-Type: application/xml; charset=utf-8' );
	echo $output;

	// Update the transient cache for up to the next 1 minute as it was expired or was deleted.
	// Transient expiration times are a maximum. There is no minimum. Transients can disappear at a random time, but they will always disapear by the expiration time.
	set_transient( 'LSWCF_RSS_Cache_Transient', $output, 60 );
	exit;
}

/**
 * Emit one product entry - filters inputs.
 *
 * @since 1.4.1
 *
 * @param string $title A single string containing the product's title.
 * @param string $description A single string containting the product's description.
 * @param string $sku A single string containing the product's SKU.
 * @param string $image_link A single string containing a URL pointing to the product's (or variation's) main image.
 * @param string $color A single string containing the product's color.
 * @param array $price_data An array containing prices. Index 1 being main price (always populated) and optionally 1 and 2 containing sale price and dates. Index 0 denotes if the array includes sale data.
 * @param string $stock A single string containing the product's stock status.
 * @param string $id A single string containting the product's internal woocommerce ID.
 * @param string $region A single string containing the product's currency region.
 * @param string $gpid A single string containing the product's google product ID (optional).
 * @param type $is_variant Internal marker saying if the product is a single or variable product.
 */
function LSWCF_emit_single_filtered($title, $description, $sku, $image_link, $color, $price_data, $stock, $id, $region, $gpid, $is_variant) {
	// Begin new product.
	$output = "\t\t" . '<item>' . PHP_EOL;

	// Output product stripped data as XML.
	if ( $is_variant ) {
		// Fallback to parent ID if needed.
		$fsku = strlen ( $sku[1] ) == 0 ? $sku[0] : $sku[1];
		$fsku = strlen ( $fsku ) == 0 ? $id : $fsku;

		$output .= "\t\t\t" . '<g:title>' . esc_html( $title[1] ) . '</g:title>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:item_group_title>' . esc_html( $title[0] ) . '</g:item_group_title>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:item_group_id>' . esc_html( $sku[0] ) . '</g:item_group_id>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:mpn>' . esc_html( $fsku ) . '-' . esc_html( $id ) . '</g:mpn>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:sku>' . esc_html( $fsku ) . '</g:sku>' . PHP_EOL;
        $output .= "\t\t\t" . '<g:id>' . esc_html( $fsku ) . '</g:id>' . PHP_EOL;
	}
	else {
	    $output .= "\t\t\t" . '<g:title>' . esc_html( $title ) . '</g:title>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:mpn>' . esc_html( $sku ) . '-' . esc_html($id) . '</g:mpn>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:sku>' . esc_html( $sku ) . '</g:sku>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:id>' . esc_html( $sku ) . '</g:id>' . PHP_EOL;
	}

	$output .= "\t\t\t" . '<g:description><![CDATA[' . esc_html( $description ) . ']]></g:description>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:image_link>' . esc_url( $image_link ) . '</g:image_link>' . PHP_EOL;

	if (strlen( $color ) > 0) {
	    $output .= "\t\t\t" . '<color>' . esc_html( $color ) . '</color>' . PHP_EOL;
	}

	// Include sale price if it is on sale.
	if ( $price_data[0] == true ) {
	    $output .= "\t\t\t" . '<g:sale_price>' . esc_html( $price_data[2] ) . '</g:sale_price>' . PHP_EOL;

		// Include sale dates, if provided.
		if ( strlen( $price_data[3] ) > 0 ) {
		    $output .= "\t\t\t" . '<g:sale_price_effective_date>' . esc_html( $price_data[3] ) . '</g:sale_price_effective_date>' . PHP_EOL;
		}
	}

	$output .= "\t\t\t" . '<g:price>' . esc_html( $price_data[1] ) . '</g:price>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:brand>' . esc_html( sanitize_text_field( get_bloginfo( 'name' ) ) ) . '</g:brand>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:availability>' . esc_html( $stock ) . '</g:availability>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:condition>New</g:condition>' . PHP_EOL;

	// Conditional to avoid printing un-used fields.
	if (strlen($region) > 0) {
		$output .= "\t\t\t" . '<additional_variant_attribute><label>Region</label><value>' . esc_html( $region ) . '</value></additional_variant_attribute>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:link>' . esc_html( sanitize_url( get_permalink( $id ) ) ) . '?attribute_pa_region=' . esc_html( $region ) . '</g:link>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:region>' . esc_html( $region ) . '</g:region>' . PHP_EOL;
	}
	else {
		$output .= "\t\t\t" . '<g:link>' . esc_url( sanitize_url( get_permalink( $id ) ) ) . '</g:link>' . PHP_EOL;
	}

	// Adds a google product tag if present - this helps SEO.
	if (strlen($gpid) > 0) {
		$output .= "\t\t\t" . '<g:google_product_category>' . esc_html( $gpid ) . '</g:google_product_category>' . PHP_EOL;
	}

	// End of product.
	$output .= "\t\t" . '</item>' . PHP_EOL;
	return $output;
}

/**
 * Convert and return a date for google merchant center in ISO 8601 format.
 *
 * @since 1.4.1
 *
 * @param type $product A woocommerce product object.
 * @param type $currency A string containing the product's currency.
 * @return string An array. [0] = On sale or Normal price (bool) [1] = Normal price [2] = Sale price [3] = Sale dates.
 */
function LSWCF_get_price_data( $product, $region ) {
	$from = $product->get_date_on_sale_from();
    $to = $product->get_date_on_sale_to();
	$sale_dates = "";

    // Include sale dates, if they are set.
    if ($from != null or $to != null) {
        $sale_dates = $from->__toString() . '/' . $to->__toString();
    }

    $currency = match ( $region ) {
		'US' => ' USD',
		'CA' => ' CAD',
		'EU' => ' EUR',
		'GB' => ' GBP',
		default => ' USD', // Default to USD if it doesn't exist in the list
	};

    return [
        $product->is_on_sale(),
        $product->get_regular_price() . $currency,
        $product->get_sale_price() . $currency,
        $sale_dates
    ];
}

/**
 * Convert and return a value for RSS stock value.
 *
 * @since 1.4.2
 *
 * @param type $product A woocommerce product object.
 * @return string A string denoting the product's stock status. (In Stock, Backorder, Out of Stock).
 */
function LSWCF_get_stock_data( $product ) {
    return match ( $product->get_stock_status() ) {
        'instock' => 'In Stock',
        'outofstock' => 'Out of stock',
        'onbackorder' => 'Backorder',
        default => $product->get_stock_status(),
    };
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'LSWCF_setup_view_feed_link' );
add_action( 'init', 'LSWCF_product_feed' );
