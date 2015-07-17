<?php
/*
* Plugin Name: Ban User (WP Hacks)
* Version: 1.0.0
* Description: Prevent Users from logging in.
* Author: wphacks
* Author URI: 
* Text Domain: wphacks
* License: GPL v2 or later
*/ 

/*
TODO: url hook for displaying denied message on custom login pages
TODO: Add Ajax hook for ban / unban user
TODO: Add User Column / quick ban/unban
TODO: 
*/

/*
change the capability needed to ban a user by adding the following lines to your theme's functions.php, replacing 'edit_user', with your own capability

add_filter('wphacks_ban_user_cap', 'replace_wphacks_ban_user_capability');
function replace_wphacks_ban_user_capability($cap){
	return 'edit_user';
}

*/
add_action( 'edit_user_profile', 'wphacks_edit_user_profile_add_banned_option', 10, 1 );
add_action( 'edit_user_profile_update', 'wphacks_edit_user_profile_update_save_banned_option', 10, 1 );
add_filter( 'authenticate', 'wphacks_ban_user_check', 100, 3 );
add_action( 'wp_ajax_wphacks_ban_user', 'wphacks_ajax_ban_user' );
add_action( 'wp_ajax_wphacks_unban_user', 'wphacks_ajax_unban_user' );
add_action( 'wp_ajax_wphacks_parse_url', 'wphacks_ajax_parse_url' );
/*
Don't want the user row actions?
add the following line to your theme's functions.php file.
remove_filter( 'user_row_actions', 'wphacks_user_row_actions_add_ban_unban_user' ); //Plugin: Ban User (WP Hacks) - This removed the use quick action to ban and unban the user from the user list. 
*/
add_filter( 'user_row_actions', 'wphacks_user_row_actions_add_ban_unban_user', 10, 2);
add_action( 'admin_enqueue_scripts', 'wphacks_admin_enqueue_ban_user_js' );

function wphacks_edit_user_profile_add_banned_option($profile_user){
?>
<table class="form-table">
	<tr class="wphacks-user-banned">
		<th scope="row"><label><?php echo __('Banned?','wphacks'); ?></label></th>
		<td>
		<?php 
		$banned = get_user_meta($profile_user->ID,'_wphacks_banned',true);
		$banning_user = get_user_meta($profile_user->ID,'_wphacks_banned_by',true);
		?>
		<input type="checkbox" name="_wphacks_banned" <?php checked(1, $banned ,true); ?> value="1"?> <?php echo (($banned) ? '<p>Banned on '. date('r',get_user_meta($profile_user->ID,'_wphacks_banned_timestamp',true)) . (get_user_by('id',$banning_user) ? ' by <a href="'. get_edit_user_link($banning_user) .'">'. get_user_by('id',$banning_user)->display_name .'</a>': '').'</p>' : ''); ?>
		<p class="description">If checked, the user will be unable to log in.</p>
		</td>
	</tr>
</table>
<?php
}

function wphacks_edit_user_profile_update_save_banned_option($user_id){
		if( isset($_POST['_wphacks_banned']) ){
			//permissions check is done inside wphacks_ban_user
			wphacks_ban_user($user_id);
		} else {
			//permissions check is done inside wphacks_unban_user
			wphacks_unban_user($user_id);
		}
}

function wphacks_ban_user_check($user, $username, $password){
	switch($user){
		case is_null($user):
		//user has not been authenticated yet
		break;
		case is_wp_error($user):
		//login has already failed.
		break;
		default:
		//this should be a wordpress user object
		if(wphacks_is_user_banned($user->ID)){
			do_action('wphacks_banned_user_login_blocked',$user, $username);
			$code = "Login Denied";
			$message = apply_filters( 'wphacks_banner_user_login_denied_message', 'This account has been banned.');
			return new WP_Error($code,__("$message","wphacks"));
		}
		break;
	}
	return $user;
}

function wphacks_ban_user($user_id, $reason = ""){
	//TODO Let users add a reason
	if ( (current_user_can(apply_filters('wphacks_ban_user_cap','edit_user'),$user_id)) && (get_current_user_id() !== $user_id) ){
		$ban_result = add_user_meta($user_id, '_wphacks_banned',1,true);
		add_user_meta($user_id, '_wphacks_banned_by',get_current_user_id(),true);
		add_user_meta($user_id, '_wphacks_banned_timestamp',time(),true);
		return (bool) $ban_result; //any integer (Primary key in the db) will report as true
	} else {
		return false;
	}
}

function wphacks_unban_user($user_id){
	//prevent unbaning yourself - because we're doing the same check on banning yourself
	if ( (current_user_can(apply_filters('wphacks_ban_user_cap','edit_user'),$user_id)) && (get_current_user_id() !== $user_id) ){
		$unban_result = delete_user_meta($user_id,'_wphacks_banned',1);
		delete_user_meta($user_id,'_wphacks_banned_by');
		delete_user_meta($user_id,'_wphacks_banned_timestamp');
		return $unban_result;
	} else {
		return false;
	}
}

function wphacks_is_user_banned($user_id){
	$isUserBanned =  get_user_meta($user_id, '_wphacks_banned',true);
	if( (1 == intval($isUserBanned))){
		return true;
	} else {
		return false;
	}
}

function wphacks_ajax_ban_user(){
	$user_id = intval($_GET['user_id']);
	//check_ajax_referer("wphacks_user_row_actions_ban_user_{$user_id}-",'_wpnonce');
	if(wp_verify_nonce( $_GET['_wpnonce'], "wphacks_user_row_actions_ban_user_{$user_id}-")){
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ){
			if(wphacks_ban_user($user_id)){
				wp_send_json_success(wphacks_unban_user_row_action_link($user_id));
			} else {
				wp_send_json_error("banning user failed ". $_GET['_wpnonce']);
			}
		}
	} else {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ){
			wp_send_json_error("failed verify nonce ". $_GET['_wpnonce']);
		} 
	}
}

function wphacks_ajax_unban_user(){
	$user_id = intval($_GET['user_id']);
	check_ajax_referer("wphacks_user_row_actions_unban_user_{$user_id}-",'_wpnonce');
	if(wp_verify_nonce( $_GET['_wpnonce'], "wphacks_user_row_actions_unban_user_{$user_id}-")){
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ){
			if(wphacks_unban_user($user_id)){
				wp_send_json_success(wphacks_ban_user_row_action_link($user_id));
			} else {
				wp_send_json_error("unbanning user failed ". $_GET['_wpnonce']);
			}
		}
	} else {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ){
			wp_send_json_error("failed verify nonce ". $_GET['_wpnonce']);
		}
	}
}

function wphacks_user_row_actions_add_ban_unban_user($actions, $user){
	//prevent banning yourself
	if ( (current_user_can(apply_filters('wphacks_ban_user_cap','edit_user'),$user->ID)) && (get_current_user_id() !== $user->ID) ){
		if(wphacks_is_user_banned($user->ID)){
			$actions['unban_user'] = wphacks_unban_user_row_action_link($user->ID);
		} else {
			$actions['ban_user'] = wphacks_ban_user_row_action_link($user->ID);
		}
	}
	return $actions;
}

function wphacks_ban_user_row_action_link($user_id){
	return '<span class="delete"><a class="wphacks-row-action-link-ban-user hide-if-no-js" href="'. wp_nonce_url( add_query_arg(array('action'=>'wphacks_ban_user','user_id'=>$user_id),admin_url('admin-ajax.php')), "wphacks_user_row_actions_ban_user_{$user_id}-")  .'">'. __('Ban User','wphacks') .'</a></span>';
}
function wphacks_unban_user_row_action_link($user_id){
	return '<span><a class="wphacks-row-action-link-unban-user hide-if-no-js" href="'. wp_nonce_url( add_query_arg(array('action'=>'wphacks_unban_user','user_id'=>$user_id),admin_url('admin-ajax.php')), "wphacks_user_row_actions_unban_user_{$user_id}-")  .'">'. __('Unban User','wphacks') .'</a></span>';
}

function wphacks_admin_enqueue_ban_user_js($hook){
	if( 'users.php' != $hook ){
		return;
	}
	wp_enqueue_script( 'wphacks_ban_user', plugin_dir_url( __FILE__ ) . 'wphacks-ban-user.js', '', false, true );
}

function wphacks_ajax_parse_url(){
	$url = urldecode(esc_url_raw($_REQUEST['url']));
	$components = parse_url($url);
	$components['_get'] = (!empty($components['query']) ? wp_parse_args($components['query']) : array() );
	wp_send_json_success($components);
}
?>
