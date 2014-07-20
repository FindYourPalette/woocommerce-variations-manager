jQuery(document).ready(function($) {
	data = { //check the last copied data when page loads
		action: 'wccv_get_in_data',
		wccv_nonce: wccv_vars.wccv_nonce,
		wccv_id_to_copy: $('#wccv_id_to_copy').val()
	};	
	$.post(ajaxurl,data,function(response) {
		$('#wccv-in-report').html(response);
	});	
	
	$('#wccv_submit').attr('disabled',true); //disable submit button until both fields are valid products
	
	$('#wccv_id_to_copy').keyup(function() {
		$('#wccv_in_loading').show();
		data = {
			action: 'wccv_get_in_data',
			wccv_nonce: wccv_vars.wccv_nonce,
			wccv_id_to_copy: $('#wccv_id_to_copy').val()
		};		
		$.post(ajaxurl,data,function(response) {
			$('#wccv-in-report').html(response);
			$('#wccv_in_loading').hide();
			if(($('#wccv-in-report').html() == "No Product Found") || ($('#wccv-out-report').html() == "No Product Found")) {
				$('#wccv_submit').attr('disabled',true);
			} else {
				$('#wccv_submit').attr('disabled',false); //reactivate submit button once 2 valid ids have been entered				
			};
		});		
	});
	
	$('#wccv_id_to_write_to').keyup(function() {
		$('#wccv_out_loading').show();
		data = {
			action: 'wccv_get_out_data',
			wccv_nonce: wccv_vars.wccv_nonce,
			wccv_id_to_copy: $('#wccv_id_to_write_to').val()
		};		
		$.post(ajaxurl,data,function(response) {
			$('#wccv-out-report').html(response);
			$('#wccv_out_loading').hide();
			if(($('#wccv-in-report').html() == "No Product Found") || ($('#wccv-out-report').html() == "No Product Found")) {
				$('#wccv_submit').attr('disabled',true);
			} else {
				$('#wccv_submit').attr('disabled',false); //reactivate submit button once 2 valid ids have been entered				
			};
		});		
	});
	
	$('#wccv-form').submit(function() {
		//alert('test');
		$('#wccv_loading').show();
		$('#wccv_submit').attr('disabled',true);
		data = {
			action: 'wccv_get_results',
			wccv_nonce: wccv_vars.wccv_nonce,
			wccv_id_to_copy: $('#wccv_id_to_copy').val(),
			wccv_id_to_write_to: $('#wccv_id_to_write_to').val()
		};
		
		$.post(ajaxurl,data,function(response) {
			$('#wccv-results').html(response);
			$('#wccv_loading').hide();
			$('#wccv_submit').attr('disabled',false);
			$('#wccv_id_to_write_to').val("");
			$('#wccv-out-report').html("No Product Found");
		});
		
		return false;
	});
	
	$('.wccv_list_item').click(function() {
		var $id = $(this).text();
		$('#wccv_id_to_write_to').val($id);
		$('#wccv_out_loading').show();
		data = {
			action: 'wccv_get_out_data',
			wccv_nonce: wccv_vars.wccv_nonce,
			wccv_id_to_copy: $('#wccv_id_to_write_to').val()
		};		
		$.post(ajaxurl,data,function(response) {
			$('#wccv-out-report').html(response);
			$('#wccv_out_loading').hide();
			if(($('#wccv-in-report').html() == "No Product Found") || ($('#wccv-out-report').html() == "No Product Found")) {
				$('#wccv_submit').attr('disabled',true);
			} else {
				$('#wccv_submit').attr('disabled',false); //reactivate submit button once 2 valid ids have been entered				
			};
		});	
	});
});