<?php /*
Plugin Name: RSS feed for Woo
Plugin URI: https://github.com/Laserology/woocommerce-product-feed/
Description: Free public XML/RSS feed for your woo store.
License: GPL v2 or later
Version: 1.3.8
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
	$settings_link = "<a href='$url'>" . __( 'View feed', 'rss-feed-for-woo' ) . '</a>';
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
	$output .= "\t\t" . '<title>' . wp_strip_all_tags(get_bloginfo( 'name' )) . '</title>' . PHP_EOL;
	$output .= "\t\t" . '<description>' . wp_strip_all_tags(get_bloginfo( 'description' )) . '</description>' . PHP_EOL;
	$output .= "\t\t" . '<link>' . esc_url(get_site_url()) . '</link>' . PHP_EOL;

	// Loop over all products.
	foreach ( $products as $product ) {
		$product_obj = wc_get_product( $product->ID );

		# Check if the product is visible in the first place to avoid leaking secrets and preventing uploading unwanted listings.
		if ( !$product_obj->is_visible() ) {
			# Skip this entry if it is hidden.
			continue;
		}

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
				$strip_linkto = wp_get_attachment_image_src( $variation_obj->get_image_id(), 'full' )[0];
				$strip_title = wp_strip_all_tags($product->post_title);
				$strip_sku = wp_strip_all_tags($variation_obj->get_sku());
				$price = $variation_obj->get_price() .  $currency;
				$id = wp_strip_all_tags( $variation_obj->get_id() );
				$parent_id = wp_strip_all_tags( $product_obj->get_id() ); // Share same product ID across variations to properly group them.

				$GPID = wp_strip_all_tags($product_obj->get_meta( 'google-product-id' ));

				// Write one product to the output for this loop.
				$output .= emit_single($strip_title, $description, $strip_sku, $strip_linkto, $strip_color, $price, $stock, $parent_id, $id, $strip_region, $GPID);
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
			$strip_linkto = wp_get_attachment_image_src( $product_obj->get_image_id(), 'full' )[0];
			$strip_title = wp_strip_all_tags($product->post_title);
			$strip_sku = wp_strip_all_tags($product_obj->get_sku());
			$price = $product_obj->get_price() . $currency;
			$id = wp_strip_all_tags( $product_obj->get_id() );

			$GPID = wp_strip_all_tags($product_obj->get_meta( 'google-product-id' ));

			// Write one product to the output.
			$output .= emit_single($strip_title, $description, $strip_sku, $strip_linkto, $strip_color, $price, $stock, $id, $id, $strip_region, $GPID);
		}
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

// Emit one product entry.
function emit_single($strip_title, $description, $strip_sku, $strip_linkto, $strip_color, $price, $stock, $parent_id, $id, $strip_region, $GPID) {
	// Begin new product.
	$output = "\t\t" . '<item>' . PHP_EOL;

	// Output product stripped data as XML.
	$output .= "\t\t\t" . '<g:title>' . htmlspecialchars($strip_title, ENT_XML1, 'UTF-8') . '</g:title>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:description><![CDATA[' . $description . ']]></g:description>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:sku>' . htmlspecialchars($strip_sku, ENT_XML1, 'UTF-8') . '</g:sku>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:image_link>' . htmlspecialchars($strip_linkto, ENT_XML1, 'UTF-8') . '</g:image_link>' . PHP_EOL;

	if (strlen($strip_color) > 0) {
	    $output .= "\t\t\t" . '<color>' . htmlspecialchars($strip_color, ENT_XML1, 'UTF-8') . '</color>' . PHP_EOL;
	}

	$output .= "\t\t\t" . '<g:brand>' . htmlspecialchars(wp_strip_all_tags(get_bloginfo('name')), ENT_XML1, 'UTF-8') . '</g:brand>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:mpn>' . htmlspecialchars($strip_sku . '-' . $id, ENT_XML1, 'UTF-8') . '</g:mpn>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:price>' . htmlspecialchars($price, ENT_XML1, 'UTF-8') . '</g:price>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:availability>' . htmlspecialchars($stock, ENT_XML1, 'UTF-8') . '</g:availability>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:condition>New</g:condition>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:item_group_id>' . $parent_id . '</g:item_group_id>' . PHP_EOL;

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
	return $output;
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
