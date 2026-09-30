<?php
/**
 * Registers the plugin abilities with the WordPress Abilities API (WordPress 6.9+).
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WIS_Abilities {

	const CATEGORY = 'social-feed';

	/**
	 * Capability required by the Feeds admin page (WIS_FeedsPage).
	 */
	const CAPABILITY = 'manage_options';

	const PER_PAGE = 20;

	const PREVIEW_DEFAULT = 5;

	const PREVIEW_MAX = 20;

	/**
	 * WIS_Abilities constructor.
	 */
	public function __construct() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register the ability category.
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category( self::CATEGORY, [
			'label'       => __( 'Social feeds', 'instagram-slider-widget' ),
			'description' => __( 'Manage Instagram, Facebook and YouTube feeds of Social Slider Feed.', 'instagram-slider-widget' ),
		] );
	}

	/**
	 * Register the abilities.
	 */
	public function register_abilities() {
		$sources = array_keys( $this->get_providers() );

		wp_register_ability( 'social-feed/list-feeds', [
			'label'               => __( 'List social feeds', 'instagram-slider-widget' ),
			'description'         => __( 'Lists the configured Instagram, Facebook and YouTube feeds and the saved account connections (names only). Pass an id to get one feed with its settings, its embed shortcode and a bounded preview of its items. The preview uses the plugin cache and may query the provider when the cache is expired.', 'instagram-slider-widget' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => [],
				'properties'           => [
					'id'            => [
						'type'        => 'integer',
						'description' => __( 'Feed id. Returns this feed in full.', 'instagram-slider-widget' ),
						'minimum'     => 1,
					],
					'source'        => [
						'type'        => 'string',
						'description' => __( 'Only list feeds of this network.', 'instagram-slider-widget' ),
						'enum'        => $sources,
					],
					'page'          => [
						'type'        => 'integer',
						'description' => __( 'Page of the list, 20 feeds per page.', 'instagram-slider-widget' ),
						'minimum'     => 1,
						'default'     => 1,
					],
					'preview_limit' => [
						'type'        => 'integer',
						'description' => __( 'Number of preview items returned with a single feed. 0 disables the preview.', 'instagram-slider-widget' ),
						'minimum'     => 0,
						'maximum'     => self::PREVIEW_MAX,
						'default'     => self::PREVIEW_DEFAULT,
					],
				],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'feeds'       => [
						'type'  => 'array',
						'items' => [ 'type' => 'object' ],
					],
					'feed'        => [ 'type' => 'object' ],
					'connections' => [
						'type'  => 'array',
						'items' => [ 'type' => 'object' ],
					],
					'total'       => [ 'type' => 'integer' ],
					'page'        => [ 'type' => 'integer' ],
					'total_pages' => [ 'type' => 'integer' ],
				],
			],
			'execute_callback'    => [ $this, 'list_feeds' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
			],
		] );

		wp_register_ability( 'social-feed/upsert-feed', [
			'label'               => __( 'Create or update a social feed', 'instagram-slider-widget' ),
			'description'         => __( 'Creates a feed, or updates the feed given by feed_id, from a saved account connection. Connections are referenced by the id returned by social-feed/list-feeds. Use dry_run to validate without saving.', 'instagram-slider-widget' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'feed_id'       => [
						'type'        => 'integer',
						'description' => __( 'Feed to update. Omit to create a feed.', 'instagram-slider-widget' ),
						'minimum'     => 1,
					],
					'source'        => [
						'type'        => 'string',
						'description' => __( 'Network of the feed. Required when creating.', 'instagram-slider-widget' ),
						'enum'        => $sources,
					],
					'connection_id' => [
						'type'        => 'string',
						'description' => __( 'Id of a saved account connection of that network.', 'instagram-slider-widget' ),
					],
					'source_type'   => [
						'type'        => 'string',
						'description' => __( 'Instagram only: what the feed shows.', 'instagram-slider-widget' ),
						'enum'        => [ 'account', 'account_business', 'username', 'hashtag' ],
					],
					'source_value'  => [
						'type'        => 'string',
						'description' => __( 'Instagram only: the username or hashtag for the username and hashtag source types.', 'instagram-slider-widget' ),
					],
					'title'         => [
						'type'        => 'string',
						'description' => __( 'Feed title.', 'instagram-slider-widget' ),
					],
					'layout'        => [
						'type'        => 'string',
						'description' => __( 'Feed template, e.g. slider, thumbs, masonry. Available layouts depend on the network.', 'instagram-slider-widget' ),
					],
					'filters'       => [
						'type'                 => 'object',
						'description'          => __( 'Comma separated content filters. Not every network supports every filter.', 'instagram-slider-widget' ),
						'properties'           => [
							'blocked_words' => [ 'type' => 'string' ],
							'allowed_words' => [ 'type' => 'string' ],
							'blocked_users' => [ 'type' => 'string' ],
						],
						'additionalProperties' => false,
					],
					'settings'      => [
						'type'        => 'object',
						'description' => __( 'Other feed settings by their stored name, e.g. images_number, columns, orderby, refresh_hour, images_link, custom_url. The setting names of a feed are returned by social-feed/list-feeds.', 'instagram-slider-widget' ),
					],
					'dry_run'       => [
						'type'        => 'boolean',
						'description' => __( 'Validate and return the resulting feed without saving.', 'instagram-slider-widget' ),
						'default'     => false,
					],
				],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'feed'    => [ 'type' => 'object' ],
					'created' => [ 'type' => 'boolean' ],
					'dry_run' => [ 'type' => 'boolean' ],
				],
			],
			'execute_callback'    => [ $this, 'upsert_feed' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
			],
		] );

		wp_register_ability( 'social-feed/refresh-feed', [
			'label'               => __( 'Refresh a social feed', 'instagram-slider-widget' ),
			'description'         => __( 'Bypasses the cache of a feed and loads its items again from the provider with the saved account connection.', 'instagram-slider-widget' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'feed_id' => [
						'type'        => 'integer',
						'description' => __( 'Feed to refresh.', 'instagram-slider-widget' ),
						'minimum'     => 1,
					],
					'limit'   => [
						'type'        => 'integer',
						'description' => __( 'Number of refreshed items returned as a preview.', 'instagram-slider-widget' ),
						'minimum'     => 0,
						'maximum'     => self::PREVIEW_MAX,
						'default'     => self::PREVIEW_DEFAULT,
					],
				],
				'required'             => [ 'feed_id' ],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'feed_id'    => [ 'type' => 'integer' ],
					'source'     => [ 'type' => 'string' ],
					'refreshed'  => [ 'type' => 'boolean' ],
					'item_count' => [ 'type' => 'integer' ],
					'items'      => [
						'type'  => 'array',
						'items' => [ 'type' => 'object' ],
					],
				],
			],
			'execute_callback'    => [ $this, 'refresh_feed' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
			],
		] );
	}

	/**
	 * Same capability as the Feeds admin page.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * Execute social-feed/list-feeds.
	 *
	 * @param array $input
	 *
	 * @return array|WP_Error
	 */
	public function list_feeds( $input = [] ) {
		$input  = is_array( $input ) ? $input : [];
		$source = isset( $input['source'] ) ? sanitize_key( $input['source'] ) : '';

		if ( $source && ! $this->get_provider( $source ) ) {
			return new WP_Error( 'wis_invalid_source', __( 'Unknown or unavailable feed network.', 'instagram-slider-widget' ) );
		}

		if ( ! empty( $input['id'] ) ) {
			$found = $this->find_feed( absint( $input['id'] ), $source );
			if ( is_wp_error( $found ) ) {
				return $found;
			}

			list( $feed_source, $feed ) = $found;

			$result  = $this->describe_feed( $feed, $feed_source, true );
			$preview = isset( $input['preview_limit'] ) ? min( self::PREVIEW_MAX, absint( $input['preview_limit'] ) ) : self::PREVIEW_DEFAULT;

			if ( $preview > 0 ) {
				$items = $this->query_items( $feed, $feed_source );
				if ( is_wp_error( $items ) ) {
					$result['preview'] = [
						'items' => [],
						'error' => $items->get_error_message(),
					];
				} else {
					$result['preview'] = [
						'items' => array_slice( $items, 0, $preview ),
						'total' => count( $items ),
					];
				}
			}

			return [ 'feed' => $result ];
		}

		$all = [];
		foreach ( $this->get_providers() as $name => $provider ) {
			if ( $source && $source !== $name ) {
				continue;
			}
			foreach ( $this->get_feeds( $name ) as $feed ) {
				$all[] = $this->describe_feed( $feed, $name, false );
			}
		}

		$page        = isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1;
		$total       = count( $all );
		$total_pages = (int) ceil( $total / self::PER_PAGE );

		return [
			'feeds'       => array_slice( $all, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ),
			'connections' => $this->get_connections( $source ),
			'total'       => $total,
			'page'        => $page,
			'total_pages' => $total_pages,
		];
	}

	/**
	 * Execute social-feed/upsert-feed.
	 *
	 * @param array $input
	 *
	 * @return array|WP_Error
	 */
	public function upsert_feed( $input = [] ) {
		$input   = is_array( $input ) ? $input : [];
		$feed_id = isset( $input['feed_id'] ) ? absint( $input['feed_id'] ) : 0;
		$source  = isset( $input['source'] ) ? sanitize_key( $input['source'] ) : '';
		$dry_run = ! empty( $input['dry_run'] );

		if ( $source && ! $this->get_provider( $source ) ) {
			return new WP_Error( 'wis_invalid_source', __( 'Unknown or unavailable feed network.', 'instagram-slider-widget' ) );
		}

		if ( $feed_id ) {
			$found = $this->find_feed( $feed_id, $source );
			if ( is_wp_error( $found ) ) {
				return $found;
			}
			list( $source, $existing ) = $found;
		} elseif ( ! $source ) {
			return new WP_Error( 'wis_missing_source', __( 'The source is required to create a feed.', 'instagram-slider-widget' ) );
		}

		$provider = $this->get_provider( $source );
		$class    = $provider['class'];
		$blank    = new $class();
		$defaults = $blank->instance;
		$data     = $defaults;

		if ( $feed_id ) {
			$data = array_merge( $defaults, array_intersect_key( (array) $existing->instance, $defaults ) );
		} else {
			// The add form always posts one of the available templates.
			$templates = array_keys( $this->get_templates( $blank, $source ) );
			foreach ( [ 'template', 'm_template' ] as $key ) {
				if ( $templates && isset( $data[ $key ] ) && ! in_array( $data[ $key ], $templates, true ) ) {
					$data[ $key ] = $templates[0];
				}
			}
		}

		$changes = [];

		if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
			$changes = $input['settings'];
		}

		if ( isset( $input['title'] ) ) {
			$changes['title'] = $input['title'];
		}

		if ( isset( $input['layout'] ) ) {
			$changes['template'] = $input['layout'];
		}

		if ( isset( $input['filters'] ) && is_array( $input['filters'] ) ) {
			foreach ( $input['filters'] as $key => $value ) {
				$changes[ $key ] = $value;
			}
		}

		if ( 'instagram' === $source ) {
			if ( isset( $input['source_type'] ) ) {
				$changes['search_for'] = $input['source_type'];
			}

			$search_for = isset( $changes['search_for'] ) ? sanitize_key( $changes['search_for'] ) : $data['search_for'];

			if ( isset( $input['connection_id'] ) ) {
				$connection = sanitize_text_field( $input['connection_id'] );
				if ( ! isset( $changes['search_for'] ) ) {
					// Same preference as the feed form: business accounts first.
					$search_for = $this->connection_exists( 'instagram', $connection, 'account_business' ) ? 'account_business' : 'account';

					$changes['search_for'] = $search_for;
				}
				if ( ! in_array( $search_for, [ 'account', 'account_business' ], true ) ) {
					return new WP_Error( 'wis_connection_not_applicable', __( 'A connection_id can only be used with the account and account_business source types.', 'instagram-slider-widget' ) );
				}
				$changes[ $search_for ] = $connection;
			}

			if ( isset( $input['source_value'] ) ) {
				if ( ! in_array( $search_for, [ 'username', 'hashtag' ], true ) ) {
					return new WP_Error( 'wis_source_value_not_applicable', __( 'A source_value can only be used with the username and hashtag source types.', 'instagram-slider-widget' ) );
				}
				$changes[ $search_for ] = ltrim( $input['source_value'], '@#' );
			}
		} else {
			if ( isset( $input['source_type'] ) || isset( $input['source_value'] ) ) {
				return new WP_Error( 'wis_source_type_not_applicable', __( 'source_type and source_value are only available for Instagram feeds.', 'instagram-slider-widget' ) );
			}
			if ( isset( $input['connection_id'] ) ) {
				$changes[ $provider['connection_key'] ] = sanitize_text_field( $input['connection_id'] );
			}
		}

		$invalid = [];
		foreach ( $changes as $key => $value ) {
			$key = is_string( $key ) ? $key : '';
			if ( 'id' === $key || ! array_key_exists( $key, $defaults ) ) {
				$invalid[ $key ] = __( 'Unknown setting for this network.', 'instagram-slider-widget' );
				continue;
			}

			$clean = $this->sanitize_setting( $key, $value, $defaults[ $key ], $blank, $source );
			if ( is_wp_error( $clean ) ) {
				$invalid[ $key ] = $clean->get_error_message();
				continue;
			}

			$data[ $key ] = $clean;
		}

		if ( $invalid ) {
			return new WP_Error( 'wis_invalid_settings', __( 'Some settings are not valid.', 'instagram-slider-widget' ), [
				'status'   => 400,
				'settings' => $invalid,
			] );
		}

		$valid = $this->validate_connection( $data, $source );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$data['id'] = $feed_id ? $feed_id : null;
		$feed       = new $class( $data );

		if ( ! $dry_run ) {
			$feeds = new WIS_Feeds( $source );
			if ( $feed_id ) {
				$feeds->update_feed( $feed_id, $feed );
			} else {
				$feed_id = $feeds->add_feed( $feed );
			}
		}

		return [
			'feed'    => $this->describe_feed( $feed, $source, true ),
			'created' => ! isset( $existing ),
			'dry_run' => $dry_run,
		];
	}

	/**
	 * Execute social-feed/refresh-feed.
	 *
	 * @param array $input
	 *
	 * @return array|WP_Error
	 */
	public function refresh_feed( $input = [] ) {
		$input   = is_array( $input ) ? $input : [];
		$feed_id = isset( $input['feed_id'] ) ? absint( $input['feed_id'] ) : 0;

		if ( ! $feed_id ) {
			return new WP_Error( 'wis_missing_feed_id', __( 'The feed_id is required.', 'instagram-slider-widget' ) );
		}

		$found = $this->find_feed( $feed_id );
		if ( is_wp_error( $found ) ) {
			return $found;
		}

		list( $source, $feed ) = $found;

		$feed->force_refresh = true;

		$items = $this->query_items( $feed, $source );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$limit = isset( $input['limit'] ) ? min( self::PREVIEW_MAX, absint( $input['limit'] ) ) : self::PREVIEW_DEFAULT;

		return [
			'feed_id'    => $feed_id,
			'source'     => $source,
			'refreshed'  => true,
			'item_count' => count( $items ),
			'items'      => array_slice( $items, 0, $limit ),
		];
	}

	/**
	 * Installed feed providers.
	 *
	 * @return array
	 */
	private function get_providers() {
		$providers = [
			'instagram' => [
				'class'          => 'WIS_Instagram_Feed',
				'shortcode'      => 'jr_instagram',
				'connection_key' => 'account',
			],
			'facebook'  => [
				'class'          => 'WIS_Facebook_Feed',
				'shortcode'      => 'cm_facebook_feed',
				'connection_key' => 'account',
			],
			'youtube'   => [
				'class'          => 'WIS_Youtube_Feed',
				'shortcode'      => 'cm_youtube_feed',
				'connection_key' => 'search',
			],
		];

		foreach ( $providers as $name => $provider ) {
			if ( ! class_exists( $provider['class'] ) ) {
				unset( $providers[ $name ] );
			}
		}

		return $providers;
	}

	/**
	 * @param string $source
	 *
	 * @return array|null
	 */
	private function get_provider( $source ) {
		$providers = $this->get_providers();

		return isset( $providers[ $source ] ) ? $providers[ $source ] : null;
	}

	/**
	 * Saved feeds of a network.
	 *
	 * @param string $source
	 *
	 * @return WIS_Feed[]
	 */
	private function get_feeds( $source ) {
		$feeds = ( new WIS_Feeds( $source ) )->feeds;
		if ( ! is_array( $feeds ) ) {
			return [];
		}

		return array_filter( $feeds, function ( $feed ) {
			return $feed instanceof WIS_Feed;
		} );
	}

	/**
	 * Feed ids are unique across networks.
	 *
	 * @param int $feed_id
	 * @param string $source
	 *
	 * @return array|WP_Error Network name and feed.
	 */
	private function find_feed( $feed_id, $source = '' ) {
		foreach ( $this->get_providers() as $name => $provider ) {
			if ( $source && $source !== $name ) {
				continue;
			}
			$feeds = $this->get_feeds( $name );
			if ( isset( $feeds[ $feed_id ] ) ) {
				return [ $name, $feeds[ $feed_id ] ];
			}
		}

		return new WP_Error( 'wis_feed_not_found', __( 'Feed not found.', 'instagram-slider-widget' ), [ 'status' => 404 ] );
	}

	/**
	 * Templates of a feed. The premium templates are only hooked in wp-admin.
	 *
	 * @param WIS_Feed $feed
	 * @param string $source
	 *
	 * @return array
	 */
	private function get_templates( $feed, $source ) {
		$templates = (array) $feed->getTemplates();

		if ( 'instagram' === $source && class_exists( 'WIS_Instagram_Pro' ) && WIS_Instagram_Pro::instance() ) {
			$templates = WIS_Instagram_Pro::instance()->sliders( $templates );
		}

		return $templates;
	}

	/**
	 * "Link to" options of a feed. The premium options are only hooked in wp-admin.
	 *
	 * @param WIS_Feed $feed
	 * @param string $source
	 *
	 * @return array
	 */
	private function get_link_options( $feed, $source ) {
		$options = (array) $feed->getLinkto();

		if ( 'instagram' === $source && class_exists( 'WIS_Instagram_Pro' ) && WIS_Instagram_Pro::instance() ) {
			$options = WIS_Instagram_Pro::instance()->link( $options );
		}

		if ( 'youtube' === $source && class_exists( 'WIS_Youtube_Pro' ) && WIS_Youtube_Pro::instance() ) {
			$options = WIS_Youtube_Pro::instance()->youtube_links( $options );
		}

		return $options;
	}

	/**
	 * Saved account options of a network, keyed by connection type.
	 *
	 * @param string $source
	 *
	 * @return array
	 */
	private function get_accounts( $source ) {
		$options = [];

		if ( 'instagram' === $source ) {
			$options = [
				'account_business' => WIG_BUSINESS_PROFILES_OPTION,
				'account'          => WIG_PROFILES_OPTION,
			];
		} elseif ( 'facebook' === $source ) {
			$options = [ 'account' => WIS_FACEBOOK_ACCOUNT_PROFILES_OPTION_NAME ];
		} elseif ( 'youtube' === $source ) {
			$options = [ 'account' => WYT_ACCOUNT_OPTION_NAME ];
		}

		$accounts = [];
		foreach ( $options as $type => $option ) {
			$saved             = WIS_Plugin::app()->getPopulateOption( $option, [] );
			$accounts[ $type ] = is_array( $saved ) ? $saved : [];
		}

		return $accounts;
	}

	/**
	 * Saved connections without credentials.
	 *
	 * @param string $source
	 *
	 * @return array
	 */
	private function get_connections( $source = '' ) {
		$connections = [];

		foreach ( $this->get_providers() as $name => $provider ) {
			if ( $source && $source !== $name ) {
				continue;
			}
			foreach ( $this->get_accounts( $name ) as $type => $accounts ) {
				foreach ( $accounts as $id => $account ) {
					$label = (string) $id;
					if ( 'youtube' === $name && is_object( $account ) && isset( $account->snippet->title ) ) {
						$label = (string) $account->snippet->title;
					}

					$connections[] = [
						'id'     => (string) $id,
						'source' => $name,
						'type'   => $type,
						'label'  => sanitize_text_field( $label ),
					];
				}
			}
		}

		return $connections;
	}

	/**
	 * @param string $source
	 * @param string $connection_id
	 * @param string $type
	 *
	 * @return bool
	 */
	private function connection_exists( $source, $connection_id, $type = 'account' ) {
		$accounts = $this->get_accounts( $source );

		return '' !== (string) $connection_id && isset( $accounts[ $type ][ $connection_id ] );
	}

	/**
	 * The connection a feed uses.
	 *
	 * @param array $instance
	 * @param string $source
	 *
	 * @return array Connection type and id.
	 */
	private function get_feed_connection( $instance, $source ) {
		if ( 'instagram' === $source ) {
			$type = isset( $instance['search_for'] ) ? $instance['search_for'] : '';
			if ( ! in_array( $type, [ 'account', 'account_business' ], true ) ) {
				return [ '', '' ];
			}

			return [ $type, isset( $instance[ $type ] ) ? (string) $instance[ $type ] : '' ];
		}

		$provider = $this->get_provider( $source );
		$key      = $provider['connection_key'];

		return [ 'account', isset( $instance[ $key ] ) ? (string) $instance[ $key ] : '' ];
	}

	/**
	 * Check that the feed points to a saved connection, or has a username/hashtag.
	 *
	 * @param array $instance
	 * @param string $source
	 *
	 * @return true|WP_Error
	 */
	private function validate_connection( $instance, $source ) {
		if ( 'instagram' === $source && in_array( $instance['search_for'], [ 'username', 'hashtag' ], true ) ) {
			if ( '' === trim( (string) $instance[ $instance['search_for'] ] ) ) {
				return new WP_Error( 'wis_missing_source_value', __( 'A source_value is required for the username and hashtag source types.', 'instagram-slider-widget' ) );
			}

			return true;
		}

		list( $type, $connection_id ) = $this->get_feed_connection( $instance, $source );

		if ( '' === $connection_id ) {
			return new WP_Error( 'wis_missing_connection', __( 'A connection_id is required.', 'instagram-slider-widget' ) );
		}

		if ( ! $this->connection_exists( $source, $connection_id, $type ) ) {
			return new WP_Error( 'wis_connection_not_found', __( 'The account connection was not found. Connect the account in the plugin settings first.', 'instagram-slider-widget' ), [ 'status' => 404 ] );
		}

		return true;
	}

	/**
	 * Sanitize a feed setting by the type of its default value.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param mixed $default
	 * @param WIS_Feed $feed
	 * @param string $source
	 *
	 * @return mixed|WP_Error
	 */
	private function sanitize_setting( $key, $value, $default, $feed, $source ) {
		$name = 0 === strpos( $key, 'm_' ) ? substr( $key, 2 ) : $key;

		if ( is_array( $default ) ) {
			$value = is_array( $value ) ? $value : explode( ',', (string) $value );

			return array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
		}

		if ( ! is_scalar( $value ) ) {
			return new WP_Error( 'wis_invalid_setting', __( 'The value must be a string, number or boolean.', 'instagram-slider-widget' ) );
		}

		if ( is_bool( $value ) ) {
			$value = $value ? 1 : 0;
		}

		$enums = [
			'template'   => array_keys( $this->get_templates( $feed, $source ) ),
			'orderby'    => [ 'rand', 'date-ASC', 'date-DESC', 'popular-ASC', 'popular-DESC' ],
			'search_for' => [ 'account', 'account_business', 'username', 'hashtag' ],
			'controls'   => [ 'prev_next', 'numberless', 'none' ],
			'animation'  => [ 'slide', 'fade' ],
		];

		foreach ( [ 'images_link', 'fbimages_link', 'yimages_link' ] as $link_key ) {
			$enums[ $link_key ] = array_merge( array_keys( $this->get_link_options( $feed, $source ) ), [ (string) $default ] );
		}

		if ( isset( $enums[ $name ] ) ) {
			$value = (string) $value;
			if ( ! in_array( $value, $enums[ $name ], true ) ) {
				/* translators: %s: comma separated list of allowed values. */
				return new WP_Error( 'wis_invalid_setting', sprintf( __( 'Allowed values: %s.', 'instagram-slider-widget' ), implode( ', ', array_unique( $enums[ $name ] ) ) ) );
			}

			return $value;
		}

		if ( 'custom_url' === $name ) {
			return esc_url_raw( (string) $value );
		}

		if ( 'shopifeed_color' === $name ) {
			$color = sanitize_hex_color( (string) $value );

			return $color ? $color : new WP_Error( 'wis_invalid_setting', __( 'The value must be a hex color.', 'instagram-slider-widget' ) );
		}

		if ( is_int( $default ) ) {
			if ( ! is_numeric( $value ) || $value < 0 ) {
				return new WP_Error( 'wis_invalid_setting', __( 'The value must be a positive number.', 'instagram-slider-widget' ) );
			}

			return absint( $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Feed data for a response. Feeds never hold credentials, only the connection name.
	 *
	 * @param WIS_Feed $feed
	 * @param string $source
	 * @param bool $full
	 *
	 * @return array
	 */
	private function describe_feed( $feed, $source, $full ) {
		$provider = $this->get_provider( $source );
		$instance = (array) $feed->instance;
		$feed_id  = isset( $instance['id'] ) ? absint( $instance['id'] ) : 0;

		list( $type, $connection_id ) = $this->get_feed_connection( $instance, $source );

		$result = [
			'id'            => $feed_id,
			'source'        => $source,
			'title'         => isset( $instance['title'] ) ? sanitize_text_field( (string) $instance['title'] ) : '',
			'layout'        => isset( $instance['template'] ) ? sanitize_text_field( (string) $instance['template'] ) : '',
			'connection_id' => $connection_id,
			'shortcode'     => $feed_id ? sprintf( '[%s id="%d"]', $provider['shortcode'], $feed_id ) : '',
		];

		if ( 'instagram' === $source ) {
			$result['source_type'] = isset( $instance['search_for'] ) ? sanitize_key( $instance['search_for'] ) : '';
			if ( in_array( $result['source_type'], [ 'username', 'hashtag' ], true ) ) {
				$result['source_value'] = sanitize_text_field( (string) $instance[ $result['source_type'] ] );
			}
		}

		if ( ! $full ) {
			return $result;
		}

		$class    = $provider['class'];
		$blank    = new $class();
		$settings = array_intersect_key( $instance, $blank->instance );
		unset( $settings['id'] );

		$result['connection_found']  = '' !== $connection_id && $this->connection_exists( $source, $connection_id, $type );
		$result['settings']          = $settings;
		$result['available_layouts'] = array_keys( $this->get_templates( $feed, $source ) );
		$result['embed']             = [
			'type'      => 'shortcode',
			'tag'       => $provider['shortcode'],
			'shortcode' => $result['shortcode'],
		];

		return $result;
	}

	/**
	 * Load the items of a feed through the provider query of the plugin.
	 *
	 * @param WIS_Feed $feed
	 * @param string $source
	 *
	 * @return array|WP_Error Normalized items.
	 */
	private function query_items( $feed, $source ) {
		$instance = (array) $feed->instance;
		$valid    = $this->validate_connection( $instance, $source );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		try {
			if ( 'instagram' === $source ) {
				$data = $this->query_instagram( $feed );
			} elseif ( 'facebook' === $source ) {
				$data = $this->query_facebook( $feed );
			} else {
				$data = $this->query_youtube( $feed );
			}
		} catch ( \Throwable $e ) {
			return new WP_Error( 'wis_provider_error', __( 'The provider request failed. Check the account connection in the plugin settings.', 'instagram-slider-widget' ) );
		}

		if ( is_string( $data ) && '' !== $data ) {
			return new WP_Error( 'wis_provider_error', sanitize_text_field( $data ) );
		}

		if ( is_array( $data ) && isset( $data['error'] ) ) {
			return new WP_Error( 'wis_provider_error', sanitize_text_field( (string) $data['error'] ) );
		}

		if ( ! is_array( $data ) ) {
			return [];
		}

		unset( $data['stories'] );

		$items = [];
		foreach ( $data as $item ) {
			$item = $this->normalize_item( $item, $source );
			if ( $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Same query as WIS_Instagram_Feed::display_images().
	 *
	 * @param WIS_Instagram_Feed $feed
	 *
	 * @return array|string
	 */
	private function query_instagram( $feed ) {
		$type = $feed->search_for;
		if ( ! in_array( $type, [ 'hashtag', 'account', 'account_business' ], true ) ) {
			$type = 'username';
		}

		$search_for = [
			$type           => $feed->$type,
			'blocked_users' => $feed->blocked_users,
			'blocked_words' => $feed->blocked_words,
			'allowed_words' => $feed->allowed_words,
		];

		return $feed->feed_query( $search_for, $feed->refresh_hour, $feed->images_number );
	}

	/**
	 * Same query as WIS_Facebook_Feed::display_images().
	 *
	 * @param WIS_Facebook_Feed $feed
	 *
	 * @return array|string|null
	 */
	private function query_facebook( $feed ) {
		$accounts = $this->get_accounts( 'facebook' );
		$account  = ( new \WIS\Facebook\Includes\Api\FacebookAccount() )->fromArray( $accounts['account'][ $feed->account ] );

		return $feed->feed_query( $account, $feed->refresh_hour, $feed->images_number * 2 );
	}

	/**
	 * Same query as WIS_Youtube_Feed::display_videos().
	 *
	 * @param WIS_Youtube_Feed $feed
	 *
	 * @return array|string
	 */
	private function query_youtube( $feed ) {
		$args     = (array) $feed->instance;
		$accounts = $feed->get_youtube_feeds( $args['search'] );

		$args['account'] = $accounts[ $args['search'] ];

		return $feed->feed_query( $args );
	}

	/**
	 * @param mixed $item
	 * @param string $source
	 *
	 * @return array|null
	 */
	private function normalize_item( $item, $source ) {
		if ( 'instagram' === $source ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				return null;
			}

			return [
				'id'        => (string) $item['id'],
				'type'      => isset( $item['type'] ) ? (string) $item['type'] : '',
				'link'      => isset( $item['link'] ) ? esc_url_raw( $item['link'] ) : '',
				'image'     => isset( $item['image'] ) ? esc_url_raw( $item['image'] ) : '',
				'caption'   => isset( $item['caption'] ) ? wp_trim_words( (string) $item['caption'], 30 ) : '',
				'timestamp' => isset( $item['timestamp'] ) ? (int) $item['timestamp'] : 0,
			];
		}

		if ( ! is_object( $item ) ) {
			return null;
		}

		if ( 'facebook' === $source ) {
			if ( empty( $item->id ) ) {
				return null;
			}

			return [
				'id'        => (string) $item->id,
				'type'      => 'post',
				'link'      => esc_url_raw( 'https://facebook.com/' . $item->id ),
				'image'     => isset( $item->full_picture ) ? esc_url_raw( (string) $item->full_picture ) : '',
				'caption'   => isset( $item->message ) ? wp_trim_words( (string) $item->message, 30 ) : '',
				'timestamp' => isset( $item->created_time ) ? (int) strtotime( (string) $item->created_time ) : 0,
			];
		}

		if ( empty( $item->id->videoId ) ) {
			return null;
		}

		return [
			'id'        => (string) $item->id->videoId,
			'type'      => 'video',
			'link'      => esc_url_raw( 'https://www.youtube.com/watch?v=' . $item->id->videoId ),
			'image'     => isset( $item->snippet->thumbnails->medium->url ) ? esc_url_raw( (string) $item->snippet->thumbnails->medium->url ) : '',
			'caption'   => isset( $item->snippet->title ) ? sanitize_text_field( (string) $item->snippet->title ) : '',
			'timestamp' => isset( $item->snippet->publishedAt ) ? (int) strtotime( (string) $item->snippet->publishedAt ) : 0,
		];
	}
}

new WIS_Abilities();
