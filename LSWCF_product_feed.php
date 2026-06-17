<?php /*
Plugin Name: RSS feed for Woo
Plugin URI: https://github.com/Laserology/rss-for-woo/
Description: Free public XML/RSS feed for your woo store.
Version: 1.3.12
Requires at least: 7.0
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Laserology, vladjpuscasu
Author URI: https://laserology.net/
Requires Plugins: woocommerce
Text Domain: rss-feed-for-woo
*/

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

		$currency = LSWCF_get_currency( $product_obj->get_attribute( 'pa_region' ) );

		// Sanitize the descriptions
		$short_description = sanitize_text_field( $product->post_excerpt );
		$long_description = sanitize_text_field( $product->post_content );

		$title =        sanitize_text_field( $product->post_title );
		$description =  strlen($short_description) > 0 ? $short_description : $long_description;
		$image_link =   sanitize_text_field( wp_get_attachment_image_src( $product_obj->get_image_id(), 'full' )[0] );
		$stock =        $product_obj->get_stock_status() == 'instock' ? 'In stock' : 'Out of stock';
		$price =        $product_obj->get_price() . $currency;
		$region =       sanitize_text_field( $product_obj->get_attribute( 'pa_region' ) );
		$color =        sanitize_text_field( $product_obj->get_attribute( 'pa_colour' ) );
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
				$stock =        $variation_obj->get_stock_status() == 'instock' ? 'In stock' : 'Out of stock';
				$region =       sanitize_text_field( $variation_obj->get_attribute( 'pa_region' ) );
				$color =        sanitize_text_field( $variation_obj->get_attribute( 'pa_colour' ) );
				$image_link =   strlen( $temp_link ) > 0 ? $temp_link : $image_link;
				$sku =          [ $product_obj->get_sku(), $variation_obj->get_sku() ];
				$price =        $variation_obj->get_price() .  $currency;
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

// Emit one product entry - filters inputs.
function LSWCF_emit_single_filtered($title, $description, $sku, $image_link, $color, $price, $stock, $id, $region, $gpid, $is_variant) {
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

	if (strlen($color) > 0) {
	    $output .= "\t\t\t" . '<color>' . esc_html( $color ) . '</color>' . PHP_EOL;
	}

	$output .= "\t\t\t" . '<g:brand>' . esc_html( sanitize_text_field( get_bloginfo( 'name' ) ) ) . '</g:brand>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:price>' . esc_html( $price ) . '</g:price>' . PHP_EOL;
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

function LSWCF_get_currency($currency) {
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
