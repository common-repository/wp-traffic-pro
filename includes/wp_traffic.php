<?php

/*
 * @class - wp_traffic_pro
 */

if( ! defined( 'WPTRAFFIC_FILE' ) ) die( 'Silence ' );

if( ! class_exists('wp_traffic_pro')):
class wp_traffic_pro
{
	function __construct()
	{
		global $wpdb;

		//few definitions
		define( "WPTRAFFIC_DIR" 			, plugin_dir_path( WPTRAFFIC_FILE ) 		);
		define( "WPTRAFFIC_URL"				, esc_url( plugins_url( '', WPTRAFFIC_FILE ) ).'/');

		define( "WPTRAFFIC_VER"				, "1.0.0" 							);
		define( "WPTRAFFIC_DEBUG"			, false							);

		register_activation_hook( WPTRAFFIC_FILE		, array( &$this, 'wptraffic_activate'	));
		register_deactivation_hook ( WPTRAFFIC_FILE	, array( &$this, 'wptraffic_deactivate'	));

		add_action( 'admin_menu'			, array( &$this, 'wptraffic_options_page'		));
		add_filter( 'plugin_action_links'		, array( &$this, 'wptraffic_plugin_actions'	), 10, 2 );
	}

	function wptraffic_activate()
	{
		global $wpdb;

		if( ! $wptraffic_cron = get_option ("wptraffic_cron") )
		{
			$cron = '';
			foreach(range(1,5) as $a)
				$cron .= chr(mt_rand(97, 122));
			$cron = strtolower($cron);
			update_option ("wptraffic_cron", $cron);
		}

		if( ! $wptraffic_ver = get_option ("wptraffic_ver") )
			update_option ("wptraffic_ver", WPTRAFFIC_VER);
	}

	function wptraffic_deactivate()
	{
		wp_clear_scheduled_hook( 'wptraffic_cronjob' );
		//nothing here//
	}

	static function wptraffic_footer() 
	{
		$plugin_data = get_plugin_data( WPTRAFFIC_FILE );
		printf('%1$s plugin | Version %2$s | by %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']); 
	}

	static function wptraffic_footer_rating() 
	{
		$footer_text = sprintf(
			__( 'Please support <strong><em>WP Traffic Pro</em></strong> by leaving us a %s rating. A huge thanks in advance!', 'wptraffic_lang' ),
			'<a href="https://wordpress.org/support/plugin/wp-traffic-pro/reviews?filter=5&rate=5#new-post" target="_blank" 
				class="wptraffic-rating-link">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
		);
		echo $footer_text;
	}

	static function wptraffic_page_footer() 
	{
		echo '<br/><div id="page_footer" class="postbox" style="text-align:center;padding:10px;clear:both"><em>';
			self::wptraffic_footer(); 
		echo '</em><br/>'."\n";

		echo '<div>';
			self::wptraffic_footer_rating(); 
		echo '</div>';

		echo '</div>';
	}

	function wptraffic_plugin_actions($links, $file)
	{
		if( strpos( $file, basename(WPTRAFFIC_FILE)) !== false )
		{
			$link = '<a href="'.admin_url( 'options-general.php?page=wptraffic_main').'">'.__('Settings', 'wptraffic_lang').'</a>';
			array_unshift( $links, $link );
		}
		return $links;
	}

	function wptraffic_options_page()
	{
		global $wp_traffic_pro_admin;
		add_options_page(__('WP Traffic Pro', 'wptraffic_lang'), __('WP Traffic Pro', 'wptraffic_lang'), 8, 'wptraffic_main', array( &$wp_traffic_pro_admin, 'wptraffic_main' ) );
	}
}
endif;

require_once __DIR__.'/wp_traffic_admin.php';

global $wp_traffic_pro;
if( ! $wp_traffic_pro ) $wp_traffic_pro = new wp_traffic_pro();