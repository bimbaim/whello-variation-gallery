<?php
/**
 * Whello Variation Gallery
 *
 * @package       WHVG
 * @author        Muhammad Ibrahim
 * @license       gplv2-or-later
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   Whello Variation Gallery
 * Plugin URI:    https://whello.id
 * Description:   Add Gallery in Variation Products
 * Version:       1.0.0
 * Author:        Muhammad Ibrahim
 * Author URI:    https://baimquraisy.com
 * Text Domain:   whello-variation-gallery
 * Domain Path:   /languages
 * License:       GPLv2 or later
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with Whello Variation Gallery. If not, see <https://www.gnu.org/licenses/gpl-2.0.html/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'whvg_add_custom_fields' );
register_activation_hook( __FILE__, 'whvg_add_custom_fields' );

if ( !function_exists( 'whvg_add_custom_fields' ) ) {
	function whvg_add_custom_fields() {
		if (is_plugin_active( 'woocommerce/woocommerce.php' )) {
			add_action( 'woocommerce_product_after_variable_attributes', 'whvg_variation_settings_fields', 10, 3 );
			if (is_admin()) {
				wp_register_script('whvg_admin_scripts', plugins_url('/admin/js/scripts.js', __FILE__), array('jquery'), NULL);
				wp_enqueue_script('whvg_admin_scripts');
				wp_register_style('whvg_admin_css', plugins_url('/admin/css/style.css', __FILE__), NULL, NULL);
				wp_enqueue_style('whvg_admin_css');
			} else {
				wp_register_script('whvg_public_scripts', plugins_url('/public/js/scripts.js', __FILE__), array('jquery'), NULL);
				wp_enqueue_script('whvg_public_scripts');
				wp_localize_script( 'whvg_public_scripts', 'whvg_ajax',
            		array( 'ajax_url' => admin_url( 'admin-ajax.php' )) );
			}
		} else {
			add_action( 'admin_notices', 'whvg_wc_needed_notice' );
			return false;
		}
	}
}

if ( !function_exists('whvg_wc_needed_notice') ) {
	function whvg_wc_needed_notice() {
		?>
    	<div class="notice notice-error is-dismissible">
        	<p><?php _e( 'It looks like WooCommerce is not installed or activated!', 'whello-variation-gallery' ); ?></p>
    	</div>
    	<?php
	}
}

if ( !function_exists('whvg_variation_settings_fields') ) {
	function whvg_variation_settings_fields($loop, $variation_data, $variation) {
		echo 
"	<div class=\"options_group whvg-gallery\">
		<label>Additional images</label>";
		woocommerce_wp_hidden_input( 
			array( 
				'id'		=> '_whvg_gallery[' . $variation->ID . ']',
				'name'		=> '_whvg_gallery[' . $variation->ID . ']',
				'value'		=> get_post_meta( $variation->ID, '_whvg_gallery', true )
			)
		);
		echo
"			<ul class=\"whvg-gallery-images\">";
		$images = get_post_meta( $variation->ID, '_whvg_gallery', true );
		if ($images) {
			$images = explode(';', $images);
			foreach ($images as $image) {
				if (!empty($image)) {
					$src = wp_get_attachment_image_src($image, 'thumbnail');
					echo
"				<li data-id=\"{$image}\"><img src=\"{$src[0]}'\"></li>";
				}
			}
		}
		echo
"			</ul>";
		echo
"			<a href=\"#\" class=\"button-primary whvg-gallery-add-button\">".__('Add Variation Images', 'whello-variation-gallery')."</a>
	</div>";
	}
}

add_action( 'woocommerce_save_product_variation', 'whvg_save_variation_fields', 10, 2 );

if ( !function_exists('whvg_save_variation_fields') ) {
	function whvg_save_variation_fields($variation_id, $i) {
		$text_field = $_POST['_whvg_gallery'][ $variation_id ];
		update_post_meta( $variation_id, '_whvg_gallery', esc_attr( $text_field ) );
	}
}

//Frontend Part

add_action( 'after_setup_theme', 'whvg_actions', 999 );

function whvg_actions() {

	// Add gallery html to available variation data

	add_filter('woocommerce_available_variation', 'whvg_woocommerce_available_variation', 3, 999);

	// Replace main image template data
	remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
	add_action('woocommerce_before_single_product_summary', 'whvg_woocommerce_show_product_images', 20);	

	// Replace additional images template data
	remove_action( 'woocommerce_product_thumbnails', 'woocommerce_show_product_thumbnails', 20 );
	add_action( 'woocommerce_product_thumbnails', 'whvg_woocommerce_show_product_thumbnails', 20 );
	
}

function whvg_woocommerce_available_variation($data, $product, $variation){

	//get main image id
	$image = $variation->get_image_id();

	//get additional images ids
	if ($gallery_ids = get_post_meta($variation->get_id(), '_whvg_gallery', true)) {
		$gallery_ids = explode(';', $gallery_ids);
	} else {
		$parent = $variation->get_parent_id();
		$parent = wc_get_product($parent);
		$gallery_ids = $parent->get_gallery_image_ids();
	}

	ob_start();
	?>
	<div class="woocommerce-product-gallery images">
		<figure class="woocommerce-product-gallery__wrapper">
			<?php
			if ( $image ) {
				echo wc_get_gallery_image_html( $image, true );
			}
			if ( $gallery_ids ) {
				foreach ( $gallery_ids as $gallery_id ) {

					if ($gallery_id != '') {
						echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', wc_get_gallery_image_html( $gallery_id ), $gallery_id );
						// phpcs:disable WordPress.XSS.EscapeOutput.OutputNotEscaped
					}
					
				}
			}
			?>


		</figure>
	</div>
	<?php
	$output = ob_get_contents();
	ob_end_clean();
	
	$data['gallery'] = $output;

	return $data;
}

/*
 * Replace main product image with default variation image if available
 */

function whvg_woocommerce_show_product_images() {
	global $product;

	if ($product->get_type() == 'variable') {
		// error_log('[WVG] Main product is '.$product->get_sku());
		$attributes = $product->get_default_attributes();

		foreach ( $attributes as $key => $value ) {
			if (strpos('attribute_', $key) == false) {
				$attributes['attribute_'.$key] = $value;
				unset($attributes[$key]);
			}
		}

		// error_log('[WVG] Main product default attributes:');
		// error_log(print_r($attributes, true));

		// Try to get matching variation
		$variatoins = $product->get_available_variations();
		if ($variatoins) {
			foreach ($variatoins as $variation) {
				// error_log('[WVG] Variation '.$variation['variation_id'].' attributes:');
				// error_log(print_r($variation['attributes'], true));
				if ($variation['attributes'] === $attributes) {
					// error_log('[WVG] FOUND MATCHING VARIATION :'.$variation['variation_id'].'.');
					$parent_product = $product;
					$product = wc_get_product($variation['variation_id']);
					break;
				}
			}
		}
	}

	wc_get_template( 'single-product/product-image.php' );
	if (isset($parent_product)) $product = $parent_product;
}

/*
 * Replace additiona product images with default varition additional images if available
 */

function whvg_woocommerce_show_product_thumbnails(){
	global $product;

	//error_log('[WVG] Trying to get additional images');

	if ($product->get_type() == 'variation') {
		// error_log('[WVG] Getting additional images for variation '.$product->get_sku());

		if ($attachment_ids = get_post_meta($product->get_id(), '_whvg_gallery', true)) {
			$attachment_ids = explode(';', $attachment_ids);
			$attachment_ids = (array_filter($attachment_ids, fn($value) => !is_null($value) && $value !== ''));
			// error_log('[WVG] Variation additional images ids are: ');
			// error_log(print_r($attachment_ids, true));
		} else {
			$parent = $product->get_parent_id();
			$parent = wc_get_product($parent);
			$attachment_ids = $parent->get_gallery_image_ids();
		}
	} else {
		$attachment_ids = $product->get_gallery_image_ids();
	}
	
	if ( $attachment_ids ) {
		foreach ( $attachment_ids as $attachment_id ) {
			echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', wc_get_gallery_image_html( $attachment_id ), $attachment_id ); // phpcs:disable WordPress.XSS.EscapeOutput.OutputNotEscaped
		}
	}

}
