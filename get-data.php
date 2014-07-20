<?php
	// The function that gets called from the ajax javacript file
	function wccv_process_data() {
		if (!isset($_POST['wccv_nonce']) || !wp_verify_nonce($_POST['wccv_nonce'],'wccv_nonce')) {
			die('Permissions check failed!');
		};
		
		// Set current ID's that are being edited
		if (!is_numeric($_POST['wccv_id_to_copy'])) {
			die('Please fill out field as integer');
		};
		
		// Access database object since this is an include file
		global $wpdb; 
		
		// Post id to copy from
		$id_of_post = sanitize_text_field($_POST['wccv_id_to_copy']);
		$image = wp_get_attachment_image_src(get_post_thumbnail_id($id_of_post),'thumbnail');
		$message = '<p align="center"><img src="' . $image[0] . '" /></p>';
		
		// Select posts to copy variations from by id
		$posts_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_posts WHERE id=%d", $id_of_post));
		
		if (!empty($posts_data)) {
		
			foreach ($posts_data as $post_data) {	
			
				if ($post_data->post_type == "product") {			
				
					$message .= "<strong>Product: " . $post_data->post_title . "</strong><br/>";
					
					$post_product_details = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_postmeta WHERE post_id = %d AND meta_key = '_price' ORDER BY post_id DESC,meta_key", $id_of_post));	
					
					$message .= "<strong>Base Price: $" . $post_product_details[0]->meta_value . "</strong><br /><br />";
					
					$post_variations = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_posts WHERE post_parent=%d AND post_type='product_variation' ORDER BY id", $id_of_post));	
					
					foreach ($post_variations as $post_variation) {
						$message .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $post_variation->post_title . ":<br/>";
						
						$var_attrs = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_postmeta WHERE post_id=%d AND meta_key LIKE %s", $post_variation->ID, '%' . like_escape("attribute") . '%')); //must use "like_escape" because Wordpress reserves the % for placeholders
						foreach ($var_attrs as $var_attr) {
							$attr_name = $var_attr->meta_key;
							$attr_name = str_replace("attribute_pa_","",$attr_name);
							$attr_name = str_replace("-"," ",$attr_name);
							$message .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . ucfirst($attr_name) . ": " . $var_attr->meta_value . "<br/>";
						};
						
						$var_prices = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_postmeta WHERE post_id=%d AND meta_key='_regular_price'", $post_variation->ID));	
						foreach ($var_prices as $var_price) {
							$message .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Price: $" . $var_price->meta_value . "<br/>";
						};
						
						$var_classes = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_terms t LEFT JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id LEFT JOIN wp_term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id=%d AND tt.taxonomy='product_shipping_class'", $post_variation->ID));	
						foreach ($var_classes as $var_class) {
							$message .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Shipping Class: " . $var_class->name . "<br/><br/>";
						};
					};
					
					$message .= '<p align="center"><a href="post.php?post=' . $id_of_post . '&action=edit">Edit This Product</a></p>';					
				
				} else {
			
					$message = "No Product Found";
					
				};

			};
				
			
		} else {
			
			$message = "No Product Found";
			
		};
		
		echo $message;
		
		die();
	};
?>