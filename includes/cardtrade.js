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
				//jQuery('.respond_contents').html(response);
				 setTimeout(function() 
  				{
    				location.reload();  //Refresh page
  				}, 3000);
				//window.location.reload(true);
			},
			error : function(response){
				//jQuery('.respond_contents').html(response);
			 	setTimeout(function() 
  				{
    				location.reload();  //Refresh page
  				}, 3000);
				window.location.reload(true);
			},
			 async: false
		});
				
	});
	/*window.Location.href = window.location.href;
				window.location.reload(false); 
				location.reload(true);
				history.go(0);
	*/
	//then should change button text to say "block" and change function call to cardtrade_block_user or just remove button(that would be easier)
}

//should call ct_update_stars with correct number of stars as input whenever mouse hovers over given area	 
function ct_update_stars(num_half_stars, post_id){
	//round num_half_stars to the nearest int
	num_half_stars = Math.round(num_half_stars);
	
	//prevent rating from being below 1 star(2 half stars)
	if(num_half_stars < 2){
		num_half_stars = 2;
	}
	
	var element, width = num_half_stars*10;
	var post_id_fore = String(post_id) + "fore";
	//window.alert('ct_update_stars started. width: ' + width);
	//currently: program gets to this point and then crashes
	
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
	
	//window.alert("ct_update_stars finished");
}

////does the variable "val" need to be declared as a variable?
function cardtrade_get_attr(obj, attr_name) {
	var attr = obj.attributes;
	for (var i = 0; i < attr.length; i++) {
		//var val = attr[i].name; ???
		val = attr[i].name;
		if (!val.localeCompare(attr_name)) return attr[i].value;
	}
	return "INVALID";
}

function cardtrade_open_new_posts(posts_received_array){
	//should open new window containing each post corresponding to each post id in the posts_received_array
	posts_received_array.forEach(function(post_id) {
		window.open(get_permalink(post_id));
		});
}

function cardtrade_unblock_user(blocked_id){
  	jQuery(document).ready(function($) {

		$.ajax({
			type : 'POST',
			url : ajax_object.ajax_url, //admin_url('admin-ajax.php') ,
			//dataType: 'json',
			//
			data: {
					action : 'unblock_user',
					blockedID_post : blocked_id
					},
			success : function(response){
			//TODO: make popup appear telling user they unblocked the other user
			}
		});
	});
}

function cardtrade_setTopicRequest(val)
{
	var inputs;

	inputs = document.getElementsByTagName('input');
	for (var i = 0; i < inputs.length; ++i) {
		//does "obj" need to be declared as "var obj" ???
	  obj = inputs[i];
	    if (!obj.id.substring(0,3).localeCompare('nf-')) {
 		//alert(obj.id + " " + obj.tagName + " " + obj.innerText + " " + obj.className);	
		if (!cardtrade_get_attr(obj, 'type').localeCompare('hidden')) {
			//alert("Setting hidden ninja form field value from " + obj.value + " to " + val);
			//document.getElementsByTagName('input')[i].setAttribute('value',val);
			obj.setAttribute('value',val);
			//because of Backbone model, setAttribute() not enough -- must trigger change 
			//https://developer.ninjaforms.com/codex/changing-field-values/
		 	jQuery( '#' + obj.id ).val( val ).trigger( 'change' );	
		}
	   }
	
	}
	
	

}

//need to inject html code into webpage <script type="text/javascript"> window.onload=addRatingEventListener; </script>

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
	
	//window.alert('FINISHED Adding event listener...');
		//.addEventListener("click", changeRating);
}


function changeRatingForeground(e){
	//window.alert('ChangeRating Started');
	var mX = e.pageX;
	//background  = $('ct_rating_background');
	var post_id = e.target.id;
	//window.alert('changeRating id: ' + post_id + ' ' + mX);
    var distance;
	
	jQuery(document).ready(function($) {
		var id_name = '#' + String(post_id);
		//window.alert("id name: " + id_name + "mX: " + mX);
		var element  = $(id_name);
		//window.alert("element found");
		distance = mX - element.offset().left;
		//window.alert('distance: ' + distance + ' ' + mX);
	});
    //var distance = 50;
	
	if(distance < 100 && distance > 0){
		//window.alert('starting if statement');
		if(post_id.substring(0,1) != "-"){ //if the rating is not read-only, then update the rating
			//window.alert('calling ct_update_stars');
			ct_update_stars(distance/10, post_id.substring(0,post_id.length-4));
			//window.alert('inside if statement finished');
		}
	}
	//ct_update_stars(distance/10, post_id);
	//window.alert('ChangeRating Finished');
}

function changeRatingBackground(e){
	//window.alert('ChangeRating Started');
	var mX = e.pageX;
	//background  = $('ct_rating_background');
	var post_id = e.target.id;
	//window.alert('changeRating id: ' + post_id + ' ' + mX);
    var distance;
	
	jQuery(document).ready(function($) {
		var id_name = '#' + String(post_id);
		//window.alert("id name: " + id_name + "mX: " + mX);
		var element  = $(id_name);
		//window.alert("element found");
		distance = mX - element.offset().left;
		//window.alert('distance: ' + distance + ' ' + mX);
	});
    //var distance = 50;
	
	if(distance < 100 && distance > 0){
		//window.alert('starting if statement');
		if(post_id.substring(0,1) != "-"){ //if the rating is not read-only, then update the rating
			//window.alert('inside if statement');
			ct_update_stars(distance/10, post_id);
			//window.alert('inside if statement finished');
		}
	}
	//ct_update_stars(distance/10, post_id);
	//window.alert('ChangeRating Finished');
}

function cardtrade_see_trade_info(offerID) {
	// call some ajax to set the offerID in the PHP server-side $_SESSION['cardtrade_offerID']

	// once that is done, then refresh the 'respond to offer' ninja form, like in a new window
	//HttpContext.Current.Session["offerID"] = offerID;
  //window.alert("started cardtrade_see_trade_info");
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
			//jQuery('.respond_contents').html(response);
		}
 	});

	  	window.open(ajax_object.site_url.concat("/view-past-trade/"));
	});
}
