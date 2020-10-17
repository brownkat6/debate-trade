<?php

//Setup tradeoffers table that stores every trade offer and related metadata
function install() {
	global $wpdb;
	$table_name = $wpdb->prefix . "tradeoffers";
	$charset_collate = $wpdb->get_charset_collate();
	$create_ddl = "CREATE TABLE $table_name (
			  offer_id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  init_time DATETIME,
			  last_update DATETIME,
			  requestor_id INT,
			  recipient_id INT,
			  pending_user_id INT,
			  status ENUM('offer_made','counter_offer_made','recipient_accept','counteroffer_accept','recipient_decline','counteroffer_decline') CHARACTER SET binary,
			  requested_list VARCHAR(1000),
			  offered_list VARCHAR(1000),
			  number_of_cards_offered_to_requestor INT,
			  number_of_cards_offered_to_recipient INT,
			  counteroffer_list VARCHAR(1000)
		 ) $charset_collate;";

	$query = $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) );
 
	    if ( $wpdb->get_var( $query ) == $table_name ) {
       		return true;
	    }
 
    	// Didn't find it try to create it..
    	$wpdb->query($create_ddl);
	// We cannot directly tell that whether this succeeded!
	    if ( $wpdb->get_var( $query ) == $table_name ) {
      	  	return true;
	    }
	    return false;
}

if (strnatcmp(phpversion(),'5.4.0') >= 0)
{
	if (session_status() == PHP_SESSION_NONE) {
		session_start();
	}
}
else
{
	if(session_id() == '') {
		session_start();
	}
}

install();

function mylog($txt) {
    file_put_contents('/kunden/homepages/23/d792353330/htdocs/mylogs/php-errors.log', "[" . date(DATE_RFC2822) . "] " . $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
}

//hide the admin bar at the top from all but super-users
function hide_admin_bar() {
	if ( ! current_user_can( 'publish_posts' ) ) {
		return false;
	} else	{ 
		return true; 
	}
}
add_filter( 'show_admin_bar', 'hide_admin_bar' );

//load javascript files
add_action( 'wp_enqueue_scripts', 'cardtrade_custom_script_load' );

//add_action( 'plugins_loaded', 'enqueue_ct_scripts' );

//Setup jQuery update scripts
function cardtrade_custom_script_load(){
  wp_enqueue_script( 'cardtrade-js', plugins_url( 'cardtrade.js', __FILE__ ), array('jquery'));
  wp_enqueue_script( 'ct-script',  plugins_url( 'cardtrade_respond.js', __FILE__ ), array('jquery', 'cardtrade-js'));
	
  wp_localize_script( 'cardtrade-js', 'ajax_object',
        array( 'ajax_url' => admin_url( 'admin-ajax.php' ),
			 	'site_url' => site_url() ,
				'nonce' => wp_create_nonce('ajax-nonce') ));
					 
  wp_localize_script( 'ct-script', 'ajax_object',
        array( 'ajax_url' => admin_url( 'admin-ajax.php' ),
			 	'site_url' => site_url() ,
				'nonce' => wp_create_nonce('ajax-nonce') ));
	
}

// AJAX hooks
add_action('wp_ajax_respond_to_offer',  'ct_respond_to_offer');
add_action('wp_ajax_unblock_user', 'ct_unblock_user');
add_action('wp_ajax_view_past_trade', 'ct_view_past_trade');

//Set Session variable to store the trade id
//Used for the View Past Trades page to populate the past trades data
function ct_view_past_trade($useless){
	error_log("ct_view_past_trade");
	$nonce = $_POST['nonce'];
    if (1) { // (wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
		$past_trade_id = sanitize_text_field($_POST['offer_id_post']); 
		global $_SESSION;
		$_SESSION['past_trade_id'] = $past_trade_id;
		error_log("ct_view_past_trade called with " . $past_trade_id);
	}
}

//Set session variable to store the id of the current trade offer for the user to respond to
function ct_respond_to_offer($useless){
	$nonce = $_POST['nonce'];
    if (1) { //(wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
		$offer_ID = sanitize_text_field($_POST['offerID_post']); 
		$_SESSION['offerID'] = $offer_ID;
		error_log("ct_respond_to_offer() called with $offer_ID");
	}
}

//Return whether the user has permissions to make a trade for this post
//Return false if post authoer has blocked current user
function ct_is_user_blocked($useless){
		$topic_id = sanitize_text_field($_POST['topic_id']);
		$current_user = get_current_user_id();
		$user_being_accessed = bbp_get_topic_author_id( $topic_id );
		$blocked_list = get_user_meta($current_user, "blocked_by_list");
		foreach($blocked_list as $user_id){
			if($user_id == $user_being_accessed){
				return true;
			}
		}
		return false;
}

//Return whether the user has permissions to make a trade for this post
//Return false if post authoer has blocked current user
function ct_is_user_blocked_php($topic_id){
	$current_user = get_current_user_id();
	$user_being_accessed = bbp_get_topic_author_id( $topic_id );
	$blocked_list = get_user_meta($current_user, "blocked_by_list", true);
	if (!empty($blocked_list)) {
	 foreach($blocked_list as $user_id){
		if($user_id == $user_being_accessed){
			return true;
		}
	 }
	}
	return false;
}

//Update user metadata to remove post author from the current user's "blocked" list
function ct_unblock_user($useless){
	$nonce = $_POST['nonce'];
    if (1) {
		//remove current_user_id from $user_id's blocked list
		$user_id = sanitize_text_field($_POST['blockedID_post']);
		$current_user = get_current_user_id();
		$blocked_by_list = get_user_meta($user_id, "blocked_by_list", true);
		delete_user_meta($user_id, "blocked_by_list");

		error_log("unblocking " . $user_id . " from " . $current_user);

		//removes the current user from $blocked_by_list
		for($i = 0; $i < count($blocked_by_list); $i++){
			if($blocked_by_list[$i] == $current_user || $blocked_by_list[$i] == (string)$current_user){
				unset($blocked_by_list[$i]);
			}
		}
		add_user_meta($user_id, "blocked_by_list", $blocked_by_list);

		$blocked_list = get_user_meta($current_user, "blocked_list", true);
		delete_user_meta($current_user, "blocked_list");
		for($i = 0; $i < count($blocked_list); $i++){
			if($blocked_list[$i] == $user_id || $blocked_list[$i] == (string)$user_id){
				unset($blocked_list[$i]);
				//$i = $i-1; this was commented out because it appeared to be causing undefined index errors in php testing window? very odd
			}
		}

		add_user_meta($current_user, "blocked_list", $blocked_list);
	}
}

/**
 * Debugging below:
 * Send error messages to _temp_out.txt error file
*/
define('temp_file', ABSPATH.'/_temp_out.txt' );

add_action("activated_plugin", "activation_handler1");
function activation_handler1(){
    $cont = ob_get_contents();
    if(!empty($cont)) file_put_contents(temp_file, $cont );
}

add_action( "pre_current_active_plugins", "pre_output1" );
function pre_output1($action){
    if(is_admin() && file_exists(temp_file))
    {
        $cont= file_get_contents(temp_file);
        if(!empty($cont))
        {
            echo '<div class="error"> Error Message:' . $cont . '</div>';
            @unlink(temp_file);
        }
    }
}


// the add_action('save_post', ...) function will receive a $_POST[] variable with the submitted form info from the post.  This can be parsed and saved to the database.
add_action('save_post', 'cardtrade_add_topic_meta');

//When post is created, add post metadata to database
function cardtrade_add_topic_meta( $post_id ) { //, $post, $update ) {

	/**
		Do bunch of checks on the $_POST variable, get data, and pack it into $var_to_save

		Assume $_POST has a variable called "isVisibleToAll", etc. which is set up earlier
	*/
	
	//If there was a request for access to restricted topics, process that here
	
	$isVisibleAll = false; //$_POST['isVisibleToAll'];
	if ( ! add_post_meta( $post_id, "isVisibleAll", $isVisibleAll, true ) ) { 
   		update_post_meta( $post_id, "isVisibleAll", $isVisibleAll);
	}

	$isVisibleFriends = false; //$_POST['isVisibleFriends'];
	if ( ! add_post_meta( $post_id, "isVisibleFriends", $isVisibleFriends, true ) ) { 
   		update_post_meta( $post_id, "isVisibleFriends", $isVisibleFriends);
	}
	
	$ListOfUsers = array (); //$_POST[...???
	$UserArrayString = implode(",", $ListOfUsers);
	if ( ! add_post_meta( $post_id, "isVisibleList", $UserArrayString, true ) ) { 
   		update_post_meta( $post_id, "isVisibleList", $UserArrayString);
	}
	
	//$visibility_info_array = array ($isVisibleToAll, $isVisibleToFriends, $UserArrayString);
	//$visibility_info = implode (" ", $visibility_info_array);
	//add_post_meta( $post_id, '_cardtrade_visibility', $visibility_info, false );

	return true;
}

/*Hijack post rendering
 *If current user is author of current post, show post and average rating
 *else if current user has access to current post, show post, average rating, and option for current user to rate the post
 *else if current user is blocked by post author, inform user they have been blocked
 *else if current user does not have access, show average rating and button allowing user to make a trade offer
*/
function cardtrade_filter_reply ($post_content, $reply_id) {
	//If post is user's own, show visibility
	if(get_current_user_id()==0){
		return "Sorry, you must be logged in to view this post and make trade offers.";
	}
	$vis_info = "<style type=\"text/css\"> p.vis {color: #bb3300 !important; font-size: small !important;} </style> <p class=\"vis\"> ";
	$post_user_id = get_post_field( 'post_author', $reply_id );
	if ($post_user_id == get_current_user_id()) {
		if(get_post_type($reply_id)=="reply"){
			return $vis_info . "[This reply visible to all users] </p>" . $post_content;
		}
		if (get_post_meta($reply_id, 'isVisibleAll', true)==true) {
			$vis_info .= "[Visible to all users] </p>";
		}
		else {
			$vis_info .= "[Visible ONLY to trade recipients] </p>";
		}
	}
	else {
		$vis_info = "";
	}
	
	if(get_post_type($reply_id)=="reply"){
		return $post_content;
	}
	$sane_reply_id = sanitize_text_field("$reply_id");
	$new_content = "";
	//prepare the button code
	$button_code = '<button class="pum-trigger  popmake-card_offer "  data-do-default="" onclick=" cardtrade_setTopicRequest(';
	$button_code .= $sane_reply_id;
	$button_code .= ')"> Make Trade Offer </button>'; //try temporary 
	
	if(get_current_user_id() == bbp_get_topic_author_id($sane_reply_id)){
		//$new_content .= sanitize_textarea_field($post_content);
		$new_content .= $post_content;
		$new_content .= make_stars_in_table(get_post_rating_avg($sane_reply_id) * 2, get_post_num_ratings($sane_reply_id), $sane_reply_id);
	}elseif (cardtrade_is_visible($sane_reply_id)==true){
		//leave post_content as is
		//$new_content .= get_post_meta($reply_id, 'isVisibleAll', true);
		$new_content .= " You have access to: " . sanitize_textarea_field($post_content);
		$num_half_stars = get_current_user_post_rating($sane_reply_id) * 2;
		$new_content .= "\r\n Would you like to rate the topic?";
		$new_content .= make_both_ratings($num_half_stars, get_post_num_ratings($sane_reply_id), $sane_reply_id);
	} else {
		//Replace with access request
		//see https://stackoverflow.com/questions/25186140/how-to-use-post-in-wordpress
		if(ct_is_user_blocked_php($sane_reply_id)){
			$new_content = "<p>Sorry, that user has blocked you from trading and you don't have access to their posts.</p>";
		}else{
			$new_content = 'Sorry, you dont have access. ';
			$other_user_id = get_post_field( 'post_author', $sane_reply_id );
			$want = bp_get_profile_field_data( array(
			  'field' => "Want ",
			  'user_id' => $other_user_id
			) );
			$other_user_name = get_the_author_meta('display_name', $other_user_id);
			$new_content .= $button_code;

			//add link to ask author a question -- 8/22/19 JWB
			$my_login_name = strtolower(get_the_author_meta('user_login',get_current_user_id()));
			$author_login_name = strtolower(get_the_author_meta('user_login', $other_user_id));
			$post_permalink = get_post_permalink($reply_id);
			$author_msg_link = site_url() . "/members/$my_login_name/messages/compose/?r=" . $author_login_name;
			$new_content .="<p> or <a href=\"$author_msg_link\">click here to ask a question.</a></p>";

			$new_content .= make_stars_in_table(get_post_rating_avg($sane_reply_id) * 2, get_post_num_ratings($sane_reply_id), $sane_reply_id);
		}
		$num_stars = 0;//user meta query
		//add non-editable star-rating
		
		
		
	}

	return $vis_info . $new_content;
}

add_filter( 'bbp_get_reply_content', 'cardtrade_filter_reply', 10, 2);

//update offer form with user's available posts
//create a new custom copy of the form to avoid collision with simultaneous users
//
add_filter( 'ninja_forms_render_options', 'cardtrade_render_form', 10, 2);
//add_filter('ninja_forms_display_fields', 'cardtrade_render_form');
//add_filter('ninja_forms_display_form_settings', 'cardtrade_render_form');

//Hijack Ninja Forms form rendering for "Make Trade Offer", "Respond to Trade Offer" forms
// Dynamically populate field here with user's available topics to offer...
// Populate offered and requested posts based on Session Variable storing current offer id
//First, get list of topics owned by user
function cardtrade_render_form($options, $settings) {
	//if ( $form_id == 2) {  //update make_offer form
	/******************************************
* SHOW ALL topics IN FIELD WITH KEY "offered_cards"
******************************************/
	
	if (( $settings['key'] == 'card_offered' ) || ( $settings['key'] == 'counteroffer' ) || ($settings['key'] == 'counter_offered_list_given')) {
	   $author_array = array(get_current_user_id());
	   $args = array(
           'post_type' => 'topic',
		   'author__in' => $author_array,
           'orderby' => 'menu_order',
           'order' => 'ASC',
           'posts_per_page' => 100,
           'post_status' => 'publish',
		   'date_query' => array(
				//'after' => $past_date
				array(
        		 'after'     => '6 months ago',
        		 'inclusive' => true,
     			), 
			  )
       );
		error_log("getting query for posts");
       $the_query = new WP_Query( $args ); 
       if ( $the_query->have_posts() ){
           global $post;
           while ( $the_query->have_posts() ){
               $the_query->the_post();
			   $label_val = ct_get_post_title_with_breadcrumbs($post->ID, true);
			   
               $options[] = array('label' => $label_val , 'value' => $post->ID);
           }
           wp_reset_postdata(); 
       }
   }
	if($settings['key'] == 'counter_offered_list_taken'){
		//populate with all posts authored by the other user
		if (!(array_key_exists('offerID', $_SESSION))) {return $options;}
		$offerID = $_SESSION['offerID'];
		
		//Query database based on current offer id to get ids of requestor and recipient users
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$result_row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE offer_id = %d",
												   $offerID));
		foreach($result_row as $row){
			//error_log(gettype($row));
			foreach($row as $key=>$value)
			{
				if($key == "requestor_id"){
					$requestor_id = $value;
				}
				if($key == "recipient_id"){
					$recipient_id = $value;
				}
				
			}
		}
		
		$current_user_id = get_current_user_id();
		if($requestor_id != $current_user_id){
			$other_user_id = $requestor_id;
		}else{
			$other_user_id = $recipient_id;
		}
		$author_array = array($other_user_id);
		$args = array(
           'post_type' => 'topic',
		   'author__in' => $author_array,
           'orderby' => 'menu_order',
           'order' => 'ASC',
           'posts_per_page' => 100,
           'post_status' => 'publish',
			'date_query' => array(
				//'after' => $past_date
				array(
        		 'after'     => '6 months ago',  // or '-2 days'
        		 'inclusive' => true,
     			), 
			  )
       );
       $count_selected = 0;
		$the_query = new WP_Query( $args ); 
       if ( $the_query->have_posts() ){
           global $post;
           while ( $the_query->have_posts() ){
               $the_query->the_post();
			   
			   $label_val = ct_get_post_title_with_breadcrumbs($post->ID, true);
			   $options[] = array('label' => $label_val , 'value' => $post->ID);
           }
		   
           wp_reset_postdata(); 
       }
	}
	if($settings['key'] == 'acceptlist'){
		if (!(array_key_exists('offerID', $_SESSION))) {return $options;}
		$offerID = $_SESSION['offerID'];
		
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$result_row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE offer_id = %d",$offerID ));
		foreach($result_row as $row){
			//error_log(gettype($row));
			foreach($row as $key=>$value)
			{
				if($key == "offered_list"){
					$offered_list_value = $value;
				}
				if($key == "requested_list"){
					$requested_list_value = $value;
				}
				if($key == "recipient_id"){
					$recipient_id = $value;
				}
			}
		}
		if($recipient_id == get_current_user_id()){
			$offered_list = $offered_list_value;
		}else{
			$offered_list = $requested_list_value;
		}
		//array_filter removes any values set to ""
		$offered_id_array = array_filter(explode(',', $offered_list));
		if (! empty($offered_id_array)) {
		 foreach($offered_id_array as $offer_id){
		     //get breadcrumbs
		     if ($offer_id != 'none') { //fixed bug "Respond to trade offer ()" in multi-select 8/19/19 JWB
			$post = get_post($offer_id);
			
			$label_val = ct_get_post_title_with_breadcrumbs($post->ID, true);
			$options[] = array('label' => $label_val, 'value' => $offer_id);//need to know if $options[] statement still works while not in wonky loop used for above population of multi-select
		     }
		 }
		}	
		wp_reset_postdata();
	}
	
	if($settings['key'] == 'pending_offers'){
		error_log("cardtrade_render_form() pending_offers called!");
		$random_val = array();
		error_log("test variable: " . empty($random_val));
		
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$user_id = get_current_user_id();
		$result = $wpdb->get_results ( $wpdb->prepare("SELECT offer_id FROM %s WHERE pending_user_id = %d", array($table_name, $user_id)), ARRAY_A);

		$is_result_empty = empty($result);
		error_log("cardtrade_render_form() pending_offers result = " . implode(', ', $result) . "sizeof result = " . sizeof($result) . ". Result is empty: " . $is_result_empty);
		foreach($result as $offer_id){
			//foreach loop inside foreach loop gets the correct offerid!!!!!!
			foreach($offer_id as $offer){
				error_log("cardtrade_render_form pending_offers offerid = " . $offer);
				error_log("type of offer variable: " . gettype($offer));
				error_log("type of offerid variable: " . gettype($offer_id));
				
				
				$result4 = $wpdb->get_results ( "SELECT status FROM $table_name WHERE offer_id = $offer" );
				foreach($result4 as $status_array){
				//foreach loop inside foreach loop gets the correct status
					foreach($status_array as $status){
						//must set $isUnresolvedOffer to true if status enum = offer_made or counter_offer_made
						if($status == "offer_made" || $status == "counter_offer_made"){
							$isUnresolvedOffer = true;
						}else{
							$isUnresolvedOffer = false;
						}
					}
				}
				
				if($isUnresolvedOffer){
					$result2 = $wpdb->get_results ( "SELECT recipient_id FROM $table_name WHERE offer_id = $offer",ARRAY_A );
					error_log("type of result2 variable: " . gettype($result2));
					$recipient_id = (int)current($result2); //current($arr) returns first value in array
					error_log("recipient_id = " . gettype($recipient_id) . " " . $recipient_id);

					//error_log("cardtrade_render_form() pending_offers offered_list = " . implode(',',$result2->offered_list) . "sizeof result = " . sizeof($result2));
					$is_result2_empty = empty($result2);
					error_log("result2 is empty: " . $is_result2_empty);
					//$mylink->link_id
					$titles_string = "";
					if(!empty($recipient_id)){
						//if($result2->recipient_id == $user_id){
						if($recipient_id == $user_id){
							error_log("cardtrade_render_form() pending_offers recipient_id == user_id");
							//should show offered_list
							//create separate third database call for now
							$result3 = $wpdb->get_results ( "SELECT offered_list FROM $table_name WHERE offer_id = $offer",ARRAY_A );

						}else{
							//should show requested_list
							$result3 = $wpdb->get_results ( "SELECT requested_list FROM $table_name WHERE offer_id = $offer",ARRAY_A );
						}
						$offered_list_string = "";
							foreach($result3 as $offered_string_array){
								foreach($offered_string_array as $offered_string){
									$offered_list_string = $offered_string;
								}
							}
							//$offered_list_string = (string)current($result3); //current($arr) returns first value in array
							$ids_array = explode(",", $offered_list_string);
							error_log("value: " . $offered_list_string . "val of first element in ids_array: " . current($ids_array) . "type: " . gettype($offered_list_string) . "sizeof(ids_array): " . sizeof($ids_array));



							$num_titles = 0;
							while($num_titles < 3 && $num_titles < sizeof($ids_array)){
								$post_id_array = array_slice($ids_array, $num_titles, 1);
								foreach($post_id_array as $postid){
									$post_id = $postid;
								}

								error_log("cardtrade_render_form() pending_offers post_id in loop = " . $post_id);
								$label_val = get_the_title($post_id);
								$titles_string .= substr($label_val, 0, 20) . ", ";
								$num_titles = $num_titles + 1;
							error_log("label_val = " . $label_val . " titles string: " . $titles_string);
							}
							$titles_string = substr($titles_string, 0, (strlen($titles_string)-2));
							if($num_titles > 3){
								$titles_string .= "...";
							}

						$options[] = array('label' => $titles_string, 'value' => $offer);
					

					}
				}
			}
		}
		
		wp_reset_postdata();
	}

	
   return $options;
}

/**param $post_id is the id of the post you need the title for
 * param $shorten is true if you want the post title to be 20 characters or under, false if you don't care about length
 * function returns the post title along with the forum(s) it belongs in
*/
function ct_get_post_title_with_breadcrumbs($post_id, $shorten){
	if ((! isset($post_id)) || ($post_id == null) || ($post_id == 'none')) return '[none]';
	$post = get_post($post_id);
			$post_parent = $post->post_parent;
			$breadcrumb_val = ""; //assertion may change   
			if ($post_parent != null) $breadcrumb_val = get_the_title($post_parent);
			   global $wpdb;
			   $table_name = $wpdb->prefix . "posts";
			   //error_log("post_parent->ID = " . $post_parent->ID);
			   $result_row = $wpdb->get_results( $wpdb->prepare( 
					"SELECT post_parent FROM $table_name WHERE ID = %d", $post_parent),OBJECT
				);
			   //$result_row = $wpdb->get_results ( "SELECT post_parent FROM $table_name WHERE ID = $post_parent" );
			   $post_parent_id = null; //assertion may change
			   
				   foreach($result_row as $post_parent_id_object){
					   //error_log("post_parent " . $post_parent_id_object->post_parent . get_the_title($post_parent_id_object->post_parent));
					   //echo $post_parent_id_object . gettype($post_parent_id_object);
					   //var_dump($post_parent_id_object);
					   if($post_parent_id_object->post_parent != null){
						   $post_parent_id = intval($post_parent_id_object->post_parent);
					   }else{
						   $post_parent_id = null;
					   }
					   
				   }
			   if ($post_parent_id != null) {
				   
			  		 while ($post_parent_id != null && (int)$post_parent_id != 0) {
						 $breadcrumb_val = get_the_title($post_parent_id) . " > " . $breadcrumb_val; 
				   		//$post_parent = $post_parent->post_parent;
				   		$result_row = $wpdb->get_results( $wpdb->prepare( 
							"SELECT post_parent FROM $table_name WHERE ID = %d",
						   $post_parent_id
						) );
						 //$result_row = $wpdb->get_results ( "SELECT post_parent FROM $table_name WHERE ID = $post_parent_id" );
					    foreach($result_row as $post_parent_id_object){
						    //error_log("post_parent " . $post_parent_id_object->post_parent . get_the_title($post_parent_id_object->post_parent));
						    $post_parent_id = $post_parent_id_object->post_parent;
					    }
						 if(sizeof($result_row) < 1 || $post_parent == 0){
							 $post_parent_id = null;
						 }
			   		}
			   }
			
	$post_title = get_the_title($post_id);
	//if $shorten is true, fix the post title length at $max_characters characters or less
	$max_characters = 40;
	if((strlen($post_title) > $max_characters) && $shorten == true){
			$post_title = substr($post_title, 0, $max_characters) . "...";
	}
	$label_val = '#' .$post_id . " " . $post_title . " (" . $breadcrumb_val . ")";
	return $label_val;
}

//Dynamically set offer id in Ninja Form based on current Session Variable offerID
add_filter('ninja_forms_localize_field_hidden', 'cardtrade_set_hidden');
function cardtrade_set_hidden($field){
	//error_log("cardtrade_set_hidden called");
	$settings= $field['settings'];
	if($settings['key'] == 'hidden_offer_id'){
		if (!(array_key_exists('offerID', $_SESSION))) {return $field;}
		$offerID = $_SESSION['offerID'];
		$field['settings']['default'] = $offerID;
	}
	return $field;
}

add_filter('ninja_forms_localize_fields', 'cardtrade_set_options');
function cardtrade_set_options($field){
	//function attempted to set default values for dynamically populated multi-select, failed
	return $field;
}


//Dynamically change ninja form fields with 'localize' function
add_filter('ninja_forms_localize_field_number','cardtrade_set_number', 10, 1);
function cardtrade_set_number($field){
	//error_log("cardtrade_set_number called");
	$settings=$field['settings'];
	if (!(array_key_exists('offerID', $_SESSION))) {return $field;}
	$offer_id = $_SESSION['offerID'];
	if($offer_id == null){
		return $field;
	}
	if ($settings['key'] == 'counter_num_posts_given') {
		$current_user_id = get_current_user_id();
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$result_row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE offer_id = %d",$offer_id ));
		foreach($result_row as $row){
			//error_log(gettype($row));
			foreach($row as $key=>$value)
			{
				if($key == "requestor_id"){
					$requestor_id = $value;
				}
				if($key == "recipient_id"){
					$recipient_id = $value;
				}
				if($key == "number_of_cards_offered_to_recipient"){
					$num_recipient_posts = $value;
					//error_log("num_recipient_posts: " . $num_recipient_posts);
				}
				if($key == "number_of_cards_offered_to_requestor"){
					$num_requestor_posts = $value;
					//error_log("num_requestor_posts: " . $num_requestor_posts);
				}
			}
		}
		if($current_user_id == $requestor_id){
			$default_val = $num_recipient_posts;
		}else{
			$default_val = $num_requestor_posts;
		}
		//error_log("new default val: " . $default_val);
		$field['settings'][ 'default' ] = $default_val;
	}
	if ($settings['key'] == 'counter_num_posts_taken') {
		$current_user_id = get_current_user_id();
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$result_row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE offer_id = %d",$offer_id));
		foreach($result_row as $row){
			//error_log(gettype($row));
			foreach($row as $key=>$value)
			{
				if($key == "requestor_id"){
					$requestor_id = $value;
				}
				if($key == "recipient_id"){
					$recipient_id = $value;
				}
				if($key == "number_of_cards_offered_to_recipient"){
					$num_recipient_posts = $value;
					//error_log("num_recipient_posts: " . $num_recipient_posts);
				}
				if($key == "number_of_cards_offered_to_requestor"){
					$num_requestor_posts = $value;
					//error_log("num_requestor_posts: " . $num_requestor_posts);
				}
			}
		}
		if($current_user_id == $recipient_id){
			$default_val = $num_recipient_posts;
		}else{
			$default_val = $num_requestor_posts;
		}
		//error_log("new default val: " . $default_val);
		$field['settings'][ 'default' ] = $default_val;
	}
	
	return $field;
}

add_filter('ninja_forms_localize_field_textarea','cardtrade_set_offertext', 10, 1);
//Dynamically update ninja form text field to offer a text description of the offer to the user
function cardtrade_set_offertext($field) {
	//>>> change all the form settings here...
	$settings=$field['settings'];
	if (!(array_key_exists('offerID', $_SESSION))) { 
		return $field;
	}
	if (!(array_key_exists('offerID', $_SESSION))){
			$field['settings'][ 'default' ] = "An error occurred. Please close this window, nothing you submit on this form will be processed.";
			return $field;
	}else{
		$offerID = $_SESSION['offerID'];
	}
	
	
	if ($settings['key'] == 'description_of_offer') {
		$message = '';
		//Katrina's code to get array of titles requested
		
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$result_row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE offer_id = %d",$offerID));
		foreach($result_row as $row){
			//error_log(gettype($row));
			foreach($row as $key=>$value)
			{
				if($key == "requestor_id"){
					$requestor_id = $value;
				}
				if($key == "recipient_id"){
					$recipient_id = $value;
				}
				if($key == "requested_list"){
					$requested_list = $value;
				}
				if($key == "offered_list"){
					$offered_list = $value;
				}
				if($key == "number_of_cards_offered_to_recipient"){
					$num_recipient_posts = $value;
				}
				if($key == "number_of_cards_offered_to_requestor"){
					$num_requestor_posts = $value;
				}
				if($key == "pending_user_id"){
					$pending_user_id = $value;
				}
				if($key == "status"){
					$status = $value;
				}
			}
		}
		$current_user_id = get_current_user_id();
		if($pending_user_id != $current_user_id || ($status != "offer_made" && $status != "counter_offer_made")){
			$field['settings'][ 'default' ] = "You are trying to respond to an offer you have already responded to. Please close this window, nothing you submit on this form will be processed.";
			return $field;
		}
		if($requestor_id == $current_user_id){
			$num_allowed = $num_requestor_posts;
			$current_requested_list = $offered_list;
			$current_offered_list = $requested_list;
			$other_user_id = $recipient_id;
		}else{
			$num_allowed = $num_recipient_posts;
			$current_requested_list = $requested_list;
			$current_offered_list = $offered_list;
			$other_user_id = $requestor_id;
		}
		$offered_list_array = array_filter(explode(',', $current_offered_list));
		if(empty($offered_list_array)){
			$offered_list_array = array();
		}
		//if the other person said they offered 9 posts but only offered 3, the textbox will say they offered 3 posts
		if($num_allowed > sizeof($offered_list_array)){
			$num_allowed = sizeof($offered_list_array);
		}
		if($current_requested_list != null && !empty($requestor_id)){
			$offered_id_array = array_filter(explode(',', $current_requested_list));
			if(empty($offered_id_array)){
				$offered_id_array = array();
			}
			$num_wanted = sizeof($offered_id_array);
			$user_info = get_userdata($other_user_id);
			$message = "\r\n" . $user_info->user_login . " has requested " . $num_wanted . " of your post(s):" . "\r\n";
			foreach($offered_id_array as $offered_id){
				//error_log("offered post id = " . $offered_id);
				$label_val = get_the_title($offered_id);
				$message .= "&#9;" . $label_val . "\r\n";
			}
		}
		$want = bp_get_profile_field_data( array(
		  'field' => "Want ",
		  'user_id' => $other_user_id
		  //'profile_group_id' => 1
		) );
		
		$message .= "\r\nWhat they're looking for: \r\n" . $want . "\r\n";
		if($num_allowed == 0){
			$message .= "\r\n" . "They have offered you nothing in return.";
		}else{
			$message .= "\r\n" . "They have offered you " . $num_allowed . " of the posts in the below multiselect in return.";
		}
		if($num_allowed > 1){
			$message .= "\r\n" . "Ctrl-Click to select more than one post in the multiselect.";
		}
		
		wp_reset_postdata();

		
		$field['settings'][ 'default' ] = $message;
	}
	return $field;
}

//Verify form setting functionality
function cardtrade_set_forms($settings, $form_id) {
	//>>> change all the form settings here...
	if (1) { 
		$settings['value'] = 'This is a fire drill 2';
	}
	return $settings;
}

//Return whether current user has access to given post
function cardtrade_is_visible($post_id) {
	//Is given post visible to everyone?
	global $bp;
	if (get_post_meta($post_id, 'isVisibleAll', true)==true) {return true;}
	
	//no? Then is the current user the post_author?
	$the_user=get_current_user_id();
	$the_post=get_post($post_id);
	$post_author=$the_post->post_author;
	if ($post_author == $the_user ) { return true; }
	
	//still no?  Then is it visible to friends, and is the current user a BuddyPress friend of the post_author?
	if (get_post_meta($post_id, 'isVisibleFriends', true)==true) {
		//see if topic poster is friend of user
		$result = friends_check_friendship_status($the_user, $post_author);
		if ( $result == 'is_friend' ) { return true; }
	}

	//still no? Then is the user_id in the access_list?
	$visible_list = get_post_meta($post_id, 'isVisibleList', true); //
	$visible_list_array=explode(',', $visible_list);

	foreach ($visible_list_array as $item) {
		if ($the_user == $item) { return true;}
	}
	
	//still no?  Then access denied
	error_log($the_user . " does not have access to post " . $post_id);
	return false;
}

// Ninja form for request processing below

/**
 * @tag my_ninja_forms_processing
 * @callback my_ninja_forms_processing_callback
 */

add_action('my_ninja_forms_response', 'my_ninja_forms_response_callback');

/**
 * @param $form_data array
 * @return void
 */
add_action( 'my_ninja_forms_offer', 'my_ninja_forms_offer_callback' );
function my_ninja_forms_offer_callback( $form_data ){ //process offer
	global $wpdb;
	
	error_log("my_ninja_forms_offer_callback() called!");
	
    $form_id       = $form_data[ 'form_id' ];
    $form_fields   =  $form_data[ 'fields' ];
    foreach( $form_fields as $field ){
        
        if( 'hidden_requested_topic_2' == $field[ 'key' ] ){
            
            $requested_topic_id = $field [ 'value' ];
        }
		if( 'card_offered' == $field[ 'key' ]) {
			$offered_list = implode(',', $field['value']);
			
			mylog('Cards offered: ' . $offered_list);
		}
		if('number_of_cards_offered' == $field['key']){
			$num_offered = $field['value'];
			if($num_offered == null || $num_offered < 1 || $num_offered > 10){
				$num_offered = 1;
			}
		}
    }
	
	$recipient_id = bbp_get_topic_author_id($requested_topic_id);
	error_log("requested_topic_id: " . $recipient_id . ", " . $requested_topic_id);
	if ($recipient_id ===null) error_log('Error: my_ninja_forms_offer_callback(): recipient_id is NULL');
	
	
    $form_settings = $form_data[ 'settings' ];
    $form_title    = $form_data[ 'settings' ][ 'title' ];
	// Insert the new offer into the tradeoffer table
	
	$wpdb->insert('cardtrade_tradeoffers', array (
		'last_update' => current_time('mysql'),
		'init_time' => current_time('mysql'),
		'requestor_id' => get_current_user_id(),
		'recipient_id' => $recipient_id,
		'pending_user_id' => $recipient_id,
		'status' => 1,
		'requested_list' => $requested_topic_id,
		'offered_list' => $offered_list,
		'number_of_cards_offered_to_recipient' => $num_offered,
		'number_of_cards_offered_to_requestor' => 1,
		'counteroffer_list' => ''
	));
	$insert_id = $wpdb->insert_id; //id of the inserted offer
	
	if ( bp_is_active( 'notifications' ) ) {
        bp_notifications_add_notification( array(
            'user_id'           => $recipient_id,
            'item_id'           => $requested_topic_id,
            'secondary_item_id' => '0',
            'component_name'    => buddypress()->activity->id,
            'component_action'  => 'new_at_mention',
            'date_notified'     => bp_core_current_time(),
            'is_new'            => 1,
        ) );
    }
	
	// Send private message to offer recipient, using BuddyPress message function
	$message = "You have a trade request from " . bp_core_get_user_displayname(get_current_user_id());
	
	$args = array( 'recipients' => $recipient_id, 'sender_id' => get_current_user_id(), 'subject' => 'You have a trade offer', 'content' => $message );
	messages_new_message( $args );
}

//Prepopulate number of cards offered/desired based on database queries
function my_ninja_forms_response_callback( $form_data ){ //process offer
	global $wpdb;
	//if status != 1 or 2 then don't process anything
	error_log("my_ninja_forms_response_callback called");
    $form_id       = $form_data[ 'form_id' ];

    $form_fields   =  $form_data[ 'fields' ];
    foreach( $form_fields as $field ){
		if( 'acceptlist' == $field[ 'key' ] ){
			$post_ids_list = $field['value'];
			for($i = 0; $i < count($post_ids_list); $i++){
				if($post_ids_list[$i] == "none"){
					unset($post_ids_list[$i]);
				}
			}
        }
		if( 'hidden_offer_id' == $field[ 'key' ] ){
            $offer_id = $field['value'];
			if($offer_id == null){
				return;
			}
        }
		if( 'response' == $field[ 'key' ] ){
            $response = $field['value'];
        }
		if( 'counter_offered_list_taken' == $field[ 'key' ] ){
            $counter_offered_list_taken = $field['value'];
			for($i = 0; $i < count($counter_offered_list_taken); $i++){
				if($counter_offered_list_taken[$i] == "none"){
					unset($counter_offered_list_taken[$i]);
				}
			}
        }
		if( 'counter_offered_list_given' == $field[ 'key' ] ){
            $counter_offered_list_given = $field['value'];
			for($i = 0; $i < count($counter_offered_list_given); $i++){
				if($counter_offered_list_given[$i] == "none"){
					unset($counter_offered_list_given[$i]);
				}
			}
        }
		if( 'counter_num_posts_given' == $field[ 'key' ] ){
            $counter_num_posts_given = $field['value'];
        }
	}
	error_log("response = " . $response);
	
	$current_user_id = get_current_user_id();
	
	global $wpdb;
	$table_name = $wpdb->prefix . "tradeoffers";
	$result_row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE offer_id = %d",$offer_id));
	foreach($result_row as $row){
		foreach($row as $key=>$value)
			{
				if($key == "requestor_id"){
					$requestor_id = $value;
				}
				if($key == "recipient_id"){
					$recipient_id = $value;
				}
				if($key == "status"){
					$status = $value;
				}
				if($key == "requested_list"){
					$requested_list = $value;
				}
				if($key == "offered_list"){
					$offered_list = $value;
				}
				if($key == "number_of_cards_offered_to_recipient"){
					$num_recipient_posts = $value;
				}
				if($key == "number_of_cards_offered_to_requestor"){
					$num_requestor_posts = $value;
				}
				if($key == "pending_user_id"){
					$pending_user_id = $value;
				}
			}
	}
	if(!($status == "offer_made" || $status == "counter_offer_made")){
		error_log("user tried to respond to offer when they already responded. Actual status is " . $status);
		return;
	}elseif($pending_user_id != $current_user_id){
		error_log("user tried to respond to offer that was waiting for the other user to respond to.");
		return;
	}else{
		error_log("processing user response to tradeoffer");
	}
	//update last_update time
	$time = current_time('mysql');
	$wpdb->query( $wpdb->prepare("UPDATE $table_name 
           SET last_update = %s 
       	   WHERE offer_id = %s",$time, $offer_id));
	
	//determine whether current user is requestor or recipient
	if($current_user_id == $requestor_id){
		$user_status = "requestor";
		$other_user_id = $recipient_id;
	}else if($current_user_id == $recipient_id){
		$user_status = "recipient";
		$other_user_id = $requestor_id;
	}else{
		return;
	}
	
	//alter $post_ids_list if needed so that it contains less than or equal to the num of allowed cards
	if($user_status == "requestor"){
		$num_allowed = $num_requestor_posts;
		$posts_sent_array = $offered_list;
		$posts_received_array = $requested_list;
	}else{
		$num_allowed = $num_recipient_posts;
		$posts_sent_array = $requested_list;
		$posts_received_array = $offered_list;
	}
	
	if(sizeof($post_ids_list) > $num_allowed){
		//user selected more posts than the other user said they could
		$post_ids_list = array_slice($post_ids_list, 0, $num_allowed);
	}

	if(strcmp($response, "accept") == 0){
		//If user did not fill out acceptlist give error and make them resubmit the form
		//User accepts offer, so change status to accepted
		
		if($status == "offer_made"){
			$new_status = recipient_accept;
		}else{
			$new_status = counteroffer_accept;
		}
		//now update status
		$wpdb->query( $wpdb->prepare("UPDATE $table_name 
               SET status = %s 
        	   WHERE offer_id = %s",$new_status, $offer_id));
		
		//depending on whether current user is requestor or recipient, update either the offered_list or the requested_list in the database
		if($user_status == "requestor"){
			//update requested_list in database
			$wpdb->query( $wpdb->prepare("UPDATE $table_name 
               SET requested_list = %s 
        	   WHERE offer_id = %s",implode(",",$post_ids_list), $offer_id));
		}else{
			//update offered_list in database
			$wpdb->query( $wpdb->prepare("UPDATE $table_name 
               SET offered_list = %s 
        	   WHERE offer_id = %s",implode(",",$post_ids_list), $offer_id));
		}
		
		//add offer_id to "past_trades" user meta
		$past_trades_recipient = get_user_meta($recipient_id, "past_trades",true);
		if($past_trades_recipient == null){
			$past_trades_recipient = array();
		}
		$past_trades_recipient[] = $offer_id;
		update_user_meta($recipient_id, "past_trades", $past_trades_recipient);
		
		$past_trades_requestor = get_user_meta($requestor_id, "past_trades",true);
		if($past_trades_requestor == null){
			$past_trades_requestor = array();
		}
		$past_trades_requestor[] = $offer_id;
		update_user_meta($requestor_id, "past_trades", $past_trades_requestor);
		
		//Only give user access to each post if they already don't have access and only add that $post_id if it hasn't already been added
		//then give recipient_id user access to posts in offered_list
		$offered_list_array = explode(",",$offered_list);
		$current_user_past_traded_posts_list = get_user_meta($recipient_id, "past_traded_posts",true);
		if($current_user_past_traded_posts_list == null){
			$current_user_past_traded_posts_list = array();
		}
		foreach($offered_list_array as $post_id){
			$current_access_list = get_post_meta($post_id, "isVisibleList",true);
			error_log("post " . $post_id . " current_access_list = " . $current_access_list);
			if($current_access_list == null){
				$current_access_list = $recipient_id;
			}else{
				$current_access_list .= "," . $recipient_id;
			}
			update_post_meta( $post_id, "isVisibleList", $current_access_list);
			
			//meta "past_traded_posts is an associative array with key=post_id and value=trade_offer_id
			$current_user_past_traded_posts_list[$post_id] = $offer_id;
		}

		update_user_meta($recipient_id, "past_traded_posts", $current_user_past_traded_posts_list);
		
		//then give requestor_id access to posts in requested_list
		$requested_list_array = explode(",",$requested_list);
		$current_other_user_past_traded_posts_list = get_user_meta($requestor_id, "past_traded_posts",true);
		if($current_other_user_past_traded_posts_list == null){
			$current_other_user_past_traded_posts_list = array();
		}
		foreach($requested_list_array as $post_id){
			$current_access_list = get_post_meta($post_id, "isVisibleList",true);
			if($current_access_list == null){
				$current_access_list = $requestor_id;
			}else{
				$current_access_list .= "," . $requestor_id;
			}
			update_post_meta( $post_id, "isVisibleList", $current_access_list);
			
			//meta "past_traded_posts" is an associative array with key=post_id and value=trade_offer_id
			$current_other_user_past_traded_posts_list[$post_id] = $offer_id;
		}
		
		update_user_meta($requestor_id, "past_traded_posts", $current_other_user_past_traded_posts_list);

		//Give addresses to share google docs with
		$user_gmail_addr = bp_get_profile_field_data( array(
			  'field' => "Gmail_address ",
			  'user_id' => $current_user_id
			) );
		if (empty($user_gmail_addr)) {$user_gmail_addr = wp_get_current_user()->user_email;}
		$other_gmail_addr = bp_get_profile_field_data( array(
			  'field' => "Gmail_address ",
			  'user_id' => $other_user_id
		) );
		if (empty($other_gmail_addr)) {
			$other_user = get_user_by('id', $other_user_id);
			$other_gmail_addr = $other_user->user_email;
		}
		
		// Now message the current user informing them of acceptance
		$message = "You accepted the trade from " . bp_core_get_user_displayname($other_user_id) . "! ";
		$message .= " (If you are using google docs, be sure to share them with your trade partner's gmail address " . $other_gmail_addr .")";
		$subject = 'You accepted a trade';
		
		// private message to $other_user_id informing them the trade was accepted, using BuddyPress message function and message to $current_user_id informing they they accepted a trade
		$message2 = "Your trade with " . bp_core_get_user_displayname($current_user_id) . " was accepted! ";
		$message2 .= "Go to Past Trades to view your new posts!";
		$message2 .= " (If you are using google docs, be sure to share them with your trade partner's gmail address " . $user_gmail_addr .")";
		$subject2 = 'Your trade was accepted';
		

	}elseif(strcmp($response, "counter") == 0){
		//user makes counter offer
		//uses $counter_offered_list_taken, $counter_num_posts_given
		
		//update database with new offer information
		//must update correct num_cards variable, correct offered list of cards
		error_log("User wants to make a counteroffer");
		if($user_status == "requestor"){
			//'offered_list' = $counter_offered_list_taken
				//update offered_list in database
				$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET offered_list = %s 
        	   		WHERE offer_id = %s",implode(",",$counter_offered_list_given), $offer_id));
				//update requested_list in database
				$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET requested_list = %s 
        	   		WHERE offer_id = %s",implode(",",$counter_offered_list_taken), $offer_id));
				//update num_requested in database
				$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET number_of_cards_offered_to_requestor = %s 
        	   		WHERE offer_id = %s",sizeof($counter_offered_list_taken), $offer_id));
			if($counter_num_posts_given != null){
				$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET number_of_cards_offered_to_recipient = %s 
        	   		WHERE offer_id = %s",$counter_num_posts_given, $offer_id));
			}
			
		}else{
			//'offered_list' = $counter_offered_list_taken
				//update offered_list in database
				$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET offered_list = %s 
        	   		WHERE offer_id = %s",implode(",",$counter_offered_list_taken), $offer_id));
				//update requested_list in database
				$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET requested_list = %s 
        	   		WHERE offer_id = %s",implode(",",$counter_offered_list_given), $offer_id));
				$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET number_of_cards_offered_to_recipient = %s 
        	   		WHERE offer_id = %s",sizeof($counter_offered_list_given), $offer_id));
			
			if($counter_num_posts_given != null){
				//update requested_list in database
				$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET number_of_cards_offered_to_requestor = %s 
        	   		WHERE offer_id = %s",$counter_num_posts_given, $offer_id));
			}
		}
		$wpdb->query( $wpdb->prepare("UPDATE $table_name 
				    SET pending_user_id = %s 
        	   		WHERE offer_id = %s",$other_user_id, $offer_id));
		error_log("New updated user_id = " . $other_user_id);
		
		//change status to counter_offer_made
		$wpdb->query( $wpdb->prepare("UPDATE $table_name 
               SET status = %s 
        	   WHERE offer_id = %s",'counter_offer_made', $offer_id));
		
		// private message to $other_user_id informing them the trade was countered, using BuddyPress message function and message to $current_user_id informing they countered a trade
		$message = "You countered a trade from " . bp_core_get_user_displayname($other_user_id) . ". ";
		$subject = 'You countered a trade';
		
		$message2 = "Your trade with " . bp_core_get_user_displayname($current_user_id) . " was countered! ";
		$subject2 = 'Your trade was countered';
	}else{
		//user either blocked or rejected the offer
		error_log("user rejects the offer");
		if($response == "block"){
			//block the requesting user
			if($current_user_id == $recipient_id){
				$id_to_be_blocked = $requestor_id;
			}else{
				$id_to_be_blocked = $recipient_id;
			}
			
			$current_blocked_list = get_user_meta($id_to_be_blocked, "blocked_by_list",true);
			//delete_user_meta($id_to_be_blocked, "blocked_by_list");
			
			//check if user has already been blocked
			$user_not_already_blocked = true;
			if($current_blocked_list != null){
				foreach($current_blocked_list as $user_id){
					if($user_id == $current_user_id){
						$user_not_already_blocked = false;
					}
				}
			}
			
			//only block user if user is not already blocked
			if($user_not_already_blocked){
				error_log("blocking user " . $id_to_be_blocked . " because they are not already blocked.");
				if($current_blocked_list == null){
					$current_blocked_list = array((int)$current_user_id);
				}else{
					//array_push($current_blocked_list, $current_user_id);
					$current_blocked_list[] = (int)$current_user_id;
				}
				update_user_meta( $id_to_be_blocked, "blocked_by_list", $current_blocked_list);
				//error_log("blocked_meta: " . current(get_user_meta($id_to_be_blocked, "blocked_by_list")));

				$current_block_others_list = get_user_meta($current_user_id, "blocked_list",true);
				//Check to see if meta exists or not, determines whether to update or add meta. use: metadata_exists('user',$user_id,'blocked_list')
				
				if($current_block_others_list == null){
					$current_block_others_list = array($id_to_be_blocked);
				}else{
					//array_push($current_block_others_list, $current_user_id);
					$current_block_others_list[] = $id_to_be_blocked;
				}
				error_log("new user blocked: ". $current_block_others_list[0]);
				update_user_meta($current_user_id, "blocked_list", $current_block_others_list);

				//The reject messages are set to the subject and message variables that are finally sent at the end of the function
				//message users about the blocking
				$reject_message = "You were blocked from trading with " . bp_core_get_user_displayname($current_user_id);
				$reject_subject = 'You were blocked';
				$reject_message2 = "You blocked " . bp_core_get_user_displayname($other_user_id) . ". ";
				$reject_subject2 = 'You blocked someone.';
				$reject_subject2 = 'Click the Blocked Users bar at the profile icon.';
				$args = array( 'recipients' => $other_user_id, 'sender_id' => $current_user_id, 'subject' => $reject_subject, 'content' => $reject_message );
				messages_new_message( $args );
				$args2 = array( 'recipients' => $current_user_id, 'sender_id' => $current_user_id, 'subject' => $reject_subject2, 'content' => $reject_message2 );
				messages_new_message( $args2 );
			}else{
				error_log("did not block user " . $id_to_be_blocked . " because they were already blocked.");
			}
		}

		//now reject the offer
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT status FROM $table_name WHERE offer_id = %d",$offer_id));
		foreach($result as $status_array){
		//foreach loop inside foreach loop gets the correct status
			foreach($status_array as $status){
				//error_log("my_ninja_forms_my_trades_callback old status = " . $status);
				//must convert status into new status
				if($status == "offer_made"){
					$new_status = recipient_decline;
				}else{
					$new_status = counteroffer_decline;
				}
			}
		}
		//now update status
		$wpdb->query( $wpdb->prepare("UPDATE $table_name 
               SET status = %s 
        	   WHERE offer_id = %s",$new_status, $offer_id));
		
		// private message to $other_user_id informing them the trade was rejected, using BuddyPress message function and message to $current_user_id informing they rejected a trade
		$message = "You rejected a trade from " . bp_core_get_user_displayname($other_user_id) . ". ";
		$subject = 'You rejected a trade';
		
		$message2 = "Your trade with " . bp_core_get_user_displayname($current_user_id) . " was rejected. ";
		$subject2 = 'Your trade was rejected';
	}
	//send notifications to both users about the trade response
	if ( bp_is_active( 'notifications' ) ) {
			bp_notifications_add_notification( array(
				'user_id'           => $recipient_id,
				'item_id'           => $offer_id,
				'secondary_item_id' => '0',
				'component_name'    => buddypress()->activity->id,
				'component_action'  => 'new_at_mention',
				'date_notified'     => bp_core_current_time(),
				'is_new'            => 1,
			) );
    	}
		
	if ( bp_is_active( 'notifications' ) ) {
			bp_notifications_add_notification( array(
				'user_id'           => $requestor_id,
				'item_id'           => $offer_id,
				'secondary_item_id' => '0',
				'component_name'    => buddypress()->activity->id,
				'component_action'  => 'new_at_mention',
				'date_notified'     => bp_core_current_time(),
				'is_new'            => 1,
			) );
    	}
	
	//send messages
	// Currently second message is from the current user to the current user, which is weird. Maybe send from the website overlord instead.
	$args = array( 'recipients' => $current_user_id, 'sender_id' => $current_user_id, 'subject' => $subject, 'content' => $message );
	messages_new_message( $args );
	$args2 = array( 'recipients' => $other_user_id, 'sender_id' => $current_user_id, 'subject' => $subject2, 'content' => $message2 );
	messages_new_message( $args2 );
	//error_log("Finished processing callback");
	
}

//-------------------
// Make custom rendering of offer list:
// Labeled "Pending Offers" page on the site
function cardtrade_get_offer_list( $args ){
	//If current user not logged in, show nothing
	if(get_current_user_id() == 0){
		echo "<p>Sorry, you need to be logged in to view pending offers.</p>";
		return;
	}
	
	
	echo "<style type=\"text/css\"> #rating_stars_be_vertical{vertical-align: 80% !important; width: 110px !important;}</style>";
	echo "<font size=\"4\"><table> <tr>   <th>Offer</th>     <th> </th> <th>User</th> <th width=\"120\">User Rating</th> <th> </th> </tr>";
	//echo "<table> <tr>   <th>Offer</th>     <th> </th> <th>User</th> OfferID<th width=\"120\">User Rating and Number of Ratings         </th> </tr>";
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$user_id = get_current_user_id();
		$result = $wpdb->get_results( "SELECT offer_id FROM $table_name WHERE pending_user_id =$user_id ORDER BY offer_id DESC",ARRAY_A);
		
		$is_result_empty = empty($result);
		
		foreach($result as $offer_id_array){
			//foreach loop inside foreach loop gets the correct offerid
			//creates new array that converts string offer_id's into int offer_id's
			$offer_id_array_int = array();
			foreach($offer_id_array as $offer_id_int){
				$offer_id_array_int[] = (int)$offer_id_int;
			}

			foreach($offer_id_array_int as $offer){
				//only display the offer if the status enum equals 1 or 2 and the offer has not been resolved
				$result4 = $wpdb->get_results( $wpdb->prepare( "SELECT status FROM $table_name WHERE offer_id = %d",$offer));
				foreach($result4 as $status_array){
				//foreach loop inside foreach loop gets the correct status
					foreach($status_array as $status){
						//must set $isUnresolvedOffer to true if status enum = offer_made or counter_offer_made
						if($status == "offer_made" || $status == "counter_offer_made"){
							$isUnresolvedOffer = true;
						}else{
							$isUnresolvedOffer = false;
						}
					}
				}
				
				if($isUnresolvedOffer){
					$result2 = $wpdb->get_results( $wpdb->prepare( "SELECT recipient_id FROM $table_name WHERE offer_id = %d",$offer),ARRAY_A );
					foreach($result2 as $recipient_array){
						foreach($recipient_array as $recipient){
							$recipient_id = $recipient;
						}
					}
					
					$result4= $wpdb->get_results( $wpdb->prepare( "SELECT requestor_id FROM $table_name WHERE offer_id = %d",$offer),ARRAY_A );
					foreach($result4 as $requestor_id_array){
						foreach($requestor_id_array as $requestor_id_in_loop){
							$requestor_id = $requestor_id_in_loop;
						}
					}
					$is_result2_empty = empty($result2);
					$titles_string = "";
					if(!empty($recipient_id)){
						if($recipient_id == $user_id){
							//Show offered_list
							//create separate third database call for now
							$result3=$wpdb->get_results($wpdb->prepare( "SELECT offered_list FROM $table_name WHERE offer_id = %d",$offer),ARRAY_A);
						}else{
							//should show requested_list
							$result3=$wpdb->get_results( $wpdb->prepare( "SELECT requested_list FROM $table_name WHERE offer_id = %d",$offer),ARRAY_A );
						}
						$offered_list_string = "";
							foreach($result3 as $offered_string_array){
								foreach($offered_string_array as $offered_string){
									$offered_list_string = $offered_string;
								}
							}
							//$offered_list_string = (string)current($result3);
							$ids_array = array_filter(explode(",", $offered_list_string));
							$num_titles = 0;
							while($num_titles < 3 && $num_titles < sizeof($ids_array)){
								$post_id_array = array_slice($ids_array, $num_titles, 1);
								foreach($post_id_array as $postid){
									$post_id = $postid;
								}

								//error_log("cardtrade_render_form() pending_offers post_id in loop = " . $post_id);
								if($post_id != "none"){
									$label_val = get_the_title($post_id);
									$titles_string .= substr($label_val, 0, 20) . ", ";
								}
								$num_titles = $num_titles + 1;
							
							}
							if($titles_string == ""){
								$titles_string .= "Nothing";
							}else{
								$titles_string = substr($titles_string, 0, (strlen($titles_string)-2));
							}
							if($num_titles > 3){
								$titles_string .= "...";
							}

						
					$targ_page = site_url() . "/respond-to-trade-offer?offerID=$offer" ;
						$titles_string = sanitize_text_field($titles_string);
					echo "<tr>   <td>$titles_string</td> ";
					echo "<td> <button onclick=\"cardtrade_setSessionOfferID($offer)\"> Respond </button></td>";
					
					if(get_current_user_id() == $recipient_id){
						$other_user_id = (int)$requestor_id;
					}else{
						$other_user_id = (int)$recipient_id;
					}
					
					$username = strtolower(get_userdata($other_user_id)->user_login);
						
					$user_url = bp_core_get_user_domain( $other_user_id ) . "profile";
					
					echo "<td> <a href = \"" . site_url() . "/members/$username/profile/\"> $username </a></td>";
						
					$rating = round((double)get_user_rating_avg($other_user_id), 1);
					$num_ratings = "(" . (int)get_user_num_ratings($other_user_id) . ")";
					$stars = make_stars($rating * 2, $num_ratings, -1);//-1 means that this rating is read-only
					echo "<td id=\"rating_stars_be_vertical\"> $stars</td>";
					echo "<td> $num_ratings</td>";
					echo "</tr>";
					

					}
				}
			}
		}
	
	echo "</table></font>";
}

//Create Page "Past Trades"
function cardtrade_get_past_trades_list( $args ){
	if(get_current_user_id()==0){
		echo "Sorry, you must be logged in to view this page.";
		return;
	}
	$past_trades_array = get_user_meta(get_current_user_id(), "past_traded_posts",true);
	echo "<font size=\"4\"><table> <tr>   <th>Post</th>     <th>Date</th> <th>Post Author</th> <th>Trade Number</tr>";
	if (is_string($past_trades_array)) {wp_parse_str($past_trades_array, $past_trades);}
	else {$past_trades = $past_trades_array;}
	
	//Need to sort table rows by date. To do so, sort them in descending order by trade_id.
	arsort($past_trades);
	
	foreach(array_keys($past_trades) as $post_id){
		//get the title of the post obtained, with breadcrumbs
		if($post_id != ""){
			$label_val = ct_get_post_title_with_breadcrumbs($post_id, true);
		}else{
			$label_val = "Nothing";
		}
		
		//get additional table variables
		$author_id = bbp_get_topic_author_id($post_id);
		$author_name = bp_core_get_user_displayname($author_id);
		$user_url = get_author_posts_url( $author_id);
		$url = get_permalink($post_id);
		
		//get date of trade offerid associated with $post_id key
		$offer_id = $past_trades_array[$post_id];
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$result_row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE offer_id = %d",$offer_id));
		$sql_date = "";
		foreach($result_row as $row){
			foreach($row as $key=>$value)
				{
					if($key == "last_update"){
						$sql_date = $value;
						error_log("sql_date: " . $sql_date);
					}
				}
		}
		$readable_date = strtotime($sql_date);
		$readable_date = date("m/d/y", $readable_date);
		
		
		
		echo "<tr>   <td><a href = \"$url\">$label_val</a></td> ";
		echo "<td>$readable_date</td>";
		//echo "<td> <a href = \"$user_url\">$author_name</a></td>";
		$tmp_url = bp_core_get_user_domain( $author_id ) . "profile";
		echo "<td> <a href = \"$tmp_url\"> $author_name </a></td>";
		echo "<td> <button onclick = \"cardtrade_see_trade_info($offer_id);\">$offer_id</button></td>";
		echo "</tr>";
	}
	echo "</table></font>";
}

//Create Page "Blocked Users"
function cardtrade_get_blocked_users_list( $args ){
	if(get_current_user_id()==0){
		echo "Sorry, you must be logged in to view this page.";
		return;
	}
	$blocked_user_ids_array = get_user_meta(get_current_user_id(), "blocked_list",true);
	
	echo "<style type=\"text/css\"> #rating_stars_be_vertical{vertical-align: top !important; width: 110px !important;} #num_ratings_align_left{ text-align: left !important; vertical-align: top !important; width: 20px !important;}</style>";
	echo "<font size=\"4\"><table> <tr>   <th>Username</th> <th>Rating</th><th> </th> <th>Unblock</th> </tr>";
	if (!empty($blocked_user_ids_array)) {
	 foreach($blocked_user_ids_array as $blocked_user_id){
		error_log("blocked_user_id: " . $blocked_user_id);
		if(gettype($blocked_user_id) != "array"){
			$user_url = bp_core_get_user_domain( $blocked_user_id ) . "profile";
			$username = bp_core_get_user_displayname($blocked_user_id);
			echo "<tr><td style=\"width:120px !important;\"> <a href = \"$user_url\">$username</a> </td>";

			$rating_avg = ((get_user_rating_avg($blocked_user_id) * 2))/((double)2);
			$rating_formatted = "(" . $rating_avg . ")";
			$num_ratings = (int)get_user_num_ratings($blocked_user_id);
			$stars = make_stars($rating_avg * 2,  -1);//-1 means that this rating is read-only
			echo "<td id = \"rating_stars_be_vertical\"> $stars</td>";
			echo "<td id = \"num_ratings_align_left\"> $rating_formatted</td>";
			echo "<td> <button onclick = \"cardtrade_unblock_user($blocked_user_id); setTimeout(function() {location.reload();}, 2000); \">Unblock</button></td>"; // window.location.reload(true);
			echo "</tr>";
		}
	 }
	}
	echo "</table></font>";
}
	
//Create Page "Reputations"
function cardtrade_get_reputations($args){
	if(get_current_user_id()==0){
		echo "Sorry, you must be logged in to view this page.";
		return;
	}
	//Show: username, number of trades, rating
	echo "<style type=\"text/css\"> #rating_stars_be_vertical{vertical-align: top !important; width: 110px !important;} #num_ratings_align_left{ text-align: left !important; vertical-align: top !important; width: 20px !important;}</style>";
	echo "<font size=\"4\"><table><tr><th>User</th><th>Total Trades</th><th>Rating</th><th> </th></tr>";
	
	//needs to order them by sizeof past_trades array from most trades to fewest trades
	$args  = array(
		'number' => 50,
		'meta_key' => 'past_trades', // required for the orderby parameter
		'orderby' => 'meta_value',
		'order' => 'DESC'
	);

	$user_query = new WP_User_Query( $args );
	$users = $user_query->get_results();
	
	foreach($users as $user_object){
		$user_id = $user_object->ID;
		error_log("display reputation of " . $user_id);
		//echo "<td>user id is " . $user_id . "</td>";
		$username = bp_core_get_user_displayname($user_id);
		$user_url = bp_core_get_user_domain( $user_id ) . "profile";
		$rating_avg = ((get_user_rating_avg($user_id) * 2))/((double)2);
		$num_ratings = (int)get_user_num_ratings($user_id);
		$rating_formatted = "(" . $num_ratings . ")";
		
		$stars = make_stars($rating_avg * 2,  -1);//-1 means that this rating is read-only
		
		//Find number of unique values in associative array of past_traded_posts
		$num_trades = sizeof(get_user_meta($user_id, "past_trades", true));
		
		echo "<tr><td><a href = \"$user_url\">$username</a></td>";
		echo "<td>$num_trades</td>";
		echo "<td id = \"rating_stars_be_vertical\"> $stars</td>";
		echo "<td id = \"num_ratings_align_left\"> $rating_formatted</td></tr>";
	}
	echo "</table></font>";
}

//Create "View Past Trades" Page that user is redirected toward
function cardtrade_view_past_trade($args){
	if(get_current_user_id()==0){
		echo "Sorry, you must be logged in to view this page.";
		return;
	}
	global $_SESSION;
	$trade_id = $_SESSION['past_trade_id'];
	if($trade_id == null){
		echo "The page timed out, please close this window";
		return;
	}
		$message = '';
		
		global $wpdb;
		$table_name = $wpdb->prefix . "tradeoffers";
		$result_row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE offer_id = %d",$trade_id));
		foreach($result_row as $row){
			//error_log(gettype($row));
			foreach($row as $key=>$value)
			{
				if($key == "requestor_id"){
					$requestor_id = $value;
				}
				if($key == "recipient_id"){
					$recipient_id = $value;
				}
				if($key == "requested_list"){
					$requested_list = $value;
					error_log("requested_list: " . $requested_list);
				}
				if($key == "offered_list"){
					$offered_list = $value;
					error_log("offered_list: " . $offered_list);
				}
				if($key == "number_of_cards_offered_to_recipient"){
					$num_recipient_posts = $value;
				}
				if($key == "number_of_cards_offered_to_requestor"){
					$num_requestor_posts = $value;
				}
				if($key == "pending_user_id"){
					$pending_user_id = $value;
				}
				if($key == "status"){
					$status = $value;
				}
				if($key == "last_update"){
					$sql_date = $value;
				}
			}
		}
		$current_user_id = get_current_user_id();
		
		if($requestor_id == $current_user_id){
			$current_requested_list = $offered_list;
			$current_offered_list = $requested_list;
			$other_user_id = $recipient_id;
		}else{
			$current_requested_list = $requested_list;
			$current_offered_list = $offered_list;
			$other_user_id = $requestor_id;
		}
		if(!empty($other_user_id)){
			$user_info = get_userdata($other_user_id);
			
		}
		
	$readable_date = strtotime($sql_date);
	$readable_date = date("m/d/y", $readable_date);
	
	
	$user_url = site_url() . "/members/" . $user_info->user_login . "/profile/";
	$message .= "You traded with <a href = \"$user_url\">" . $user_info->user_login . "</a> on " . $readable_date . ". <p>";
	
		if(!empty($current_requested_list)){
			$offered_id_array = explode(',', $current_requested_list);
			$num_wanted = sizeof($offered_id_array);
			$message .= $user_info->user_login . " obtained " . $num_wanted . " of your posts:" . "<br>";
			foreach($offered_id_array as $offered_id){
				$label_val = get_the_title($offered_id);
				$post_url = get_permalink($offered_id);
				$message .= "&nbsp;&nbsp;&nbsp;&nbsp;<a href = \"$post_url\">" . $label_val . "</a><br>";
			}
			$message .= "<p>";
		}else{
			error_log("current_requested_list not found");
			$message .= $user_info->user_login . " didn't get any of your posts." . "<p>";
		}
		
	
	if(!empty($current_offered_list)){
			$requested_id_array = explode(',',$current_offered_list);
			$num_allowed = sizeof($requested_id_array);
			$message .= "In return, you got " . $num_allowed . " of their posts:" . "<br>";
			foreach($requested_id_array as $requested_id){
				//error_log("offered post id = " . $offered_id);
				$label_val = get_the_title($requested_id);
				$post_url = get_permalink($requested_id);
				$message .= "&nbsp;&nbsp;&nbsp;&nbsp;<a href = \"$post_url\">" . $label_val . "</a><br>";
			}
			$message .= "<p>";
		}else{
			error_log("current_offered_list not found");
			$message .= "You didn't want any of their posts." . "<p>";
		}
		//$message .= "<table class=\"wp-block-table\"><tbody><tr><td>";
	echo $message;	
	wp_reset_postdata();
}

//Create button to unblock user
function cardtrade_get_unblock_user_button($args){
	$author_id = 1;
	//$author_id = bbp_get_topic_author_id();
	$current_user = get_current_user_id();
	$blocked_list = get_user_meta($current_user, "blocked_list");
	$user_is_blocked = false;
	foreach($blocked_list as $user_id){
		if($user_id == $author_id){
			$user_is_blocked = true;
		}
	}
	if($user_is_blocked){
		echo "<button onclick = cardtrade_unblock_user($author_id)>Unblock</button>";
	}
}

//Remove unnecessary BP user profile pages
add_action('bp_setup_nav', 'remove_user_profile_pages', 100 );
function remove_user_profile_pages(){

  buddypress()->members->nav->edit_nav( array( 
    'position' => 100, 
  ), 'activity' );
  buddypress()->members->nav->edit_nav( array( 
    'position' => 1, 
  ), 'photos' );
  bp_core_remove_nav_item('home');
	bp_core_remove_nav_item('friends');
  bp_core_remove_nav_item('forums');
	bp_core_remove_nav_item('projects');
	bp_core_remove_nav_item('home');
	bp_core_remove_nav_item('docs');
	bp_core_remove_nav_item('activity');
  bp_core_remove_nav_item('groups');
  bp_core_remove_nav_item('notifications');
  
}

//function redirects any user that goes to the admin page
function ct_redirect_admin(){
    if ( ! current_user_can( 'edit_posts' ) && (! wp_doing_ajax())){
        wp_redirect( site_url() );
        exit;
    }
}
add_action( 'admin_init', 'ct_redirect_admin' );

//removes admin toolbar
add_filter('show_admin_bar', '__return_false');

// change name of "from" emails from site
function ct_wp_mail_from ($email) {
  return "support@debatetrade.com";
}

function ct_wp_mail_from_name ($from_name) {
  return "DebateTrade Moderator";
}

add_filter('wp_mail_from_name', 'ct_wp_mail_from_name');
add_filter('wp_mail_from', 'ct_wp_mail_from');

// fix broken bp member permalinks
function ct_bp_get_member_permalink(){
	global $members_template;
	$url = get_site_url();
	$login_name =$members_template->member->user_login; 
	return $url.'/members/'.$login_name.'/profile';
}
add_filter('bp_get_member_permalink', 'ct_bp_get_member_permalink');

function ct_bbp_get_user_profile_url($url, $user_id, $user_nicename){
mylog('CALLING!!!');
	$url = trailingslashit($url);
	if (substr($url, -8, 7) != 'profile') { $url .= 'profile';}
mylog('Called ct_bbp_get_user_profile_url ' . $url);
	return url;
}
add_filter('bbp_get_user_profile_url', 'ct_bbp_get_user_profile_url', 10, 3);

//fix bbp user profile urls
function ct_bbp_pre_get_user_profile_url($user_id) {
	return trailingslashit($user_id) .'profile';
}
add_filter('bbp_pre_get_user_profile_url','ct_bbp_pre_get_user_profile_url', 20, 1);

add_action('bbp_theme_before_reply_form_notices','ct_theme_before_reply_form');
function ct_theme_before_reply_form() {
	echo "<style type=\"text/css\"> p.vis {color: #bb3300 !important; font-size: small !important;} </style> <p class=\"vis\"> [This is a comment that EVERYONE will be able to see] </p>";

}

?>
