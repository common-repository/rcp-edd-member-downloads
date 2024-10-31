<?php
/**
 * User Download Allowance
 *
 * @package   rcp-edd-member-downloads
 * @copyright Copyright (c) 2019, Restrict Content Pro team
 * @license   GPL2+
 * @since     1.0.5
 */

class RCP_EDD_Member_Downloads_Allowance {

	/**
	 * ID of the user being checked.
	 *
	 * @var int
	 */
	protected $user_id = 0;

	/**
	 * Current number of downloads made in this period.
	 *
	 * @var int
	 */
	protected $current = 0;

	/**
	 * Maximum number of downloads allowed in this period.
	 *
	 * @var int
	 */
	protected $max = 0;

	/**
	 * ID of the membership being used for the maximum. This will be the membership
	 * with the highest allowance.
	 *
	 * @var int
	 */
	protected $membership_id = 0;

	/**
	 * Membership level ID being used for the maximum. This is the membership level ID
	 * for the associated membership.
	 *
	 * @var int
	 */
	protected $level_id = 0;

	/**
	 * Whether or not the user has an active membership with a download allowance.
	 *
	 * @var bool
	 */
	protected $has_download_membership = false;

	/**
	 * Whether or not this user is inheriting a membership from a group owner.
	 *
	 * @var bool
	 */
	protected $is_group_member = false;

	/**
	 * RCP_EDD_Member_Downloads_Allowance constructor.
	 *
	 * @param int $user_id
	 *
	 * @since 1.0.5
	 */
	public function __construct( $user_id = 0 ) {

		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$this->user_id = $user_id;

		$this->init( $this->user_id );

		if ( ! $this->has_download_membership && function_exists( 'rcpga_group_accounts' ) && $group_id = rcpga_group_accounts()->members->get_group_id( $this->user_id ) ) {
			$owner_id = rcpga_group_accounts()->groups->get_owner_id( $group_id );

			if ( $owner_id != $this->user_id ) {
				$this->is_group_member = true;

				// Reinitialize with the owner's membership.
				$this->init( $owner_id );

				// Now re-set the current value because right now it's set to the owner's.
				$this->current = (int) get_user_meta( $user_id, 'rcp_edd_member_downloads_current_download_count', true );
			}
		}

	}

	/**
	 * Set the user's maximum download allowance and current number of downloads in this period.
	 *
	 * @param int $user_id ID of the user to initialize with.
	 *
	 * @since 1.0.5
	 * @return void
	 */
	protected function init( $user_id ) {

		/**
		 * @var RCP_Levels $rcp_levels_db
		 */
		global $rcp_levels_db;

		if ( function_exists( 'rcp_get_customer_by_user_id' ) ) {
			/**
			 * RCP 3.0+
			 */
			$customer = rcp_get_customer_by_user_id( $user_id );

			if ( ! empty( $customer ) && ! $customer->is_pending_verification() ) {
				$memberships = $customer->get_memberships( array(
					'status__in' => array( 'active', 'cancelled' )
				) );

				if ( ! empty( $memberships ) ) {
					// Use the highest maximum value across all the user's memberships.
					foreach ( $memberships as $membership ) {
						/**
						 * @var RCP_Membership $membership
						 */

						if ( ! $membership->is_active() ) {
							continue;
						}

						$this_level_id = $membership->get_object_id();
						$this_max      = (int) $rcp_levels_db->get_meta( $this_level_id, 'edd_downloads_allowed', true );

						if ( $this_max > $this->max ) {
							$this->max           = $this_max;
							$this->current       = (int) rcp_get_membership_meta( $membership->get_id(), 'edd_member_downloads_count', true );
							$this->membership_id = $membership->get_id();
							$this->level_id      = $this_level_id;
						}
					}
				}
			}
		} else {

			/**
			 * RCP 2.9 and lower
			 */
			$member         = new RCP_Member( $user_id );
			$this->level_id = rcp_get_subscription_id( $user_id );

			if ( ! empty( $this->level_id ) && ! $member->is_expired() && 'pending' !== $member->get_status() && ! $member->is_pending_verification() ) {
				$this->max = (int) $rcp_levels_db->get_meta( $this->level_id, 'edd_downloads_allowed', true );
			}

		}

		if ( ! empty( $this->max ) ) {
			$this->has_download_membership = true;
		}

		// If there's still no $current, then let's check user meta. Group account members will use this.
		if ( empty( $this->current ) ) {
			$this->current = (int) get_user_meta( $user_id, 'rcp_edd_member_downloads_current_download_count', true );

			if ( ! empty( $this->current ) && ! empty( $this->membership_id ) && function_exists( 'rcp_update_membership_meta' ) ) {
				// Delete the user meta and move it to membership meta.
				delete_user_meta( $user_id, 'rcp_edd_member_downloads_current_download_count' );
				rcp_update_membership_meta( $this->membership_id, 'edd_member_downloads_count', absint( $this->current ) );
			}
		}

	}

	/**
	 * Get the number of downloads made this period.
	 *
	 * @since 1.0.5
	 * @return int
	 */
	public function get_current() {
		return absint( $this->current );
	}

	/**
	 * Get the maximum number of downloads allowed in this period.
	 *
	 * @since 1.0.5
	 * @return int
	 */
	public function get_max() {
		return absint( $this->max );
	}

	/**
	 * Get the number of downloads remaining in this period.
	 *
	 * @since 1.0.5
	 * @return int
	 */
	public function get_number_remaining() {

		if ( empty( $this->max ) ) {
			return 0;
		}

		$remaining = $this->max - $this->current;

		if ( $remaining < 0 ) {
			$remaining = 0;
		}

		return $remaining;

	}

	/**
	 * Increment the current download count
	 *
	 * @sincei 1.0.5
	 * @return void
	 */
	public function increment_current() {

		$this->current ++;

		if ( ! empty( $this->membership_id ) && ! $this->is_group_member && function_exists( 'rcp_update_membership_meta' ) ) {
			/**
			 * RCP 3.0+ / membership holders
			 */
			rcp_update_membership_meta( $this->membership_id, 'edd_member_downloads_count', absint( $this->current ) );
		} else {
			/**
			 * RCP 2.9 and lower / group members
			 */
			update_user_meta( $this->user_id, 'rcp_edd_member_downloads_current_download_count', absint( $this->current ) );
		}

	}

	/**
	 * Decrement the download count
	 *
	 * @since 1.0.5
	 * @return void
	 */
	public function decrement_current() {

		$this->current --;

		if ( $this->current < 0 ) {
			$this->current = 0;
		}

		if ( ! empty( $this->membership_id ) && ! $this->is_group_member && function_exists( 'rcp_update_membership_meta' ) ) {
			/**
			 * RCP 3.0+ / membership holders
			 */

			if ( $this->current <= 0 ) {
				rcp_delete_membership_meta( $this->membership_id, 'edd_member_downloads_count' );
			} else {
				rcp_update_membership_meta( $this->membership_id, 'edd_member_downloads_count', absint( $this->current ) );
			}
		} else {
			/**
			 * RCP 2.9 and lower / group members
			 */
			if ( $this->current <= 0 ) {
				delete_user_meta( $this->user_id, 'rcp_edd_member_downloads_current_download_count' );
			} else {
				update_user_meta( $this->user_id, 'rcp_edd_member_downloads_current_download_count', absint( $this->current ) );
			}
		}

	}

	/**
	 * Whether or not the user has an active membership with a download allowance.
	 *
	 * @since 1.0.5
	 * @return bool
	 */
	public function has_download_membership() {
		return $this->has_download_membership;
	}

	/**
	 * Get the ID of the membership level being used for the allowance.
	 *
	 * @since 1.0.5
	 * @return int
	 */
	public function get_level_id() {
		return absint( $this->level_id );
	}

	/**
	 * Get the ID of the membership used for calculating the allowance.
	 *
	 * @since 1.0.5
	 * @return int
	 */
	public function get_membership_id() {
		return absint( $this->membership_id );
	}

}