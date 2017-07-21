<?php
/*
Plugin Name: WP Captcha Free
Plugin URI: http://wordpresssupplies.com/wordpress-plugins/captcha-free/
Description: Block comment spam without captcha.
Author: iDope
Version: 0.4
Author URI: http://wordpresssupplies.com/
*/

/*  Copyright 2008  Saurabh Gupta  (email : saurabh0@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


// are we inside wp?
if(!defined('ABSPATH')) {
	// check if this is an ajax post
	if(isset($_POST['post_id'])) {
		// find wp-config.php
		if(file_exists('../../wp-config.php')) {
			$includefile='../../wp-config.php';
		} else if(file_exists('../../../wp-config.php')) {
			$includefile='../../../wp-config.php';
		} else {
			die('alert("Unable to include wp-config.php. Please make sure \'captcha-free.php\' is uploaded to the \'wp-content/plugins/\' folder.")');
		}
		// load wordpress
		require_once($includefile);
		nocache_headers();
		$post_id = intval($_POST['post_id']);
		$timehash=timehash($post_id,time());
	    echo "gothash('$timehash')";
	}
	exit;
}

// generate random salt on activation
register_activation_hook(__FILE__,'cf_make_salt');
function cf_make_salt() {
	update_option('cf_salt',mt_rand());
}
// add javascripts
add_action('wp_head', 'cf_js_header' );
function cf_js_header() {
	wp_print_scripts( array( 'sack' ));
}
// add hidden field for hash and ajax stuff to the form
add_action('comment_form', 'cf_comment_form', 10);
function cf_comment_form($post_id) {
	?>
<script type="text/javascript">
//<![CDATA[
	function gethash(){
		document.getElementById('commentform').onsubmit = null;
		if(document.getElementById('submit')) document.getElementById('submit').value='Please wait...';
		var mysack = new sack("<?php bloginfo( 'wpurl' ); ?>/wp-content/plugins/captcha-free.php");
		mysack.execute = 1;
		mysack.method = 'POST';
		mysack.onError = function() { alert('Unable to get Captcha-Free Hash!') };
		mysack.setVar('post_id', <?php echo $post_id; ?>);
		mysack.runAJAX();
		return false;
	}
	function gothash(myhash){
		document.getElementById('captchafree').value = myhash;
		// Workaround for Wordpress' retarded choice of naming the submit button same as a JS function name >:-(
		document.getElementById('submit').click();
	}
	document.getElementById('commentform').onsubmit = gethash;
//]]>
</script>
<input type="hidden" id="captchafree" name="captchafree" value="" />
<p><small><noscript><strong>Please note:</strong> JavaScript is required to post comments.</noscript> <a href="http://wordpresssupplies.com/wordpress-plugins/captcha-free/">Spam protection by WP Captcha-Free</a></small></p>
<?php
}

// Validate the hash
add_action('preprocess_comment', 'cf_comment_post');
function cf_comment_post($commentdata) {
	// Ignore trackbacks
	if($commentdata['comment_type']!='trackback') {
		// Calculate the timehash that is valid now
		$timehash=timehash($commentdata['comment_post_ID'],time());
		// Calculate the timehash that was valid 1 hour back to give some cushion
		$timehash_old=timehash($commentdata['comment_post_ID'],time()-3600);
		if($_POST['captchafree']!=$timehash && $_POST['captchafree']!=$timehash_old)
			wp_die('Invalid Data: Please go back and try again.');
	}
	return $commentdata;
}

// generate a hash for a given post and timestamp
function timehash ($post_id,$timestamp) {
	// Make a hash out of stuff that shouldn't change between requests
	return md5(get_option('cf_salt').$post_id.date('yzH',$timestamp).$_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']);
}
?>
