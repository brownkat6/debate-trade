//When user click "Unblock User" button, update the database and refresh the page to show the updated button
function cardtrade_unblock_user(user_id){
	jQuery(document).ready(function($) {

		$.ajax({
			type : 'POST',
			url : ajax_object.ajax_url, //admin_url('admin-ajax.php') ,
			//dataType: 'json',
			data: {
					action : 'unblock_user',
					blockedID_post : user_id
					},
			success : function(response){
				 setTimeout(function() 
  				{
    				location.reload();  //Refresh page
  				}, 3000);
			},
			error : function(response){
			 	setTimeout(function() 
  				{
    				location.reload();  //Refresh page
  				}, 3000);
				window.location.reload(true);
			},
			 async: false
		});
				
	});
}

//Call ct_update_stars() with correct number of stars as input whenever mouse hovers over given area	 
function ct_update_stars(num_half_stars, post_id){
	//round num_half_stars to the nearest int
	num_half_stars = Math.round(num_half_stars);
	
	//prevent rating from being below 1 star(2 half stars)
	if(num_half_stars < 2){
		num_half_stars = 2;
	}
	
	var element, width = num_half_stars*10;
	var post_id_fore = String(post_id) + "fore";
	
	jQuery(document).ready(function($) {
	
		$.ajax({
				type : 'POST',
				url : ajax_object.ajax_url, //admin_url('admin-ajax.php') ,
				//dataType: 'json',
				data: {
						action : 'update_post_rating_meta',
						num_half_stars_post : num_half_stars,
						post_id_post: post_id
						},
				success : function(response){

				},
			});
		$('#' + post_id_fore).css("width", width);
		jQuery( '#' . post_id_fore).val( width ).trigger( 'change' );
	});
	
}

//return attributes of given object
function cardtrade_get_attr(obj, attr_name) {
	var attr = obj.attributes;
	for (var i = 0; i < attr.length; i++) {
		val = attr[i].name;
		if (!val.localeCompare(attr_name)) return attr[i].value;
	}
	return "INVALID";
}

//Open new window containing each post corresponding to each post id in the posts_received_array
function cardtrade_open_new_posts(posts_received_array){
	
	posts_received_array.forEach(function(post_id) {
		window.open(get_permalink(post_id));
		});
}

//Update database to unblock given user
function cardtrade_unblock_user(blocked_id){
  	jQuery(document).ready(function($) {

		$.ajax({
			type : 'POST',
			url : ajax_object.ajax_url,
			data: {
					action : 'unblock_user',
					blockedID_post : blocked_id
					},
			success : function(response){
			}
		});
	});
}

//dynamically update the Ninja Form with the correct offer ID so that the trade offer populates the right posts from the database
function cardtrade_setTopicRequest(val)
{
	var inputs;

	inputs = document.getElementsByTagName('input');
	for (var i = 0; i < inputs.length; ++i) {
	  obj = inputs[i];
	    if (!obj.id.substring(0,3).localeCompare('nf-')) {
		if (!cardtrade_get_attr(obj, 'type').localeCompare('hidden')) {

			obj.setAttribute('value',val);
			//because of Backbone model, setAttribute() not enough -- must trigger change 
			//https://developer.ninjaforms.com/codex/changing-field-values/
		 	jQuery( '#' + obj.id ).val( val ).trigger( 'change' );	
		}
	   }
	
	}
	
	

}

//Monitor webpage to trigger event whenever user clicks to rate a post
function addRatingEventListener(){
	//window.alert('Adding event listener...');
	rating_background_buttons = document.getElementsByClassName("ct_rating_background");
	rating_foreground_buttons = document.getElementsByClassName("ct_rating_foreground");
	for(i = 0; i < rating_background_buttons.length; i++){
		rating_background_buttons[i].addEventListener("click", changeRatingBackground);
	}
	for(i = 0; i < rating_foreground_buttons.length; i++){
		rating_foreground_buttons[i].addEventListener("click", changeRatingForeground);
	}
}

//After user rates a post, jQuery updates the rating foreground on the current page to show the user the new rating
function changeRatingForeground(e){
	var mX = e.pageX;
	var post_id = e.target.id;
	//window.alert('changeRating id: ' + post_id + ' ' + mX);
    var distance;
	
	jQuery(document).ready(function($) {
		var id_name = '#' + String(post_id);
		var element  = $(id_name);
		distance = mX - element.offset().left;
	});
	
	if(distance < 100 && distance > 0){
		if(post_id.substring(0,1) != "-"){ //if the rating is not read-only, then update the rating
			ct_update_stars(distance/10, post_id.substring(0,post_id.length-4));
		}
	}
}

//After user rates a post, jQuery updates the rating background on the current page to show the user the new rating
function changeRatingBackground(e){
	var mX = e.pageX;
	var post_id = e.target.id;
    var distance;
	
	jQuery(document).ready(function($) {
		var id_name = '#' + String(post_id);
		var element  = $(id_name);
		distance = mX - element.offset().left;
	});
	
	if(distance < 100 && distance > 0){
		//window.alert('starting if statement');
		if(post_id.substring(0,1) != "-"){ //if the rating is not read-only, then update the rating
			//window.alert('inside if statement');
			ct_update_stars(distance/10, post_id);
			//window.alert('inside if statement finished');
		}
	}
}

// call some ajax to set the offerID in the PHP server-side $_SESSION['cardtrade_offerID']
// once that is done, then refresh the 'respond to offer' ninja form, in a new window
function cardtrade_see_trade_info(offerID) {
	jQuery(document).ready(function($) {

	$.ajax({
        type : 'POST',
        url : ajax_object.ajax_url, //admin_url('admin-ajax.php') ,
        //dataType: 'json',
        data: {
				action : 'view_past_trade',
                offer_id_post : offerID
				},
        success : function(response){
		}
 	});
	  	window.open(ajax_object.site_url.concat("/view-past-trade/"));
	});
}
