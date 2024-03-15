<?php

use WP_CLI\CommandWithDBObject;
use WP_CLI\Utils;
use WP_CLI\Fetchers\Signup as SignupFetcher;

/**
 * Manages signups on a multisite installation.
 *
 * ## EXAMPLES
 *
 *     # List signups.
 *     $ wp signup list
 *     +-----------+------------+---------------------+---------------------+--------+------------------+
 *     | signup_id | user_login | user_email          | registered          | active | activation_key   |
 *     +-----------+------------+---------------------+---------------------+--------+------------------+
 *     | 1         | bobuser    | bobuser@example.com | 2024-03-13 05:46:53 | 1      | 7320b2f009266618 |
 *     | 2         | johndoe    | johndoe@example.com | 2024-03-13 06:24:44 | 0      | 9068d859186cd0b5 |
 *     +-----------+------------+---------------------+---------------------+--------+------------------+
 *
 *     # Activate signup.
 *     $ wp signup activate 2
 *     Success: Signup activated. Password: bZFSGsfzb9xs
 *
 *     # Delete signup.
 *     $ wp signup delete 3
 *     Success: Signup deleted.
 *
 * @package wp-cli
 */
class Signup_Command extends CommandWithDBObject {

	protected $obj_type = 'signup';

	protected $obj_id_key = 'signup_id';

	protected $obj_fields = [
		'signup_id',
		'user_login',
		'user_email',
		'registered',
		'active',
		'activation_key',
	];

	private $fetcher;

	public function __construct() {
		$this->fetcher = new SignupFetcher();
	}

	/**
	 * Lists signups.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each signup.
	 *
	 * [--<field>=<value>]
	 * : Filter results by key=value pairs.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each signup:
	 *
	 * * signup_id
	 * * user_login
	 * * user_email
	 * * registered
	 * * active
	 * * activation_key
	 *
	 * These fields are optionally available:
	 *
	 * * domain
	 * * path
	 * * title
	 * * activated
	 * * meta
	 *
	 * ## EXAMPLES
	 *
	 *     # List signup IDs.
	 *     $ wp signup list --field=signup_id
	 *     1
	 *
	 *     # List all signups.
	 *     $ wp signup list
	 *     +-----------+------------+---------------------+---------------------+--------+------------------+
	 *     | signup_id | user_login | user_email          | registered          | active | activation_key   |
	 *     +-----------+------------+---------------------+---------------------+--------+------------------+
	 *     | 1         | bobuser    | bobuser@example.com | 2024-03-13 05:46:53 | 1      | 7320b2f009266618 |
	 *     | 2         | johndoe    | johndoe@example.com | 2024-03-13 06:24:44 | 0      | 9068d859186cd0b5 |
	 *     +-----------+------------+---------------------+---------------------+--------+------------------+
	 *
	 * @subcommand list
	 *
	 * @package wp-cli
	 */
	public function list_( $args, $assoc_args ) {
		global $wpdb;

		if ( isset( $assoc_args['fields'] ) ) {
			$assoc_args['fields'] = explode( ',', $assoc_args['fields'] );
		} else {
			$assoc_args['fields'] = $this->obj_fields;
		}

		$signups = array();

		$results = $wpdb->get_results( "SELECT * FROM $wpdb->signups", ARRAY_A );

		if ( $results ) {
			foreach ( $results as $item ) {
				// Support features like --active=0.
				foreach ( array_keys( $item ) as $field ) {
					if ( isset( $assoc_args[ $field ] ) && $assoc_args[ $field ] !== $item[ $field ] ) {
						continue 2;
					}
				}

				$signups[] = $item;
			}
		}

		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$formatter = $this->get_formatter( $assoc_args );

		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', wp_list_pluck( $signups, 'signup_id' ) ) );
		} else {
			$formatter->display_items( $signups );
		}
	}

	/**
	 * Gets details about the signup.
	 *
	 * ## OPTIONS
	 *
	 * <signup>
	 * : Signup ID, user login, user email or activation key.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole signup, returns the value of a single field.
	 *
	 * [--fields=<fields>]
	 * : Get a specific subset of the signup's fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get signup.
	 *     $ wp signup get 1
	 *     +-----------+------------+---------------------+---------------------+--------+------------------+
	 *     | signup_id | user_login | user_email          | registered          | active | activation_key   |
	 *     +-----------+------------+---------------------+---------------------+--------+------------------+
	 *     | 1         | bobuser    | bobuser@example.com | 2024-03-13 05:46:53 | 1      | 663b5af63dd930fd |
	 *     +-----------+------------+---------------------+---------------------+--------+------------------+
	 *
	 * @package wp-cli
	 */
	public function get( $args, $assoc_args ) {
		$signup = $this->fetcher->get_check( $args[0] );

		$formatter = $this->get_formatter( $assoc_args );

		$formatter->display_items( array( $signup ) );
	}

	/**
	 * Activates a signup.
	 *
	 * ## OPTIONS
	 *
	 * <signup>
	 * : Signup ID, user login, user email or activation key.
	 *
	 * ## EXAMPLES
	 *
	 *     # Activate signup.
	 *     $ wp signup activate 2
	 *     Success: Signup activated. Password: bZFSGsfzb9xs
	 *
	 * @package wp-cli
	 */
	public function activate( $args, $assoc_args ) {
		$signup = $this->fetcher->get_check( $args[0] );

		if ( $signup ) {
			$result = wpmu_activate_signup( $signup->activation_key );
		}

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( 'Signup could not be activated. Reason: ' . $result->get_error_message() );
		} else {
			WP_CLI::success( "Signup activated. Password: {$result['password']}" );
		}
	}

	/**
	 * Deletes a signup.
	 *
	 * ## OPTIONS
	 *
	 * <signup>
	 * : Signup ID, user login, user email or activation key.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete signup.
	 *     $ wp signup delete 3
	 *     Success: Signup deleted.
	 *
	 * @package wp-cli
	 */
	public function delete( $args, $assoc_args ) {
		global $wpdb;

		$signup = $this->fetcher->get_check( $args[0] );

		$result = $wpdb->delete( $wpdb->signups, array( 'signup_id' => $signup->signup_id ), array( '%d' ) );

		if ( $result ) {
			WP_CLI::success( 'Signup deleted.' );
		} else {
			WP_CLI::error( 'Error occurred while deleting signup.' );
		}
	}
}
