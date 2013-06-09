<?php
/*
  * Plugin Name: SMTP Configuration
  * Plugin URI: http://projectnami.org
  * Description: Allows for easy configuration of the default SMTP server via class constants.
  * Author: Spencer Cameron-Morin
  * Version: 1.0
  * Author URI: http://projectnami.org
*/

class SMTP_Configuration {
	
	var $plugin_page_name = 'smtp-settings';

	function __construct() {
		add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		add_action( 'admin_menu' , array( $this, 'create_settings_menu' ) );	
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function configure_smtp( PHPMailer $phpmailer ) {
		$phpmailer->Host = get_option( $this->plugin_page_name . '-smtp-host' );
		$phpmailer->Port = get_option( $this->plugin_page_name . '-smtp-port' );
		$phpmailer->Username = get_option( $this->plugin_page_name . '-smtp-username' );
		$phpmailer->Password = get_option( $this->plugin_page_name . '-smtp-password' );;
		
		if( get_option( $this->plugin_page_name . '-smtp-auth'  ) == true )
			$phpmailer->SMTPAuth = get_option( $this->plugin_page_name . '-smtp-auth' );
		
		if( get_option( $this->plugin_page_name . '-smtp-auth' == true ) )
			$phpmailer->SMTPSecure = 'ssl';

		$phpmailer->IsSMTP();
	}

	public function create_settings_menu() {
		add_options_page( 'SMTP Settings', 'SMTP', 'manage_options', $this->plugin_page_name, array( $this, 'create_settings_page' ) );
	}

	public function create_settings_page() {
		if(  ! empty( $_POST ) )
			$this->process_form_input();

		$this->generate_settings_form();
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'smtp-settings-javascript', plugins_url( '/js/smtp-settings.js', __FILE__ ) );
	}

	private function process_form_input() {
		
		// Make sure there's no monkey-business going on.
		check_admin_referer( $this->plugin_page_name );

		$smtp_host = ! empty ( $_POST[ 'smtp_host' ] ) ? sanitize_text_field( $_POST[ 'smtp_host' ] ) : '';
		$smtp_port = ! empty( $_POST[ 'smtp_port' ] ) ? absint( $_POST[ 'smtp_port' ] ) : '';
		$smtp_auth = ! empty( $_POST[ 'smtp_auth' ] ) ? true : false;
		$smtp_username = ! empty( $_POST[ 'smtp_username' ] ) ? sanitize_text_field( $_POST[ 'smtp_username' ] ) : '';
		$smtp_password = ! empty( $_POST[ 'smtp_password' ] ) ? sanitize_text_field( $_POST[ 'smtp_password' ] ) : '';
		$smtp_secure = ! empty( $_POST[ 'smtp_secure' ] ) ? true : false;

		update_option( $this->plugin_page_name . '-smtp-host', $smtp_host );
		update_option( $this->plugin_page_name . '-smtp-port', $smtp_port );
		update_option( $this->plugin_page_name . '-smtp-auth', $smtp_auth );
		update_option( $this->plugin_page_name . '-smtp-username', $smtp_username );
		update_option( $this->plugin_page_name . '-smtp-password', $smtp_password );
		update_option( $this->plugin_page_name . '-smtp-secure', $smtp_secure );
	}

	private function maybe_smtp_auth_checked() {
		if( get_option( $this->plugin_page_name . '-smtp-auth' ) == true )
			echo 'checked="checked"';
	}

	private function maybe_smtp_secure_checked() {
		if( get_option( $this->plugin_page_name . '-smtp-secure' ) == true )
			echo 'checked="checked"';
	}

	private function maybe_hide_smtp_auth_parameters() {
		if( get_option( $this->plugin_page_name . '-smtp-auth' ) == true )
			echo 'block';
		else
			echo 'none';
	}

	function generate_settings_form() { ?>
		<div>
			<style>
				form input {
					display: block;
					margin: 0 0 20px 0;
				}
			
				form input[ type=checkbox ] {
					display: inline-block;
					height: 18px;
					margin: 0 5px 20px 0;
				}	

				label {
					font-size: 14px;
				}

				p {
					font-size: 14px;
					margin: 0;
					padding: 0 0 5px 0;
				}
			</style>

			<h2>SMTP Settings</h2>
			<form method="post" action="<?php menu_page_url( $this->plugin_page_name ); ?>" >
				<p>Host Name ( <i>eg. smtp.youresp.com</i> ):</p>
				<input type="text" name="smtp_host" value="<?php echo esc_attr( get_option( $this->plugin_page_name . '-smtp-host' ) ); ?>" />

				<p>Port:</p>
				<input type="text" name="smtp_port" value="<?php echo esc_attr( get_option( $this->plugin_page_name . '-smtp-port' ) ); ?>" />

				<input type="checkbox" name="smtp_auth" id="smtp_auth" <?php $this->maybe_smtp_auth_checked(); ?> />
				<label for="smtp_auth">Use SMTP Authentication?</label>

				<div id="smtp_auth_parameters" style="display: <?php $this->maybe_hide_smtp_auth_parameters(); ?>">
					<p>Username:</p>
					<input type="text" name="smtp_username" id="smtp_username" value="<?php echo esc_attr( get_option( $this->plugin_page_name . '-smtp-username' ) ); ?>" />
	
					<p>Password:</p>
					<input type="text" name="smtp_password" id="smtp_password" value="<?php echo esc_attr( get_option( $this->plugin_page_name . '-smtp-password' ) ); ?>" />
	
					<input type="checkbox" name="smtp_secure" id="smtp_secure" <?php $this->maybe_smtp_secure_checked(); ?> />
					<label for="smtp_secure">Use SSL?</label>
				</div>

				<input type="submit" value="Update Settings" />
				<?php wp_nonce_field( $this->plugin_page_name ); ?>
			</form>
		</div><?php
	}
}

new SMTP_Configuration;

?>
