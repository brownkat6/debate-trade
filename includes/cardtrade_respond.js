// Call some ajax to set the offerID in the PHP server-side $_SESSION['cardtrade_offerID']
// once that is done, then refresh the 'respond to offer' ninja form, in a new window
function cardtrade_setSessionOfferID(offerID) {
  jQuery(document).ready(function($) {

	$.ajax({
        type : 'POST',
        url : ajax_object.ajax_url,
        data: {
				action : 'respond_to_offer',
                offerID_post : offerID
				},
        success : function(response){

		}
 	});
	  	window.open(ajax_object.site_url.concat("/respond-to-trade-offer/"));
	});

}
