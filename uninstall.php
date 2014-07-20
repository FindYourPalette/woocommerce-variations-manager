<?php
	if (!defined('WP_UNINSTALL_PLUGIN')) {
	   exit;  
	};
	 
	if (false != get_option('wccv_op_array')) {
	   delete_option('wccv_op_array');
	};
?>