<?php
   /*
   Plugin Name: Hide Out Of Stock By Category
   Plugin URI: 
   description: Hide WooCommerce products by category when they're out of stock.
   Version: 1.0
   Author: Nerdy WP
   Author URI: https://www.nerdywp.com
   */

// check Woocommerce installed, activated, and compatible version
function nwp_whc_version_check( $version = '3.0' ) {
	if ( ! class_exists( 'WooCommerce' ) ) {
		?>
	    <div class="error notice">
	        <p><?php echo 'WooCommerce not found. Please install and activate it to use Hide Out Of Stock By Category.'; ?></p>
	    </div>
	    <?php
	    return;
	}
		
	if ( version_compare( WC_VERSION, $version, "<" ) ) {
		?>
	    <div class="error notice">
	        <p><?php echo 'WooCommerce version too low! Hide Out Of Stock By Category requires version 3.0 or greater.'; ?></p>
	    </div>
	    <?php
	    return;
	}
}
add_action('init', 'nwp_whc_version_check');

//change the query to exclude products that are out of stock and have the categories to be hidden
function nwp_whc_hide_outofstock_products( $q ) {
 
    if ( ! $q->is_main_query() || is_admin() ) {
        return;
    }


    //get all hidden cats
    $args = array(
	  'taxonomy' =>  'product_cat',
	  'hide_empty' =>  false,
	  'fields' => 'ids',
	  'meta_query' => array(
	    'key' => 'hide_products_in_cat',
	    'value' => 'yes',
	 ),
	);
	$terms = get_terms( $args );

    //get products with cats that should hide
    $post_ids = get_posts( 
		array(
	      'post_type' => 'product',
	      'numberposts' => -1,
	      'post_status' => 'publish',
	      'fields' => 'ids',
	      'tax_query' => array(
	         array(
	            'taxonomy' => 'product_cat',
	            'field' => 'term_taxonomy_id',
	            'terms' => $terms,
	            'operator' => 'IN'
		      )
		   )
	    ) 
	);

    $hidden_cats_IDs = array();
	foreach ( $post_ids as $id ) {
      $hidden_cats_IDs[] = $id;
   	}

    //get products that are out of stock
    $outofstock_term = get_term_by( 'name', 'outofstock', 'product_visibility' );
	$post_ids = get_posts( 
		array(
	      'post_type' => 'product',
	      'numberposts' => -1,
	      'post_status' => 'publish',
	      'fields' => 'ids',
	      'tax_query' => array(
	         array(
	            'taxonomy' => 'product_visibility',
	            'field' => 'term_taxonomy_id',
	            'terms' => array( $outofstock_term->term_taxonomy_id ),
	            'operator' => 'IN'
		      )
		   )
	    ) 
	);

	$outofstock_IDs = array();
	foreach ( $post_ids as $id ) {
      $outofstock_IDs[] = $id;
   	}
    

    //find the overlap
    $overlap = array_intersect ( $hidden_cats_IDs, $outofstock_IDs );

    //remove them from the main query
   	$q->set( 'post__not_in', $overlap );
 
    remove_action( 'pre_get_posts', 'nwp_whc_hide_outofstock_products' );
 
}
add_action( 'pre_get_posts', 'nwp_whc_hide_outofstock_products' );

// Add to new term page
function nwp_whc_added_hide_in_cat( $taxonomy ) { 
	$hide_all_setting = get_option('woocommerce_hide_out_of_stock_items', true);
	$checkbox_output = '<input type="checkbox" id="hide_products_in_cat" name="hide_products_in_cat" value="yes" />';
	if ( $hide_all_setting == 'yes') {
		$checkbox_output = '<strong>Error: </strong> this feature only works when "Hide out of stock items from the catalog" in WooCommerce settings is unchecked';
	}

	?>
    <div class="form-field term-group">
        <label for="hide_products_in_cat">
          Hide products in this category when out of stock <?php echo $checkbox_output; ?>
        </label>
    </div><?php
}
add_action( 'product_cat_add_form_fields', 'nwp_whc_added_hide_in_cat', 99, 2 );

// Add to edit term page
function nwp_whc_edited_hide_in_cat( $term, $taxonomy ) {
    $hide_products_in_cat = get_term_meta( $term->term_id, 'hide_products_in_cat', true ); 

	$hide_all_setting = get_option('woocommerce_hide_out_of_stock_items', true);
	$checked = ( $hide_products_in_cat ) ? checked( $hide_products_in_cat, 'yes', false ) : "";
	$checkbox_output = '<input type="checkbox" id="hide_products_in_cat" name="hide_products_in_cat" value="yes" '.$checked.'/>';
	if ( $hide_all_setting == 'yes') {
		$checkbox_output = '<strong>Error: </strong> this feature only works when "Hide out of stock items from the catalog" in WooCommerce settings is unchecked';
	}
    ?>

    <tr class="form-field term-group-wrap">
        <th scope="row">
            <label for="hide_products_in_cat">Hide products in this category when out of stock</label>
        </th>
        <td>
            <?php echo $checkbox_output; ?>
        </td>
    </tr><?php
}
add_action( 'product_cat_edit_form_fields', 'nwp_whc_edited_hide_in_cat', 99, 2 );

// Save it
function nwp_whc_save_hide_in_cat( $term_id, $tag_id ) {
	$setting = sanitize_key($_POST[ 'hide_products_in_cat' ]);
    if ( !empty( $setting ) ) {
        update_term_meta( $term_id, 'hide_products_in_cat', 'yes' );
    } else {
        update_term_meta( $term_id, 'hide_products_in_cat', '' );
    }
}
add_action( 'created_product_cat', 'nwp_whc_save_hide_in_cat', 10, 2 );
add_action( 'edited_product_cat', 'nwp_whc_save_hide_in_cat', 10, 2 );