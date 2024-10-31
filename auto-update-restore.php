<?php
/**
* @package SafeAutoUpdateRestoreManager
*/

/*
Plugin Name: Autoupdate Plugins & Themes
Plugin URI: http://www.telberia.com/
Description: Safely upgrade or rollback any plugin either automatically or manually
version: 1.0.0
Author: codemenschen
Author URI: http://www.codemenschen.at/
Licence: GPLv2
Text Domain: safe-auto-update-restore-manager
*/

/*
Autoupdate Plugins & Themes is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

Autoupdate Plugins & Themes is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2019-2020 Automatic, Inc.
*/

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

define( 'AUTOUPDATERESTORE_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'AUTOUPDATERESTORE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'AUTOUPDATERESTORE_PLUGIN_DIR_URL', plugins_url() );

$upload = wp_upload_dir();
define( 'autoupdaterestore_upload_backup_dir_path', $upload['basedir'].'/autoupdaterestore_backup/' );
define( 'autoupdaterestore_upload_sitemaps_dir_path', $upload['basedir'].'/autoupdaterestore_sitemaps/' );
define( 'autoupdaterestore_upload_sitemaps_dir_url', $upload['baseurl'].'/autoupdaterestore_sitemaps/' );


include_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
if ( ! function_exists( 'get_plugins' ) ) { include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); }
if(basename($_SERVER['PHP_SELF']) == 'options-general.php'){
	if ( ! function_exists( 'plugins_api' ) ) { include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' ); }
}
require_once( AUTOUPDATERESTORE_PLUGIN_DIR.'/include/function.php' );

add_action( 'init', 'autoupdaterestore_wptuts_scripts_basic' );
function autoupdaterestore_wptuts_scripts_basic()
{
    wp_register_style( 'autoupdaterestore_style', AUTOUPDATERESTORE_PLUGIN_URL.'/css/style.css' );
    wp_enqueue_style( 'autoupdaterestore_style' );

	wp_enqueue_script( 'autoupdaterestore_script', AUTOUPDATERESTORE_PLUGIN_URL.'/js/script.js', array ( 'jquery' ), 1.1, true);

	wp_enqueue_style('autoupdaterestore_custom_style',get_template_directory_uri() . '/css/custom_script.css');

    $custom_css = "
            #rollback-message{
                display: none;
            }
            #message{
                display: none;
            }
            #default{
            	display: block;
            }";
    wp_add_inline_style( 'autoupdaterestore_custom_style', $custom_css );
}

register_activation_hook( __FILE__, 'autoupdaterestore_plugin_activation' );
function autoupdaterestore_plugin_activation()
{
    #Check to see if the table exists already, if not, then create it
    global $wpdb;
    $autoupdaterestore_data_tblname = $wpdb->prefix.'autoupdaterestore_data';
	$autoupdaterestore_backup_tblname = $wpdb->prefix.'autoupdaterestore_backup';
	$autoupdaterestore_updates_tblname = $wpdb->prefix.'autoupdaterestore_updates';
	$charset_collate = $wpdb->get_charset_collate();

    if($wpdb->get_var( "show tables like '$autoupdaterestore_data_tblname'" ) != $autoupdaterestore_data_tblname)
    {
        $autoupdaterestore_data_sql = "CREATE TABLE $autoupdaterestore_data_tblname (
		 	id mediumint(9) NOT NULL AUTO_INCREMENT,
		 	plugin_name text NOT NULL,
		 	plugin_version text NOT NULL,
		 	type text NOT NULL,
			PRIMARY KEY  (id)
	 	) $charset_collate;";

        dbDelta($autoupdaterestore_data_sql);
    }

    if($wpdb->get_var( "show tables like '$autoupdaterestore_backup_tblname'" ) != $autoupdaterestore_backup_tblname)
    {
        $autoupdaterestore_backup_sql = "CREATE TABLE $autoupdaterestore_backup_tblname (
        	ID mediumint(9) NOT NULL AUTO_INCREMENT,
			datetime datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			file_name text NOT NULL,
			PRIMARY KEY (ID)
	 	) $charset_collate;";

        dbDelta($autoupdaterestore_backup_sql);
    }

    if($wpdb->get_var( "show tables like '$autoupdaterestore_updates_tblname'" ) != $autoupdaterestore_updates_tblname)
    {
        $autoupdaterestore_updates_sql = "CREATE TABLE $autoupdaterestore_updates_tblname (
        	id mediumint(9) NOT NULL AUTO_INCREMENT,
			plugin_name text NOT NULL,
			slug varchar(255) DEFAULT NULL,
			date_time datetime NOT NULL,
			cron_name varchar(255) DEFAULT NULL,
			status int(11) DEFAULT '0',
			version varchar(255) DEFAULT NULL,
			PRIMARY KEY (id)
	 	) $charset_collate;";

        dbDelta($autoupdaterestore_updates_sql);
    }

	$allPlugins = get_plugins();

	$count = 0; $result = array();
    
    foreach($allPlugins as $key => $value) {
    	$count++;
    	$plugin_name = $value['Name'];
    	$plugin_version = $value['Version'];
    	$autoupdaterestore_data_tblname = $wpdb->prefix.'autoupdaterestore_data';

    	$plugin = $wpdb->get_row( "SELECT * FROM $autoupdaterestore_data_tblname WHERE plugin_name = '$plugin_name' AND type='plugin'" );

    	$data = array(
			'plugin_name' => $plugin_name ,
			'plugin_version' => $plugin_version,
			'type' => 'plugin'
		);

    	if($plugin){
    		$plugin_id = $plugin->id;
			$wpdb->update($autoupdaterestore_data_tblname,$data,array('id'=>$plugin_id));
    	}else{
    		$wpdb->insert($autoupdaterestore_data_tblname,$data);
    		$id = $wpdb->insert_id;
    	}
    }
}

register_deactivation_hook( __FILE__, 'deactivate_plugin_database_table' );
function deactivate_plugin_database_table(){
	// Deactivation Code and Stop Cron
	$timestamp = wp_next_scheduled ('autoupdaterestore_plugin_cron');
	wp_clear_scheduled_hook('autoupdaterestore_plugin_cron');
}

register_uninstall_hook( __FILE__, 'uninstall_auto_update' );
function uninstall_auto_update(){
 	global $wpdb;
	$autoupdaterestore_data_tblname = $wpdb->prefix.'autoupdaterestore_data';
	$autoupdaterestore_backup_tblname = $wpdb->prefix.'autoupdaterestore_backup';
	$autoupdaterestore_updates_tblname = $wpdb->prefix.'autoupdaterestore_updates';

 	$sql = "DROP TABLE IF EXISTS $autoupdaterestore_data_tblname;";
 	$wpdb->query($sql);

 	$sql = "DROP TABLE IF EXISTS $autoupdaterestore_backup_tblname;";
 	$wpdb->query($sql);

 	$sql = "DROP TABLE IF EXISTS $autoupdaterestore_updates_tblname;";
 	$wpdb->query($sql);

 	delete_option("autoupdaterestore_plugin_settings");
 	delete_option("cron_timeing");
 	delete_option("autoupdaterestore_plugin_eml");

 	autoupdaterestore_remove_directory(autoupdaterestore_upload_backup_dir_path);
 	autoupdaterestore_remove_directory(autoupdaterestore_upload_sitemaps_dir_path);
}

add_action('admin_menu', 'auto_update_admin_settings_setup');
function auto_update_admin_settings_setup() {
	add_options_page('Auto Update Manager', 'Auto Update Manager', 'manage_options', 'auto-update-restore', 'autoupdaterestore_admin_settings_page');
}

function autoupdaterestore_admin_settings_page(){
	global $autoupdaterestore_active_tab;
	$autoupdaterestore_active_tab = isset( $_GET['tab'] ) ? sanitize_text_field($_GET['tab']) : 'settings'; ?>
 
	<h2 class="nav-tab-wrapper">
	<?php
		do_action( 'autoupdaterestore_settings_tab' );
	?>
	</h2>
	<?php
		do_action( 'autoupdaterestore_settings_content' );
}

add_action( 'autoupdaterestore_settings_tab', 'autoupdaterestore_welcome_tab', 1 );
function autoupdaterestore_welcome_tab(){
	global $autoupdaterestore_active_tab; ?>
	<a class="nav-tab <?php echo $autoupdaterestore_active_tab == 'settings' || '' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'options-general.php?page=auto-update-restore&tab=settings' ); ?>"><?php _e( 'Settings', 'autoupdaterestore' ); ?> </a>
	<?php
	// Add some default settings

	$email = get_option('admin_email');

	$setting_email = get_option('autoupdaterestore_plugin_eml');
	$from_email_address = get_option('autoupdaterestore_plugin_frm_eml');

	if(!$setting_email){
		add_option( 'autoupdaterestore_plugin_eml', $email, '', 'yes' );
	}else{
		update_option('autoupdaterestore_plugin_eml',$email,'yes');
	}

	if(!$from_email_address){
		add_option( 'autoupdaterestore_plugin_frm_eml', $from_email_address, '', 'yes' );
	}else{
		update_option('autoupdaterestore_plugin_frm_eml',$from_email_address,'yes');
	}
}

//START Cron Code

// create a scheduled event (if it does not exist already)

add_filter( 'cron_schedules', 'autoupdaterestore_cron_add_weeklymonthly' );
function autoupdaterestore_cron_add_weeklymonthly( $schedules ) {
 	// Adds once weekly to the existing schedules.
 	$schedules['weekly'] = array(
 		'interval' => 604800,
 		'display' => __( 'Once Weekly' )
 	);

 	// Adds once monthly to the existing schedules.
 	$schedules['monthly'] = array(
 		'interval' => 2592000,
 		'display' => __( 'Once Monthly' )
 	);

 	return $schedules;
}

add_action('init', 'autoupdaterestore_cronstarter_activation');
function autoupdaterestore_cronstarter_activation() {

	$cron_timing = get_option('cron_timeing');

	if($cron_timing){
		if( !wp_next_scheduled( 'autoupdaterestore_plugin_cron' ) ) {  
		   wp_schedule_event( time(), $cron_timing, 'autoupdaterestore_plugin_cron' );
		}
	}
}

add_action ('autoupdaterestore_plugin_cron', 'autoupdaterestore_repeat_function');
function autoupdaterestore_repeat_function() {
	global $wpdb;
    $allPlugins = get_plugins(); // associative array of all installed plugins
    $activePlugins = get_option('active_plugins'); // simple array of active plugins
    $autoupdaterestore_backup_tblname = $wpdb->prefix.'autoupdaterestore_backup';
    $autoupdaterestore_updates_tblname = $wpdb->prefix.'autoupdaterestore_updates';

    if ( ! function_exists( 'plugins_api' ) ) {
	    require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
	}
	
	$auto_update_settings = get_option('autoupdaterestore_plugin_settings');

    // traversing $allPlugins array
    $result = array(); $count=0;

    $to = get_option('autoupdaterestore_plugin_eml');
    $from_email_address = get_option('autoupdaterestore_plugin_frm_eml');

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

    // Additional headers
	$headers .= 'From: '.$from_email_address;
	

    $mail_content_header = "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.0 Transitional//EN'>
    <html>
    	<head>
    		<title>Plugin Updates</title>
    		<meta name='' content=''>
    	</head>
    	<body style='margin: 0px;font-family:Arial;'>
    		<table width='523' align='center' border='0' cellpadding='2' cellspacing='2' style='font-size:14px; color:#333333; background: #f2f2f2;padding: 20px;'>
    			<tbody>";

 	$mail_content_footer = "
 				</tbody>
		    </table>
		</body>
	</html>";
    
	if($auto_update_settings == 'yes'){
		// Mail Content if Auto update is On
		$plugin_update_notice_mail_content = "
	    <tr>
	        <td colspan='4' style='color: #666666; font-size:16px;  padding-top: 40px;text-align:center;width:100%'>
	        	We have detected that below listed plugin's update are available, or else plugins will be updated automatically.
	        </td>
	    </tr>
	    <tr></tr><tr></tr>
		<tr>
			<th>Name</th>
			<th>Current Version</th>
			<th>Latest Version</th>
			<th>Update Now</th>
	   	</tr>";
	}else{
		// Mail Content if Auto update is Off
		$plugin_update_notice_mail_content = "
	    <tr>
	        <td colspan='4' style='color: #666666; font-size:16px;  padding-top: 40px;text-align:center;width:100%'>
	        	We have detected that below listed plugin's update are available, But Your Auto Update settings is off If you allow from Admin we will Auto Update the Plugin.
	        </td>
	    </tr>
	    <tr></tr><tr></tr>
		<tr>
			<th>Name</th>
			<th>Current Version</th>
			<th>Latest Version</th>
			<th>Update Now</th>
	   	</tr>";
	}

	$plugin_updated_mail_content = "
	<tr>
        <td colspan='4' style='color: #666666; font-size:16px;  padding-top: 40px;text-align:center;width:100%'>
        	Below plugins have been updated automatically.Kindly check you site for issues.
    	</td>
    </tr>
    <tr></tr><tr></tr>
	<tr>
		<th>Name</th>
		<th>Plugin Version</th>
		<th>Rollback</th>
	</tr>";   

    $updated ='';
    $available = '';
    
    $date_time = date('Y-m-d H:i:s');

    foreach($allPlugins as $key => $value) {

    	$check_version_latest = '';
    	$version_latest = '';

        if(in_array($key, $activePlugins)) { // display active only
            $slug = explode('/',$key)[0]; // get active plugin's slug
            $args = array(
			    'slug' => $slug,
			    'fields' => array(
			        'version' => true,
			    )
			);

			$call_api = plugins_api( 'plugin_information', $args);

			if ( is_wp_error( $call_api ) ) {
			    $api_error = $call_api->get_error_message();
			} else {
			    if ( ! empty( $call_api->version ) ) {
			        $version_latest = $call_api->version;
			    }
			}

			if(isset($version_latest) && $version_latest != ''){
				$check_version_latest = $version_latest;
			}else{
				$check_version_latest = 'Unavailable';
			}

			if($value['Version'] != $check_version_latest && $check_version_latest != 'Unavailable'){

				$plugin_name = $value['Name'];

				$plugin_data = $wpdb->get_row("SELECT * FROM $autoupdaterestore_updates_tblname WHERE plugin_name = '$plugin_name' AND status='0'");
				
				if($plugin_data ){
					if($auto_update_settings == 'yes'){
						// Plugin Update code

						autoupdaterestore_update_restore_plugin($slug,'');

						$update_plugin = array('status' => '1');
					    
					    $wpdb->update($autoupdaterestore_updates_tblname,$update_plugin,array('plugin_name'=>$plugin_name));
						
						$updated = true;
						$plugin_updated_mail_content.= "
						<tr></tr><tr></tr>
						<tr>
							<td style='color: #666666; font-size:16px;'>".$plugin_name."</td>
							<td style='color: #666666; font-size:16px;'>".$version_latest."</td>
							<td style='color: #666666; font-size:16px;'><a href='".admin_url('options-general.php?page=auto-update-restore&tab=settings')."' > Rollback</a></td>
						</tr>";
					}
				}else{
					// First time mail send for updates

					$count++;
					$available = true;
				    
				    $data2 = array('plugin_name' => $plugin_name,'slug'=>$slug,'date_time' => $date_time,'version' => $value['Version'],'status' => '0');
				    $wpdb->insert($autoupdaterestore_updates_tblname,$data2);

					$plugin_update_notice_mail_content.= "
					<tr></tr><tr></tr>
					<tr>
						<td style='color: #666666; font-size:16px;'>".$value['Name']."</td>
						<td style='color: #666666; font-size:16px;'>".$value['Version']."</td>
						<td style='color: #666666; font-size:16px;'>".$check_version_latest."</td>
						<td style='color: #666666; font-size:16px;'><a href='".admin_url('plugins.php')."' > Update Now</a></td>
					</tr>";
				}
			}else{
				//$available = false;
			}
        }
    }

    if($updated) {
    	$autoupdaterestore_updates_tblname = $wpdb->prefix.'autoupdaterestore_updates';
    	$mail_subject = "Plugin updated automatically.";
    	$mail_content = $mail_content_header . $plugin_updated_mail_content . $mail_content_footer;
		wp_mail($to,$mail_subject,$mail_content,$headers);
		
		// Backup after update plugin
		autoupdaterestore_create_full_backup();
		
		$date = date('Y-m-d');
		
		$qry = "SELECT * FROM $autoupdaterestore_backup_tblname WHERE DATE(datetime)='".$date."'";
		$results = $wpdb->get_results($qry);
		$status = 0;

		foreach($results as $row){
			
			$filename = autoupdaterestore_upload_backup_dir_path.$date.'/'.$row->file_name;
			
			$contents = file_get_contents($filename);
			$Parse = 'Parse error:';
			$Parsepattern = preg_quote($Parse, '/');
			$Parsepattern = "/^.*$Parsepattern.*\$/m";
			$Syntax = 'Syntax error';
			$Syntaxpattern = preg_quote($Syntax, '/');
			$Syntaxpattern = "/^.*$Syntaxpattern.*\$/m";
			$Fatal = 'Fatal error:';
			$Fatalpattern = preg_quote($Fatal, '/');
			$Fatalpattern = "/^.*$Fatalpattern.*\$/m";
			$Warning  = 'Warning:';
			$Warningpattern = preg_quote($Warning, '/');
			$Warningpattern = "/^.*$Warningpattern.*\$/m";
			$Notice  = 'Notice:';
			$Noticepattern = preg_quote($Notice, '/');
			$Noticepattern = "/^.*$Noticepattern.*\$/m";
			
			// search, and store all matching occurences in $matches
			if(preg_match_all($Parsepattern, $contents, $matches)){
			   $status++;
			   implode("\n", $matches[0]);
			}
			else if(preg_match_all($Syntaxpattern, $contents, $matches)){
			   $status++;
			   implode("\n", $matches[0]);
			}
			else if(preg_match_all($Fatalpattern, $contents, $matches)){
			   $status++;
			   implode("\n", $matches[0]);
			}
			else if(preg_match_all($Warningpattern, $contents, $matches)){
			   $status++;
			   implode("\n", $matches[0]);
			}
			else if(preg_match_all($Noticepattern, $contents, $matches)){
			   $status++;
			   implode("\n", $matches[0]);
			}
			else{
			}
		}
		if($status > 0){

			$date = date('Y-m-d');
			
			$get_data = $wpdb->get_results("SELECT * FROM '$autoupdaterestore_updates_tblname' WHERE DATE(date_time) = '$date' AND status='1'");
			
			// Error Found 
			$mail_subject = "Plugin Rollback";
			$plugin_update_mail_content = "<tr><td>We did update the plugins but found some errors after update so, we have rollback that plugin.</td></tr>";
			$mail_content = $mail_content_header . $plugin_update_mail_content . $mail_content_footer;
			wp_mail($to,$mail_subject,$mail_content,$headers);

			foreach ($get_data as $value) {
				$plugin_slug = $value->slug; $plugin_version = $value->version;
				autoupdaterestore_update_restore_plugin($plugin_slug,$plugin_version);
			}
		}
		else{
			// Error not found
			
			$mail_subject = "Plugin Updated";
			$plugin_update_mail_content = "<tr><td>We did update the plugins and everything looks fine when we checked from ourside.</td></tr>
			<tr><td>Still you can check from your side.</td></tr>
			<tr>Thanks...!</tr>";
			$mail_content = $mail_content_header . $plugin_update_mail_content . $mail_content_footer;
			wp_mail($to,$mail_subject,$mail_content,$headers);
		}
		
    } if($available){
    	$mail_subject = "Plugin updates detected";
	    $mail_content = $mail_content_header . $plugin_update_notice_mail_content . $mail_content_footer;
		wp_mail($to,$mail_subject,$mail_content,$headers);
    }
}

//END Cron Code

add_action( 'autoupdaterestore_settings_content', 'autoupdaterestore_welcome_render_options_page' );
function autoupdaterestore_welcome_render_options_page() {

	global $autoupdaterestore_active_tab,$wpdb;
	if ( '' || 'settings' != $autoupdaterestore_active_tab )
		return;
	?>
 	
	<h3><?php _e( 'Plugin Settings', 'autoupdaterestore' ); ?></h3>
	<!-- Put your content here -->
	
	<p>From here you can change the settings of the Plugin.</p>
	<div class="tab">
	  <button class="tablinks active" id="default-tab" onclick="openTab(event, 'default')">Default</button>
	  <button class="tablinks" id="advanced-tab" onclick="openTab(event, 'advanced')">Advanced</button>
	  <button class="tablinks" id="backup-tab" onclick="openTab(event, 'backup_history')">Backup History</button>
	</div>

	<?php
		if(isset($_REQUEST['submit_rollback'])){
			$retrieved_nonce = sanitize_text_field($_REQUEST['_wpnonce']);
			if (!wp_verify_nonce($retrieved_nonce, 'rollback_nonce' ) ) die( 'Failed security check' );

			$radio_counter = sanitize_text_field($_REQUEST['radio-button']);
			$url = sanitize_text_field($_REQUEST['downloadurl-'.$radio_counter]);
			$folder_name = sanitize_text_field($_REQUEST['pluginslug']);

			$zip_file = WP_PLUGIN_DIR .'/downloadfile.zip';

			//Download file from url and save to zip file
			$response = wp_remote_get($url);
			file_put_contents($zip_file, wp_remote_retrieve_body($response));
		

			$zip = new ZipArchive;
			//$extractPath = $zip_file;

			if($zip->open($zip_file) != "true"){
			 	echo "Error :- Unable to open the Zip File";
			}else{
				rename(WP_PLUGIN_DIR."/".$folder_name,WP_PLUGIN_DIR."/".$folder_name.'-old');
				$zip->extractTo(WP_PLUGIN_DIR.'/');
				$zip->close();
				$removefolder = WP_PLUGIN_DIR."/".$folder_name.'-old';
				//call our function
				autoupdaterestore_remove_directory($removefolder);
			}
			unlink(WP_PLUGIN_DIR."/downloadfile.zip");

			header("Location: ".admin_url( 'options-general.php?page=auto-update-restore&tab=settings&rlmsg=2')."");
			wp_die();
		}
	?>

	<!-- Tab content -->
	<div id="default" class="tabcontent">
	  <h3>Default Configuration</h3>

	  <?php
		// Start Backup for Post

		if(isset($_REQUEST['save_settings'])){

			$retrieved_nonce = sanitize_text_field($_REQUEST['_wpnonce']);
			if (!wp_verify_nonce($retrieved_nonce, 'save_settings_nonce' ) ) die( 'Failed security check' );

			$autoupdaterestore_data_tblname = $wpdb->prefix.'autoupdaterestore_data';
			$autoupdaterestore_plugin_settings = get_option('autoupdaterestore_plugin_settings');
			$autoupdaterestore_cron_timeing = get_option('cron_timeing');

			// Setting to Allow Auto Update
			if(sanitize_text_field($_REQUEST['update_answer']) == 'yes'){
				if(!$autoupdaterestore_plugin_settings){
					add_option( 'autoupdaterestore_plugin_settings', 'yes', '', 'yes' );
				}else{
					update_option( 'autoupdaterestore_plugin_settings', 'yes', 'yes' );
				}
				if(!$autoupdaterestore_cron_timeing){
					add_option( 'cron_timeing', sanitize_text_field($_REQUEST['cron_timeing']), '', 'yes' );
				}else{
					update_option( 'cron_timeing', sanitize_text_field($_REQUEST['cron_timeing']), 'yes' );
				}

				wp_clear_scheduled_hook('autoupdaterestore_plugin_cron');

				if( !wp_next_scheduled( 'autoupdaterestore_plugin_cron' ) ) {  
				   wp_schedule_event( time(), sanitize_text_field($_REQUEST['cron_timeing']), 'autoupdaterestore_plugin_cron' );
				}

			}else if(sanitize_text_field($_REQUEST['update_answer']) == 'no'){
				if(!$autoupdaterestore_plugin_settings){
					add_option( 'autoupdaterestore_plugin_settings', 'no', '', 'yes' );
				}else{
					update_option( 'autoupdaterestore_plugin_settings', 'no', 'yes' );
				}
			}

			// Setting Email Address			
			$email = sanitize_email($_REQUEST['plugin_eml']);
			$setting_email = get_option('autoupdaterestore_plugin_eml');
			
			if($setting_email){
				update_option('autoupdaterestore_plugin_eml',$email,'yes');
			}else{
				add_option('autoupdaterestore_plugin_eml',$email, '', 'yes' );
			}

			$plugin_frm_eml = sanitize_email($_REQUEST['plugin_frm_eml']);
			$setting_from_email = get_option('autoupdaterestore_plugin_frm_eml');

			if($setting_from_email){
				update_option('autoupdaterestore_plugin_frm_eml',$plugin_frm_eml,'yes');
			}else{
				add_option('autoupdaterestore_plugin_frm_eml',$plugin_frm_eml, '', 'yes' );
			}

			// Setting Sitemap file

			if(isset($_FILES["sitemap_file"]) && $_FILES["sitemap_file"]["error"] == UPLOAD_ERR_OK){
				$upload = wp_upload_dir();
				if(!file_exists(autoupdaterestore_upload_sitemaps_dir_path)) mkdir(autoupdaterestore_upload_sitemaps_dir_path,0777);

				$autoupdaterestore_sitemap_tmp_name = $_FILES["sitemap_file"]["tmp_name"];
				$autoupdaterestore_sitemap_name = basename($_FILES["sitemap_file"]["name"]);
				move_uploaded_file($autoupdaterestore_sitemap_tmp_name, autoupdaterestore_upload_sitemaps_dir_path.$autoupdaterestore_sitemap_name);
				$sitemap_path = autoupdaterestore_upload_sitemaps_dir_url.$autoupdaterestore_sitemap_name;

				// Read xml file or txt file
				$file_parts = pathinfo($autoupdaterestore_sitemap_name);
				if($file_parts['extension'] == 'xml'){
					$xmldata = simplexml_load_file("$sitemap_path") or die("Failed to load");
					
					$autoupdaterestore_sitemap_delete_qry = "DELETE FROM $autoupdaterestore_data_tblname WHERE type='sitemap'";
					$wpdb->query($autoupdaterestore_sitemap_delete_qry);

					foreach ($xmldata->url as $url_list) {
					    $url =  (string)$url_list->loc;
					    $data = array(
							'plugin_name' =>  $url,
							'plugin_version' => '',
							'type' => 'sitemap'
						);

					    $wpdb->insert($autoupdaterestore_data_tblname,$data);
					}
					unlink(autoupdaterestore_upload_sitemaps_dir_path.$autoupdaterestore_sitemap_name);

				}else if($file_parts['extension'] == 'txt'){
					//Read TXT File
					
					$autoupdaterestore_sitemap_delete_qry = "DELETE FROM $autoupdaterestore_data_tblname WHERE type='sitemap'";
					$wpdb->query($autoupdaterestore_sitemap_delete_qry);
					$fn = fopen($sitemap_path,"r");
					while(! feof($fn))
					{
						$data = array(
							'plugin_name' =>   fgets($fn),
							'plugin_version' => '',
							'type' => 'sitemap'
						);

					    $wpdb->insert($autoupdaterestore_data_tblname,$data);
					}

					fclose($fn);
					unlink(autoupdaterestore_upload_sitemaps_dir_path.$autoupdaterestore_sitemap_name);
				}
			}

			// Setting for Backup
			if(sanitize_text_field($_REQUEST['answer']) == 'yes'){
				autoupdaterestore_create_full_backup();
			}
			header("Location: ".admin_url( 'options-general.php?page=auto-update-restore&tab=settings&msg=1')."");
		}
		// End Backup for post
	  ?>

	  <div class="main-container">
	  	<!-- Setting Form start -->
	  	<form action="" method="POST" name="backup_frm" enctype="multipart/form-data">
	  		<p class="error-message" id="message">Data Saved Successfully</p>
	  		<p class="error-message" id="rollback-message">Plugin version changed successfully</p>
	  		<?php wp_nonce_field('save_settings_nonce'); ?>
		  	<h2>Do you want to Take backup of your Data ? </h2>
		  	<input type="radio" name="answer" value="yes" <?php if(get_option('autoupdaterestore_plugin_settings') == 'yes'){ echo 'checked="checked"'; } ?>>YES<br>
		  	<input type="radio" name="answer" value="no" <?php if(get_option('autoupdaterestore_plugin_settings') == 'no' || get_option('autoupdaterestore_plugin_settings') == ''){ echo 'checked="checked"'; } ?>>NO <br><br>
		  
		  	<h2>Do you want to allow this Plugin to Update Plugin Automatically ? </h2>
		  	<?php $update_settings = get_option('autoupdaterestore_plugin_settings'); 
		  	$autoupdaterestore_cron_timeing = get_option('cron_timeing'); ?>
		  	<input type="radio" name="update_answer" value="yes" <?php if($update_settings == 'yes'){ echo 'checked="checked"'; } ?>>YES<br>
		  	<input type="radio" name="update_answer" value="no" <?php if($update_settings == 'no' || $update_settings == ''){ echo 'checked="checked"'; } ?>>NO <br><br>
		  	<div id="cron_time" >
			  	<h2>Update Plugin After this period of time.</h2>
			  	<select name="cron_timeing">
			  		<option <?php echo($autoupdaterestore_cron_timeing=='daily')?'selected':null; ?> value="daily" >Daily</option>
			  		<option <?php echo($autoupdaterestore_cron_timeing=='weekly')?'selected':null; ?> value="weekly" >Weekly</option>
			  		<option <?php echo($autoupdaterestore_cron_timeing=='monthly')?'selected':null; ?> value="monthly" >Monthly</option>
			  	</select><br><br>
		  	</div>
		  	<h2>Enter Your Email Address</h2><p>(Where you want to Recieve E-mails)</p>
		  	<input class="autoupdaterestore-input" type="email" name="plugin_eml" value="<?php $setting_email = get_option('autoupdaterestore_plugin_eml'); if($setting_email){ echo $setting_email; }else{ bloginfo('admin_email'); } ?>" placeholder="Email Address" /><br><br>

		  	<h2>Enter From Email Address</h2><p>(From which you want get notified !)</p>
		  	<input class="autoupdaterestore-input" type="email" name="plugin_frm_eml" value="<?php $setting_from_email = get_option('autoupdaterestore_plugin_frm_eml'); if($setting_from_email){ echo $setting_from_email; }else{ echo 'wordpress@website.xyz'; } ?>" placeholder="From Email Address" /><br><br>
	    	<?php
	    		$autoupdaterestore_data_tblname = $wpdb->prefix.'autoupdaterestore_data';
				$plugin = $wpdb->get_results( "SELECT * FROM $autoupdaterestore_data_tblname WHERE type='sitemap'" );

				if($plugin){
					$set_required = '';
				}else{
					$set_required = 'required';
				}
	    	?>
	    	<input type="file" name="sitemap_file" class="autoupdaterestore-input" <?php echo $set_required; ?> accept=".xml,.txt" />Upload Site map here<br><br> 
	  		<!-- <input type="file" name="sitemap_file_loggedin" style=" width: 300px;" accept=".xml,.txt" />Site map password protected page<br><br> -->

		  	<input class="button-primary" type="submit" name="save_settings" value="Submit"><br>
	  	</form>
		<!-- Setting Form END -->

	</div>

	  	<?php

	  	$allPlugins = get_plugins(); // array of all installed plugins
        $activePlugins = get_option('active_plugins'); // array of active plugins

        // building active plugins table
        echo '<hr></hr>';
        echo '<hr></hr>';
        echo '<center><h3>Plugin Details</h3></center>';
        echo '<table class="autoupdaterestore-table" width="100%">';
        echo '<thead>';
        echo '<tr class="autoupdaterestore-tr">';
        echo '<th class="autoupdaterestore-th" width="20%" >Plugin Name</th>';
        echo '<th class="autoupdaterestore-th" width="15%" >Current Version</th>';
        echo '<th class="autoupdaterestore-th" width="15%" >Latest Available Version</th>';
        echo '<th class="autoupdaterestore-th" width="15%" >Rollback</th>';
        echo '<th class="autoupdaterestore-th" width="20%" >Update Available ?</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        // traversing $allPlugins array
        $count = 0; $result = array();
        foreach($allPlugins as $key => $value) {
        	$count++; $version_latest = '';
            if(in_array($key, $activePlugins)) { // display active only
                echo '<tr class="autoupdaterestore-tr">';
                echo '<td class="autoupdaterestore-td"><strong>'.$value['Name'].'</strong></td>';
                echo '<td class="autoupdaterestore-td">'.$value['Version'].'</td>';
                $slug = explode('/',$key)[0]; // get active plugin's slug
                
                $args = array(
				    'slug' => $slug,
				    'fields' => array(
				        'version' => true,
				    )
				);

				$call_api = plugins_api( 'plugin_information', $args);

				if ( is_wp_error( $call_api ) ) {
				    $api_error = $call_api->get_error_message();
				} else {
				    if ( ! empty( $call_api->version ) ) {
				        $version_latest = $call_api->version;
				    }
				}
				$version_latest = (isset($version_latest) && $version_latest!='')?$version_latest : 'Unavailable';
				echo '<td class="autoupdaterestore-td">'.$version_latest.'</td>';
				echo "<td class='autoupdaterestore-td'><a href='". admin_url( 'options-general.php?page=auto-update-restore&tab=settings&plugin_slug='.$slug ) ."'>Rollback</a></td>";

				if(isset($version_latest) && $version_latest != ''){
					$check_version_latest = $version_latest;
				}else{
					$check_version_latest = 'Unavailable';
				}

				if($value['Version'] != $version_latest && $check_version_latest != 'Unavailable'){
					$available = "Yes";
				}else{
					$available = "No";
				}

				echo '<td class="autoupdaterestore-td">'.$available.'</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
	  ?>
	</div>

	<div id="advanced" class="tabcontent">
	  <h3>Advance Settings</h3>
	  <div id="plugin-data"></div>
	  <?php
		if(isset($_REQUEST['plugin_slug']) && sanitize_text_field($_REQUEST['plugin_slug']) != ''){
			$slug = sanitize_text_field($_REQUEST['plugin_slug']);

			$url = "https://api.wordpress.org/plugins/info/1.0/".$slug.".json";

			$json = wp_remote_retrieve_body(wp_remote_get($url));
			$obj = json_decode($json);

			$result = array();
			
			if(isset($obj->error) && $obj->error != ''){
				echo "<h5>No Data Found.</h5>";
			}else{
				$version_array = array();
				$version_array[] = $obj->versions;

				echo "<h3>".$obj->name."</h3>"."<br>";

				foreach ($version_array as $key => $value) {
				    $result[] = $value;
				    ?>
				    <form action="" method="POST" id="rollback_form" name="rollback_form">
				    	<?php wp_nonce_field('rollback_nonce'); ?>
				    <?php
				    if($value){
				    	$count = 0;

				    	foreach ($value as $key2 => $value2) {
				    		$count++;
					    	$result_key[] = $key2;
					    	?>
					    	<input type="radio" name="radio-button" value="<?php echo $count; ?>"><?php echo $key2; ?><br>
					    	<input type="hidden" name="downloadurl-<?php echo $count; ?>" value="<?php echo $value2; ?>">
					    	<input type="hidden" name="counter" value="<?php echo $count; ?>">
					    	<input type="hidden" name="pluginslug" value="<?php echo $obj->slug; ?>">
						    <?php
					    }
					    ?><br>
					    <input type="submit" name="submit_rollback" class="button-primary" value="Rollback">
					</form>
				    <?php
					}else{ echo "<h5>No Data Found.</h5>"; }
				}
			}
		}
	  ?>
	</div>


	<div id="backup_history" class="tabcontent">
	  <?php
	  	$autoupdaterestore_backup_tblname = $wpdb->prefix.'autoupdaterestore_backup';
	  	$backup_results = $wpdb->get_results( "SELECT * FROM $autoupdaterestore_backup_tblname");
	  ?>
	  <h3>Backup History</h3>
	  <table class="autoupdaterestore-table" border="1">
	  	<thead>
	  	<tr class="autoupdaterestore-tr">
	  		<th class="autoupdaterestore-th">Back Data Time</th>
	  		<th class="autoupdaterestore-th">File Name</th>
	  	</tr>
	  	</thead>
	  	<tbody>
	  <?php if($backup_results){
	  	foreach($backup_results as $row){ ?>
	  		<tr class="autoupdaterestore-tr">
	  			<td class="autoupdaterestore-td"><?php echo $row->datetime; ?></td>
	  			<td class="autoupdaterestore-td"><?php echo $row->file_name; ?></td>
	  		</tr>
	  	<?php }
	  } ?>
	  	</tbody>
	  </table>
	</div>
	<?php
}


