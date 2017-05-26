<?php
	// The function that gets called by the form if ajax isn't active
	/* function process_wccv_copy_variations()	{
		// Check for proper admin privileges
		if (!current_user_can('manage_options')) {
			wp_die( 'You are not allowed to be on this page.' );
		};
		
		// Check the nonce field
		check_admin_referer('wccv_op_verify');
		
		// Get options field/array for this plugin
		$options = get_option('wccv_op_array');
		
		// Set current ID's that are being edited
		if (isset($_POST['wccv_id_to_copy'])) {
			$options['wccv_id_to_copy'] = sanitize_text_field( $_POST['wccv_id_to_copy'] );
			$options['wccv_id_to_write_to'] = sanitize_text_field( $_POST['wccv_id_to_write_to'] );
		};
		
		update_option('wccv_op_array', $options);
		
		wp_redirect(admin_url('options-general.php?page=woocommerce-copy-variations/index.php&m=1'));
		exit;
	}; */	
	
	// The function that gets called from the ajax javacript file
	function wccv_process_ajax() {
		if (!isset($_POST['wccv_nonce']) || !wp_verify_nonce($_POST['wccv_nonce'],'wccv_nonce')) {
			die('<div id="message" class="error"><p>Permissions check failed!</p></div>');
		};
		
		// Get options field/array for this plugin
		$options = get_option('wccv_op_array');
		
		// Set current ID's that are being edited
		if (is_numeric($_POST['wccv_id_to_copy']) && is_numeric($_POST['wccv_id_to_write_to'])) {
			$options['wccv_id_to_copy'] = sanitize_text_field($_POST['wccv_id_to_copy']);
			$options['wccv_id_to_write_to'] = sanitize_text_field($_POST['wccv_id_to_write_to']);
		} else {
			die('<div id="message" class="error"><p>Please fill out both fields as integers</p></div>');
		};
		
		// Set current ID's that are being edited
		if ($_POST['wccv_id_to_copy'] == $_POST['wccv_id_to_write_to']) {
			die('<div id="message" class="error"><p>Can not use identical IDs</p></div>');
		};
		
		// Access database object since this is an include file
		global $wpdb; 
		
		$message = "";
		
		// Post id to copy from
		$id_of_post_to_copy_from = sanitize_text_field($_POST['wccv_id_to_copy']);
		$id_of_post_to_write_to = sanitize_text_field($_POST['wccv_id_to_write_to']); // FUTURE: ACCEPT MULTIPLE IDS IN FORM OF CATEGORIES OR TAGS
		
		// Select posts to copy variations from by id
		$posts_to_copy_from = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_posts WHERE id=%d", $id_of_post_to_copy_from));	
		foreach ($posts_to_copy_from as $post_to_copy_from) {
			$message1 = $post_to_copy_from->post_title;
		};		
		
		// Select posts to copy variations to by id
		$posts_to_edit = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_posts WHERE id=%d", $id_of_post_to_write_to));	
		foreach ($posts_to_edit as $post_to_edit) {			
			$message2 = $post_to_edit->post_title;				
			
			// ------------------CHANGE PARENT META------------------
			// These are the fields that need to be duplicated at the parent level
			delete_and_replace_parent_postmeta("_price",$id_of_post_to_copy_from,$post_to_edit->ID,$message);
			delete_and_replace_parent_postmeta("_product_attributes",$id_of_post_to_copy_from,$post_to_edit->ID,$message);	
			delete_and_replace_parent_postmeta("_min_variation_price",$id_of_post_to_copy_from,$post_to_edit->ID,$message);
			delete_and_replace_parent_postmeta("_max_variation_price",$id_of_post_to_copy_from,$post_to_edit->ID,$message);
			delete_and_replace_parent_postmeta("_min_variation_regular_price",$id_of_post_to_copy_from,$post_to_edit->ID,$message);
			delete_and_replace_parent_postmeta("_max_variation_regular_price",$id_of_post_to_copy_from,$post_to_edit->ID,$message);
			delete_and_replace_parent_postmeta("_min_variation_sale_price",$id_of_post_to_copy_from,$post_to_edit->ID,$message);
			delete_and_replace_parent_postmeta("_max_variation_sale_price",$id_of_post_to_copy_from,$post_to_edit->ID,$message);
			delete_and_replace_parent_postmeta("_default_attributes",$id_of_post_to_copy_from,$post_to_edit->ID,$message);
			
			// ------------------CHANGE PARENT TERMS------------------
			// Set the existing product term to variable if it isn't already
			wp_set_object_terms($post_to_edit->ID,'variable','product_type');
			
			// Delete existing term relationships - FUTURE: NEED A BETTER METHOD FOR THIS. IT'S UNLIKELY, BUT THIS COULD ACCIDENTALLY DELETE NON-WOOCOMMERCE RELATIONSHIPS IF THEY START WITH "pa_".  LOOP THROUGH "wp_woocommerce_attribute_taxonomies" TABLE AND VERIFY AGAINST IT?
			$tax_name = '%' . 'pa_' . '%';		
			$wpdb->query($wpdb->prepare("DELETE FROM wp_term_relationships where object_id=%d and term_taxonomy_id IN (select term_taxonomy_id FROM wp_term_taxonomy WHERE taxonomy LIKE %s)", $post_to_edit->ID, $tax_name));
			
			// Get terms
			$relationships_to_duplcate = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_term_relationships tr LEFT JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id LEFT JOIN wp_terms t ON tt.term_id = t.term_id where tr.object_id=%d and tt.taxonomy LIKE %s", $id_of_post_to_copy_from, $tax_name));
			
			// Loop through these results:
			foreach ($relationships_to_duplcate as $relationship_to_duplcate) {				
				// Set terms
				wp_set_object_terms($post_to_edit->ID,$relationship_to_duplcate->slug,$relationship_to_duplcate->taxonomy,true);							
			};
		
			// ------------------DELETE EXISTING VARIATIONS------------------
			// FUTURE: MODIFY EXISTING VARIATIONS INSTEAD OF DELETING
			// Delete existing variation meta (needs to happen before deleteing the variations themselves):
			$wpdb->query($wpdb->prepare("DELETE FROM wp_postmeta WHERE post_id IN (SELECT id FROM wp_posts WHERE post_type='product_variation' AND post_parent=%d)", $post_to_edit->ID));
			
			// Delete existing variation terms (needs to happen before deleteing the variations themselves):
			$wpdb->query($wpdb->prepare("DELETE FROM wp_term_relationships WHERE object_id IN (SELECT id FROM wp_posts WHERE post_type='product_variation' AND post_parent=%d)", $post_to_edit->ID));
		
			// Delete existing variations:
			$wpdb->query($wpdb->prepare("DELETE FROM wp_posts WHERE post_type='product_variation' AND post_parent=%d", $post_to_edit->ID));
			
			// ------------------CREATE NEW VARIATIONS------------------
			// Get variations:
			$variations_to_duplcate = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_posts WHERE post_type='product_variation' AND post_parent=%d", $id_of_post_to_copy_from));
			
			// Loop through these results:
			foreach ($variations_to_duplcate as $variation_to_duplcate) {			
				
				// ------------------NEW VARIATION POST------------------
				// Use the following values for new post
				$post_author = $variation_to_duplcate->post_author;
				$post_status = $variation_to_duplcate->post_status;
				$comment_status = $variation_to_duplcate->comment_status;
				$ping_status = $variation_to_duplcate->ping_status;
				$post_name = $variation_to_duplcate->post_name;
				$post_name = str_replace($id_of_post_to_copy_from,$post_to_edit->ID,$post_name); //slug: has format of "product-(parent_id)-variation-2"
				$post_parent = $post_to_edit->ID;
				$guid = $variation_to_duplcate->guid;
				$guid = str_replace($id_of_post_to_copy_from,$post_to_edit->ID,$guid); //slug: has format of "product-(parent_id)-variation-2"
				$menu_order =  $variation_to_duplcate->menu_order;
				$post_type =  $variation_to_duplcate->post_type;
				$comment_count =  $variation_to_duplcate->comment_count;
				$post_date = date('Y-m-d H:i:s',current_time('timestamp', 0));
				$post_date_gmt = date('Y-m-d H:i:s',current_time('timestamp', 1));
				$post_modified = date('Y-m-d H:i:s',current_time('timestamp', 0));
				$post_modified_gmt = date('Y-m-d H:i:s',current_time('timestamp', 1));
				
				// Insert new variation post
				$wpdb->insert( 
					'wp_posts', 
					array( 
						'post_author' => $post_author,
						'post_status' => $post_status,
						'comment_status' => $comment_status,
						'ping_status' => $ping_status,
						'post_name' => $post_name,
						'post_parent' => $post_parent,
						'guid' => $guid,
						'menu_order' => $menu_order,
						'post_type' => $post_type,
						'comment_count' => $comment_count,
						'post_date' => $post_date,
						'post_date_gmt' => $post_date_gmt,
						'post_modified' => $post_modified,
						'post_modified_gmt' => $post_modified_gmt
					), 
					array( 
						'%s',
						'%s', 
						'%s', 
						'%s', 
						'%s', 
						'%d', 
						'%s', 
						'%d', 
						'%s', 
						'%d', 
						'%s', 
						'%s', 
						'%s', 
						'%s'
					)
				);
			
				// Get the id of that inserted row
				$new_id = $wpdb->insert_id;
				
				// Update the following value to include the id
				$post_title = "Variation #" . $new_id . " of " . $post_to_edit->post_title; //slug: has format of "Variation #(inserted_id) of (parent_title)"
				
				$wpdb->update( 
					'wp_posts', 
					array( 
						'post_title' => $post_title), // column values
					array(
						'id' => $new_id), // "where" values
					array( 
						'%s'), // format of column values
					array(
						'%d') // format of "where" values
				);				
				
				// ------------------NEW VARIATION META------------------
							
				// Get the metadata for this variation to copy
				$metadata_rows_to_duplcate = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_postmeta WHERE post_id=%d", $variation_to_duplcate->ID));
				
				// Loop through these results
				foreach ($metadata_rows_to_duplcate as $metadata_row_to_duplcate) {
					$this_meta_key = $metadata_row_to_duplcate->meta_key;
					$this_meta_value = $metadata_row_to_duplcate->meta_value;
				
					// Insert new variation post
					$wpdb->insert( 
						'wp_postmeta', 
						array( 
							'post_id' => $new_id,
							'meta_key' => $this_meta_key,
							'meta_value' => $this_meta_value
						), 
						array( 
							'%d',
							'%s', 
							'%s'
						)
					);		
				};
				
				// ------------------NEW VARIATION TERMS------------------				
			
				// Get terms
				$var_relationships_to_duplcate = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_term_relationships tr LEFT JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id LEFT JOIN wp_terms t ON tt.term_id = t.term_id where tr.object_id=%d", $variation_to_duplcate->ID));
				
				// Loop through these results:
				foreach ($var_relationships_to_duplcate as $var_relationship_to_duplcate) {					
					// Set terms
					wp_set_object_terms($new_id,$var_relationship_to_duplcate->slug,$var_relationship_to_duplcate->taxonomy,true);								
				};
						
				
			};
			
		};		
		
		// Update options array to reflect most recent transaction
		update_option('wccv_op_array', $options);
		
		// Display message (returned via ajax)
?>
		<div id="message" class="updated"><p style="color:green">You have successfully copied the variations of Product ID <strong><?php echo esc_html($options['wccv_id_to_copy']); ?>: <?php echo $message1; ?></strong> to Product ID <strong><?php echo esc_html($options['wccv_id_to_write_to']); ?>: <?php echo $message2; ?></strong>!</p></div>
		
<?php			
		die();	
	};
	
	function delete_and_replace_parent_postmeta($meta_key,$id_of_post_to_copy_from,$id_of_post_to_write_to,&$message) {
		
		global $wpdb;
		
		// select post meta to copy from:			
		$parent_meta_to_copy = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_postmeta WHERE meta_key=%s AND post_id=%d", $meta_key, $id_of_post_to_copy_from));
		// delete existing field if exists
		$wpdb->query($wpdb->prepare("DELETE FROM wp_postmeta WHERE meta_key=%s AND post_id=%d", $meta_key, $id_of_post_to_write_to));
		
		// get copy parent post info to copy:
		$meta_value = $parent_meta_to_copy[0]->meta_value;
		
		// insert new duplicated field
		$wpdb->insert( 
			'wp_postmeta', 
			array( 
				'post_id' => $id_of_post_to_write_to,
				'meta_key' => $meta_key, 
				'meta_value' => $meta_value 
			), 
			array( 
				'%d',
				'%s', 
				'%s' 
			)
		);
	};
?>
