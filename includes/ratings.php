<?php

//Listen for whenever the post rating is manipulated by user
function ct_js_ratings_header() {
    ?>
            <script type="text/javascript"> window.onload=addRatingEventListener;</script>
    <?php
}
add_action('wp_head', 'ct_js_ratings_header');

//return the current average rating for the current post
function get_current_user_post_rating($post_id){
	$current_user = get_current_user_id();
	$rating_list = get_post_meta($post_id, "ratings_list",true);
	$user_rating = 0;
	if($rating_list == null){
		return 0;
	}
	foreach($rating_list as $key=>$value){
		if($key==$current_user){
			$user_rating = $value;
		}
	}
	error_log("user_rating = " . $user_rating);
	return $user_rating;
}

//AJAX hook:
add_action('wp_ajax_update_post_rating_meta', 'ct_update_post_rating_meta');

//Update ratings_avg and ratings_list meta for author of current rated post
//Update ratings_avg and ratings_list meta for current rated post
function ct_update_post_rating_meta($useless){
	if(get_current_user_id()==0){
		return;
	}
	$nonce = $_POST['nonce'];
    if (1) { //(wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
		$post_id = $_POST['post_id_post'];
		if (!cardtrade_is_visible($post_id)) return; //keep out hackers!
		error_log("ct_update_post_rating_meta num_half_stars " . $_POST['num_half_stars_post']);
		$num_stars = ((double)$_POST['num_half_stars_post']) / 2;
		$post_author = bbp_get_topic_author_id($post_id);
		$current_user = get_current_user_id();
		error_log("ct_update_post_rating_meta where num_stars = " . $num_stars . " for post " . $post_id);

		//update user meta with total list of ratings
		$current_post_ratings_list = get_post_meta($post_id, "ratings_list",true);
	    
		//if rating from $current_user exists, update it with new rating.
		//otherwise, add new entry to associative array with key -> $current_user and value -> $num_stars
		if(sizeof($current_post_ratings_list) < 1 || $current_post_ratings_list == null){
			error_log("current_posts_ratings_list doesn't exist, so it was created");
			$current_post_ratings_list = array();
		}
		if(array_key_exists ( $current_user , $current_post_ratings_list )){
			$current_post_ratings_list[$current_user] = $num_stars;
		}else{
			error_log("ct_update_post_rating_meta adding rating of " . $num_stars . " from " . $current_user);
			$current_post_ratings_list[$current_user] = $num_stars;
			error_log("new rating from current_user = " . $num_stars);
		}
		update_post_meta($post_id, "ratings_list", $current_post_ratings_list);

		//update post meta with average rating score
		$total_post_rating = array_sum($current_post_ratings_list);
		$avg_post_rating = $total_post_rating / sizeof($current_post_ratings_list);
		$avg_post_rating = round($avg_post_rating, 1);
		update_post_meta($post_id, "ratings_avg", $avg_post_rating);


		//update user meta with total list of ratings
		$current_user_ratings_list = get_user_meta($post_author, "ratings_list",true);

		if(sizeof($current_user_ratings_list) < 1 || $current_user_ratings_list == null){
			$current_user_ratings_list = array();
		}
		
		//key should be post id + id of user rating the post
		$ratings_key = $post_id . " " . $current_user;
		if(array_key_exists ( $ratings_key , $current_user_ratings_list )){
			$current_user_ratings_list[$ratings_key] = $num_stars;
		}else{
			$current_user_ratings_list[$ratings_key] = $num_stars;
		}

		update_user_meta($post_author, "ratings_list", $current_user_ratings_list);

		//update user meta with average rating score
		$total_rating = array_sum($current_user_ratings_list);
		$avg_rating = $total_rating / sizeof($current_user_ratings_list);
		$avg_rating = round($avg_rating, 1);
		update_user_meta($post_author, "ratings_avg", $avg_rating);
	}
}

//Return the average of every rating of every post authored by the given user
function get_user_rating_avg($user_id){
	$avg_meta = get_user_meta($user_id, "ratings_avg",true);
	if($avg_meta == null){
		return 0;
	}else{
		return round($avg_meta,1);
	}
}

//Return the number of ratings other users have given to the given user
function get_user_num_ratings($user_id){
	$meta_array = get_user_meta($user_id, "ratings_list",true);
	if($meta_array == null){
		return 0;
	}else{
		return sizeof($meta_array);
	}
}

//Return the average of all ratings given to the given post
function get_post_rating_avg($post_id){
	$avg_meta = get_post_meta($post_id, "ratings_avg",true);
	if($avg_meta == null){
		return 0;
	}else{
		return round($avg_meta,1);
	}
}

//Return the number of ratings assigned to the given post
function get_post_num_ratings($post_id){
	$meta_array = get_post_meta($post_id, "ratings_list",true);
	if($meta_array == null){
		return 0;
	}else{
		return sizeof($meta_array);
	}
}

//Return HTML/CSS code to create the average and current user's ratings for the given post
function make_both_ratings($num_half_stars, $num_ratings, $post_id){
	$num_read_only_half_stars = get_post_rating_avg($post_id) * 2;
	$read_only_stars = make_stars($num_read_only_half_stars, "-" . $post_id);
	$num_current_user_half_stars = get_current_user_post_rating($post_id) * 2;
	error_log("read only half: " . $num_read_only_half_stars . " current user rating: " . $num_current_user_half_stars);
	$stars = make_stars($num_current_user_half_stars, $post_id);
	$num_ratings_formatted = "(" . $num_ratings . ")";
	$stars_table = "<style type=\"text/css\"> .both_rating_table { table-layout: fixed !important; width: 350px !important; background:transparent !important;} .rating_stars{vertical-align: top !important; width: 110px !important;} #num_ratings_both{ text-align: left !important; vertical-align: middle !important; width: 5px !important;}</style>";
	$stars_table .= "<table class=\"both_rating_table\"> <tr><th>Avg Rating:</th><th> </th><th>I Rate it:</th></tr>";
	$stars_table .= "<tr><td class=\"rating_stars\">$read_only_stars</td><td id=\"num_ratings_both\">$num_ratings_formatted</td>";
	$stars_table .= "<td class=\"rating_stars\">$stars</td></tr></table>";
	return $stars_table;
}

//Return HTML/CSS code to create the table version of the average ratings for the given post
function make_stars_in_table($num_half_stars, $num_ratings, $post_id){
	$stars = make_stars($num_half_stars, "-" . $post_id);
	$num_ratings_formatted = "(" . $num_ratings . ")";
	$stars_table = "<style type=\"text/css\"> .rating_table { table-layout: fixed !important; width: 145px !important; border: 0 !important; background:transparent !important;} #rating_stars{vertical-align: top !important; width: 120px !important;} #num_ratings{text-align: right; !important; vertical-align: middle !important; width: 25px !important;}</style>";
	$stars_table .= "<table class=\"rating_table\"> <tr><th>Rating</th><th> </th></tr>";
	$stars_table .= "<tr><td id=\"rating_stars\">$stars</td><td id=\"num_ratings\">$num_ratings_formatted</td></tr></table>";
	return $stars_table;
}

//Return the HTML/CSS code to display the given rating for the given post
function make_stars($num_half_stars, $post_id){
	//$rating_image = wp_review_get_rating_image();
	$rating_value = (int)$num_half_stars * 10; //conversion factor;
	
	error_log("rating_value pixel width: " . $rating_value);
	$rating_color = "Yellow";
	$rating_image = site_url("/wp-content/plugins/cardtrade_plugin/images/new_yellow_star_fat.gif");
	$rating_image_gray = site_url("/wp-content/plugins/cardtrade_plugin/images/new_star_experiment_tiny_gray.gif");
	$inactive_color = "Gray";
	$rating_icon = $rating_image;
	$post_id_fore = (string)$post_id . "fore";
	
	$content = "
<style type=\"text/css\">
<!--
	
	#" . $post_id_fore . "{
	width:" . $rating_value . "px !important;
	}
	
	div.ct_rating_background {
    background-image:url(\"$rating_image_gray\");
	color: gray;
	
    height:20px;
    margin-left:auto;
    margin-right:auto;
    position:absolute;
    width:100px;
} 
div.ct_rating_foreground {
    background-image:url(\"$rating_image\");
    height:20px;
	color: yellow;
    left:0px; /* play around with this */
    position:relative;
    top:0px; /* and play around with this */
} 
-->
</style>
";
	$content .= "<div class = \"innerwrapper\" </div>
	<div class=\"ct_rating_background\" id=\"$post_id\">
<div class = \"ct_rating_foreground\" id=\"$post_id_fore\" style=\"width:" . $rating_value . "px\"></div>
</div>";

<div class="review-result-wrapper"<?php if ( $inactive_color ) echo " style=\"color: {$inactive_color};\""; 
	<?php
		for ( $i = 1; $i <= 5; $i++ ) :
			if ( $rating_image ) {
				?>
				<img src="<?php echo esc_url( $rating_image ); ?>" class="wp-review-image" />
				<?php
			} else {
				?>
				<i class="<?php echo esc_attr( $rating_icon ); ?>"></i>
				<?php
			}
		endfor;
		?>
	<div style="width:<?php echo floatval( ( $rating_value * 20 ) ); ?>%; color:<?php echo esc_attr( $rating_color ); ?>;">
			<?php
			for ( $i = 1; $i <= 5; $i++ ) :
				if ( $rating_image ) {
					?>
					<img src="<?php echo esc_url( $rating_image ); ?>" class="wp-review-image" />
					<?php
				} else {
					?>
					<i class="<?php echo esc_attr( $rating_icon ); ?>"></i>
					<?php
				}
			endfor;
			?>
		</div>
	</div>
	*/
	return $content;
}


//Modify the name of the author everywhere on the site to also include their average user rating
//This function renames the author in the context of "topic" type posts
function filter_bbp_get_topic_author_display_name( $wptexturize, $topic_id ) { 
    // make filter magic happen here... 
    //$author_id = bbp_get_topic_author_id();//get_the_author_meta("ID");
    /*
    $author_id = bbp_get_topic_author_id( $topic_id );
	$num_stars = get_user_rating_avg($author_id);
	$num_ratings = get_user_num_ratings($author_id);
	//error_log("user_rating_avg and user_num_ratings types: " . gettype($num_stars) . gettype($num_ratings));
	if($num_ratings < 1){
		return $wptexturize . ": 0 ratings";
	}
    return $wptexturize . ": " . $num_stars . " stars (" . $num_ratings . ")"; 
	*/
	return filter_bbp_get_reply_author_display_name( $wptexturize, $topic_id);
}; 
         
// add the filter 
add_filter( 'bbp_get_topic_author_display_name', 'filter_bbp_get_topic_author_display_name', 10, 2 ); 

//Modify the name of the author everywhere on the site to also include their average user rating
//This function renames the author in the context of "reply" type posts
function filter_bbp_get_reply_author_display_name( $wptexturize, $topic_id) { 
    // make filter magic happen here... 
    $author_id = bbp_get_topic_author_id( $topic_id );
	$num_stars = get_user_rating_avg($author_id);
	$num_ratings = get_user_num_ratings($author_id);
	if($num_ratings > 0){
		if($num_stars == 1){
			$message = " " . "1 star (" . $num_ratings . ")";
		}
		$message = " " . $num_stars . " stars (" . $num_ratings . ")";
		//$message = make_stars($num_stars*2, $topic_id);
		//$message .= " (" . $num_ratings . ")";
	}else{
		$message = "0 ratings";
	}
	$wants = bp_get_profile_field_data( array(
			  'field' => "Want ",
			  'user_id' => $author_id
			  //'profile_group_id' => 1
			) );
	if(strlen($wants) > 0){
		$wptexturize .= "<p>$message</p><p><strong>Wants: $wants</strong></p>";
	}
    return $wptexturize; 
}; 
         
// add the filter 
add_filter( 'bbp_get_reply_author_display_name', 'filter_bbp_get_reply_author_display_name', 10, 2 ); 


// define the bbp_get_topic_title callback 
//Add the average post rating to the name of each topic
//Rendered inactive to reduce site clutter
function filter_bbp_get_topic_title( $title, $topic_id ) { 
    /**
	$num_stars = get_post_rating_avg($topic_id);
	$num_ratings = get_post_num_ratings($topic_id);
    return $title . $num_stars . " out of " . (int)$num_ratings . " ratings"; */
	return $title;
}; 
         
// add the filter 
add_filter( 'bbp_get_topic_title', 'filter_bbp_get_topic_title', 10, 2 ); 
?>
