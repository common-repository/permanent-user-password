<?php
/**
 * Plugin Name: Permanent User Password
 * Description: A lightweight WordPress plugin that allows administrators to set permanent passwords for users on their website.
 * Plugin URI: https://github.com/fahidjavid/permanent-user-password
 * Author: Fahid Javid
 * Author URI: https://fahidjavid.com
 * Contributors: fahidjavid
 * Version: 1.0.0
 * License: GPL2
 * Text Domain: pup
 * Domain Path: /languages/
 *
 * @since   1.0.0
 * @package PUP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PUPA_Permanent_User_Password' ) ) :

	/**
	 * Main class for the Permanent User Password plugin.
	 *
	 * @since 1.0.0
	 */
	class PUPA_Permanent_User_Password {

		/**
		 * Plugin Version
		 *
		 * @var string
		 */
		public $version = '1.0.0';

		/**
		 * Instance of the Class
		 *
		 * @var object
		 */
		protected static $_instance;

		/**
		 * Creates an instance of the class.
		 *
		 * @return PUPA_Permanent_User_Password
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->define_constants();
			$this->init_hooks();
		}

		/**
		 * Define constants used by the plugin.
		 *
		 * @return void
		 */
		public function define_constants() {
			define( 'PUPA_VERSION', $this->version );
			define( 'PUPA_BASE_NAME', plugin_basename( __FILE__ ) );
			define( 'PUPA_BASE_URL', plugin_dir_url( __FILE__ ) );
			define( 'PUPA_BASE_DIR', plugin_dir_path( __FILE__ ) );
			define( 'PUPA_DOCS_URL', '#' );
			define( 'PUPA_ISSUE_URL', 'https://github.com/fahidjavid/permanent-user-password/issues' );
		}

		/**
		 * Initialize hooks for the plugin.
		 *
		 * @return void
		 */
		public function init_hooks() {
			add_action( 'show_user_profile', array( $this, 'pupa_user_setting' ) );
			add_action( 'edit_user_profile', array( $this, 'pupa_user_setting' ) );
			add_action( 'personal_options_update', array( $this, 'pupa_save_user_setting' ) );
			add_action( 'edit_user_profile_update', array( $this, 'pupa_save_user_setting' ) );
			add_action( 'profile_update', array( $this, 'pupa_check_user_password' ), 10, 2 );
			add_action( 'pupa_protect_password', array( $this, 'pupa_update_protect_password' ), 10, 3 );
		}

		/**
		 * Display user settings fields on the profile page.
		 *
		 * @param $user
		 *
		 * @return void
		 */
		public function pupa_user_setting( $user ) {

			$current_user = wp_get_current_user();
			if ( in_array( 'administrator', (array)$current_user->roles ) && is_admin() ) : ?>
                <div style="margin-top: 50px; background: #80808021; padding: 20px; border-radius: 10px;">
                    <h3><?php esc_html_e( 'Set Permanent Password for this User', 'permanent-user-password' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th>
                                <label for="pupa_check"><?php esc_html_e( 'Password Check', 'permanent-user-password' ); ?></label>
                            </th>

                            <td>
								<?php $checked = get_the_author_meta( 'pupa_check', $user->ID ); ?>
                                <input type="checkbox" name="pupa_check" id="pupa_check" class="regular-text" <?php echo esc_attr( $checked === 'on' ? 'checked' : '' ); ?>/>
                                <label for="pupa_check" class="description"><?php esc_html_e( 'Check to make this user password permanent.', 'permanent-user-password' ); ?></label>
                            </td>

                        </tr>
                        <tr>
                            <th>
                                <label for="pupa_password"><?php esc_html_e( 'Permanent Password', 'permanent-user-password' ); ?></label>
                            </th>
                            <td>
								<?php $pupa_password = get_the_author_meta( 'pupa_password', $user->ID ); ?>
                                <input type="password" name="pupa_password" id="pupa_password" class="regular-text" value="<?php echo esc_attr( ! empty( $pupa_password ) ? $pupa_password : '' ); ?>" /><br>
                                <label for="pupa_password" class="description"><?php esc_html_e( 'Enter a password that you want to set for this user permanently.', 'permanent-user-password' ); ?></label>
                            </td>

                        </tr>
                    </table>
                </div>
			<?php

			endif;

		}

		/**
		 * Save PUP settings on the user profile page.
		 *
		 * @param $user_id
		 *
		 * @return false|void
		 */
		public function pupa_save_user_setting( $user_id ) {

			$current_user = wp_get_current_user();
			if ( ! in_array( 'administrator', (array)$current_user->roles ) ) {
				return false;
			}

			$pupa_password = get_user_meta( $user_id, 'pupa_password', true );

			if ( isset( $_POST['pupa_check'] ) ) {
				$pupa_post_check = sanitize_text_field( $_POST['pupa_check'] );
				update_user_meta( $user_id, 'pupa_check', $pupa_post_check );
			} else {
				update_user_meta( $user_id, 'pupa_check', false );
			}

			if ( isset( $_POST['pupa_password'] ) ) {
				$pupa_post_password = sanitize_text_field( $_POST['pupa_password'] );
				update_user_meta( $user_id, 'pupa_password', $pupa_post_password );
			} else {
				update_user_meta( $user_id, 'pupa_password', false );
			}

			$pupa_post_password_check = ( isset( $_POST['pupa_password'] ) ) ? sanitize_text_field( $_POST['pupa_password'] ) : false;
			if ( isset( $_POST['pupa_password'] ) && $pupa_password !== $pupa_post_password_check ) {
				update_user_meta( $user_id, 'pupa_password_updated', true );
			} else {
				update_user_meta( $user_id, 'pupa_password_updated', false );
			}

		}

		/**
		 * Check user password if it's changed.
		 *
		 * @param $user_id
		 * @param $old_user_data
		 *
		 * @return false|void
		 */
		public function pupa_check_user_password( $user_id, $old_user_data ) {

			if ( empty( $user_id ) || empty( $old_user_data ) ) {
				return false;
			}

			$old_pass  = $old_user_data->data->user_pass;
			$user_data = get_userdata( $user_id );
			$new_pass  = $user_data->data->user_pass;

			$permanent = get_user_meta( $user_id, 'pupa_check', true );
			$permanent = ( 'on' === $permanent ) ? true : false;

			$pupa_password = get_user_meta( $user_id, 'pupa_password', true );
			$pupa_password = ( ! empty( $pupa_password ) ) ? wp_hash_password( $pupa_password ) : false;

			$pupa_password_updated = get_user_meta( $user_id, 'pupa_password_updated', true );

			/**
			 * Check to see if user password has been tried to change
			 * or user permanent password has been updated. If any of
			 * the conditions are true then proceed.
			 */
			if ( ! empty( $permanent ) && ! empty( $pupa_password ) && ( $old_pass !== $new_pass || ! empty( $pupa_password_updated ) ) ) {

				$user_data = array(
					'user_id'   => intval( $user_id ),
					'pupa_pass' => sanitize_text_field( $pupa_password ), // Hashed permanent password.
					'new_pass'  => sanitize_text_field( $new_pass )
				);

				/**
				 * Schedule the change of password
				 *
				 * @since 1.0.0
				 *
				 * @param string - pupa_protect_password
				 * @param array  - arguments required by updating function
				 *
				 * @param int     - unix timestamp of when to run the event
				 */
				wp_schedule_single_event( current_time( 'timestamp' ), 'pupa_protect_password', $user_data );

			}

		}

		/**
		 * Revert the user password to given permanent password.
		 *
		 * @param $user_id
		 * @param $pupa_pass
		 * @param $new_pass
		 *
		 * @return false|void
		 */
		public function pupa_update_protect_password( $user_id, $pupa_pass, $new_pass ) {

			if ( empty( $user_id ) || empty( $pupa_pass ) || empty( $new_pass ) ) {
				return false;
			}

			$permanent = get_user_meta( $user_id, 'pupa_check', true );
			$permanent = 'on' === $permanent;

			$pupa_password = get_user_meta( $user_id, 'pupa_password', true );

			if ( ! empty( $permanent ) && ! empty( $pupa_password ) && ( $pupa_pass !== $new_pass ) ) {

				/**
				 * Clear the 'profile_update' hook before updating the password
				 * otherwise you will get caught in recursive call. Because
				 * wp_update_user calls wp_insert_user which in turn calls
				 * 'profile_update'.
				 */
				remove_action( 'profile_update', array( $this, 'pupa_check_user_password' ) );

				// Update the password.
				$return = wp_update_user( array(
					'ID'        => $user_id,
					'user_pass' => $pupa_password
				) );

				// Error handling.
				if ( is_wp_error( $return ) ) {
					$errors[] = $return->get_error_message();
					add_action( 'admin_notices', array( $this, 'pupa_password_update_error_notice' ) );
				}

				// Clear schedule hook.
				wp_clear_scheduled_hook( 'pupa_protect_password', array( $user_id, $pupa_pass, $new_pass ) );

			}

		}

		/**
		 * Display a notice if there is any error while update the password.
		 *
		 * @return void
		 */
		public function pupa_password_update_error_notice() {

			$class   = 'notice notice-error is-dismissible';
			$message = esc_html__( 'Yikes! An error occurred while updating your permanent password.', 'permanent-user-password' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

		}

	}

endif;

/**
 * Returns the main instance of PUPA_Permanent_User_Password.
 *
 * @since 1.0.0
 * @return PUPA_Permanent_User_Password
 */
function pupa_run() {
	return PUPA_Permanent_User_Password::instance();
}

pupa_run();
