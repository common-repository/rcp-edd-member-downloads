<?php
/**
 * Plugin Name: Restrict Content Pro - EDD Member Downloads
 * Description: Allow members to download a certain number of items based on their subscription level.
 * Version: 1.0.6
 * Author: iThemes, LLC
 * Author URI: https://ithemes.com
 * Plugin URI: https://restrictcontentpro.com/downloads/edd-member-downloads/
 * Contributors: jthillithemes, layotte, ithemes
 * Text Domain: rcp-edd-member-downloads
 * iThemes Package: rcp-edd-member-downloads
 */

require_once plugin_dir_path( __FILE__ ) . 'includes/class-user-allowance.php';

/**
 * Loads the plugin textdomain.
 *
 * @return void
 */
function rcp_edd_member_downloads_textdomain() {
	load_plugin_textdomain( 'rcp-edd-member-downloads', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'rcp_edd_member_downloads_textdomain' );

/**
 * Show a notice in the admin area if EDD is not installed.
 *
 * @since 1.0.3
 * @return void
 */
function rcp_edd_member_downloads_edd_required_notice() {

	if ( class_exists( 'Easy_Digital_Downloads' ) ) {
		return;
	}

	if ( ! current_user_can( 'install_plugins' ) ) {
		return;
	}

	?>
	<div class="notice notice-error">
		<p>
			<?php printf(
				__( 'The Restrict Content Pro EDD Member Downloads add-on requires <a href="%s" target="_blank">Easy Digital Downloads</a>.' ),
				esc_url( 'https://easydigitaldownloads.com/' )
			); ?>
		</p>
	</div>
	<?php

}
add_action( 'admin_notices', 'rcp_edd_member_downloads_edd_required_notice' );


/**
 * Adds the plugin settings form fields to the subscription level form.
 *
 * @param object $level Subscription level object from the database.
 *
 * @return void
 */
function rcp_edd_member_downloads_level_fields( $level ) {

	if ( ! function_exists( 'EDD' ) ) {
		return;
	}

	/**
	 * @var RCP_Levels $rcp_levels_db
	 */
	global $rcp_levels_db;

	if ( empty( $level->id ) ) {
		$allowed = 0;
	} else {
		$existing = $rcp_levels_db->get_meta( $level->id, 'edd_downloads_allowed', true );
		$allowed  = ! empty( $existing ) ? $existing : 0;
	}
	?>

	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="rcp-edd-downloads-allowed"><?php printf( __( '%s Allowed', 'rcp-edd-member-downloads' ), edd_get_label_plural() ); ?></label>
		</th>
		<td>
			<input type="number" min="0" step="1" id="rcp-edd-downloads-allowed" name="rcp-edd-downloads-allowed" value="<?php echo absint( $allowed ); ?>" style="width: 100px;"/>
			<p class="description"><?php printf( __( 'The number of %s allowed each subscription period.', 'rcp-edd-member-downloads' ), strtolower( edd_get_label_plural() ) ); ?></p>
		</td>
	</tr>

	<?php
	wp_nonce_field( 'rcp_edd_downloads_allowed_nonce', 'rcp_edd_downloads_allowed_nonce' );
}
add_action( 'rcp_add_subscription_form', 'rcp_edd_member_downloads_level_fields' );
add_action( 'rcp_edit_subscription_form', 'rcp_edd_member_downloads_level_fields' );



/**
 * Saves the subscription level limit settings.
 *
 * @param int   $level_id ID of the subscription level.
 * @param array $args     Data being added or updated.
 *
 * @return void
 */
function rcp_edd_member_downloads_save_level_limits( $level_id = 0, $args = array() ) {

	if ( ! function_exists( 'EDD' ) ) {
		return;
	}

	/**
	 * @var RCP_Levels $rcp_levels_db
	 */
	global $rcp_levels_db;

	if ( empty( $_POST['rcp_edd_downloads_allowed_nonce'] ) || ! wp_verify_nonce( $_POST['rcp_edd_downloads_allowed_nonce'], 'rcp_edd_downloads_allowed_nonce' ) ) {
		return;
	}

	if ( empty( $_POST['rcp-edd-downloads-allowed'] ) ) {
		$rcp_levels_db->delete_meta( $level_id, 'edd_downloads_allowed' );
		return;
	}

	$rcp_levels_db->update_meta( $level_id, 'edd_downloads_allowed', absint( $_POST['rcp-edd-downloads-allowed'] ) );
}
add_action( 'rcp_add_subscription', 'rcp_edd_member_downloads_save_level_limits', 10, 2 );
add_action( 'rcp_edit_subscription_level', 'rcp_edd_member_downloads_save_level_limits', 10, 2 );


/**
 * Determines if the member is at the product submission limit.
 *
 * @param int $user_id ID of the user to check, or 0 for current user.
 *
 * @since  1.0
 * @return bool True if the user is at the limit, false if not.
 */
function rcp_edd_member_downloads_member_at_limit( $user_id = 0 ) {

	if ( ! function_exists( 'rcp_get_subscription_id' ) ) {
		return;
	}

	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$remaining = rcp_edd_member_downloads_number_remaining( $user_id );
	$at_limit  = ( $remaining > 0 ) ? false : true;

	return $at_limit;
}

/**
 * Get the maximum number of downloads allowed per period.
 *
 * @param int $user_id
 *
 * @since 1.0.5
 * @return int
 */
function rcp_edd_member_downloads_get_download_max( $user_id = 0 ) {

	if ( ! function_exists( 'rcp_get_subscription_id' ) ) {
		return 0;
	}

	$allowance = new RCP_EDD_Member_Downloads_Allowance( $user_id );

	return $allowance->get_max();

}

/**
 * Get the number of downloads remaining for a user.
 *
 * @param int $user_id ID of the user to check, or 0 for current user.
 *
 * @since  1.0.1
 * @return int|false Number of downloads available.
 */
function rcp_edd_member_downloads_number_remaining( $user_id = 0 ) {

	if ( ! function_exists( 'rcp_get_subscription_id' ) ) {
		return false;
	}

	$allowance = new RCP_EDD_Member_Downloads_Allowance( $user_id );

	return $allowance->get_number_remaining();

}

/**
 * Displays the number of downloads the current user has remaining in this period.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Shortcode content.
 *
 * @since  1.0.1
 * @return int|false
 */
function rcp_edd_member_downloads_remaining_shortcode( $atts, $content = null ) {
	return rcp_edd_member_downloads_number_remaining();
}

add_shortcode( 'rcp_edd_member_downloads_remaining', 'rcp_edd_member_downloads_remaining_shortcode' );


/**
 * Resets a vendor's product submission count when making a new payment.
 *
 * @deprecated 1.0.5 In favour of `rcp_edd_member_downloads_reset_count()`
 * @see        rcp_edd_member_downloads_reset_count()
 *
 * @param int   $payment_id ID of the payment that was just inserted.
 * @param array $args       Payment arguments.
 * @param float $amount     Amount the payment was for.
 *
 * @return void
 */
function rcp_edd_member_downloads_reset_limit( $payment_id, $args = array(), $amount = 0.00 ) {

	if ( ! empty( $args['user_id'] ) ) {

		delete_user_meta( $args['user_id'], 'rcp_edd_member_downloads_current_download_count' );

		/** Group Accounts compatibility */
		if ( ! function_exists( 'rcpga_group_accounts' ) || ! $group_id = rcpga_group_accounts()->members->get_group_id( $args['user_id'] ) ) {
			return;
		}

		$group_members = rcpga_group_accounts()->members->get_members( $group_id, array( 'number' => -1 ) );

		foreach( $group_members as $member ) {
			delete_user_meta( $member->user_id, 'rcp_edd_member_downloads_current_download_count' );
		}

	}
	
}
add_action( 'rcp_insert_payment', 'rcp_edd_member_downloads_reset_limit', 10, 3 );

/**
 * Reset the download count when the membership is renewed.
 *
 * @param string         $expiration_date New expiration date.
 * @param int            $membership_id   ID of the membership that was renewed.
 * @param RCP_Membership $membership      Membership object.
 *
 * @since 1.0.5
 * @return void
 */
function rcp_edd_member_downloads_reset_count( $expiration_date, $membership_id, $membership ) {

	$user_id = $membership->get_customer()->get_user_id();

	if ( empty( $user_id ) ) {
		return;
	}

	// Delete the count associated with this membership.
	rcp_delete_membership_meta( $membership_id, 'edd_member_downloads_count' );

	/** Group Accounts compatibility */
	// @todo Needs updating to account for multiple groups.
	if ( ! function_exists( 'rcpga_group_accounts' ) || ! $group_id = rcpga_group_accounts()->members->get_group_id( $user_id ) ) {
		return;
	}

	$group_membership_id = rcpga_group_accounts()->groups->get_membership_id( $group_id );

	// Bail if this membership and group do not match.
	if ( $membership_id != $group_membership_id ) {
		return;
	}

	// Reset allowance for all group members.
	$group_members = rcpga_group_accounts()->members->get_members( $group_id, array( 'number' => -1 ) );

	foreach( $group_members as $member ) {
		delete_user_meta( $member->user_id, 'rcp_edd_member_downloads_current_download_count' );
	}

}

add_action( 'rcp_membership_post_renew', 'rcp_edd_member_downloads_reset_count', 10, 3 );


/**
 * Determines if a user has a membership that allows downloads.
 *
 * @param int $user_id ID of the user to check.
 *
 * @return bool
 */
function rcp_edd_member_downloads_user_has_download_membership( $user_id ) {

	$allowance = new RCP_EDD_Member_Downloads_Allowance( $user_id );

	return $allowance->has_download_membership();

}

/**
 * Get the ID of the membership level being used for the download allowance.
 *
 * @param int $user_id
 *
 * @since 1.0.5
 * @return int
 */
function rcp_edd_member_downloads_get_download_level_id( $user_id ) {

	$allowance = new RCP_EDD_Member_Downloads_Allowance( $user_id );

	return $allowance->get_level_id();

}

/**
 * Render the EDD download button
 *
 * @param string $purchase_form Regular purchase form.
 * @param array  $args          Arguments for display.
 *
 * @return string
 */
function rcp_edd_member_downloads_download_button( $purchase_form, $args ) {

	if ( ! is_user_logged_in() ) {
		return $purchase_form;
	}

	// @todo support bundles
	if ( edd_is_bundled_product( $args['download_id'] ) ) {
		return $purchase_form;
	}

	// @todo maybe support variable prices
	if ( edd_has_variable_prices( $args['download_id'] ) ) {
		return $purchase_form;
	}

	// Check to see if the product has files
	$files = edd_get_download_files( $args['download_id'] );
	if ( empty( $files ) ) {
		return $purchase_form;
	}

	// Check if the member has a membership that allows downloads
	$user = wp_get_current_user();
	if ( ! rcp_edd_member_downloads_user_has_download_membership( $user->ID ) ) {
		return $purchase_form;
	}

	// Check if the member is at the download limit
	if ( rcp_edd_member_downloads_member_at_limit( $user->ID ) && ! edd_has_user_purchased( $user->ID, $args['download_id'] ) ) {
		return $purchase_form;
	}

	global $edd_displayed_form_ids;

	$download = new EDD_Download( $args['download_id'] );

	if ( isset( $edd_displayed_form_ids[ $download->ID ] ) ) {
		$edd_displayed_form_ids[ $download->ID ]++;
	} else {
		$edd_displayed_form_ids[ $download->ID ] = 1;
	}
?>
	<script type="text/javascript">
		(function($) {
			$(document).ready(function() {
				$('.rcp-edd-member-download-request').on('click', function(e) {
					e.preventDefault();
					e.stopImmediatePropagation();

					var request_button = $(this);
					var spinner_button = $(this).parent().find('.rcp-edd-member-download-request-pending');

					// Hide this button and show the spinner.
					request_button.hide();
					spinner_button.show();

					var item = $(this).parent().find("input[name='rcp-edd-member-download-request']").val();
					var data = {
						action: 'rcp-edd-member-download-request',
						security: $('#rcp-edd-member-download-nonce').val(),
						item: item
					};

					$.ajax({
						data: data,
						type: "POST",
						dataType: "json",
						url: edd_scripts.ajaxurl,
						success: function (response) {
							if ( response.file && response.file.length > 0 ) {
								request_button.show();
								spinner_button.hide();

								window.location.replace(response.file);
							}
						},
						error: function (response) {
							console.log('error ' + response);
						}
					});
				});
			});
		})(jQuery);
	</script>

<?php
	$form_id      = ! empty( $args['form_id'] ) ? $args['form_id'] : 'edd_purchase_' . $download->ID;
	$button_color = edd_get_option( 'checkout_color', 'blue' );
	ob_start();
?>
	<form id="<?php echo $form_id; ?>" class="edd_download_purchase_form edd_purchase_<?php echo absint( $download->ID ); ?>" method="post">
		<input type="hidden" name="download_id" value="<?php echo esc_attr( $download->ID ); ?>">
		<input type="hidden" name="rcp-edd-member-download-request" value="<?php echo esc_attr( $download->ID ); ?>">
		<input type="hidden" id="rcp-edd-member-download-nonce" name="rcp-edd-member-download-nonce" value="<?php echo wp_create_nonce( 'rcp-edd-member-download-nonce' ); ?>">
		<input type="submit" class="rcp-edd-member-download-request button edd-submit <?php echo esc_attr( $button_color ); ?>" value="<?php esc_html_e( 'Download', 'rcp-edd-member-downloads' ); ?>">
		<a href="#" class="rcp-edd-member-download-request-pending button edd-submit <?php echo esc_attr( $button_color ); ?>" style="display: none; position: relative;">
			<span style="opacity: 0;"><?php _e( 'Loading', 'rcp-edd-member-downloads' ); ?></span>
			<span class="edd-loading" style="opacity: 1;" aria-label="<?php esc_attr_e( 'Loading', 'rcp-edd-member-downloads' ); ?>"></span>
		</a>
	</form>
<?php
	return ob_get_clean();

}
add_filter( 'edd_purchase_download_form', 'rcp_edd_member_downloads_download_button', 10, 2 );

/**
 * Process file download via ajax and insert payment record
 *
 * @return void
 */
function rcp_edd_member_downloads_process_ajax_download() {

	check_ajax_referer( 'rcp-edd-member-download-nonce', 'security' );

	if ( ! is_user_logged_in() ) {
		wp_die(-1);
	}

	// Check if the member has a membership that allows downloads
	$user      = wp_get_current_user();
	$allowance = new RCP_EDD_Member_Downloads_Allowance( $user->ID );
	if ( ! $allowance->has_download_membership() ) {
		wp_die(-1);
	}

	if ( empty( $_POST['item'] ) ) {
		wp_die(-1);
	} else {
		$item = absint( $_POST['item'] );
	}

	if ( edd_has_user_purchased( $user->ID, $item ) ) {

		$payment_args = array(
			'number'   => 1,
			'status'   => 'publish',
			'user'     => $user->ID,
			'download' => $item,
			'meta_key' => '_rcp_edd_member_downloads'
		);

		$payments = new EDD_Payments_Query( $payment_args );

		$payment  = $payments->get_payments();

		if ( ! $payment ) {
			unset($payment_args['meta_key'] );
			$payments = new EDD_Payments_Query( $payment_args );
			$payment  = $payments->get_payments();
		}

		$payment_meta = edd_get_payment_meta( $payment[0]->ID );

		$files        = edd_get_download_files( $payment_meta['cart_details'][0]['id'] );

		if ( ! empty( $files ) ) {
			$file_keys = array_keys( $files );
			$url       = edd_get_download_file_url( $payment_meta['key'], $payment_meta['user_info']['email'], $file_keys[0], $payment_meta['cart_details'][0]['id'] );
		} else {

			$files    = false;
			$file_key = false;

			foreach ( $payment_meta['cart_details'] as $key => $cart_item ) {
				if ( $cart_item['id'] === $item ) {
					$files = edd_get_download_files( $cart_item['id'] );
					$file_key = $key;
					break;
				}
			}

			if ( $files && $file_key ) {
				$file_keys = array_keys( $files );
				$url       = edd_get_download_file_url( $payment_meta['key'], $payment_meta['user_info']['email'], $file_keys[0], $payment_meta[$key] );
			}
		}

	} else {

		remove_action( 'edd_complete_purchase', 'edd_trigger_purchase_receipt', 999 );

		$level_id = $allowance->get_level_id();

		if ( ! $level_id ) {
			wp_die( __( 'You do not have a membership.', 'rcp-edd-member-downloads' ) );
		}

		$max = $allowance->get_max();

		if ( empty( $max ) ) {
			wp_die( __( 'You must have a valid membership.', 'rcp-edd-member-downloads' ) );
		}

		if ( $allowance->get_number_remaining() < 1 ) {
			wp_die( __( 'You have reached the limit defined by your membership.', 'rcp-edd-member-downloads' ) );
		}

		$payment = new EDD_Payment();
		$payment->add_download( $item, array( 'item_price' => 0.00 ) );
		$payment->email      = $user->user_email;
		$payment->first_name = $user->first_name;
		$payment->last_name  = $user->last_name;
		$payment->user_id    = $user->ID;
		$payment->user_info  = array(
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'email'      => $user->user_email,
			'id'         => $user->ID
		);
		$payment->gateway = 'manual';
		$payment->status  = 'pending';
		$payment->save();
		$payment->status  = 'complete';
		$payment->save();

		// Add a piece of meta to the payment letting us know it was created by this plugin. We query using this meta for future checks.
		edd_update_payment_meta( $payment->ID, '_rcp_edd_member_downloads', true );

		edd_insert_payment_note( $payment->ID, __( 'Downloaded with RCP membership', 'rcp-edd-member-downloads' ) );

		$payment_meta = edd_get_payment_meta( $payment->ID );
		$files        = edd_get_download_files( $item );
		$file_keys    = array_keys( $files );
		$url          = edd_get_download_file_url( $payment_meta['key'], $user->user_email, $file_keys[0], $item );

		$allowance->increment_current();
	}

	wp_send_json( array(
		'files' => $files,
		'file'  => $url
	) );

}
add_action( 'wp_ajax_rcp-edd-member-download-request', 'rcp_edd_member_downloads_process_ajax_download' );
add_action( 'wp_ajax_nopriv_rcp-edd-member-download-request', 'rcp_edd_member_downloads_process_ajax_download' );

/**
 * Credit downloads remaining when payment is refunded.
 *
 * @param EDD_Payment $edd_payment
 *
 * @return void
 */
function rcp_edd_member_downloads_refund_payment( $edd_payment ) {

	// Bail if this wasn't from EDD Member Downloads.
	if ( ! $edd_payment->get_meta( '_rcp_edd_member_downloads' ) ) {
		return;
	}

	$allowance = new RCP_EDD_Member_Downloads_Allowance( $edd_payment->user_id );

	$allowance->decrement_current();

}
add_action( 'edd_post_refund_payment', 'rcp_edd_member_downloads_refund_payment' );

if ( ! function_exists( 'ithemes_rcp_edd_member_downloads_updater_register' ) ) {
	function ithemes_rcp_edd_member_downloads_updater_register( $updater ) {
		$updater->register( 'REPO', __FILE__ );
	}
	add_action( 'ithemes_updater_register', 'ithemes_rcp_edd_member_downloads_updater_register' );

	require( __DIR__ . '/lib/updater/load.php' );
}