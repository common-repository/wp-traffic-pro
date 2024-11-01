<?php

/*
 * @class - wp_traffic_pro_admin
 */

if( ! defined( 'WPTRAFFIC_FILE' ) ) die( 'Silence ' );

if( ! class_exists('wp_traffic_pro_admin')):
class wp_traffic_pro_admin
{
	var $date_format;
	function __construct()
	{
		global $wpdb;
		$this->date_format					= 'j M Y h:i A';
		add_action( 'admin_head'				, array( &$this, 'wptraffic_admin_header'		));
		add_action( 'admin_notices'				, array( &$this, 'wptraffic_admin_notices'	));

		add_action( 'admin_enqueue_scripts'			, array( &$this, 'wptraffic_admin_style'		));
		add_action('wp_ajax_wptraffic_get_terms'		, array( &$this, 'wptraffic_ajax_get_terms'	));

		add_action( 'init'					, array( &$this, 'wptraffic_cron'			));
		add_action( 'wptraffic_cronjob'			, array( &$this, 'wptraffic_wpcronjob'		));

		add_shortcode( 'WPTRAFFIC_TITLE'			, array( &$this, 'wptraffic_shortcode_title'	));
		add_shortcode( 'WPTRAFFIC_CONTENT'			, array( &$this, 'wptraffic_shortcode_content'	));
		add_shortcode( 'WPTRAFFIC_VIDEO'			, array( &$this, 'wptraffic_shortcode_video'	));
		add_shortcode( 'WPTRAFFIC_DATE'			, array( &$this, 'wptraffic_shortcode_date'	));

		add_filter( 'the_content'				, array( &$this, 'wptraffic_the_content' 		));
		add_action( 'the_generator'				, array( &$this, 'wptraffic_the_generator'	), 10, 2 );
	}

	/*
	*
	**/
	function wptraffic_admin_style()
	{
		if( is_admin() && strpos( $_GET['page'] , 'wptraffic' ) !== false )
		{
			wp_enqueue_style( 'wptraffic_css'	, WPTRAFFIC_URL. 'assets/css/admin.css' );
			wp_enqueue_script( 'wptraffic_js'	, WPTRAFFIC_URL.'assets/js/common.js', array('jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-ui-autocomplete' ) );
		}
	}

	function wptraffic_admin_notices()
	{
		if( is_admin() && strpos( $_GET['page'] , 'wptraffic' ) !== false )
		{
			if( empty( $_POST ) && $playlist = get_option( 'wptraffic_playlists' ) && ! $fetch_fields = get_option( "wptraffic_fetch_fields" ) ) {
				?><div class="notice notice-error is-dismissible"><p><?php _e('<strong>WP Traffic Pro Error:</strong> Please select one or more Playlist below to start posting videos to your blog.', 'wptraffic_lang')?></p></div><?php
			}
		}

		$screen = get_current_screen();
		if( is_admin() && ( $screen->id == 'dashboard' || strpos( $_GET['page'] , 'wptraffic' ) !== false ) )
		{
			echo '<div class="notice notice-success is-dismissible wptraffic-notice wptraffic-row">';
			echo '<div class="wptraffic-left">';
			echo '<h3>';
			echo esc_html__( 'WP-Traffic Pro: The Best Traffic generator for your YouTube channel Website!', 'wptraffic_lang' );
			echo '</h3>';

			echo '<p>';
			echo sprintf( esc_html__( 'The %sPremium (Free) Version of WP Traffic Pro, The %sWP Traffic Plus%s %sprovides all the tools in one spot needed to help your website generate unlimited traffic.', 'wptraffic_lang' ), '<span class="wp-traffic-pro">', '<u>', '</u>', '</span>' );
			echo '</p>';

			echo '<p>';
			echo esc_html__( 'We&#039;ve carefully tested and approved each one of the free WordPress Plugins you can install in your blog and activate to help drive your business to a new level.', 'wptraffic_lang' );
			echo '</p>';

			echo '<p>';
			echo esc_html( 'Register your account and immediately download the premium version of WP-Traffic Pro absolutely free! Make sure you bookmark the download page for free training on how to implement and use our recommended mix of programs. All programs are free and effective.', 'wptraffic_lang' );
			echo '</p>';

			echo '<p>';
			echo sprintf( esc_html__( 'Simply visit %1$sWP-Traffic.com%2$s', 'wptraffic_lang' ), '<a class="button button-primary button-hero" target="_blank" href="https://wp-traffic.com">', ' &raquo;</a>' );
			echo '</p>';

			echo '</div>';

			echo '<div class="wptraffic-right">';
			echo '<img class="wptraffic-image wptraffic-image-large" src="' . esc_url( WPTRAFFIC_URL . 'assets/img/wp-traffic.png').'">';
			echo '</div>';

			echo '<div class="clear"></div>';

			echo '</div>';
			wp_enqueue_style( 'wptraffic_css'	, WPTRAFFIC_URL. 'assets/css/admin.css' );
		}
	}

	/*
	*
	**/
	function wptraffic_admin_header()
	{
		global $wpdb;

		if( is_admin() && strpos( $_GET['page'] , 'wptraffic' ) !== false )
		{
			?>
		<script type="text/javascript">
		if( typeof jQuery == 'function' ){
			jQuery(document).ready( function($){
				var ajax_nonce 		= '<?php echo wp_create_nonce( 'wptraffic_ajax' ); ?>';
				var ajaxurl 		= '<?php echo admin_url('admin-ajax.php') ?>';

				function split( val ) {
					return val.split( /,\s*/ );
				}
				function extractLast( term ) {
					return split( term ).pop();
				}

				$('body').on( 'change', '#wptraffic_type', function(event){
					$('.wptraffic_typediv').hide();
				/*	$('.wptraffic_typediv .regular-text').val(''); */
					val = $('#wptraffic_type option:selected').val();
					$('#'+val+'_div').slideDown();
				});
				$('#wptraffic_type').change();

				$("#wptraffic_playlist_tbl thead tr:last th:first input:checkbox").click(function() {
					var checkedStatus = this.checked;
					$("#wptraffic_playlist_tbl tbody tr td:first-child input:checkbox").each(function() {
						this.checked = checkedStatus;
					});
				});

				var searchRequest;
				$('.wptraffic_terms').autocomplete({
					minChars: 2,
					source: function(wptraffic_name, response) {
						try { searchRequest.abort(); } catch(err){}
						var search_term = wptraffic_name.term;
						if( wptraffic_name.term.indexOf( ',' ) > 0 ) {
							search_term = extractLast( wptraffic_name.term );
						}

						searchRequest = $.ajax({
							type: 'POST',
							dataType: 'json',
							url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
							data: 'action=wptraffic_get_terms&terms='+search_term,
							success: function(data) {
								response(data);
							}
						});
					},
					search: function() {
						var term = extractLast( this.value );
						if ( term.length < 2 ) {
							return false;
						}
					},
					focus: function() {
						return false;
					},
					select: function( event, ui ) {
						var terms = split( this.value );
						terms.pop();
						terms.push( ui.item.value );
						terms.push( "" );
						this.value = terms.join( ", " );
						return false;
					}
				});

				$('.wptraffic_terms').on('blur', function(){
					$('.wptraffic_terms').autocomplete("close");
				});

				$("#tabs").tabs();

				$("#wptraffic_size_w").blur(function() {
					w = $('#wptraffic_size_w').val();
					if(!isNaN(w))
					{
						h = parseInt((385/480) * w, 10);
						$('#wptraffic_size_h').val(h);
					}
					else
					{
						alert("Please enter a valid number");
						$('#wptraffic_size_w').focus();
					}
					return false;
				});
			});
		}
		</script>
			<?php
		}
	}

	function wptraffic_ajax_get_terms()
	{
		global $wpdb, $current_user;
		get_currentuserinfo();

		$out = array();

		if (!current_user_can('manage_options'))
		{
			$out = array();
			$out['msg'] = __('Sorry, but you have no permissions to change settings.');
			$out['err'] = __LINE__;
			header( "Content-Type: application/json" );
			echo json_encode( $out );
			die();
		}
	//	check_ajax_referer( "wptraffic_ajax" );

		if(!defined('DOING_AJAX')) define('DOING_AJAX', 1);
		set_time_limit(60);

		$html = "";

		$wptraffic_arr	= $out = array();

		$terms 	= (isset( $_POST['terms'] )?	trim( sanitize_text_field( wp_strip_all_tags( stripslashes( $_POST['terms'] )))): ''  );
		if( empty( $terms ) || strlen( $terms ) < 3 )
		{
			$out = array();
			header( "Content-Type: application/json" );
			echo json_encode( $out );
			die();
		}

		set_time_limit(0);

		$get_terms = get_terms( 'category', array( 'search' => $terms, 'hide_empty' => false ) );
		$out = array();
		foreach( $get_terms as $get_term )
			$out[] = $get_term->name;

		header( "Content-Type: application/json" );
		echo json_encode( $out );
		die();
	}

	/*
	*
	**/
	function wptraffic_main()
	{
		global $wpdb, $current_user;
		get_currentuserinfo();

		if (!current_user_can('manage_options')) wp_die(__('Sorry, but you have no permissions to change settings.'));
		$error = $result = '';

		$wptraffic_options = get_option( "wptraffic_options" );
		if( isset( $_POST['call'] ) && $_POST['call'] == 'wptraffic_saveapi' )
		{
			check_admin_referer('wptraffic-saveapi');
//$this->reset();
			$wptraffic_api 		= (isset( $_POST['wptraffic_api'] )?	trim( sanitize_text_field( wp_strip_all_tags( stripslashes( $_POST['wptraffic_api'] )))): ''  );
			if( empty( $wptraffic_api ) )
				$error = __('Please enter a Youtube API key.','wptraffic_lang');
			else
				$wptraffic_options['api'] = $wptraffic_api;

			$wptraffic_options['type'] = $wptraffic_options['username'] = $wptraffic_options['channelid'] = $wptraffic_options['playlistid'] = '';

			if( isset( $_POST['wptraffic_type'] ) && ! empty( $_POST['wptraffic_type'] ) ) {
				if( trim( $_POST['wptraffic_type'] ) == 'username' && isset( $_POST['wptraffic_username'] ) && ! empty( $_POST['wptraffic_username'] )) {
					$wptraffic_options['type'] 		= 'username';
					$wptraffic_options['username'] 	= trim( sanitize_text_field( $_POST['wptraffic_username'] ));
					$wptraffic_options['channelid']	= '';
					$wptraffic_options['playlistid'] 	= '';
					if( empty( $wptraffic_options['username'] ) )
						$error = __('Please enter a Youtube UserName.','wptraffic_lang');
				}
				else if( trim( $_POST['wptraffic_type'] ) == 'channelid' && isset( $_POST['wptraffic_channelid'] ) && ! empty( $_POST['wptraffic_channelid'] )) {
					$wptraffic_options['type'] 		= 'channelid';
					$wptraffic_options['channelid'] 	= trim( sanitize_text_field( $_POST['wptraffic_channelid'] ));
					$wptraffic_options['username'] 	= '';
					$wptraffic_options['playlistid'] 	= '';
					if( empty( $wptraffic_options['channelid'] ) )
						$error = __('Please enter a Youtube Channel Id.','wptraffic_lang');
				}
				else if( trim( $_POST['wptraffic_type'] ) == 'playlistid' && isset( $_POST['wptraffic_playlistid'] ) && ! empty( $_POST['wptraffic_playlistid'] )) {
					$wptraffic_options['type'] 		= 'playlistid';
					$wptraffic_options['playlistid'] 	= trim( sanitize_text_field( $_POST['wptraffic_playlistid'] ));
					$wptraffic_options['username'] 	= '';
					$wptraffic_options['channelid']	= '';
					if( empty( $wptraffic_options['playlistid'] ) )
						$error = __('Please enter a Youtube Playlist Id.','wptraffic_lang');
				}
			}

			if( empty( $wptraffic_options['type'] ) )
				$error = __('Please choose type and enter a valid Youtube.com Username or a ChannelId or a PlayList ID.','wptraffic_lang');

			if( empty( $error ) )
			{
				$wptraffic_options['process_key'] = 0;
				update_option( "wptraffic_options"	, $wptraffic_options );
				delete_option( 'wptraffic_channel' );
				delete_option( 'wptraffic_playlists' );
				delete_option( "wptraffic_fetch_fields" );

				$ret = $this->get_youtube_channel_list();
				if( ! empty( $ret ) )
					$error = implode( "<br/>", $ret );
				else
					$result = __('Settings have been saved. Feed processed','wptraffic_lang');
			}
		}
		else if( isset( $_POST['call'] ) && $_POST['call'] == 'wptraffic_savechannels' )
		{
			check_admin_referer('wptraffic-savechannels');

			/**
			*
			* $_POST['wptraffic_check'], AND $_POST['wptraffic_terms'] are arrays, 
			* 
			* Each element sanitized individually below;
			*
			**/
			if( empty( $_POST['wptraffic_check'] ) ){
				$error = __('Please check at least one channel or playlist.','wptraffic_lang');
			}
			else
			{
				$new_wptraffic_check = array();
				foreach( $_POST['wptraffic_check'] as $ii => $wptraffic_c ){
					$key = trim( sanitize_text_field( wp_strip_all_tags( stripslashes( $wptraffic_c ) ) ) );
					$val = trim( sanitize_text_field( wp_strip_all_tags( stripslashes( $_POST['wptraffic_terms'][$ii] ) ) ), ',' );
					$new_wptraffic_check[$key] = array( $val, '' );
				}
			}
			if( empty( $error ) )
			{
				$wptraffic_options['process_key'] = 0;
				update_option( "wptraffic_options", $wptraffic_options );

				$fetch_fields = json_encode( $new_wptraffic_check );
				update_option( "wptraffic_fetch_fields"	, $fetch_fields );
				$result = __('Settings have been saved','wptraffic_lang');
			}
		}
		else if( isset( $_POST['call'] ) && $_POST['call'] == 'wptraffic_publish' )
		{
			check_admin_referer('wptraffic-publish');

			$wptraffic_options['size_w'] 		= (isset( $_POST['wptraffic_size_w'] )? 		(int)trim( sanitize_text_field( $_POST['wptraffic_size_w'] )): '480'  );
			$wptraffic_options['size_h'] 		= (isset( $_POST['wptraffic_size_h'] )? 		(int)trim( sanitize_text_field( $_POST['wptraffic_size_h'] )): '385'  );
			$wptraffic_options['publish_cnt'] 	= (isset( $_POST['wptraffic_publish_cnt'] )? 	(int)trim( sanitize_text_field( $_POST['wptraffic_publish_cnt'] )): '5'  );
			$wptraffic_options['author'] 		= (isset( $_POST['wptraffic_author'] )? 		(int)trim( sanitize_text_field( $_POST['wptraffic_author'] )): 1  );
			$wptraffic_options['content']		= (isset( $_POST['wptraffic_content'] )? 		trim( wp_kses_post( $_POST['wptraffic_content'] )): ''  );

			$wptraffic_options['enable_cron']	= (isset( $_POST['wptraffic_enable_cron'] ) && trim( $_POST['wptraffic_enable_cron'] ) == 1? 1: 0  );
			$wptraffic_options['enable_cron_hour']= (isset( $_POST['wptraffic_enable_cron_hour'] )? (int)trim( wp_kses_post( $_POST['wptraffic_enable_cron_hour'] )): 6  );

			update_option( "wptraffic_options"	, $wptraffic_options );
				$result = __('Settings have been saved','wptraffic_lang');

			if( $wptraffic_options['enable_cron'] == 1 ){
				if( !wp_next_scheduled( 'wptraffic_cronjob' ) ) {
					wp_schedule_event( time(), 'hourly', 'wptraffic_cronjob' );
				}
			}
			else {
				if( false !== ( $time = wp_next_scheduled( 'wptraffic_cronjob' ) ) ) {
					wp_unschedule_event( $time, 'wptraffic_cronjob' );
				}
			}
		}
?>
		<div class="wrap">
		<h2><?php _e( 'WP Traffic Pro', 'wptraffic_lang' ); ?></h2>
<?php

if($error)
{
?>
<div class="notice notice-error is-dismissible"><p><b><?php _e('Error: ', 'wptraffic_lang')?></b><?php echo $error;?></p></div>
<?php
}

if($result)
{
?>
<div id="message" class="notice notice-success is-dismissible"><p><?php echo $result; ?></p></div>
<?php
}
?>
	<style>.hl{font-style:italic; background-color:#ffff23;}</style>
	<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">
	<div id="post-body-content">

	<div id="tabs">
	<ul id="wptraffic_ul">
		<li><a href="#general_settings"><?php _e('General Settings', 'wptraffic_lang' ); ?></a></li>
		<li><a href="#publish_settings"><?php _e('Publish Settings', 'wptraffic_lang' ); ?></a></li>
	</ul>

	<div id="general_settings">

	<form method="post" id="wptraffic_saveapi" name="wptraffic_saveapi">
	<?php  wp_nonce_field( 'wptraffic-saveapi' ); ?>
	<input type="hidden" name="call" value="wptraffic_saveapi"/>

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wptraffic_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Settings', 'wptraffic_lang' ); ?></span></h3>
	      <div class="inside">
			<table border="0" cellpadding="3" cellspacing="2" class="form-table" width="100%">
			<tr>
			<th><label for="wptraffic_api"><?php _e( 'Youtube API Key: ','wptraffic_lang' );?></th>
			<td><input type="text" name="wptraffic_api" id="wptraffic_api" value="<?php echo esc_attr( $wptraffic_options['api'] );?>" class="regular-text"/><br/>
			<span class="description"><?php printf( __('Please enter Youtube.com API Key. You can get a API key from %shere%s','wptraffic_lang'), '<a href="https://console.developers.google.com/" target="_blank">','</a>');?></span>
			</tr>
			<tr>
			<th><label for="wptraffic_username"><?php _e( 'Youtube : ','wptraffic_lang' );?></th>
			<td>
				<select name="wptraffic_type" id="wptraffic_type">
				<option value="channelid" <?php selected( 'channelid', $wptraffic_options['type'] );?>>ChannelID</option>
				<option value="playlistid" <?php selected( 'playlistid', $wptraffic_options['type'] );?>>PlayList</option>
				<option value="username" <?php selected( 'username', $wptraffic_options['type'] );?>>Username</option>
				</select><br/>
				<div id="channelid_div" class="wptraffic_typediv">
			<label><strong><?php _e('Channel ID','wptraffic_lang')?></strong><br/>
			<input type="text" name="wptraffic_channelid" id="wptraffic_channelid" value="<?php echo esc_attr( $wptraffic_options['channelid'] );?>" class="regular-text"/></label><br/>
			<span class="description"><?php _e('Please enter Youtube.com Channel ID to import playlists.','wptraffic_lang');?></span><br/>
			<span class="description"><?php _e('You can get Channel ID from the URL of the channel <code>https://www.youtube.com/channel/<strong>UCEXyTZGxffQZjDwq6h2Gldw</strong></code>','wptraffic_lang');?></span>
				</div>

				<div id="playlistid_div" style="display:none" class="wptraffic_typediv">
			<label><strong><?php _e('Playlist ID','wptraffic_lang')?></strong><br/>
			<input type="text" name="wptraffic_playlistid" id="wptraffic_playlistid" value="<?php echo esc_attr( $wptraffic_options['playlistid'] );?>" class="regular-text"/></label><br/>
			<span class="description"><?php _e('Please enter Youtube.com playlist ID.','wptraffic_lang');?></span><br/>
			<span class="description"><?php _e('You can get PlayList ID from the URL of the channel <code>https://www.youtube.com/playlist?list=<strong>LLDn1pOvN2Ni1Cg_Z4AfmdRg</strong></code>','wptraffic_lang');?></span>
				</div>

				<div id="username_div" style="display:none" class="wptraffic_typediv">
			<label><strong><?php _e('Your Username','wptraffic_lang')?></strong><br/>
			<input type="text" name="wptraffic_username" id="wptraffic_username" value="<?php echo esc_attr( $wptraffic_options['username'] );?>" class="regular-text"/></label><br/>
			<span class="description"><?php _e('Please enter Youtube.com username to import channels and playlists.','wptraffic_lang');?></span><br/>
			<span class="description"><?php _e('You can get User ID from the URL <code>https://www.youtube.com/user/<strong>GoogleDevelopers</strong></code>','wptraffic_lang');?></span>
				</div>
			</td>
			</tr>
			</table>
	      </div>
	    </div>
		<?php  
			if( ! empty( $wptraffic_options['api'] ) ) {
			?>
			<p class="description notice notice-info is-dismissible"><br/>
				<strong><?php _e('Note','wptraffic_lang' );?></strong><br/>
				<span><?php _e('1. Click Save to refresh the list below. This will only refresh the channel Info, and playlist info. New Videos will still be processed.','wptraffic_lang');?></span><br/>
				<span><?php _e('2. Changing the Channel ID above will not delete already published videos. New Videos will be published from the new ChannelID','wptraffic_lang');?></span><br/>
			</p>
			<?php
			}
			submit_button(__(' Save ', 'wptraffic_lang' )); 
		?>
	  </form>
		<hr/>
		<?php
			if( ! empty( $wptraffic_options['api'] ) ) {
				$channel = $playlist_arr = $fetch_fields = array();
				if( $channel = get_option( 'wptraffic_channel' ) ) {
					$channel = json_decode( $channel, true );

					if( $playlist_arr = get_option( 'wptraffic_playlists' ) )
						$playlist_arr = json_decode( $playlist_arr, true );

					if( $fetch_fields = get_option( "wptraffic_fetch_fields" ) ){
						$fetch_fields = json_decode( $fetch_fields, true );
						$fetch_fields_keys = array_keys( $fetch_fields );
					}else{
						$fetch_fields = array();
						$fetch_fields_keys = array();
					}
		?>
	<form method="post" id="wptraffic_savechannels" name="wptraffic_savechannels">

	<?php  wp_nonce_field( 'wptraffic-savechannels' ); ?>
	<input type="hidden" name="call" value="wptraffic_savechannels"/>

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wptraffic_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php printf( __( 'PlayLists for Channel: %s', 'wptraffic_lang' ), esc_attr( $channel['title'] ) ); ?></span><br/>
		<span class="description"><?php _e('Check the playlists you want to publish videos from and hit save at the bottom.','wptraffic_lang');?></span></h3>
	      <div class="inside">

			<table border="0" cellpadding="3" cellspacing="2" class="widefat form-table" id="wptraffic_playlist_tbl">
			<thead>
			<tr id="<?php echo esc_attr( $channel['id'])?>" style="background-color:#efefef;">
			<th style="width:40px!important;"><input type="checkbox" name="" value="" /></th>
			<th style="width:90px!important;"><img src="<?php echo esc_url( $channel['thumbnail'][0] )?>" border="0"/></th>
			<th style="width:80%!important;">
				<div class="wptraffic_alignleft alignleft" style="width:90%;">
					<strong>
						<a target="_blank" href="<?php echo esc_url( $channel['customurl'] )?>"><?php echo esc_attr( $channel['title'] );?></a> 
						 (<i class="dashicons dashicons-video-alt"></i> <?php echo esc_attr( $channel['vid_count'])?>)
					</strong><br/><span>[Channel]</span>
					<p><?php echo ( $channel['description']? esc_html( $channel['description'] ): esc_attr( $channel['title'] ) );?></p>
				</div>
			</th>
			</tr>
			</thead>
			<tbody>
		<?php
			if( ! empty( $playlist_arr ) ){
			foreach( $playlist_arr as $item ) {
		?>
			<tr id="<?php echo $item['id']?>">
			<td><input type="checkbox" name="wptraffic_check[<?php echo esc_attr($item['id'])?>]" value="playlist-<?php echo esc_attr($item['id'])?>"
				<?php echo ( in_array( 'playlist-'.$item['id'], $fetch_fields_keys )? ' checked="checked"':'');?>/></td>
			<td><img src="<?php echo esc_url( $item['thumbnail'][0] )?>" border="0"/></td>
			<td>
				<div class="wptraffic_alignleft alignleft" style="width:90%;">
					<strong>
						<a target="_blank" href="<?php echo esc_url( 'https://www.youtube.com/playlist?list='.$item['id'] )?>"><?php echo esc_attr( $item['title'] );?></a>
						 (<i class="dashicons dashicons-video-alt"></i> <?php echo esc_attr( $item['vid_count'])?>)
					</strong>
					<p><?php echo ( $item['description']? esc_html( $item['description'] ): esc_attr( $item['title'] ) );?></p>
					<p><label><strong><?php _e('Categories: ', 'wptraffic' );?></strong> <input type="text" class="regular-text wptraffic_terms" name="wptraffic_terms[<?php echo esc_attr($item['id'])?>]" 
						value="<?php echo $fetch_fields['playlist-'.$item['id']][0]?>" placeholder="Categories"/></label><br/>
					<span class="description"><?php _e('Publish Posts from this playlist under these Categories. Enter comma separated categories','wptraffic_lang');?></span>

					</p>
				</div>
			</td>
			</tr>
		<?php }} ?>
			</table>
	      </div>
	    </div>
		<?php 
			submit_button(' Save ');
		} ?>

	  </form>
		<?php 
		} //if channel//
		?>


	</div>


	<?php

		if( empty( $wptraffic_options['size_w'] ) ) $wptraffic_options['size_w'] = '480';
		if( empty( $wptraffic_options['size_h'] ) ) $wptraffic_options['size_h'] = '385';

		if( empty( $wptraffic_options['publish_cnt'] ) ) $wptraffic_options['publish_cnt'] = 5;
		if( empty( $wptraffic_options['content'] ) ) $wptraffic_options['content'] = "[WPTRAFFIC_VIDEO]
[WPTRAFFIC_DATE]

[WPTRAFFIC_CONTENT]
";

	?>
	<div id="publish_settings">

	<form method="post" id="wptraffic_publish" name="wptraffic_publish">
	<?php  wp_nonce_field( 'wptraffic-publish' ); ?>
	<input type="hidden" name="call" value="wptraffic_publish"/>

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wptraffic_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Settings', 'wptraffic_lang' ); ?></span></h3>
	      <div class="inside">
			<table border="0" cellpadding="3" cellspacing="2" class="form-table" width="100%">

		<tr valign="top">
		<th scope="row"><label for="wptraffic_size_w"><?php _e('Video Size:', 'wptraffic_lang')?></label></th>
		<td>
		<input type="number" min="1" name="wptraffic_size_w" id="wptraffic_size_w" value="<?php echo $wptraffic_options['size_w'];?>" style="width:60px" class="regular-text"/>px X 
		<input type="number" min="1" name="wptraffic_size_h" id="wptraffic_size_h" value="<?php echo $wptraffic_options['size_h'];?>" style="width:60px" class="regular-text"/>px
		<br/><span class="description"><?php _e('Recommended: 480px x 385px', 'wptraffic_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row"><label for="wptraffic_publish_cnt"><?php _e('Publish Count:', 'wptraffic_lang')?></label></th>
		<td><input type="number" min="1" max="10" step="1" name="wptraffic_publish_cnt" id="wptraffic_publish_cnt" value="<?php echo $wptraffic_options['publish_cnt'];?>" class="regular-text"/>
		<br/><span class="description"><?php _e('Publish X number of videos per cron call', 'wptraffic_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row"><label for="wptraffic_author"><?php _e('Post Author:', 'wptraffic_lang')?></label></th>
		<td><?php wp_dropdown_users( array( 'name' => 'wptraffic_author', 'role__in' => array( 'administrator', 'editor', 'author', 'contributor' ), 'selected'=> $wptraffic_options['author'] ) ); ?>
		<br/><span class="description"><?php _e('Select the author of the published post', 'wptraffic_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row"><label for="wptraffic_content"><?php _e('Post Content:', 'wptraffic_lang')?></label></th>
		<td> <?php wp_editor( $wptraffic_options['content'], 'wptraffic_content' ); ?> 
		<br/><span class="description"><?php _e('Enter the content of the post. You could use the following Shortcodes in the content. <br/>[WPTRAFFIC_TITLE], [WPTRAFFIC_CONTENT], [WPTRAFFIC_VIDEO], [WPTRAFFIC_DATE]', 'wptraffic_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row"><label for="wptraffic_enable_cron"><?php _e('Schedule internal cron:', 'wptraffic_lang')?></label></th>
		<td><input type="checkbox" name="wptraffic_enable_cron" id="wptraffic_enable_cron" value="1" <?php checked( $wptraffic_options['enable_cron'], "1" );?>/>
			<input type="number" min="1" max="24" step="1" name="wptraffic_enable_cron_hour" id="wptraffic_enable_cron_hour" value="<?php echo $wptraffic_options['enable_cron_hour'];?>" class="regular-text"/> Hours

		<br/><span class="description"><?php _e('Enable Wordpress cron. This will run internally X hours as specified above. Setting up of a Unix Cron job is not required.', 'wptraffic_lang');?></span>
		<br/><span class="description"><?php _e('Alternately You could choose to set up a Unix cron job using the command below.', 'wptraffic_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row" width="25%"><label for="wptraffic_cron_url"><?php _e('Unix cron URL', 'wptraffic_lang')?></label></th>
		<td width="75%">
		<input style="width:450px" class="regular-text" type="text" name="wptraffic_cron_url" id="wptraffic_cron_url" value="<?php echo home_url('/?wptraffic_cron='. get_option("wptraffic_cron"));?>" onclick="this.select()" readonly="readonly"/>
		<br/><span class="description"><?php _e('Please use the above URL to set up a cron job from your servers control panel.',"wptraffic_lang") ?></span>

		<br/><?php _e("Example: ", "wptraffic_lang") ?><br/><input style="width:450px" class="regular-text" type="text" name="wptraffic_cron_url" id="wptraffic_cron_url" value="wget -q -O /dev/null <?php echo home_url('/?wptraffic_cron='. get_option("wptraffic_cron"))?>" onclick="this.select()" readonly="readonly"/>
		</td></tr>
			</table>
	      </div>
	    </div>
		<?php submit_button(' Save '); ?>
	  </form>
	  <hr class="clear" />

	</div>
	</div><!-- tabs -->
	  <hr class="clear" />

	</div><!-- /post-body-content -->

	<div id="postbox-container-1" class="postbox-container">

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wptraffic_lang' ); ?>"><br /></div>
	      <div class="inside">
			<center><a href="http://amplifiedtrafficsystem.com/" target="_blank"><img src="<?php echo WPTRAFFIC_URL.'assets/img/ats_250.png'; ?>" border="0"/></a></center>
		</div>
	   </div>

	</div><!-- postbox-container-1 -->


	</div><!-- /post-body -->

	<br class="clear" />

	</div><!-- /poststuff -->
		</div><!-- /wrap --><br/>
	<?php
		wp_traffic_pro::wptraffic_page_footer();
	} 

	function get_youtube_channel_list()
	{
		global $wpdb;

		$wptraffic_options = get_option( "wptraffic_options" );

		$ret = array();
		if( $wptraffic_options['type'] == 'username' || $wptraffic_options['type'] == 'channelid' ) {
			if( $xx = $this->get_channel_info() )
				$ret[] = $xx;

			if( $xx = $this->get_playlist_info() )
				$ret[] = $xx;
		}
		else if( $wptraffic_options['type'] == 'playlistid' ) {
			if( $xx = $this->get_playlist_info( $wptraffic_options['playlistid'] ) )
				$ret[] = $xx;
		}
		return $ret;
	}

	/**
	*
	* Given either a Username or a channeldi, get info about channel;
	*
	*
	**/
	function get_channel_info()
	{
		global $wpdb;

		$wptraffic_options = get_option( "wptraffic_options" );

		if( empty( $wptraffic_options['api'] ) )
			return false;

		if( ! empty( $wptraffic_options['username'] ) ) {
			$url = sprintf( "https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails,statistics&forUsername=%s&key=%s&maxResults=50", 
						$wptraffic_options['username'], 
						$wptraffic_options['api'] 
				);
		}
		else if( ! empty( $wptraffic_options['channelid'] ) ) {
			$url = sprintf( "https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails,statistics&id=%s&key=%s&maxResults=50", 
						$wptraffic_options['channelid'], 
						$wptraffic_options['api'] 
				);
		}
		else {
			return false;
		}

		$ret = wp_remote_fopen($url);

		if( ! empty( $ret ) )
			$ret = json_decode( $ret, true );

		if( empty( $ret ) ){
			$err = sprintf( __("Fetching Channel: Recieved an empty response from youtube.com <!-- %s -->", 'wptraffic_lang'), $url );
			$this->log( __FUNCTION__, __LINE__, $err );
			return $err;
		}

		if( isset( $ret['error'] ) ){
			$err = sprintf( __("<!-- URL: %s; <br/> -->Error: %s %s", 'wptraffic_lang'), $url, $ret['error']['code'], $ret['error']['message'] );
			$this->log( __FUNCTION__, __LINE__, $err );
			return $err;
		}

		$ret 	= $ret['items'][0];
		if( ! empty( $ret ) ) {
			$channel 			= array();
			$channel['id'] 		= sanitize_text_field( $ret['id'] );
			$channel['title'] 	= sanitize_text_field( $ret['snippet']['title'] );
			$channel['description'] = sanitize_textarea_field( $ret['snippet']['description'] );
			$channel['customurl'] 	= ( $ret['snippet']['customUrl']? 'https://www.youtube.com/user/'.sanitize_text_field( $ret['snippet']['customUrl'] ): esc_url( 'https://www.youtube.com/channel/'.esc_attr($ret['id'] )) );
			$channel['thumbnail'] 	= array( esc_url( $ret['snippet']['thumbnails']['default']['url'] ), (int)$ret['snippet']['thumbnails']['default']['width'], (int)$ret['snippet']['thumbnails']['default']['height'] );
			$channel['vid_count']	= sanitize_text_field( $ret['statistics']['videoCount'] );

			update_option( 'wptraffic_channel', json_encode( $channel ) );

			if( empty( $wptraffic_options['channelid'] ) ) {
				$wptraffic_options['channelid'] = sanitize_text_field( $ret['id'] );
				update_option( "wptraffic_options"	, $wptraffic_options );
			}

			if( empty( $wptraffic_options['username'] ) && ! empty( $ret['snippet']['customUrl'] ) ) {
				$wptraffic_options['username'] = sanitize_text_field( $ret['snippet']['customUrl'] );
				update_option( "wptraffic_options"	, $wptraffic_options );
			}
			if( isset( $ret['contentDetails']['relatedPlaylists']['uploads'] ) ){
				$this->get_playlist_info( $ret['contentDetails']['relatedPlaylists']['uploads'] );
			}
		}
 	}
 
	/**
	*
	* Given a channelid, get all playlists in the channel;
	*
	*
	**/
	function get_playlist_info( $playlist_id = '' )
	{
		global $wpdb;

		$wptraffic_options = get_option( "wptraffic_options" );

		if( empty( $wptraffic_options['api'] ) )
			return false;

		if( ! empty( $playlist_id ) ){
			$url = sprintf( "https://www.googleapis.com/youtube/v3/playlists?part=snippet,contentDetails&id=%s&key=%s&maxResults=50", 
						$playlist_id, 
						$wptraffic_options['api'] 
				);
		}
		else if( ! empty( $wptraffic_options['channelid'] ) ) {
			$url = sprintf( "https://www.googleapis.com/youtube/v3/playlists?part=snippet,contentDetails&channelId=%s&key=%s&maxResults=50", 
						$wptraffic_options['channelid'], 
						$wptraffic_options['api'] 
				);
		}
		else{
			return false;
		}

		$ret 	= wp_remote_fopen($url);

		if( ! empty( $ret ) )
			$ret = json_decode( $ret, true );

		if( empty( $ret ) ){
			$err = sprintf( __("Fetching Playlist: Recieved an empty response from youtube.com <!-- %s -->", 'wptraffic_lang'), $url );
			$this->log( __FUNCTION__, __LINE__, $err );
			return $err;
		}

		if( isset( $ret['error'] ) ){
			$err = sprintf( __("<!-- URL: %s; <br/> -->Error: %s %s", 'wptraffic_lang'), $url, $ret['error']['code'], $ret['error']['message'] );
			$this->log( __FUNCTION__, __LINE__, $err );
			return $err;
		}

		if( ! $playlist_arr = get_option( 'wptraffic_playlists' ) )
			$playlist_arr = array();
		else
			$playlist_arr = json_decode( $playlist_arr, true );

		$channel_id = '';
		if( ! empty( $ret ) && ! empty( $ret['items'] ) ) {
			foreach( $ret['items'] as $ii => $item ) {
				$playlist 			= array();
				$playlist['id'] 		= sanitize_text_field( $item['id'] );
				$playlist['title'] 	= sanitize_text_field( $item['snippet']['title'] );
				$playlist['description']= sanitize_textarea_field( $item['snippet']['description'] );
				$playlist['thumbnail'] 	= array( esc_url( $item['snippet']['thumbnails']['default']['url'] ), (int)$item['snippet']['thumbnails']['default']['width'], (int)$item['snippet']['thumbnails']['default']['height'] );
				$playlist['vid_count']= sanitize_text_field( $item['contentDetails']['itemCount'] );

				if( isset( $item['snippet']['channelId'] ) )
					$channel_id = sanitize_text_field( $item['snippet']['channelId'] );

				$playlist_arr[] = $playlist;
			}
		}

		if( ! empty( $playlist_arr ) ) {
			update_option( 'wptraffic_playlists', json_encode( $playlist_arr ) );

			if( empty( $wptraffic_options['channelid'] ) && ! empty( $channel_id ) ) {
				$wptraffic_options['channelid'] = $channel_id;
				update_option( "wptraffic_options"	, $wptraffic_options );

				if( ! $channel = get_option( 'wptraffic_channel' ) ){
					$this->get_channel_info();
				}
			}
		}
	} 

	function get_videos_by_playlistid( $playlist_id, $next_pagetoken = '' )
	{
		global $wpdb;

		$wptraffic_options = get_option( "wptraffic_options" );

		if( empty( $wptraffic_options['api'] ) )
			return false;

		$wptraffic_options['publish_cnt'] = ( $wptraffic_options['publish_cnt']? $wptraffic_options['publish_cnt']: 5 );


		$playlist_id = str_replace( 'playlist-', '', $playlist_id );
		$url = sprintf( "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=%s&key=%s&maxResults=%d", 
						$playlist_id, 
						$wptraffic_options['api'],
						$wptraffic_options['publish_cnt'] 
				);

		if( ! empty( $next_pagetoken ) )
			$url .= '&pageToken='.$next_pagetoken;

		$ret = wp_remote_fopen($url);
		if( ! empty( $ret ) )
			$ret = json_decode( $ret, true );

		if( empty( $ret ) ){
			$this->log( __FUNCTION__, __LINE__, sprintf( __("URL: %s; Empty return", 'wptraffic_lang'), $url ) );
			return false;
		}

		if( isset( $ret['error'] ) ){
			$this->log( __FUNCTION__, __LINE__, sprintf( __("<!-- URL: %s; <br/> -->Error: %s %s", 'wptraffic_lang'), $url, $ret['error']['code'], $ret['error']['message'] ) );
			return false;
		}

		$nextPageToken = '';
		if( isset( $ret['nextPageToken'] ) )
			$nextPageToken = $ret['nextPageToken'];

		$video_arr = array();
		if( ! empty( $ret ) && ! empty( $ret['items'] ) ) {
			foreach( $ret['items'] as $ii => $item ) {
				$video 			= array();
				$video['id'] 		= sanitize_text_field( $item['snippet']['resourceId']['videoId'] );
				$video['title'] 		= sanitize_text_field( $item['snippet']['title'] );
				$video['description']= sanitize_textarea_field( $item['snippet']['description'] );

				$imagesize = '';
				if( ! empty( $item['snippet']['thumbnails']['standard'] ) && ! empty( $item['snippet']['thumbnails']['standard']['url'] ))
					$imagesize = 'standard';
				else if( ! empty( $item['snippet']['thumbnails']['high'] ) && ! empty( $item['snippet']['thumbnails']['high']['url'] ))
					$imagesize = 'high';
				if( ! empty( $item['snippet']['thumbnails']['medium'] ) && ! empty( $item['snippet']['thumbnails']['medium']['url'] ))
					$imagesize = 'medium';
				if( ! empty( $item['snippet']['thumbnails']['default'] ) && ! empty( $item['snippet']['thumbnails']['default']['url'] ))
					$imagesize = 'default';

				if( ! empty( $imagesize ) )
					$video['thumbnail'] 	= array( esc_url($item['snippet']['thumbnails'][$imagesize]['url']), (int)$item['snippet']['thumbnails'][$imagesize]['width'], (int)$item['snippet'][$imagesize]['default']['height'] );

				$video['publishedAt']	= sanitize_text_field( $item['snippet']['publishedAt'] );
				$video_arr[] = $video;
			}
		}

		return array( $video_arr, $nextPageToken );
	}

	/**
	*
	* Wordpress internal Cron job, setup via wp-admin;
	* This runs at time interval specified in wp-admin;
	*
	**/
	function wptraffic_wpcronjob()
	{
		$wptraffic_options = get_option( "wptraffic_options" );
		if( empty( $wptraffic_options['api'] ) ){
			return false;
		}

		if( $wptraffic_options['enable_cron'] != 1 ){
			return false;
		}

		if( empty( $wptraffic_options['enable_cron_hour'] ) )
			$wptraffic_options['enable_cron_hour'] = 6;

		if( empty( $wptraffic_options['enable_cron_hour_last'] ) )
			$wptraffic_options['enable_cron_hour_last'] = DAY_IN_SECONDS;

		if( ( current_time('timestamp') - $wptraffic_options['enable_cron_hour_last'] ) > ( (int)$wptraffic_options['enable_cron_hour'] * HOUR_IN_SECONDS ) )
		{
			$this->log( __FUNCTION__, __LINE__, __("-- === Internal Cron Start === --", 'wptraffic_lang'));

			$post_ids = $this->wptraffic_run_cron();

			$wptraffic_options['enable_cron_hour_last'] = current_time('timestamp');
			update_option( "wptraffic_options", $wptraffic_options );

			$this->log( __FUNCTION__, __LINE__, sprintf( __("Inserted %d Posts", 'wptraffic_lang'), count($post_ids) ) );
			$this->log( __FUNCTION__, __LINE__, __("-- === Internal Cron End === --", 'wptraffic_lang'));
		}
	}

	/**
	*
	* Unix Cron job, called at init;
	*
	*
	**/
	function wptraffic_cron()
	{
		$wptraffic_cron = get_option ("wptraffic_cron");
		if( isset( $_GET['wptraffic_cron'] ) && trim( $_GET['wptraffic_cron'] ) == $wptraffic_cron )
		{
			$this->log( __FUNCTION__, __LINE__, __("-- === Cron Start === --", 'wptraffic_lang'));
			$post_ids = $this->wptraffic_run_cron();
			$this->log( __FUNCTION__, __LINE__, sprintf( __("Inserted %d Posts", 'wptraffic_lang'), count($post_ids) ) );
			$this->log( __FUNCTION__, __LINE__, __("-- === Cron End === --", 'wptraffic_lang'));
			die();
		}
	}

	function get_playlist_name_byid( $playlist_id )
	{
		if( ! $playlist_arr = get_option( 'wptraffic_playlists' ) )
			return false;

		$playlist_id = str_replace( 'playlist-', '', $playlist_id );
		$playlist_arr = json_decode( $playlist_arr, true );
		foreach( $playlist_arr as $playlist_ar )
		{
			if( $playlist_ar['id'] == $playlist_id )
				return esc_attr( $playlist_ar['title'] );
		}
		return false;
	}

	function wptraffic_run_cron()
	{
		global $wpdb;

		$wptraffic_options = get_option( "wptraffic_options" );
		if( empty( $wptraffic_options['api'] ) )
			return false;

		if( ! $fetch_fields = get_option( "wptraffic_fetch_fields" ) ) {
			$this->log( __FUNCTION__, __LINE__, __("Error: Please select Playlists to process from the settings panel.", 'wptraffic_lang') );
			return;
		}

		$fetch_fields = json_decode( $fetch_fields, true );

		$process_key = 0;
		if( ! empty( $wptraffic_options['process_key'] ) )
			$process_key = (int)$wptraffic_options['process_key'];

		if( count( $fetch_fields ) <= $process_key ) {
			$process_key = 0;
		}

		$this->log( __FUNCTION__, __LINE__, sprintf( __("Key: %d; Playlist count: %d", 'wptraffic_lang'), $process_key, count( $fetch_fields ) ) );

		$fetch_fields_key = array_slice($fetch_fields, $process_key, 1, true);
		$fetch_fields_key = key( $fetch_fields_key );
		$fetch_fields_val = $fetch_fields[$fetch_fields_key];

		$process_key++;
		$wptraffic_options['process_key'] = $process_key;
		update_option( "wptraffic_options", $wptraffic_options );

		$playlist_name = $this->get_playlist_name_byid( $fetch_fields_key );
		$this->log( __FUNCTION__, __LINE__, sprintf( __("Processing: %s", 'wptraffic_lang'), $playlist_name.' ['.$fetch_fields_key.']; Next Key:'.$process_key ) );

		$new_cats = array();
		if( ! empty( $fetch_fields_val[0] ) ) {

			if( ! function_exists( 'wp_create_category' ) )
				require_once ABSPATH.'/wp-admin/includes/taxonomy.php';

			$cats = trim( $fetch_fields_val[0], ',' );
			if( ! empty( $cats ) )
				$cats = explode( ',', $cats );

			if( ! empty( $cats ) && ! is_array( $cats ) )
				$cats = array( $cats );

			if( ! empty( $cats ) ){
			foreach( $cats as $cat ) {
				$category = get_term_by('name', $cat, 'category');
				if( ! empty( $category ) ){
					$new_cats[] = (int)$category->term_id;
				}else{
					$new_cats[] = wp_create_category( $cat, 0 );
				}
			}}
		}

		list( $videos, $pagetoken ) = $this->get_videos_by_playlistid( $fetch_fields_key, $fetch_fields_val[1] );//playlist_id, nextPageToken;

		$this->log( __FUNCTION__, __LINE__, sprintf( __("Vid count: %s; NextPageToken: %s", 'wptraffic_lang'), count( $videos ), $pagetoken ) );

		//only overwrite, if we have a new token
		if( ! empty( $pagetoken ) ) {
			$fetch_fields[$fetch_fields_key] = array( $fetch_fields_val[0], $pagetoken );
			$fetch_fields = json_encode( $fetch_fields );
			update_option( "wptraffic_fetch_fields"	, $fetch_fields );
		}

		$post_ids = array();
		if( ! empty( $videos ) ){
		foreach( $videos as $video ){
			if( $xx = $this->wptraffic_publish_videos( $video, $new_cats ) )
				$post_ids[] = $xx;
		}}

		return $post_ids;
	}

	/**
	*
	* $video['id']
	* $video['title']
	* $video['description']
	* $video['thumbnail'] 	= array( 'url', 'width', 'height' );
	* $video['publishedAt']
	*
	*
	**/
	function wptraffic_publish_videos( $post, $new_cats )
	{
		global $wpdb;

		$post_id = $wpdb->get_var( "SELECT `post_id` FROM `".$wpdb->prefix."postmeta` where `meta_key`='_wptraffic_videoid' AND `meta_value`='".$post['id']."'" );
		if( ! empty( $post_id ) ){
			$this->log( __FUNCTION__, __LINE__, sprintf( __("Error - Dup post found ID: %s", 'wptraffic_lang'), $post_id ) );
			return false;
		}

		$wptraffic_options = get_option( "wptraffic_options" );

		//remove_filter('the_content', 'make_clickable');

		$content 	= $this->get_post_content( $post );

		$date 	= gmdate('U');
		$date 	= gmdate('Y-m-d H:i:s', $date + ( get_option('gmt_offset') * 3600 ) );

		$post_array = array(
			'post_author' 	=> ( $wptraffic_options['author']? $wptraffic_options['author']: 1 ), 
			'post_date' 	=> $date, 
			'post_title' 	=> trim( sanitize_text_field($post['title'])),
			'post_content' 	=> $content, 
			'post_category' 	=> $new_cats, 
			'post_status' 	=> 'publish',
			'post_type' 	=> 'post',
		);

		$post_id = wp_insert_post( $post_array, true ); 

		$this->log( __FUNCTION__, __LINE__, sprintf( __("Inserted Post: %s - %s", 'wptraffic_lang'), $post_id, $post_array['post_title'] ) );

		if(is_wp_error($post_id))
		{
			$this->log( __FUNCTION__, __LINE__, sprintf( __("Error - %s", 'wptraffic_lang'), $post_id->get_error_message() ));
			return false;
		}
		add_post_meta($post_id, '_wptraffic_videoid', $post['id'] ); 

		if( ! empty( $post['thumbnail'] ) )
			$this->set_featured_image( $post_id, $post['thumbnail'][0], $post_array['post_title'] );

		return $post_id;
	}

	function get_post_content( $post )
	{
		$wptraffic_options = get_option( "wptraffic_options" );

		if( empty( $wptraffic_options['content'] ) )
			$wptraffic_options['content'] = "[WPTRAFFIC_VIDEO]\n[WPTRAFFIC_DATE]\n\n[WPTRAFFIC_CONTENT]";

		$wptraffic_options['content'] = @html_entity_decode($wptraffic_options['content'], ENT_QUOTES, get_option('blog_charset'));

		$this->wptraffic_post = $post;
		return do_shortcode( $wptraffic_options['content'] );
	}

	function set_featured_image( $post_id, $image_url, $post_title = '' )
	{
		if ( !function_exists('media_handle_upload') ) {
			require_once(ABSPATH . "wp-admin" . '/includes/image.php');
			require_once(ABSPATH . "wp-admin" . '/includes/file.php');
			require_once(ABSPATH . "wp-admin" . '/includes/media.php');
		}

		$file_array 		= array();
		$file_array['name'] 	= 'post-'.intval($post_id).'-'.basename( $image_url );
		$file_array['tmp_name'] = download_url( $image_url );

		if ( is_wp_error( $file_array['tmp_name'] ) ) {
			@unlink( $file_array['tmp_name'] );
			return false;
		}

		// Do the validation and storage stuff.
		$id = media_handle_sideload( $file_array, $post_id, $post_title );

		// If error storing permanently, unlink.
		if ( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] );
			return false;
		}

		return set_post_thumbnail( $post_id, $id );
	}

	function wptraffic_shortcode_title( $atts = array(), $content = '' )
	{
		return esc_attr( $this->wptraffic_post['title'] );
	}

	function wptraffic_shortcode_content( $atts = array(), $content = '' )
	{
		return wpautop( $this->wptraffic_post['description'] );
	}

	function wptraffic_shortcode_video( $atts = array(), $content = '' )
	{
		$wptraffic_options = get_option( "wptraffic_options" );

		return '[embed width="'.(int)$wptraffic_options['size_w'].'" height="'.(int)$wptraffic_options['size_h'].'"]http://www.youtube.com/watch?v='.esc_attr($this->wptraffic_post['id']).'[/embed]';
	}

	function wptraffic_shortcode_date( $atts = array(), $content = '' )
	{
		if( ! strtotime( $this->wptraffic_post['publishedAt'] ) )
			$this->wptraffic_post['publishedAt'] = current_time('timestamp');

		return date( (get_option('date_format').' '.get_option('time_format')), strtotime( $this->wptraffic_post['publishedAt'] ) );
	}

	function wptraffic_the_content( $content = '' )
	{
		global $post;

		if( is_singular()){
			$vid_id = get_post_meta($post->ID, '_wptraffic_videoid', true ); 
			if( $vid_id )
				$content .= "\n<br/> <!-- Post created by WP-Traffic.com, VideoID: ".$vid_id." -->";
		}

		return $content;
	}

	function wptraffic_the_generator( $gen, $type )
	{
		if( $type == 'xhtml' ){
			$gen .= "\n".'<meta name="generator" content="WP-Traffic ' . esc_attr( WPTRAFFIC_VER ) . '" />';
		}
		return $gen;
	}

	/**
	* debug
	*
	*/
	function log( $func, $line, $str )
	{
		$log = "\n[".date( "Y-m-d H:i:s", current_time('timestamp') )."] Function: ".$func.'; Line: '.$line.'; '.$str;
		if( isset( $_GET['v'] ) ){
			echo '<br/>'.$log;
		}
		$this->write_log( $log );
	}

	/**
	* debug
	*
	*/
	function write_log( $str )
	{
		return false;

	/*	file_put_contents( __DIR__.'/log.txt' , $str, FILE_APPEND );

		if( filesize( __DIR__.'/log.txt' ) > 100000 ){
			$file = @file( __DIR__.'/log.txt' );
			$file = array_map( 'trim', $file );
			$file = array_filter( $file );
			$file = array_slice( $file, -200 );
			file_put_contents( __DIR__.'/log.txt' , implode("\n", $file ) );
		}
	*/
	}

	/**
	* debug
	*
	*/
	function reset()
	{
		return false;

		//delete_option( "wptraffic_options" );
		delete_option( 'wptraffic_channel' );
		delete_option( 'wptraffic_playlists' );
		delete_option( "wptraffic_fetch_fields" );
		delete_transient( 'wptraffic_content_transient' );
	}
}
endif;

global $wp_traffic_pro_admin;
if( ! $wp_traffic_pro_admin ) $wp_traffic_pro_admin = new wp_traffic_pro_admin();