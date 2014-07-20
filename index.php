<?php
	/*
	* Plugin Name: WooCommerce Variations Manager
	* Plugin URI: http://findyourpalette.com
	* Description: A plugin to compare and copy product variations from one WooCommerce product to another.
	* Author: Matt Boorstin
	* Version: 1.0
	* Author URI: http://findyourpalette.com
	*/
	
	/*  
		Copyright 2014 Matthew Boorstin (email : matt@findyourpalette.com)
		
		This program is free software; you can redistribute it and/or modify
		it under the terms of the GNU General Public License, version 2, as 
		published by the Free Software Foundation.
		
		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.
		
		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
	*/
	
	// Set up WordPress Plugin
	function wccv_op_setup_options() {
		if (version_compare(get_bloginfo('version'),'3.1','<' )) {
			wp_die("You must update WordPress to use this plugin!");
		};
		
		//FUTURE: Add WooCommerce Version Check
		
		if (get_option('wccv_op_array') === false) {
			$options_array['wccv_op_version'] = '1';
			$options_array['wccv_id_to_copy'] = '';
			$options_array['wccv_id_to_write_to'] = '';
			add_option('wccv_op_array', $options_array);
			add_option('wccv_id_to_copy', $options_array);
			add_option('wccv_id_to_write_to', $options_array);
		} else {
			$wccv_op_existing_options = get_option('wccv_op_array');
			if ($wccv_op_existing_options['wccv_op_version'] < '1' ) {
				$wccv_op_existing_options['wccv_op_version'] = '1';
				update_option('wccv_op_array',$options_array);
			};
		};
	};
	register_activation_hook(__FILE__,'wccv_op_setup_options');
	 
	// Include or Require any files
	include('process.php');
	include('get-data.php');
	
	// Enqueue Javascript
	function wccv_load_scripts($hook) {
		global $wccv_settings;
		if ($hook != $wccv_settings) {
			return;
		};
		wp_enqueue_script('wccv-ajax',plugin_dir_url(__FILE__) . 'js/wccv-ajax.js',array('jquery'));
		wp_localize_script('wccv-ajax','wccv_vars',array(
				'wccv_nonce' => wp_create_nonce('wccv_nonce')
			)
		);
		wp_enqueue_style('wccv-styles',plugin_dir_url(__FILE__) . 'css/wccv-styles.css',array());
	};	
	add_action('admin_enqueue_scripts','wccv_load_scripts');
	
	// Add menu item to sidebar
	function wccv_add_admin_menu() {
		global $wccv_settings;
		$wccv_settings = add_menu_page('WooCommerce Variations Manager','Variations','manage_options',__FILE__,'wccv_display_main_options','','58.2501'); // Last number is menu position
	};
	add_action('admin_menu','wccv_add_admin_menu');
	
	// Actually displays the page linked to in the menu
	function wccv_display_main_options() {
		global $wpdb; 
		$options = get_option('wccv_op_array');
	?>
		<div class="wrap">
			<h2>WooCommerce Variations Manager</h2>		
			<div id="wccv-results">
	<?php
			if (isset($_GET['m']) && $_GET['m'] == '1') {
	?>
				<div id="message" class="updated fade"><p style="color:green">You have successfully copied the variations of Product ID <strong><?php echo esc_html($options['wccv_id_to_copy']); ?></strong> to Product ID  <strong><?php echo esc_html($options['wccv_id_to_write_to']); ?></strong>!</p></div>
	<?php
			};
	?>
			</div>
			<form id="wccv-form" method="post" action="admin-post.php">
			<input type="hidden" name="action" value="wccv_copy_variations" />
			<div id="wccv-full">
			
				<div id="wccv-in">
					<?php //<div id="wccv-in-image"></div> ?>
					<div id="wccv-in-input">
						<p align="center"><strong>ID Of Product To Copy Variations From:</strong></p>
						<input class="wccv-input" type="text" id="wccv_id_to_copy" name="wccv_id_to_copy" value="<?php echo esc_html($options['wccv_id_to_copy']); ?>"/>
						<img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting" id="wccv_in_loading" style="display:none" />
					</div>
					<div id="wccv-in-report">No Product Found</div>
				</div>
				<div id="wccv-arrow">
					<strong>></strong><br /><br />			
					<input type="submit" id="wccv_submit" value="Copy" class="button-primary"/>
					<img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting" id="wccv_loading" style="display:none" />&nbsp;
				</div>
				<div id="wccv-out">
					<?php //<div id="wccv-out-image"></div> ?>
					<div id="wccv-out-input">
						<p align="center"><strong>ID Of Product To Copy Variations To:</strong></p>
						<input class="wccv-input" type="text" id="wccv_id_to_write_to" name="wccv_id_to_write_to" value=""/>
						<img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting" id="wccv_out_loading" style="display:none" />
					</div>
					<div id="wccv-out-report">No Product Found</div>
				</div>
				<div id="wccv-list">
					<p class="wccv-list-title" align="center"><strong>Product List:</strong><br />(Click to load ID to copy to)</p>
					<?php
						$posts_cat = "product_cat";							
						$posts_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_posts p LEFT JOIN wp_term_relationships tr on p.id=tr.object_id LEFT JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id=tt.term_taxonomy_id LEFT JOIN wp_terms t ON tt.term_id=t.term_id WHERE p.post_type='product' AND tt.taxonomy=%s ORDER BY t.name, p.post_title", $posts_cat));
						$current_cat = $posts_data[0]->name;
						$last_cat = $posts_data[0]->name;
						echo '<div class="wccv-list-div">';
						echo '<p class="wccv-product-category"><strong>' . $current_cat . '</strong></p>';								
						echo '<ul class="wccv-product-list">';
						foreach ($posts_data as $post_data) {
							
							$current_cat = $post_data->name;
							if ($current_cat != $last_cat) {
								echo '</ul>';									
								echo '</div>';
								echo '<div class="wccv-list-div">';
								echo '<p class="wccv-product-category"><strong>' . $current_cat . '</strong></p>';								
								echo '<ul class="wccv-product-list">';
							};
					?>
						<li><a href="#" class="wccv_list_item"><?php echo $post_data->object_id; ?></a> - <?php echo $post_data->post_title; ?></li>
					<?php
							$last_cat = $post_data->name;
							if ($current_cat != $last_cat) {
								echo '</ul>';
								echo '</div>';
							};
						}; 
					?>
					</ul>
				</div>
				<div id="wccv-submit">
				</div>
				
			</div>
			</form>
		</div>				
	
		<script type="text/javascript">
			var $j = jQuery.noConflict();
			$j(document).ready(function() {
				$j('.wccv-list-div .wccv-product-list').hide();
				$j('.wccv-product-category').toggle(function() {
					$j(this).siblings('.wccv-product-list').slideToggle("slow");
				},function() {
					$j(this).siblings('.wccv-product-list').slideToggle("slow");
				});
			});
		</script>
	<?php 
	};
	
	// Adds the processing of the new function defined in the plugin page's form when ajax isn't active
	/* add_action('admin_post_wccv_copy_variations','process_wccv_copy_variations' ); */
	
	// Call the functions in the include file when the ajax is triggered
	add_action('wp_ajax_wccv_get_results','wccv_process_ajax');
	add_action('wp_ajax_wccv_get_in_data','wccv_process_data');
	add_action('wp_ajax_wccv_get_out_data','wccv_process_data');
?>