<?php
	/*
	Plugin Name: Privacy and Security from CellarWeb.com
	Description: Allows easy privacy and security settings, plus information on hidden plugins, and can block AI ChatBots from scanning your site.
	Version: 4.17
	Tested up to: 6.6
	Requires at least: 4.9.6
	Requires PHP: 7.2
	Contributors: rhellewellgmailcom
	Author: Rick Hellewell / CellarWeb.com
	Author URI: https://www.cellarweb.com
	Plugin URI: https://www.cellarweb.com/wordpress-plugins/
	License: GPLv2
	 */

	/*

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
	 */
	define("CWPS_VERSION", "4.15 (30 Aug 2024)"); // show version on settings screen

	// this file used in several places, so we'll define here and global in other spots
	define("ICONFILE", get_stylesheet_directory_uri() . "/images/favicon.ico");

	// max number of failed login attempts for the limit login attempt options
	define("CWPS_LOGIN_ATTEMPT_LIMIT", 4);
	define("CWPS_LOCKOUT_SECONDS", 300); // failed login lockout time in seconds

	// put options in a constant to ensure not multiple calls to the options table
	$options = get_option('CWPS_settings');
	define("CWPS_SETTINGS", $options);

	// ----------------------------------------------------------------
	// version checking: checking, notices, register/deregister, etc       BEGIN
	// ----------------------------------------------------------------

	// version checking
	$min_wp  = '4.9.6';
	$min_php = '7.3';
	if (!CWPS_is_requirements_met($min_wp, $min_php)) {
		add_action('admin_init', 'CWPS_disable_plugin');
		add_action('admin_notices', 'CWPS_show_notice_disabled_plugin', 10, 10);
		add_action('network_admin_init', 'CWPS_disable_plugin');
		add_action('network_admin_notices', 'CWPS_show_notice_disabled_plugin', 10, 10);
		CWPS_deregister();
		return;
		die("Plugin disabled due to PHP or WP version incompatibility.");
	}

	// --------------------------------------------------------------
	// register/deregister/uninstall hooks
	register_activation_hook(__FILE__, 'CWPS_register');
	register_deactivation_hook(__FILE__, 'CWPS_deregister');
	register_uninstall_hook(__FILE__, 'CWPS_uninstall');

	// for the AI chatbot scanner blocker (since version 4.00)
	require_once 'inc/cwps_chat_corefunctions.php';

	// register/deregister/uninstall options (even though there aren't options)
	function CWPS_register() {
		return;
	}

	function CWPS_deregister() {
		return;
	}

	function CWPS_uninstall() {
		return;
	}

	// --------------------------------------------------------------
	// check if at least WP 4.6 and PHP version at least 5.3
	// based on https://www.sitepoint.com/preventing-wordpress-plugin-incompatibilities/
	function CWPS_is_requirements_met($min_wp = '4.6', $min_php = '7.3') {
		// Check for WordPress version
		if (version_compare(get_bloginfo('version'), $min_wp, '<')) {
			return false;
		}
		// Check the PHP version
		if (version_compare(PHP_VERSION, $min_php, '<')) {
			return false;
		}
		return true;
	}

	// --------------------------------------------------------------
	// disable plugin if WP/PHP versions are not enough
	function CWPS_disable_plugin() {
		if (is_plugin_active(plugin_basename(__FILE__))) {
			deactivate_plugins(plugin_basename(__FILE__));
			// Hide the default "Plugin activated" notice
			if (isset($_GET['activate'])) {
				unset($_GET['activate']);
			}
		}
	}

	// --------------------------------------------------------------
	// show notice that plugin was deactivated because WP/PHP versions not enough
	function CWPS_show_notice_disabled_plugin() {
		echo '<div class="notice notice-error is-dismissible"><h3><strong>Privacy and Security from CellarWeb.com </strong></h3><p> cannot be activated - requires at least WordPress 4.6 and PHP 5.4.&nbsp;&nbsp;&nbsp;Plugin automatically deactivated.</p></div>';
		return;
	}

	// --------------------------------------------------------------
	// admin notice if something failed
	function CWPS_admin_notice_generic_error($theerrors = array('Something did not work correctly!')) {
		foreach ($the_errors as $the_error) {
			echo "<div class='notice notice-error is-dismissible'><h3><strong>Privacy and Security from CellarWeb.com  </strong></h3><p> - Error: $the_error .</p></div>";
		}
		return;
	}

	// ----------------------------------------------------------------
	// version checking: checking, notices, register/deregsiter, etc       END
	// ----------------------------------------------------------------

	// ----------------------------------------------------------------
	//  set up all the plugin stuff           BEGIN
	// ----------------------------------------------------------------

	add_action('admin_menu', 'CWPS_add_admin_menu'); // adds to the admin menu
	add_action('admin_init', 'CWPS_settings_init'); // does register_setting, add_settings_section add_settings_field, etc

	function CWPS_add_admin_menu() {

		add_options_page('Privacy and Security  by CellarWeb', 'Privacy and Security  by CellarWeb', 'manage_options', 'cellarweb_privacy_and_security', 'CWPS_options_page');
	}

	function CWPS_remove_footer_admin() {
		echo '';
		return;
	}

	// ============================================================================
	// Add settings link on plugin page
	// ----------------------------------------------------------------------------
	function CWPS_settings_link($links) {
		$settings_link = '<a href="options-general.php?page=cellarweb_privacy_and_security" title="Privacy and Security from CellarWeb.com Settings Page">Settings Page</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	// ============================================================================
	// link to the settings page
	// ----------------------------------------------------------------------------
	$plugin = plugin_basename(__FILE__);
	add_filter("plugin_action_links_$plugin", 'CWPS_settings_link');

	function CWPS_settings_init() {
		// Force remove of the thank you on plugin screen
		add_filter('admin_footer_text', 'CWPS_remove_footer_admin');

		// get some CSS loaded for the settings page
		wp_register_style('CWPS_namespace', plugins_url('/css/settings.css', __FILE__), array(), CWPS_VERSION);
		wp_enqueue_style('CWPS_namespace'); // gets the above css file in the proper spot

		register_setting('CWPS_option_group', // option group name
			'CWPS_settings', // option name (used to store into wp-options
			array(
				'sanitize_callback' => 'CWPS_sanitize_data' // sanitize the data function'
			)
		);

		add_settings_section(
			'CWPS_CWPS_option_group_section', // id slug of the section
			__('Privacy and Security from CellarWeb.com  to enable', // string for the title of the section
				'CWPS_namespace'),
			'CWPS_settings_section_callback', // function for text below the settings title
			'CWPS_option_group' // slug name of the settings page
		);

		// ----------------------------------------------------------------
		//  all the settings fields - BEGIN

		/* syntax for add_settings_field
		- $id = string to put in the ID of the setting tag
		- $title = the 'title' of the field (will be put to the left of the  input)
		- $callback = function to render the field
		- $page - page name of the field; match the 4th parameter of add_settings_section
		- $args = array for additional settings
		'label_for' => attributes to put in the label tag
		'class' => class name to put in the class attribute the TR used to display the inut field

		Version 3.00
		Note: the 'callback' elements are all set to CWPS_render_happy(), as we don't use the normal rendering process to display all settings - so we don't have to worry about tables cluttering up the settings display area.
		 */
		// change howdy CWPS_change_howdy()
		add_settings_field(
			'CWPS_change_howdy',
			__('', 'CWPS_namespace'),
			'CWPS_render_happy', // was    CWPS_change_howdy_render
			'CWPS_option_group',
			'CWPS_CWPS_option_group_section'
		);

		// add referring page to contact 7 forms - CWPS_cf7_add_referer()
		add_settings_field(
			'CWPS_cf7_add_referer',
			__('', 'CWPS_namespace'),
			'CWPS_render_happy', // was CWPS_cf7_add_referer_render
			'CWPS_option_group',
			'CWPS_CWPS_option_group_section'
		);

		// add referring page to contact 7 forms - CWPS_add_copyright_footer()
		add_settings_field(
			'CWPS_add_copyright_footer',
			__('', 'CWPS_namespace'),
			'CWPS_render_happy', // was CWPS_add_copyright_footer_render
			'CWPS_option_group',
			'CWPS_CWPS_option_group_section'
		);

		// disable xmlrpc - CWPS_disable_xmlrpc_render()
		add_settings_field(
			'CWPS_disable_xmlrpc',
			__('', 'CWPS_namespace'),
			'CWPS_render_happy', // was CWPS_disable_xmlrpc_render
			'CWPS_option_group',
			'CWPS_CWPS_option_group_section'
		);

		// remove WP Logo()
		add_settings_field(
			'CWPS_remove_wp_logo',
			__('', 'CWPS_namespace'),
			'CWPS_render_happy', // was CWPS_remove_wp_logo_render
			'CWPS_option_group',
			'CWPS_CWPS_option_group_section'
		);

		// shortcode for current year()
		add_settings_field(
			'CWPS_current_year',
			__('', 'CWPS_namespace'),
			'CWPS_render_happy', // was CWPS_current_year_render
			'CWPS_option_group',
			'CWPS_CWPS_option_group_section'
		);

		// allow shortcodes in text widgets()
		add_settings_field(
			'CWPS_shortcodes_in_widgets',
			__('', 'CWPS_namespace'),
			'CWPS_render_happy', // was CWPS_shortcodes_in_widgets_render
			'CWPS_option_group',
			'CWPS_CWPS_option_group_section'
		);

		// add favicon from images folder - CWPS_blog_favicon
		add_settings_field(
			'CWPS_blog_favicon',
			__('', 'CWPS_namespace'),
			'CWPS_render_happy', // was CWPS_blog_favicon_render
			'CWPS_option_group',
			'CWPS_CWPS_option_group_section'
		);

		// 13 = limit login attempts = CWPS_limit_login_attempts

		add_settings_field(
			'CWPS_limit_login_attempts', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_limit_login_attempts_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 14 = remove WP version from page output = CWPS_remove_wp_version

		add_settings_field(
			'CWPS_remove_wp_version', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_remove_wp_version_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 15 = disable editor in admin pages = CWPS_disable_editor

		add_settings_field(
			'CWPS_disable_editor', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_disable_editor_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 16 = force disable error reporting = CWPS_disable_error_reporting

		add_settings_field(
			'CWPS_disable_error_reporting', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_disable_error_reporting_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 17 = check if user called 'admin' exists = CWPS_check_user_name_admin

		add_settings_field(
			'CWPS_check_user_name_admin', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_check_user_name_admin_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 19 = changed failed login message to more generic msg = CWPS_generic_failed_login_message

		add_settings_field(
			'CWPS_generic_failed_login_message', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_generic_failed_login_message_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 20 = Use custom login page = CWPS_custom_login_page

		add_settings_field(
			'CWPS_custom_login_page', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_custom_login_page_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 23 - redirect 'author=x' queries by hackers = CWPS_redirect_if_author_query
		add_settings_field(
			'CWPS_redirect_if_author_query', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_redirect_if_author_query_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		//     template for new fields
		// 24 = disable remember me on login = CWPS_disable_remember_me

		add_settings_field(
			'CWPS_disable_remember_me', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_disable_remember_me_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 25 = load home page after login/logout = CWPS_home_after_loginout

		add_settings_field(
			'CWPS_home_after_loginout', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_home_after_loginout_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 26 = put login/logout on menu bar = CWPS_loginout_menu_bar

		add_settings_field(
			'CWPS_loginout_menu_bar', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_loginout_menu_bar_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 27 = stop comment posting direct access = CWPS_stop_comment_direct_access
		add_settings_field(
			'CWPS_stop_comment_direct_access', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_happy', // was CWPS_stop_comment_direct_access_render
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		// 27 = stop comment posting direct access = CWPS_stop_comment_direct_access
		add_settings_field(
			'CWPS_chatbot_enable', // field name
			__('', 'CWPS_namespace'), // message before field (not used)
			'CWPS_render_chatbot', //
			'CWPS_option_group', // plugin page name
			'CWPS_CWPS_option_group_section' // plugin section name
		);

		/*      template for new fields
		// xx = desc = fieldname

		add_settings_field(
		'CWPS_',                   // field name
		__( '', 'CWPS_namespace' ),    // message before field (not used)
		'CWPS_render_happy',      // render function
		'CWPS_option_group',                   // plugin page name
		'CWPS_CWPS_option_group_section'      // plugin section name
		);

		 */

		// ----------------------------------------------------------------
		//  all the settings fields setup       END
		// ----------------------------------------------------------------

		// ----------------------------------------------------------------
		// add actions that will display admin-type messages
	}

	// ----------------------------------------------------------------
	// end of the admin area / settings setup
	// ----------------------------------------------------------------

	// ----------------------------------------------------------------
	// render the fields on the page via do_settings          BEGIN
	/* NOTE (since version 3.00)
	- all rendering of settings area is done via one call to CWPS_render_fields()
	- the 'render' element in the add_settings for each field is set to CWPS_render_happy(), which doesn't do anything - and is not used.
	 */

	// ----------------------------------------------------------------
	// end of rendering fields area via do_settings         END
	// ----------------------------------------------------------------

	// ----------------------------------------------------------------
	// render all fields bypassing the do_settings(0 function, which puts everything in a table - BEGIN
	// ----------------------------------------------------------------
	function CWPS_render_fields() {

	?>
<div class="GWPF_settings_fields">

<h3>General Settings</h3>
<p><b>Your site's Word Press version is                                                                                                                    <?php echo get_bloginfo('version'); ?> </b> <i>(it's important to keep current!)</i></p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_change_howdy" name="CWPS_change_howdy" value="1"' . checked(CWPS_SETTINGS['CWPS_change_howdy'], 1, false) . '/>'; ?> &nbsp;&nbsp;Change 'Howdy' to 'Welcome' on the Admin bar. Because we think 'Howdy' is for old Western movies. Unless you have an Old Western movie site.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_cf7_add_referer" name="CWPS_cf7_add_referer" value="1"' . checked(CWPS_SETTINGS['CWPS_cf7_add_referer'], 1, false) . '/>'; ?> &nbsp;&nbsp;Add referer to "Contact Form 7" (CF7) form. This allows you to see what page the contact form came from. You must put <i>['hidden referer-page default:get']</i> on the form, and <i>['referer-page']</i> on the mail page in the CF7 contact form. </p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_add_copyright_footer" name="CWPS_add_copyright_footer" value="1"' . checked(1, CWPS_SETTINGS['CWPS_add_copyright_footer'], false) . '/>'; ?> &nbsp;&nbsp;Add the CellarWeb copyright and links to any existing footer as used by your theme. Does not replace existing footer.  Text is displayed in black with white background; links are blue/underline. This ensures visibility is not 'washed out' by theme settings.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_remove_wp_logo" name="CWPS_remove_wp_logo" value="1"' . checked(1, CWPS_SETTINGS['CWPS_remove_wp_logo'], false) . '/>'; ?> &nbsp;&nbsp;Remove WP logo from left side of the admin bar.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_current_year" name="CWPS_current_year" value="1"' . checked(1, CWPS_SETTINGS['CWPS_current_year'], false) . '/>'; ?> &nbsp;&nbsp;Set up <i>[current_year]</i> shortcode to display the current year. Useful in all sorts of places.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_shortcodes_in_widgets" name="CWPS_shortcodes_in_widgets" value="1"' . checked(1, CWPS_SETTINGS['CWPS_shortcodes_in_widgets'], false) . '/>'; ?> &nbsp;&nbsp;Allow shortcodes in widgets.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_blog_favicon" name="CWPS_blog_favicon" value="1"' . checked(1, CWPS_SETTINGS['CWPS_blog_favicon'], false) . '/>'; ?> &nbsp;&nbsp;Add favicon to generated page head section. Assumes favicon.ico file is in the theme's folder as '/images/favicon.ico'. If the file is not there, a warning message will be displayed when settings are saved.</p>


<h3>PHP Settings</h3>
<p><b>Your site's PHP version is                                                                 <?php echo phpversion(); ?></b> <i>(Consider updating to the latest PHP version [after full site testing], if needed.)</i> Information about latest versions on the PHP site <a href="https://www.php.net/" target="_blank" title="PHP site">here</a> . Contact your site hosting company to upgrade to the latest PHP version; make sure you fully test new versions.</p>
<p>Changes to various PHP settings, like file upload size, are best done via the wp-config.php file, see <a href="https://developer.wordpress.org/apis/wp-config-php/" target="_blank" title="WordPress Config File Information">here</a>. Note that changing these values is an advanced topic, so proceed carefully.</p>


<h3>Security Settings - General</h3>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_disable_xmlrpc" name="CWPS_disable_xmlrpc" value="1"' . checked(1, CWPS_SETTINGS['CWPS_disable_xmlrpc'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Disable XMLRPC, since it can be an access point for hackers. Enabling this option may affect remote admin access or remote posting.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_remove_wp_version" name="CWPS_remove_wp_version" value="1"' . checked(1, CWPS_SETTINGS['CWPS_remove_wp_version'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Remove WP version info from generated page meta values. Some hackers use this information in their attacks.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_disable_editor" name="CWPS_disable_editor" value="1"' . checked(1, CWPS_SETTINGS['CWPS_disable_editor'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Disable code editor in all theme/plugins by removing it from the Appearance menu. Editing site code can adversely affect your site.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_disable_error_reporting" name="CWPS_disable_error_reporting" value="1"' . checked(1, CWPS_SETTINGS['CWPS_disable_error_reporting'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Disables all error reporting by creating a <em>CWPS_no_errors.ini</em> file in this plugin's folder. Should override any error reporting settings in themes/plugins. </p>


    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_redirect_if_author_query" name="CWPS_redirect_if_author_query" value="1"' . checked(1, CWPS_SETTINGS['CWPS_redirect_if_author_query'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Disable ability to query by author ID. This prevents hackers from finding user names with a query similar to "?author=1". That will reveal the author with ID of 1, which is usually the admin-level user. Enabling this will redirect hacker to the site's home page. Blocking this access can help protect your site from hackers.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_check_user_name_admin" name="CWPS_check_user_name_admin" value="1"' . checked(1, CWPS_SETTINGS['CWPS_check_user_name_admin'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Display all users with Administrator or SuperAdministrator roles. Also checks if there is a user called 'admin' with administrator privileges. If found, a warning is displayed at the top of this page; the setting will not remove/demote that user. That 'admin' user name is often used by hackers when they attempt to log into your site.<?php echo CWPS_get_admin_list(); ?>
</p>
<h3>Security Settings - Login Related</h3>
 <p>             <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_limit_login_attempts" name="CWPS_limit_login_attempts" value="1"' . checked(1, CWPS_SETTINGS['CWPS_limit_login_attempts'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Limit consecutive login attempts to<?php echo CWPS_LOGIN_ATTEMPT_LIMIT; ?>. After that, logins will be locked out for<?php echo CWPS_LOCKOUT_SECONDS; ?> seconds. This is a protection against login attacks.</p>


    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_generic_failed_login_message" name="CWPS_generic_failed_login_message" value="1"' . checked(1, CWPS_SETTINGS['CWPS_generic_failed_login_message'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Change failed login message to a more generic error: "Sorry, that is incorrect" - so that there is no indication of whether the name or password was incorrect. This is a protection against login attacks.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_login_site_logo" name="CWPS_login_site_logo" value="1"' . checked(1, CWPS_SETTINGS['CWPS_login_site_logo'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Replace logo on login page with the site logo defined in your theme. If you haven't defined the site logo, or it is not supported, then the default WP logo is used. This helps to personalize your site.</p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_disable_remember_me" name="CWPS_disable_remember_me" value="1"' . checked(1, CWPS_SETTINGS['CWPS_disable_remember_me'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Disable 'Remember Me' on login form. This helps with login security, especially for users that log in on public computer. </p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_home_after_loginout" name="CWPS_home_after_loginout" value="1"' . checked(1, CWPS_SETTINGS['CWPS_home_after_loginout'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Forces a redirect to home page after login/logout, instead of any other page (including the login screen).  </p>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_checkbox" id="CWPS_loginout_menu_bar" name="CWPS_loginout_menu_bar" value="1"' . checked(1, CWPS_SETTINGS['CWPS_loginout_menu_bar'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Put login/lougout links, along with "Hello (user name)", on menus on main site. You must have defined a menu in your theme's Appearances, Menu.  </p>

<h3><br><br>Plugin Information</h3>

<h4><img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-information-48.png" width="16" height="16" alt="Information"  title="Information"> Hidden Plugins </h4>
<p> Shows a list of hidden plugins (if any). Hidden plugins can be a risk on your site. They can be used by attackers to gain access to your site and data. They can also be used to install malware on your site, or to redirect your visitors to malicious websites.</p>
<?php echo "<div style='margin-left:3%'>" . CWPS_show_hidden_plugins() . "</div>";?>


<h4><img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-information-48.png" width="16" height="16" alt="Information"  title="Information"> Plugins That Need Updates Installed </h4>
<?php CWPS_list_plugins_with_pending_updates();?>

<h3><br><br>ChatBot AI Scanner Blocking</h3>

    <p>                      <?php echo '<input type="checkbox" class = "CWPS_chatbot" id="CWPS_chatbot_enable" name="CWPS_chatbot_enable" value="1"' . checked(1, CWPS_SETTINGS['CWPS_chatbot_enable'], false) . '/>'; ?> &nbsp;&nbsp;<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt=""> Enables blocking AI ChatBot Scanners from scanning your site (and using your site content in AI programs) by adding blocking directives to the Word Press the virtual robots.txt file.  Does not affect scanning content by search engines, and does not affect any SEO on your site. If enabled, the virtual robots.txt file directives will be shown below. Note that if you have an actual robots.txt file in your site root, the virtual robots.txt directives will not be used.</p>

<?php // enable chabot blocking (since version 4.00)
		if (!empty(CWPS_SETTINGS['CWPS_chatbot_enable'])) {
			// enable chatbot blocking
			add_filter('robots_txt', 'CWPS_robots_option_content', 99, 2); // filter to add robots
			$content = CWPS_robots_build_content(); // displays virtual robots.txt file on the settings screen
			CWPS_robots_settings_info($content);
		}
	?>

<h3><img src="<?php echo plugin_dir_url(__FILE__); ?>css/warning-yellow.png" width="20" height="20" alt=""> &nbsp;&nbsp;Security Settings - htaccess File Information</h3>
<p><b>Below is your current htaccess file.</b> Review to ensure all is well. Information about the htaccess file can often be found in your hosting site's help documents, as well as lots of other places. <b>There are commands in your htaccess file that are specific to Word Press; don't change them</b> (they are noted with a "BEGIN/END Word Press" block). Other commands may have been placed by your hosting site. Be careful about changes; they could break your site. Always have a backup plan to restore a working htaccess file. </p>
<p>Some suggested htaccess commands are shown below. Use guidance from your hosting place to determine and implement possible changes. It's good practice to check the htaccess file for any improper or unwanted commands. Hackers often try to change your htaccess file for their nefarious purposes.</p>
<h2 align='center'>Current htaccess File</h2>
<?php CWPS_show_htaccess();?>
<h2 align='center'>htaccess File Suggestions</h2>
<p><b>Here are some htaccess commands that you may need to add to your htaccess file. </b><br>You will have to add them manually. Your hosting place will probably have help documents to assist you with that. Make sure you have a backup copy of your current htaccess file before you make changes. <i>Incorrect htaccess commands can break your site - so proceed cautiously.</i></p>

    <p><b>Force SSL Requests </b><br>This htaccess command block, if needed, will ensure that all requests are re-written to HTTPS (SSL) requests. This will stop any browser error messages about insecure ties. This should work even if you have a plugin that adds SSL, although it makes that plugin unnecessary. Work with your hosting place to ensure your site has a valid SSL certificate.</p>
        <div class="CWPS_box_green">
            <?php
            	echo nl2br("## Force request to use SSL" . PHP_EOL . "RewriteEngine on" . PHP_EOL . "RewriteCond %{HTTPS} !on " . PHP_EOL . " RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301] ");
            	?>
        </div>
    <p><b>  Disable directory listing via <i>htaccess</i> with this directive</b></p>
 <div class='CWPS_box_green'>## Disable directly listing<br>Options -Indexes</div>

    <p><b>Protect the wp-config.php file with this htaccess directive</b> </p>
                <div class="CWPS_box_green">## protect wp-config file from improper access<br>
&lt;files wp-config.php&gt;<br />
        order allow,deny<br />
        deny from all<br />
        &lt;/files&gt;</div>

    <p><b>Block direct access of <i>wp-comments-post.php</i>, which is often used by bots for comment spam</b><br /> The following is placed in <i>htaccess</i>; the bot is redirected back to itself via [REMOTE_ADDR]. (Also see our  our <a href="https://wordpress.org/plugins/block-comment-spam-bots/" target="_blank">Block Comment Spam Bots</a> plugin which adds additional and more effective protection against comment spam, and our <a href="https://www.formspammertrap.com" target="_blank">FormSpammerTrap</a> processes which protects against contact form spam.) </p>
        <div class="CWPS_box_green">
            <?php
            	echo nl2br("## Block direct access to posting comments" . PHP_EOL . "RewriteEngine On" . PHP_EOL . "    RewriteCond %{REQUEST_METHOD} POST" . PHP_EOL . "    RewriteCond %{REQUEST_URI} .wp-comments-post\.php* " . PHP_EOL . "    RewriteCond %{HTTP_REFERER} !." . $_SERVER['HTTP_HOST'] . ".* [OR]" . PHP_EOL . "    RewriteCond %{HTTP_USER_AGENT} ^$" . PHP_EOL . "    RewriteRule (.*) http://%{REMOTE_ADDR}/$ [R=301,L] ");
            	?>
        </div>
</div>

<?php
	return;
	}

	// ----------------------------------------------------------------
	// render all fields bypassing the do_settings(0 function, which puts everything in a table - END
	// ----------------------------------------------------------------
	// this function is to keep add-Settings 'render' element happy. Doesn't do anything. All rendering of settings text is done via the CWPS_render_fields() function
	function CWPS_render_happy() {
		return;
	}

	// ----------------------------------------------------------------
	// callback to sanitize the form posting
	//      $input should contain all of the form post data
	//         @param array $input Contains all settings fields as array keys
	//      $new_input returned to let the API store the data in wp-options table

	//  errata: for some reason, $input is empty, so we are using $_POST['

	function CWPS_sanitize_data($input) {
		// need at least one array value,  just in case nothing checked, or errors caused on render functions trying to read array items from a null string stored in the wp-option
		$new_input = array('CellarWeb' => 'Private Functions');
		if (isset($_POST['CWPS_change_howdy'])) {
			$new_input['CWPS_change_howdy'] = 1;} else { $new_input['CWPS_change_howdy'] = 0;}

		if (isset($_POST['CWPS_cf7_add_referer'])) {
			$new_input['CWPS_cf7_add_referer'] = "1";} else { $new_input['CWPS_cf7_add_referer'] = "0";}

		if (isset($_POST['CWPS_add_copyright_footer'])) {
			$new_input['CWPS_add_copyright_footer'] = "1";} else { $new_input['CWPS_add_copyright_footer'] = "0";}

		if (isset($_POST['CWPS_disable_xmlrpc'])) {
			$new_input['CWPS_disable_xmlrpc'] = "1";} else { $new_input['CWPS_disable_xmlrpc'] = "0";}

		if (isset($_POST['CWPS_remove_wp_logo'])) {
			$new_input['CWPS_remove_wp_logo'] = 1;} else { $new_input['CWPS_remove_wp_logo'] = 0;}

		if (isset($_POST['CWPS_current_year'])) {
			$new_input['CWPS_current_year'] = "1";} else { $new_input['CWPS_current_year'] = "0";}

		if (isset($_POST['CWPS_blog_favicon'])) {
			$new_input['CWPS_blog_favicon'] = "1";} else { $new_input['CWPS_blog_favicon'] = "0";}

		if (isset($_POST['CWPS_shortcodes_in_widgets'])) {
			$new_input['CWPS_shortcodes_in_widgets'] = "1";} else { $new_input['CWPS_shortcodes_in_widgets'] = "0";}

		// template
		/*
		if (isset($_POST['fieldname'])) {
		$new_input['fieldname'] = "1";} else { $new_input['fieldname'] = "0";}

		 */

		if (isset($_POST['CWPS_remove_wp_version'])) {
			$new_input['CWPS_remove_wp_version'] = "1";} else { $new_input['CWPS_remove_wp_version'] = "0";}

		if (isset($_POST['CWPS_disable_editor'])) {
			$new_input['CWPS_disable_editor'] = "1";} else { $new_input['CWPS_disable_editor'] = "0";}

		if (isset($_POST['CWPS_disable_error_reporting'])) {
			$new_input['CWPS_disable_error_reporting'] = "1";} else { $new_input['CWPS_disable_error_reporting'] = "0";}

		if (isset($_POST['CWPS_check_user_name_admin'])) {
			$new_input['CWPS_check_user_name_admin'] = "1";} else { $new_input['CWPS_check_user_name_admin'] = "0";}

		if (isset($_POST['CWPS_disable_directory_listing'])) {
			$new_input['CWPS_disable_directory_listing'] = "1";} else { $new_input['CWPS_disable_directory_listing'] = "0";}

		if (isset($_POST['CWPS_generic_failed_login_message'])) {
			$new_input['CWPS_generic_failed_login_message'] = "1";} else { $new_input['CWPS_generic_failed_login_message'] = "0";}

		if (isset($_POST['CWPS_login_site_logo'])) {
			$new_input['CWPS_login_site_logo'] = "1";} else { $new_input['CWPS_login_site_logo'] = "0";}

		if (isset($_POST['CWPS_protect_config_file'])) {
			$new_input['CWPS_protect_config_file'] = "1";} else { $new_input['CWPS_protect_config_file'] = "0";}

		if (isset($_POST['CWPS_redirect_if_author_query'])) {
			$new_input['CWPS_redirect_if_author_query'] = "1";} else { $new_input['CWPS_redirect_if_author_query'] = "0";}

		if (isset($_POST['CWPS_disable_remember_me'])) {
			$new_input['CWPS_disable_remember_me'] = "1";} else { $new_input['CWPS_disable_remember_me'] = "0";}

		if (isset($_POST['CWPS_home_after_loginout'])) {
			$new_input['CWPS_home_after_loginout'] = "1";} else { $new_input['CWPS_home_after_loginout'] = "0";}

		if (isset($_POST['CWPS_loginout_menu_bar'])) {
			$new_input['CWPS_loginout_menu_bar'] = "1";} else { $new_input['CWPS_loginout_menu_bar'] = "0";}

		if (isset($_POST['CWPS_stop_comment_direct_access'])) {
			$new_input['CWPS_stop_comment_direct_access'] = "1";} else { $new_input['CWPS_stop_comment_direct_access'] = "0";}

		if (isset($_POST['CWPS_limit_login_attempts'])) {
			$new_input['CWPS_limit_login_attempts'] = "1";} else { $new_input['CWPS_limit_login_attempts'] = "0";}

		if (isset($_POST['CWPS_chatbot_enable'])) {
			$new_input['CWPS_chatbot_enable'] = "1";} else { $new_input['CWPS_chatbot_enable'] = "0";}

		return $new_input;
	}

	// ----------------------------------------------------------------
	//  end of validation area
	// ----------------------------------------------------------------

	// ----------------------------------------------------------------
	// display into text at the top of the section
	// ----------------------------------------------------------------
	function CWPS_settings_section_callback() {
		echo "<p>Use the checkboxes to enable/disable the indicated function/feature.</p>";

		return;
	}

	// ----------------------------------------------------------------
	// Settings page header box, any intro text, etc
	//      also creates the form for all of the settings fields
	// ----------------------------------------------------------------

	function CWPS_options_page() {
	?>
<div align='center' class = 'CWPS_header'>
     <img src="<?php echo plugin_dir_url(__FILE__); ?>assets/banner-1000x200.jpg" width="95%"  alt="" class='CWPS_shadow'>
<p align='center'>Version
<?php echo CWPS_VERSION; ?></p>
</div>
<?php CWPS_intro_text();?>
<div>
<div class='CWPS_settings'>
    <div class='CWPS_options'>
        <form action='options.php' method='post'>
        <?php
        	submit_button(); // creats the submit button at the top of the page
        		settings_fields('CWPS_option_group'); // initializes all of the settings fields
        		//do_settings_sections( 'CWPS_option_group' ); // does the settings section; into a table
        		CWPS_render_fields(); // render fields without do_settings so no table codes
        		submit_button(); // creats the submit button at the bottom of the page
        	?>
<?php
;?>
        </form>
    </div>
</div>
</div>
    <div class='CWPS_sidebar'>
        <?php CWPS_sidebar();?>
    </div>
<!--</div>  --><!-- not sure why this one is needed ... -->
<div class='CWPS_footer'>
    <?php CWPS_footer();?>
</div>
<?php
	}

	// ----------------------------------------------------------------

	// CSS for settings page
	// Add stylesheet
	function CWPS_load_css() {
		wp_register_style('CWPS_regular_css', plugins_url('/css/settings.css', __FILE__));
		wp_enqueue_style('CWPS_regular_css'); // gets the above css file in the proper spot
	}

	add_action('wp_enqueue_scripts', 'CWPS_load_css');

	function CWPS_load_login_css() {
		$login_css_file = plugins_url('/css/custom_login_page.css', __FILE__);
		wp_register_style('CWPS_login_css', plugins_url('/css/custom_login_page.css', __FILE__));
		wp_enqueue_style('CWPS_login_css'); // gets the above css file in the proper spot
		return;
	}

	// ----------------------------------------------------------------
	// additional place for intro text under the header on the settings page

	function CWPS_intro_text() {
	?>
<div class = 'CWPS_introtext'>
    <h2 align='center'>Privacy and Security from CellarWeb.com</h2>
    <p>These Privacy and Security settings are for any Word Press web site. All settings are in one place for convenience. Each option has a description of it's function. Enable options with it's checkbox. We use this plugin on all of the Word Press web sites we manage. The settings are based on best-use configurations we use on all sites.</p>
    <p>Security-related features are shown with the lock icon (<img src="<?php echo plugin_dir_url(__FILE__); ?>css/icons8-lock-30.png" width="16" height="16" alt="">). Carefully consider using these recommended security and privacy options.</p>
    <p> This icon <img src="<?php echo plugin_dir_url(__FILE__); ?>css/warning-yellow.png" width="16" height="16" alt=""> indicates settings that could affect your site if configured incorrectly. It might also alert you to unwanted changes that could affect your site - or be an indication of malware on your site. </p>
    <p>Any errors found by enabling a setting will only be displayed on this settings page. We recommend checking this settings page when you make other changes to the site, or check this page on a regular basis. Checking the htaccess file, for example, would alert you to possible site hacks.</p>
</div>
<?php
	return;
	}

	// ----------------------------------------------------------------
	//  this does the work of enabling the selected options  BEGIN
	// ----------------------------------------------------------------

	// --------------------------------------------------------------
	// enable login css only if that option enabled
	if (isset(CWPS_SETTINGS['CWPS_login_site_logo'])) {
		add_action('login_enqueue_scripts', 'CWPS_custom_login_logo');
	}

	// --------------------------------------------------------------
	// disable remember me in login form
	// must be disabled by JS, since the login_form is not used by core
	//  see https://wordpress.stackexchange.com/a/404232/29416
	// --------------------------------------------------------------
	if (!empty(CWPS_SETTINGS['CWPS_disable_remember_me'])) {
		add_action('login_footer', function () {
		?>
            <script>
                try {
                    document.querySelector( '.forgetmenot' ).remove();
                } catch ( err ) {}
            </script>
        <?php
        	});
        	}

        	// ----------------------------------------------------------------
        	// this disables xmlrpc, which can be used to attack sites and insert code
        	// test at https://xmlrpc.eritreo.it/
        	//       site should fail with a '405 XML-RPC services are disabled on this site.'
        	// echo "805<br>";

        	if (!empty(CWPS_SETTINGS['CWPS_disable_xmlrpc'])) {
                add_filter( 'xmlrpc_enabled', '__return_false' ,999);

        	}

        	// --------------------------------------------------------------
        	// check for the favico.ico file, if not found, show an admin notice
        	//      (must bein plugin settings area)
        	// --------------------------------------------------------------
        	if (!empty(CWPS_SETTINGS['CWPS_blog_favicon'])) {
        		//if ((!file_exists(ICONFILE)) and (empty(CWPS_SETTINGS['CWPS_blog_favicon']))) { // chcek for favicon filein the right sopt
        		add_action('admin_notices', 'CWPS_warning_notice_no_favicon', 10);
        	}

        	// ============================================================================
        	//  Use a custom login page
        	// --------------------------------------------------------------
        	if (!empty(CWPS_SETTINGS['CWPS_login_site_logo'])) {
        		// add_filter('login_headerurl', 'CWPS_custom_login_logo_url');  // obsolete
        		// use header logo as login logo  via CSS insertions
        		add_action('login_enqueue_scripts', 'CWPS_custom_login_logo');
        		// Disable administration email verification
        		add_filter('admin_email_check_interval', 'CWPS_return_false');
        	}
        	function CWPS_return_false() {return false;}

        	// --------------------------------------------------------------
        	//  Checks for user named admin, shows warning notice if found (no actions taken)
        	//      note: have to wait for init to happen, otherwise the function is not found
        	// --------------------------------------------------------------
        	if (!empty(CWPS_SETTINGS['CWPS_check_user_name_admin'])) {
        		add_action('init', 'CWPS_check_user_admin_exist');
        	}

        	// --------------------------------------------------------------
        	// add-actions if setting is enabled. Not-empty is used to block warning if var doesn't exist (especially in PHP version 8.x)
        	// --------------------------------------------------------------
        	if ((CWPS_SETTINGS['CWPS_change_howdy'])) {
        		add_action('admin_bar_menu', 'CWPS_change_howdy', 11);
        	}
        	if (!empty(CWPS_SETTINGS['CWPS_remove_wp_logo'])) {
        		add_action('wp_before_admin_bar_render', 'CWPS_remove_wp_logo');
        	}
        	if (!empty(CWPS_SETTINGS['CWPS_add_copyright_footer'])) {
        		add_action('wp_footer', 'CWPS_add_copyright_footer');
        	}
        	if (!empty(CWPS_SETTINGS['CWPS_current_year'])) {
        		add_shortcode('current_year', 'CWPS_current_year', 10, 2); // our caption shortcode process
        	}
        	if (!empty(CWPS_SETTINGS['CWPS_shortcodes_in_widgets'])) {
        		add_filter('widget_text', 'do_shortcode');
        	}
        	if (!empty(CWPS_SETTINGS['CWPS_blog_favicon'])) {
        		add_action('wp_head', 'CWPS_blog_favicon');
        	}

        	if (!empty(CWPS_SETTINGS['CWPS_disable_editor'])) {
        		if (!defined("DISALLOW_FILE_EDIT")) {
        			define('DISALLOW_FILE_EDIT', true);
        		}
        	}
        	// disable error reporting
        	if ((!empty(CWPS_SETTINGS['CWPS_disable_error_reporting'])) and (!empty($_GET['page']) == 'cellarweb_privacy_and_security')) {
        		add_action('added_option', 'CWPS_disable_error_reporting', 11);
        	} else {
        		add_action('added_option', 'CWPS_disable_error_cancel', 11);
        	}

        	// disable wp version in output page heading
        	if (!empty(CWPS_SETTINGS['CWPS_remove_wp_version'])) {
        		remove_action('wp_head', 'CWPS_remove_wp_version');
        		remove_action('wp_head', 'wp_generator');
        	}

        	//  Change failed login messages to something more generic
        	if (!empty(CWPS_SETTINGS['CWPS_generic_failed_login_message'])) {
        		add_filter('login_errors', function ($error) {
        			global $errors;
        			$err_codes = $errors->get_error_codes();

        			// Invalid username.
        			if (in_array('invalid_username', $err_codes)) {
        				$error = '<strong>ERROR</strong>: Sorry, that is incorrect.';
        			}

        			// Incorrect password.
        			if (in_array('incorrect_password', $err_codes)) {
        				$error = '<strong>ERROR</strong>: Sorry, that is incorrect.';
        			}

        			return $error;
        		});
        	}
        	// add login/logout to menu if enabled
        	if (!empty(CWPS_SETTINGS['CWPS_loginout_menu_bar'])) {
        		add_filter('wp_nav_menu_items', 'CWPS_login_menu_item', 10, 2);
        	}

        	// show home page after login / logout
        	if (!empty(CWPS_SETTINGS['CWPS_home_after_loginout'])) {
        		add_action('wp_logout', 'CWPS_after_logout'); // to home page after logout
        		add_filter('login_redirect', 'CWPS_login_to_home_page'); // home after login
        	}

        	// add robots directives
        	if (!empty(CWPS_SETTINGS['CWPS_chatbot_enable'])) {
        		// enable chatbot blocking
        		add_filter('robots_txt', 'CWPS_robots_build_content', 99, 2); // filter to add robots
        	}

        	// -------------------------------------------------------------
        	//  end of all features activation code
        	// --------------------------------------------------------------

        	// --------------------------------------------------------------
        	// end of add_actions setting
        	// --------------------------------------------------------------

        	// --------------------------------------------------------------
        	// check if favicon file exists, display warning if needed
        	// --------------------------------------------------------------
        	function CWPS_warning_notice_no_favicon() {
        	?>
    <div class="notice notice-warning is-dismissible"><strong>Privacy and Security from CellarWeb.com Options Alert!</strong>: The favico.ico file is not found in theme folder<?php echo get_stylesheet_directory_uri(); ?>/images/favicon.ico . Either disable that option, or put the file in that folder. </div>
    <?php
    	return;
    	}

    	// --------------------------------------------------------------
    	// check if user called 'admin'; display warning notice
    	// --------------------------------------------------------------
    	function CWPS_check_user_admin_exist() {
    		if ((get_user_by('login', 'admin')) and ((!empty($_GET['page']) == 'cellarweb_privacy_and_security'))) {
    			add_action('admin_notices', 'CWPS_user_admin_notice', 10);
    		}
    		return;
    	}

    	// --------------------------------------------------------------
    	// user admin notices
    	// --------------------------------------------------------------
    	function CWPS_user_admin_notice() {
    	?>
    <div class="notice notice-warning is-dismissible"><strong>Privacy and Security from CellarWeb.com Options Alert!</strong>: There is a user called 'admin' in the user's list. If that user has admin privileges, it might be possible for a hacker to access that account. We recommend not having a user called 'admin', or making sure that user has no privileges. </div>
<?php
	return;}

	// --------------------------------------------------------------
	// change the howdy
	// --------------------------------------------------------------

	function CWPS_change_howdy($wp_admin_bar) {
		$user_id      = get_current_user_id();
		$current_user = wp_get_current_user();
		$profile_url  = get_edit_profile_url($user_id);

		if (0 != $user_id) {
			$avatar = get_avatar($user_id, 28);
			$howdy  = sprintf(__('Welcome, %1$s'), $current_user->display_name);
			$class  = empty($avatar) ? '' : 'with-avatar';

			$wp_admin_bar->add_menu(array(
				'id' => 'my-account',
				'parent' => 'top-secondary',
				'title' => $howdy . $avatar,
				'href' => $profile_url,
				'meta' => array(
					'class' => $class,
				),
			));
		}
	}

	// --------------------------------------------------------------
	// remove WP logo from admin toolbar
	// from http://maorchasen.com/blog/2012/04/21/remove-wordpress-menu-from-the-admin-bar/
	// --------------------------------------------------------------
	function CWPS_remove_wp_logo() {
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu('wp-logo');
	}

	// --------------------------------------------------------------
	// add the referring page to the contact form 7 mail
	//      from https://www.tech90.com/2015/08/11/add-referer-url-to-contact-form-7-form-and-email/
	// --------------------------------------------------------------
	function CWPS_cf7_add_referer($form_tag) {
		if ($form_tag['name'] == 'referer-page') {
			$form_tag['values'][''] = htmlspecialchars($_SERVER['HTTP_REFERER']);
		}
		return $form_tag;
	}

	if ((!is_admin()) and (!empty(CWPS_SETTINGS['CWPS_remove_wp_logo']))) {
		add_filter('wpcf7_form_tag', 'CWPS_cf7_add_referer');
	}

	// --------------------------------------------------------------
	// display the bottom info part of the page
	// --------------------------------------------------------------
	function CWPS_add_copyright_footer() {
		// print copyright with current year, never needs updating
		$xstartyear    = "2013";
		$xname         = "Rick Hellewell";
		$xcompanylink1 = ' <a href="http://cellarweb.com" title="CellarWeb" style="text-decoration:underline !important;color:blue !important;" >CellarWeb.com</a>';
		// leave this empty if no company 2
		$xcompanylink2 = '';
		// output
		echo '<p id="site-info" align="center"   >Design and implementation Copyright &copy; ' . $xstartyear . '  - ' . gmdate("Y") . ' by ' . $xname . ' and ' . $xcompanylink1;
		if ($xcompanylink2) {
			echo ' and ';
			echo $xcompanylink2;
		}
		echo ' , All Rights Reserved.</p> ';
		return;
	}

	// --------------------------------------------------------------
	// set up a current_year shortcode to display the current year
	// --------------------------------------------------------------
	function CWPS_current_year() {
		return gmdate("Y");
	}

	// --------------------------------------------------------------
	//  Enable shortcodes in text widgets
	// --------------------------------------------------------------

	// --------------------------------------------------------------
	// add the favicon
	// --------------------------------------------------------------
	function CWPS_blog_favicon() {
	?>
<link rel="shortcut icon" href="<?php echo ICONFILE; ?>">
    <?php
    	}

    	// ============================================================================
    	//  Remove the WP version from the meta of generated pages
    	// --------------------------------------------------------------
    	function CWPS_remove_wp_version() {

    		return '';
    	}

    	// ============================================================================
    	//  Disable the editor on all admin screens for plugins/themes
    	// --------------------------------------------------------------

    	// ============================================================================
    	//  Disable error reporting with custom ini file (because all ini files are loaded by php)
    	// uses wp_filesystem (since
    	// --------------------------------------------------------------

    	function CWPS_disable_error_reporting() {
    		if (!defined("WP_DEBUG_DISPLAY")) {
    			define('WP_DEBUG_DISPLAY', false);
    		}
    		global $wp_filesystem;
    		$custom_ini_file = plugin_dir_path(__FILE__) . 'CWPS_no_errors.ini';
    		//$file_handle     = fopen($custom_ini_file, "w");
    		$file_handle = $wp_filesystem->open($custom_ini_file, "w");
    		$txt         = "# Privacy and Security from CellarWeb.com Options added \n display_errors(false);   \n log_errors(true);  \n  ";
    		if ($file_handle) {
    			// $success = fwrite($file_handle, $txt);
    			$success = $wp_filesystem->put_contents($file_handle, $txt);
    			//fclose($file_handle);
    			$wp_filesystem->close($file_handle);
    			if ($success) {add_action('admin_notices', 'CWPS_no_error_file_added', 10);} else {add_action('admin_notices', 'CWPS_no_error_file_failed', 10);}
    		} else {add_action('admin_notices', 'CWPS_no_error_file_failed', 10);}

    		return;
    	}

    	function CWPS_disable_error_cancel() {
    		$custom_ini_file = plugin_dir_path(__DIR__) . 'CWPS_private-functions/CWPS_no_errors.ini';
    		if (file_exists($custom_ini_file)) {
    			unlink($custom_ini_file);
    			add_action('admin_notices', 'CWPS_no_error_file_removed', 10);
    		}
    		return;
    	}

    	function CWPS_no_error_file_removed() {
    		echo '<div class="notice notice-success is-dismissible"><p><strong>Privacy and Security from CellarWeb.com Options</strong>: removed the <em>CWPS_no_errors.ini</em> file. Error reporting settings not changed.</p></div>';

    		return;
    	}

    	function CWPS_no_error_file_added() {
    		echo '<div class="notice notice-success is-dismissible"><p><strong>Privacy and Security from CellarWeb.com Options</strong>: Added the <em>CWPS_no_errors.ini</em> file. All error reporting to screen disabled. </p></div>';

    		return;
    	}

    	// --------------------------------------------------------------
    	// login screen stuff
    	// --------------------------------------------------------------
    	// some login change code based on https://codex.wordpress.org/Customizing_the_Login_Form

    	function CWPS_generic_failed_login_message() {
    		return '<p class="message">Sorry, that is incorrect.</p>';
    	}

    	// change the url of the logo on the login page
    	function CWPS_custom_login_logo_url($url) {
    		return home_url();
    	}

    	// --------------------------------------------------------------
    	// loads the login page custom css as a php file, so php things inside will work
    	// --------------------------------------------------------------
    	function CWPS_custom_login_page_css() {
    		// the css file is really a php file with a css header, to process php statements inside css
    		wp_register_style('CWPS_login', plugin_dir_url(__FILE__) . 'css/custom_login_page.php');
    		wp_enqueue_style('CWPS_login');
    		return;
    	}

    	// if custom logo, use the header logo
    	function CWPS_custom_login_logo() {
    		$custom_login_logo_url = (wp_get_attachment_url(get_theme_mod('custom_logo')));
    		// if no custom logo, return
    		if (!strlen($custom_login_logo_url)) {return;}
    	?>
    <style type="text/css">
        #login h1 a, .login h1 a, body.login div#login h1 a {
            background-image:url("<?php echo $custom_login_logo_url; ?>");
        height:65px;
        width:320px;
        background-size: 320px 65px;
        background-repeat: no-repeat;
            padding-bottom: 20px;
        }

    </style>
<?php
	return;}

	// --------------------------------------------------------------
	//  end of custom login page stuff
	// ============================================================================

	// ============================================================================
	// don't allow user enumeration with author parameter on any page
	// --------------------------------------------------------------

	function CWPS_redirect_if_author_query() {

		$is_author_set = get_query_var('author', '');
		if ($is_author_set != '' && !is_admin()) {
			wp_redirect(home_url(), 301); // send them somewhere else
			exit;
		}
	}

	add_action('template_redirect', 'CWPS_redirect_if_author_query');

	// ============================================================================
	//  settings page sidebar content
	// --------------------------------------------------------------
	function CWPS_sidebar() {
	?>
<h3 align="center">But wait, there's more!!</h3>
<p><b>Totally eliminate comment spam</b> with our <a href="https://wordpress.org/plugins/block-comment-spam-bots/" target="_blank">Block Comment Spam Bots</a> plugin. No more automated comment spam - it's very effective.</p>
<p>There's our plugin that will automatically add your <strong>Amazon Affiliate code</strong> to any Amazon links - even links entered in comments by others!&nbsp;&nbsp;&nbsp;Check out our nifty <a href="https://wordpress.org/plugins/amazolinkenator/" target="_blank">AmazoLinkenator</a>! It will probably increase your Amazon Affiliate revenue!</p>
<p>We've got a <a href="https://wordpress.org/plugins/simple-gdpr/" target="_blank"><strong>Simple GDPR</strong></a> plugin that displays a GDPR banner for the user to acknowledge. And it creates a generic Privacy page, and will put that Privacy Page link at the bottom of all pages.</p>
<p>How about our <strong><a href="https://wordpress.org/plugins/url-smasher/" target="_blank">URL Smasher</a></strong> which automatically shortens URLs in pages/posts/comments?</p>
<p><a href="https://wordpress.org/plugins/blog-to-html/" target="_blank"><strong>Blog To HTML</strong></a> : a simple way to export all blog posts (or specific categories) to an HTML file. No formatting, and will include any pictures or galleries. A great way to convert your blog site to an ebook.</p>
<hr />
<p><strong>To reduce and prevent spam</strong>, check out:</p>
<p> <a href="https://wordpress.org/plugins/block-comment-spam-bots/" target="_blank"><b>Block Comment Spam Bots</b></a> plugin totally blocks all automated comment spam! No more automated comment spam - it's very effective. A total rewrite of our FormSpammerTrap for Comments plugin.</p>
<p><a href="https://wordpress.org/plugins/formspammertrap-for-comments/" target="_blank"><strong>FormSpammerTrap for Comments</strong></a>: reduces spam without captchas, silly questions, or hidden fields - which don't always work. </p>
<p><a href="https://wordpress.org/plugins/formspammertrap-for-contact-form-7/" target="_blank"><strong>FormSpammerTrap for Contact Form 7</strong></a>: reduces spam when you use Contact Form 7 forms. All you do is add a little shortcode to the contact form.</p>
<p>And check out our <a href="https://www.FormSpammerTrap.com" target="_blank"><b>FormSpammerTrap</b></a> code for Word Press and non-Word Press sites. A contact form that spam bots can't get past!</p>
<hr />
<p>For <strong>multisites</strong>, we've got:
    <ul>
    <li><strong><a href="https://wordpress.org/plugins/multisite-comment-display/" target="_blank">Multisite Comment Display</a></strong> to show all comments from all subsites.</li>
    <li><strong><a href="https://wordpress.org/plugins/multisite-post-reader/" target="_blank">Multisite Post Reader</a></strong> to show all posts from all subsites.</li>
    <li><strong><a href="https://wordpress.org/plugins/multisite-media-display/" target="_blank">Multisite Media Display</a></strong> shows all media from all subsites with a simple shortcode. You can click on an item to edit that item.
    </li>
    </ul>
    </p>
    <hr />
    <p><strong>They are all free and fully featured!</strong></p>
    <hr />
        <p>I don't drink coffee, but if you are inclined to donate because you like my Word Press plugins, go right ahead! I'll grab a nice hot chocolate, and maybe a blueberry muffin. Thanks!</p>
<div align='center'><?php CWPS_donate_button();?></div>
<hr />
<p><strong>Privacy Notice</strong>: This plugin does not store or use any personal information or cookies.</p>
<hr>
<?php
	CWPS__cellarweb_logo();

		return;
	}

	// ----------------------------------------------------------------------------
	// footer for settings page
	// ----------------------------------------------------------------------------
	function CWPS_footer() {
	?>
<p align="center"><strong>Copyright &copy; 2016-<?php echo gmdate('Y'); ?> by Rick Hellewell and  <a href="http://CellarWeb.com" title="CellarWeb" >CellarWeb.com</a> , All Rights Reserved. Released under GPL2 license. <a href="http://cellarweb.com/contact-us/" target="_blank" title="Contact Us">Contact us page</a>.</strong></p>
<?php
	return;
	}

	// ----------------------------------------------------------------------------
	// get and display htaccess file
	// ----------------------------------------------------------------------------
	function CWPS_show_htaccess() {
		// make sure the wp_filesystem class is loaded
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		global $wp_filesystem;

		$htaccess_path = ABSPATH . '.htaccess';
		$filesystem    = new WP_Filesystem_Direct(true);
		// build htaccess display area and contents; alert if not found
		if (file_exists($htaccess_path)) {
			$htaccess_contents = $filesystem->get_contents($htaccess_path);

			$htaccess_message = "<div class='CWPS_box_green'>" . nl2br($htaccess_contents) . " </div>";
		} else {
			$htaccess_message = "<div class='CWPS_box_yellow'>No htaccess file found .. probably not good...<br>Location: $htaccess_file </div>";
		}
		echo $htaccess_message;
		return;
	}

	// --------------------------------------------------------------
	//  show login on menu bar if logged in
	// --------------------------------------------------------------
	function CWPS_login_menu_item($items, $args) {
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			$name = trim($user->display_name); // or user_login , user_firstname, user_lastname
			$items .= '<li><a href="' . wp_logout_url() . '">Hello, ' . $name . ' (Log Out)</a></li>';
		} elseif (!is_user_logged_in() && $args->theme_location == 'primary') {
			$items .= '<li><a href="' . site_url('wp-login.php') . '"> Log In</a></li>';
		}
		return $items;
	}

	// --------------------------------------------------------------
	// redirects after login/logout to home page
	// --------------------------------------------------------------
	// redirect user to home page after login if not admin
	function CWPS_login_to_home_page() {
		if (!is_admin()) {
			return home_url();
		}
	}

	// redirect to home page after logout
	function CWPS_after_logout() {
		wp_redirect(home_url());
		exit();
		return;
	}

	// ============================================================================
	// PayPal donation button for settings sidebar (as of 25 Jan 2022)
	// ----------------------------------------------------------------------------
	function CWPS_donate_button() {
	?>
<form action="https://www.paypal.com/donate" method="post" target="_top">
<input type="hidden" name="hosted_button_id" value="TT8CUV7DJ2SRN" />
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
</form>

<?php
	return;
	}

	// ----------------------------------------------------------------------------
	function CWPS__cellarweb_logo() {
	?>
 <p align="center"><a href="https://www.cellarweb.com" target="_blank" title="CellarWeb.com site"><img src="<?php echo plugin_dir_url(__FILE__); ?>assets/cellarweb-logo-2022.jpg"  width="90%" class="AZLNK_shadow" ></a></p>
 <?php
 	return;
 	}

 	// ----------------------------------------------------------------------------
 	// limit login attempts (added in version 3.00)  - BEGIN
 	//      - based on https://www.virfice.com/how-to-limit-login-attempts-in-wordpress-site/
 	//      = uses CWPS_LOGIN_ATTEMPT_LIMIT constant
 	// ----------------------------------------------------------------------------

 	function CWPS_check_attempted_login($user, $username, $password) {
 		if (get_transient('attempted_login')) {
 			$datas = get_transient('attempted_login');

 			if ($datas['tried'] >= CWPS_LOGIN_ATTEMPT_LIMIT) {
 				$until = get_option('_transient_timeout_' . 'attempted_login');
 				$time  = CWPS_time_to_go($until);

 				return new WP_Error('too_many_tried', sprintf(__('<strong>ERROR</strong>: You have reached authentication limit, you will be able to try again in %1$s.'), $time));
 			}
 		}

 		return $user;
 	}

 	add_filter('authenticate', 'CWPS_check_attempted_login', 30, 3);
 	// ----------------------------------------------------------------------------
 	// figure out failed logins
 	// ----------------------------------------------------------------------------
 	function CWPS_login_failed($username) {
 		if (get_transient('attempted_login')) {
 			$datas = get_transient('attempted_login');
 			$datas['tried']++;

 			if ($datas['tried'] <= 3) {
 				set_transient('attempted_login', $datas, CWPS_LOCKOUT_SECONDS);
 			}
 		} else {
 			$datas = array(
 				'tried' => 1
 			);
 			set_transient('attempted_login', $datas, 300);
 		}
 	}

 	add_action('wp_login_failed', 'CWPS_login_failed', 10, 1);

 	// ----------------------------------------------------------------------------
 	// timer function for limiting login attempts
 	// ----------------------------------------------------------------------------
 	function CWPS_time_to_go($timestamp) {

 		// converting the mysql timestamp to php time
 		$periods = array(
 			"second",
 			"minute",
 			"hour",
 			"day",
 			"week",
 			"month",
 			"year"
 		);
 		$lengths = array(
 			"60",
 			"60",
 			"24",
 			"7",
 			"4.35",
 			"12"
 		);
 		$current_timestamp = time();
 		$difference        = abs($current_timestamp - $timestamp);
 		for ($i = 0; $difference >= $lengths[$i] && $i < count($lengths) - 1; $i++) {
 			$difference /= $lengths[$i];
 		}
 		$difference = round($difference);
 		if (isset($difference)) {
 			if ($difference != 1) {
 				$periods[$i] .= "s";
 			}

 			$output = "$difference $periods[$i]";
 			return $output;
 		}
 	}

 	// ----------------------------------------------------------------------------
 	// limit login attempts (added in version 3.00)  - END
 	// ----------------------------------------------------------------------------

 	// ----------------------------------------------------------------------------
 	// returns a formatted 'li' list of admin users
 	// ----------------------------------------------------------------------------
 	function CWPS_get_admin_list() {
 		$blogusers = get_users('orderby=nicename&role=administrator');
 		// Array of WP_User objects.
 		$admin_list = "<p style='margin-left:3.5%'><b>Current list of 'administrator' level role user names</b>. Check to ensure thsee accounts are authorized. Bogus administrator level accounts can be an indication of malware on your site.</p>";
 		$admin_list .= "<ul  style='margin-left:5%;'>";
 		$admin_list = "<li style='list-style-type: none;margin-left:3%;'><b>Display Name - Login - Nice Name - Email </b></li>";
 		foreach ($blogusers as $user) {
 			$admin_list .= "<li style='list-style-type: circle;margin-left:3%;'>" . esc_html($user->display_name) . " - " . esc_html($user->user_login) . " - " . esc_html($user->user_nicename) . " - " . esc_html($user->user_email) . "</li>";
 		}
 		$admin_list .= "</ul>";
 		return $admin_list;
 	}

 	// ----------------------------------------------------------------------------
 	// show robots.txt output if option enables
 	//      - also will add_filter to enable changes
 	// ----------------------------------------------------------------------------

 	function CWPS_robots_build_content() {
 		$botlist = array(
 			"ChatGPT" => "GPTBot",
 			"Bard" => "Bard",
 			"Bing" => "bingbot-chat/2.0",
 			"Common Crawl" => "CCBot",
 			"omgili" => "Omgili", // since version 4.10
 			"omgilibot" => "Omgili Bot", // since version 4.10
			"Diffbot" 	=> "Diffbot",
			"MJ12bot"	=> "MJ12bot",
			"anthropic-ai" => "anthropic-ai",
			"ClaudeBot" 	=> "ClaudeBot",
			"FacebookBot"	=> "FacebookBot",
			"Google-Extended"	=> "Google-Extended",
			"SentiBot"		=> "SentiBot",
			"sentibot"		=> "sentibot",
			// updated 9 Mar 2024
			"Twitterbot"	=> "Twitterbot",
			"AhrefsBot"		=> "AhrefsBot",
			"CCBot"			=> "CCBot",
			"AwarioRssBot"	=> "AwarioRssBot",
			"AwarioSmartBot"=> "AwarioSmartBot",
			"Claude-Web"	=> "Claude-Web",
			"FacebookBot"	=> "FacebookBot",
			"magpie-crawler"	=> "magpie-crawler",
			"peer39_crawler"	=> "peer39_crawler",
			"PerplexityBot"		=> "PerplexityBot",
			"CrystalSemanticsBot"	=> "CrystalSemanticsBot",
			"Applebot"		=> "Applebot",

 		);

 		$directive = "\n# Added by Privacy and Security from CellarWeb.com plugin version " . CWPS_VERSION . " (begin)\n";
 		$directive .= "# These directives block access by AI scanners. \n\n";
 		foreach ($botlist as $name => $agent) {
 			$directive .= "  #  Blocks " . $name . " bot scanning \n";
 			$directive .= str_repeat(" ", 8) . "User-agent: " . $agent . "\n" . str_repeat(" ", 8) . "Disallow: / \n";
 		}

 		$content  = "User-agent: *\n";
 		$site_url = wp_parse_url(site_url());
 		$path     = (!empty($site_url['path'])) ? $site_url['path'] : '';
 		$content .= "Disallow: $path/wp-admin/\n";
 		$content .= "Allow: $path/wp-admin/admin-ajax.php\n";
 		$content .= "Sitemap: {$site_url['scheme']}://{$site_url['host']}/sitemap_index.xml\n";
 		$content .= $directive;

 		$content .= "\n# Added by Privacy and Security from CellarWeb.com plugin version " . CWPS_VERSION . "  (end)\n";
 		return $content;
 	}

 	function CWPS_robots_settings_info($content) {
 		$site_url   = home_url();
 		$robotslink = $site_url . "?robots=1";
 		echo "<p>This is the current content of the virtual robots.txt file that WordPress will use. These AI Bot blocking directives will not affect search engine scanning or SEO.</p><p>You can test your virtual robots.txt file by using this link: <a href='$robotslink' target='_blank'>$robotslink</a> (opens in a new tab/window).</p> ";
	  //	echo "<p>You can add additional directives to the virtual robots.txt file by adding commands to this box. Note there is no syntax checking of added commands.</p>";
 		echo "<textarea readonly rows='15'  cols='80' class='CWPS_textarea'>" . $content . "</textarea>";
 		$robots_file = get_home_path() . "robots.txt";
 		$robots_url  = site_url() . "/robots.txt";
 		if (file_exists($robots_file)) {
 			$message = "<p><b style='background-color:yellow;'>WARNING! You have an actual robots.txt file at $robots_file.</b> That will override the above virtual robots.txt file used by Word Press. We recommend not using an actual robots.txt file to allow WP to manage it.</p>";
 		} else {
 			$message = "<p><b>You do not have an actual robots.txt file in your site root, so the above Word Press virtual robots.txt directives will be used.</b></p>";
 		}
 		echo $message;
 		submit_button(); // creates the submit button at the top of the page
 		return;
 	}

 	function CWPS_show_hidden_plugins() {
 		// Get the list of all plugins before the filter is applied
 		$all_plugins_before = get_plugins();

 		// Apply the filter to remove plugins
 		$all_plugins_after = apply_filters('all_plugins', $all_plugins_before);

 		// Find the hidden plugins (those removed by the filter)
 		$hidden_plugins = array_diff_key($all_plugins_before, $all_plugins_after);

 		if (!count($hidden_plugins)) {$message = "<p  style='margin-left:3%;'><i>Huzzah! No hidden plugins found. That's great!</i></p>";return $message;}
 		// List the hidden plugins.
 		$message = "<p><img src='" . plugin_dir_url(__FILE__) . "css/warning-yellow.png' width='16' height='14' alt=''>&nbsp;<b>You have some hidden plugins installed, listed here. </b>There are some hidden plugins that are not malicious. <i>You should carefully research any plugins on this list to ensure they aren't malicious. Be cautious about following the links to the hidden plugin.</i></p>";
 		// Create an unordered list of hidden plugins
 		$message .= '<ul>';
 		foreach ($hidden_plugins as $plugin_file => $plugin_data) {
 			$status = is_plugin_active($plugin_file) ? '<b>Active</b>' : '<i>Inactive</i>';

 			$plugin_name = esc_html($plugin_data['Name']);
 			$version     = esc_html($plugin_data['Version']);
 			$message .= '<li  style=\'list-style-type: disc;margin-left:3%;\'><b>' . $plugin_name . '</b> (Version: ' . $version . ', Status: ' . $status . ')';
  		$plugin_slug = admin_url('admin.php?page=' . $plugin_data['TextDomain']);
 		$message .= "<br><i>This may be the link to this hidden plugin's settings page:</i> " . $plugin_slug . '</li>' . PHP_EOL;
		}
 		$message .= '</ul>';

 		return $message;
 	}

 	//  show plugins that need updates
 	function CWPS_list_plugins_with_pending_updates() {
 		$update_plugins               = get_site_transient('update_plugins');
 		$plugins_with_pending_updates = array();

 		if (isset($update_plugins->response) && is_array($update_plugins->response)) {
 			foreach ($update_plugins->response as $plugin_file => $update_data) {
 				$plugin_data                    = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
 				$plugins_with_pending_updates[] = $plugin_data['Name'] . ' (Version: ' . $plugin_data['Version'] . ')';
 			}
 		}

 		if (!empty($plugins_with_pending_updates)) {
 			echo '<h3>Plugins with Pending Updates:</h3>';
 			echo '<ul style="margin-left:3%;">';
 			foreach ($plugins_with_pending_updates as $plugin_info) {
 				echo '<li>' . esc_html($plugin_info) . '</li>';
 			}
 			echo '</ul>';
 		} else {
 			echo '<p style="margin-left:3%;"><i>Huzzah! All plugins are updated to the latest version!</i></p>';
 		}
 	}

 	function CWPS_print_array($the_array = array()) {
 		echo "<pre>";
 		print_r($the_array);
 		echo "</pre>";
 		return;
 	}

 	// --------------------------------------------------------------
 	// end of everything
 // ============================================================================
