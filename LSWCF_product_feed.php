<?php /*
Plugin Name: RSS feed for Woo
Plugin URI: https://github.com/Laserology/rss-for-woo/
Description: Free public XML/RSS feed for your woo store.
Version: 1.4.3
Requires at least: 7.0
Requires PHP: 8.0
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

		[ $attributes, $region, $color ] = LSWCF_get_attributes( $product_obj );

		// Sanitize the descriptions
		$short_description = sanitize_text_field( $product->post_excerpt );
		$long_description = sanitize_text_field( $product->post_content );

		$title =        sanitize_text_field( $product->post_title );
		$description =  strlen($short_description) > 0 ? $short_description : $long_description;
		$image_link =   sanitize_text_field( wp_get_attachment_image_src( $product_obj->get_image_id(), 'full' )[0] );
		$stock =        LSWCF_get_stock_data( $product_obj );
		$price =        LSWCF_get_price_data( $product_obj, $region );
		$gpid =         sanitize_text_field( $product_obj->get_meta( 'google-product-id' ) );
		$sku =          sanitize_text_field( $product_obj->get_sku() );
		$id =           $product_obj->get_id();

		// Run through variable product type.
		// Overwrite specific variables if available from variation.
		if ( $product_obj->is_type( 'variable' ) ) {
			$used_feed_ids = [];

			foreach ( $product_obj->get_available_variations() as $variation ) {
				$variation_obj = new WC_Product_Variation( $variation['variation_id'] );

				$temp_link = sanitize_text_field( wp_get_attachment_image_src( $variation_obj->get_image_id(), 'full' )[0] );

				[ $attributes, $region, $color ] = LSWCF_get_attributes( $variation_obj );

				$title =        [ $product->post_title, LSWCF_get_variation_title( $product->post_title, $variation_obj ) ];
				$stock =        LSWCF_get_stock_data( $variation_obj );
				$image_link =   strlen( $temp_link ) > 0 ? $temp_link : $image_link;
				$additional_images = LSWCF_get_additional_images( $variation_obj );
				$price =        LSWCF_get_price_data( $variation_obj, $region );
				$sku =          [ $product_obj->get_sku(), $variation_obj->get_sku() ];
				$id =           $variation_obj->get_id();
				$feed_id =      LSWCF_get_variation_feed_id( $product_obj->get_sku(), $product_obj->get_id(), $variation_obj );

				// Siblings can be indistinguishable by both SKU and attributes.
				if ( isset( $used_feed_ids[ $feed_id ] ) ) {
					$feed_id .= '-v' . $id;
				}

				$used_feed_ids[ $feed_id ] = true;

				// Write one product to the output.
				$output .= LSWCF_emit_single($title, $description, $sku, $image_link, $color, $price, $stock, $id, $attributes, $region, $gpid, true, $additional_images, $feed_id);
			}

			// Go to next product.
			continue;
		}

		// Write one product to the output.
		$additional_images = LSWCF_get_additional_images( $product_obj );
		$output .= LSWCF_emit_single( $title, $description, $sku, $image_link, $color, $price, $stock, $id, [], $region, $gpid, false, $additional_images );
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
 * @param array $additional_images Optional list of extra image URLs belonging to this item.
 * @param string $feed_id Optional item ID that overrides the one derived from the SKU.
 */
function LSWCF_emit_single( $title, $description, $sku, $image_link, $color, $price_data, $stock, $id, $attributes, $region, $gpid, $is_variant, $additional_images = [], $feed_id = '' ) {
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
        $output .= "\t\t\t" . '<g:id>' . esc_html( strlen( $feed_id ) > 0 ? $feed_id : $fsku ) . '</g:id>' . PHP_EOL;
	}
	else {
		$fid = strlen( $sku ) == 0 ? $id : $sku;
 	    $output .= "\t\t\t" . '<g:title>' . esc_html( $title ) . '</g:title>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:mpn>' . esc_html( $sku ) . '-' . esc_html($id) . '</g:mpn>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:sku>' . esc_html( $sku ) . '</g:sku>' . PHP_EOL;
		$output .= "\t\t\t" . '<g:id>' . esc_html( $fid ) . '</g:id>' . PHP_EOL;
	}

	// Output product attributes that have been specified.
	foreach ( $attributes as $attribute ) {
	    $output .= "\t\t\t" . '<g:variant_option>' . PHP_EOL;
		$output .= "\t\t\t\t" . '<g:name>' . esc_html( $attribute[0] ) . '</g:name>' . PHP_EOL;
		$output .= "\t\t\t\t" . '<g:value>' . esc_html( $attribute[1] ) . '</g:value>' . PHP_EOL;
        $output .= "\t\t\t" . '</g:variant_option>' . PHP_EOL;
	}

	$output .= "\t\t\t" . '<g:description><![CDATA[' . esc_html( $description ) . ']]></g:description>' . PHP_EOL;
	$output .= "\t\t\t" . '<g:image_link>' . esc_url( $image_link ) . '</g:image_link>' . PHP_EOL;

	// Additional images are only emitted when the item carries its own gallery.
	foreach ( $additional_images as $additional_image ) {
		$output .= "\t\t\t" . '<g:additional_image_link>' . esc_url( $additional_image ) . '</g:additional_image_link>' . PHP_EOL;
	}

	if (strlen( $color ) > 0) {
 	    $output .= "\t\t\t" . '<g:color>' . esc_html( $color ) . '</g:color>' . PHP_EOL;
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

	// Use at own risk. Unknown if google will flag ever-changing available dates as suspicous or not.
	if ( $stock == "Backorder" ) {
	    $tomorrow = new WC_DateTime( '+2 days' );
	    $output .= "\t\t\t" . '<g:availability_date>' . esc_html( $tomorrow->__toString() ) . '</g:availability_date>' . PHP_EOL;
	}

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
 * Extract product attributes, splitting region and color out from the rest.
 *
 * @since 1.4.2
 *
 * @param WC_Product $product A woocommerce product object.
 * @return array An array containing [0] = List of general attributes (name/value pairs), [1] = Region (if set), [2] = Color (if set).
 */
function LSWCF_get_attributes( $product ) {
	$attributes = [];
	$region = "";
	$color = "";

	foreach ( $product->get_attributes() as $name => $value ) {
		if ( $name == "region" ) {
			$region = sanitize_text_field( $value );
		}
		elseif ( $name == "pa_color" || $name == "pa_colour" || $name == "color" || $name == "colour" ) {
			$color = sanitize_text_field( $value );
		}
		else {
			$attributes[] = [
				sanitize_text_field( $name ),
				sanitize_text_field( $value )
			];
		}
	}

	return [ $attributes, $region, $color ];
}

/**
 * Build a title for a variation that makes it distinguishable from its siblings.
 *
 * WooCommerce variation titles are frequently empty, or omit the attribute values
 * that separate one variant from another - every variant of a product then carries
 * the same title. Google cannot tell two items in an item group apart unless they
 * differ visibly, so any attribute value missing from the title is appended.
 *
 * @since 1.4.3
 *
 * @param string     $parent_title The parent product's title.
 * @param WC_Product $variation The variation object.
 * @return string The title to use for this variation.
 */
function LSWCF_get_variation_title( $parent_title, $variation ) {
	$title = sanitize_text_field( get_the_title( $variation->get_id() ) );

	// Fall back to the parent name when the variation has no title of its own.
	if ( strlen( $title ) == 0 ) {
		$title = sanitize_text_field( $parent_title );
	}

	if ( ! is_callable( [ $variation, 'get_attributes' ] ) ) {
		return $title;
	}

	foreach ( $variation->get_attributes() as $name => $value ) {
		$label = LSWCF_get_attribute_label( $name, $value );

		if ( strlen( $label ) == 0 ) {
			continue;
		}

		// Leave titles that already mention the value alone.
		$found = function_exists( 'mb_stripos' )
			? mb_stripos( $title, $label )
			: stripos( $title, $label );

		if ( false !== $found ) {
			continue;
		}

		// Google truncates very long titles, so don't push one past the limit.
		if ( strlen( $title . ' - ' . $label ) > 150 ) {
			continue;
		}

		$title .= ' - ' . $label;
	}

	return $title;
}

/**
 * Resolve a variation attribute value to the label a customer would see.
 *
 * Taxonomy backed attributes are stored on variations as term slugs, so the term
 * name is used where it can be resolved. Custom (local) attributes already hold
 * their label and are returned as-is.
 *
 * @since 1.4.3
 *
 * @param string $name The attribute name, for example pa_size.
 * @param mixed  $value The attribute value.
 * @return string The label, or an empty string when there is nothing usable.
 */
function LSWCF_get_attribute_label( $name, $value ) {
	if ( ! is_string( $value ) || strlen( $value ) == 0 ) {
		return '';
	}

	if ( taxonomy_exists( $name ) ) {
		$term = get_term_by( 'slug', $value, $name );

		if ( $term && ! is_wp_error( $term ) ) {
			return sanitize_text_field( $term->name );
		}
	}

	return sanitize_text_field( $value );
}

/**
 * Collect an item's own gallery images for use as additional_image_link entries.
 *
 * Only images attached to the item itself are returned; nothing is inherited from a
 * parent product. An item without its own gallery yields an empty array, so no
 * additional_image_link elements are emitted for it. An item with a single gallery
 * image is treated the same way: one extra image is not a list.
 *
 * @since 1.4.3
 *
 * @param WC_Product $product A woocommerce product or variation object.
 * @return array A list of image URLs when the item has two or more gallery images,
 *               capped at the 10 that Google accepts.
 */
function LSWCF_get_additional_images( $product ) {
	// Gallery accessors are only exposed by newer WooCommerce releases, and the
	// variation gallery feature is still flag-gated in some of them. Skip silently
	// when unavailable.
	if ( ! is_callable( [ $product, 'get_gallery_image_ids' ] ) ) {
		return [];
	}

	$attachment_ids = $product->get_gallery_image_ids();

	if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
		return [];
	}

	$images = [];

	foreach ( $attachment_ids as $attachment_id ) {
		$image_src = wp_get_attachment_image_src( (int) $attachment_id, 'full' );

		// Skip attachments WooCommerce cannot resolve to a usable URL.
		if ( ! is_array( $image_src ) || empty( $image_src[0] ) ) {
			continue;
		}

		$images[] = $image_src[0];

		// Google accepts at most 10 additional images per item.
		if ( count( $images ) >= 10 ) {
			break;
		}
	}

	// Only report a gallery that is genuinely a list; a single extra image adds
	// nothing the item's own image_link does not already cover.
	return count( $images ) > 1 ? $images : [];
}

/**
 * Build the item ID Google uses to track a variation.
 *
 * A variation's own SKU is used verbatim when it has one, so existing item IDs stay
 * stable across re-imports and migrations. Variations without a SKU are identified
 * by the parent's SKU plus a signature of their attribute values, which keeps every
 * sibling distinct instead of collapsing them all onto the parent SKU.
 *
 * Raw attribute values are used rather than their labels, so renaming a term does
 * not hand the item a new identity.
 *
 * @since 1.4.3
 *
 * @param string     $parent_sku The parent product's SKU. May be empty.
 * @param int        $parent_id The parent product's ID.
 * @param WC_Product $variation The variation object.
 * @return string The value to submit as the item's g:id.
 */
function LSWCF_get_variation_feed_id( $parent_sku, $parent_id, $variation ) {
	$variation_sku = sanitize_text_field( $variation->get_sku() );

	if ( strlen( $variation_sku ) > 0 ) {
		return $variation_sku;
	}

	$parent_sku = sanitize_text_field( $parent_sku );
	$base = strlen( $parent_sku ) > 0 ? $parent_sku : 'p' . $parent_id;

	$values = [];

	if ( is_callable( [ $variation, 'get_attributes' ] ) ) {
		foreach ( $variation->get_attributes() as $value ) {
			if ( is_string( $value ) && strlen( $value ) > 0 ) {
				$values[] = $value;
			}
		}
	}

	// Nothing distinguishes this variation from its siblings, so the ID must.
	if ( empty( $values ) ) {
		return $base . '-v' . $variation->get_id();
	}

	sort( $values );

	$signature = strtolower( implode( ' ', $values ) );
	$signature = trim( preg_replace( '/[^a-z0-9]+/', '-', $signature ), '-' );

	if ( strlen( $signature ) > 40 ) {
		$signature = substr( $signature, 0, 32 ) . '-' . substr( md5( $signature ), 0, 7 );
	}

	$item_id = $base . '-' . $signature;

	// Keep the item ID inside the length Google accepts.
	if ( strlen( $item_id ) > 50 ) {
		$item_id = substr( $base, 0, 24 ) . '-' . substr( md5( $base . '|' . $signature ), 0, 7 );
	}

	return $item_id;
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
        'onbackorder' => 'Out of stock', // Would be 'Backorder' but the constantly changing dates might be suspicous?
        default => 'Out of stock',
    };
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'LSWCF_setup_view_feed_link' );
add_action( 'init', 'LSWCF_product_feed' );
