jQuery(document).ready( function() {
	jQuery("#top").on('click', 'a.event_choose_branch_link', function() {
		var branchID = jQuery(this).attr('branchID');
		//alert( branchID );
		//action = jQuery(this).attr("action")
		//alert( 'BranchID: ' + branchID );
		
		//alert( 'boo' );
		jQuery.ajax({
			type: 'POST',  
			url: 'admin-ajax.php',
			datatype: "json", 
			data: {  
				action: 'event_choose_branch', 
				branchID: branchID,  
			},  
			success: function(responseRaw){  
					//alert( response );
					var response = eval('(' + responseRaw + ')');
					jQuery("#top").fadeOut('fast',function(){jQuery(this).html(response.top)}).fadeIn("fast");
					jQuery("#room_list").fadeOut('fast',function(){jQuery(this).html(response.room_list	)}).fadeIn("fast");
			}
		});
		return false;
	});

	jQuery("#room_list").on('click', 'a.event_choose_roomCont_link', function() {
		var branchID = jQuery(this).attr('branchID');
		var roomContID = jQuery(this).attr('roomContID');
		//alert( branchID );
		//action = jQuery(this).attr("action")
		//alert( 'BranchID: ' + branchID );
		
		//alert( 'boo' );
		jQuery.ajax({
			type: 'POST',  
			url: 'admin-ajax.php',
			datatype: "json", 
			data: {  
				action: 'event_choose_room', 
				branchID: branchID,  
				roomContID: roomContID,  
			},  
			success: function(responseRaw){  
					//alert( response );
					var response = eval('(' + responseRaw + ')');
					alert( response );
					//jQuery("#top").fadeOut('fast',function(){jQuery(this).html(response.top)}).fadeIn("fast");
					//jQuery("#room_list").fadeOut('fast',function(){jQuery(this).html(response.room_list	)}).fadeIn("fast");
			}
		});
		return false;
	});
	
	jQuery.ajax({
		type: 'POST',  
		url: 'admin-ajax.php',
		datatype: "json", 
		data: {  
			action: 'event_choose_branch'
		},
		success: function(responseRaw){  
				//alert( response );
				var response = eval('(' + responseRaw + ')');
				jQuery("#top").fadeOut('fast',function(){jQuery(this).html(response.top)}).fadeIn("fast");
		}
	});
	return false;
});
