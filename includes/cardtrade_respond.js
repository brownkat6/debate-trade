function cardtrade_setSessionOfferID(offerID) {
	// check this out:  https://www.w3schools.com/js/js_ajax_intro.asp
	// call some ajax to set the offerID in the PHP server-side $_SESSION['cardtrade_offerID']

	// once that is done, then refresh the 'respond to offer' ninja form, like in a new window
	//HttpContext.Current.Session["offerID"] = offerID;
  jQuery(document).ready(function($) {

	$.ajax({
        type : 'POST',
        url : ajax_object.ajax_url, //admin_url('admin-ajax.php') ,
        //dataType: 'json',
        data: {
				action : 'respond_to_offer',
                offerID_post : offerID
				},
        success : function(response){
			//jQuery('.respond_contents').html(response);

		}
 	});
/*
		$.ajax({
		 url: '/echo/html',
		 data: {},
		 success: function(){
			 //window.open(plugins_url( 'readme.txt', __FILE__ ) );
			 //BUG:  site_url is a PHP function!!
			 window.open(ajax_object.site_url.concat("/respond-to-trade-offer/"));
		 },
		 async: true
		});
*/
	  	window.open(ajax_object.site_url.concat("/respond-to-trade-offer/"));
	});

}
