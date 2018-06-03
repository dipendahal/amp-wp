<?php
/**
 * Class AMP_Core_Theme_Sanitizer.
 *
 * @package AMP
 * @since 1.0
 */

/**
 * Class AMP_Core_Theme_Sanitizer
 *
 * Fixes up common issues in core themes and others.
 *
 * @since 1.0
 */
class AMP_Core_Theme_Sanitizer extends AMP_Base_Sanitizer {

	/**
	 * Array of flags used to control sanitization.
	 *
	 * @var array {
	 *      @type string $stylesheet     Stylesheet slug.
	 *      @type string $template       Template slug.
	 *      @type array  $theme_features List of theme features that need to be applied. Features are method names,
	 * }
	 */
	protected $args;

	/**
	 * Body element.
	 *
	 * @var DOMElement
	 */
	protected $body;

	/**
	 * XPath.
	 *
	 * @var DOMXPath
	 */
	protected $xpath;

	/**
	 * Config for features needed by themes.
	 *
	 * @var array
	 */
	protected static $theme_features = array(
		'twentyseventeen' => array(
			'force_svg_support'                   => array(),
			'force_fixed_background_support'      => array(),
			'add_twentyseventeen_masthead_styles' => array(),
			'add_has_header_video_body_class'     => array(),
			'add_nav_menu_styles'                 => array(),
			'add_nav_menu_toggle'                 => array(),
			'add_nav_sub_menu_buttons'            => array(),
			// @todo Dequeue scripts and replace with AMP functionality where possible.
		),
		'twentyfifteen'   => array(
			'add_nav_menu_styles'      => array(),
			'add_nav_menu_toggle'      => array(),
			'add_nav_sub_menu_buttons' => array(),
		),
	);

	/**
	 * Find theme features for core theme.
	 *
	 * @param array $args   Args.
	 * @param bool  $static Static. that is, whether should run during output buffering.
	 * @return array Theme features.
	 */
	protected static function get_theme_features( $args, $static = false ) {
		$theme_features   = array();
		$theme_candidates = wp_array_slice_assoc( $args, array( 'stylesheet', 'template' ) );
		foreach ( $theme_candidates as $theme_candidate ) {
			if ( isset( self::$theme_features[ $theme_candidate ] ) ) {
				$theme_features = self::$theme_features[ $theme_candidate ];
				break;
			}
		}

		// Allow specific theme features to be requested even if the theme is not in core.
		if ( isset( $args['theme_features'] ) ) {
			$theme_features = array_merge( $args['theme_features'], $theme_features );
		}

		$final_theme_features = array();
		foreach ( $theme_features as $theme_feature => $feature_args ) {
			if ( ! method_exists( __CLASS__, $theme_feature ) ) {
				continue;
			}
			try {
				$reflection = new ReflectionMethod( __CLASS__, $theme_feature );
				if ( $reflection->isStatic() === $static ) {
					$final_theme_features[ $theme_feature ] = $feature_args;
				}
			} catch ( Exception $e ) {
				unset( $e );
			}
		}
		return $final_theme_features;
	}

	/**
	 * Add filters to manipulate output during output buffering before the DOM is constructed.
	 *
	 * @since 1.0
	 *
	 * @param array $args Args.
	 */
	public static function add_buffering_hooks( $args = array() ) {
		$theme_features = self::get_theme_features( $args, true );
		foreach ( $theme_features as $theme_feature => $feature_args ) {
			if ( method_exists( __CLASS__, $theme_feature ) ) {
				call_user_func( array( __CLASS__, $theme_feature ), $feature_args );
			}
		}
	}

	/**
	 * Fix up core themes to do things in the AMP way.
	 *
	 * @since 1.0
	 */
	public function sanitize() {
		$this->body = $this->dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $this->body ) {
			return;
		}

		$this->xpath = new DOMXPath( $this->dom );

		$theme_features = self::get_theme_features( $this->args, false );
		foreach ( $theme_features as $theme_feature => $feature_args ) {
			if ( method_exists( $this, $theme_feature ) ) {
				call_user_func( array( $this, $theme_feature ), $feature_args );
			}
		}
	}

	/**
	 * Get theme config.
	 *
	 * @param string $theme Theme slug.
	 * @return array Class names.
	 */
	protected static function get_theme_config( $theme ) {
		// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
		$config = array(
			'dropdown_class' => 'dropdown-toggle',
		);
		switch ( $theme ) {
			case 'twentyfifteen':
				return array_merge(
					$config,
					array(
						'nav_container_id'           => 'secondary',
						'nav_container_toggle_class' => 'toggled-on',
						'menu_button_class'          => 'secondary-toggle',
						'menu_button_query'          => '//header[ @id = "masthead" ]//button[ contains( @class, "secondary-toggle" ) ]',
						'menu_button_toggle_class'   => 'toggled-on',
						'sub_menu_toggle_class'      => 'toggle-on',
						'expand_text '               => __( 'expand child menu', 'twentyfifteen' ),
						'collapse_text'              => __( 'collapse child menu', 'twentyfifteen' ),
					)
				);

			case 'twentyseventeen':
			default:
				return array_merge(
					$config,
					array(
						'nav_container_id'           => 'site-navigation',
						'nav_container_toggle_class' => 'toggled-on',
						'menu_button_class'          => 'menu-toggle',
						'menu_button_query'          => '//nav[@id = "site-navigation"]//button[ contains( @class, "menu-toggle" ) ]',
						'menu_button_toggle_class'   => 'toggled-on',
						'sub_menu_toggle_class'      => 'toggled-on',
						'expand_text '               => __( 'expand child menu', 'twentyseventeen' ),
						'collapse_text'              => __( 'collapse child menu', 'twentyseventeen' ),
					)
				);
		}
		// phpcs:enable WordPress.WP.I18n.TextDomainMismatch
	}

	/**
	 * Force SVG support, replacing no-svg class name with svg class name.
	 *
	 * @link https://github.com/WordPress/wordpress-develop/blob/1af1f65a21a1a697fb5f33027497f9e5ae638453/src/wp-content/themes/twentyseventeen/assets/js/global.js#L211-L213
	 * @link https://caniuse.com/#feat=svg
	 */
	public function force_svg_support() {
		$this->dom->documentElement->setAttribute(
			'class',
			preg_replace(
				'/(^|\s)no-svg(\s|$)/',
				' svg ',
				$this->dom->documentElement->getAttribute( 'class' )
			)
		);
	}

	/**
	 * Force support for fixed background-attachment.
	 *
	 * @link https://github.com/WordPress/wordpress-develop/blob/1af1f65a21a1a697fb5f33027497f9e5ae638453/src/wp-content/themes/twentyseventeen/assets/js/global.js#L215-L217
	 * @link https://caniuse.com/#feat=background-attachment
	 */
	public function force_fixed_background_support() {
		$this->dom->documentElement->setAttribute(
			'class',
			$this->dom->documentElement->getAttribute( 'class' ) . ' background-fixed'
		);
	}

	/**
	 * Add body class when there is a header video.
	 *
	 * @link https://github.com/WordPress/wordpress-develop/blob/a26c24226c6b131a0ed22c722a836c100d3ba254/src/wp-content/themes/twentyseventeen/assets/js/global.js#L244-L247
	 *
	 * @param array $args Args.
	 */
	public static function add_has_header_video_body_class( $args = array() ) {
		$args = array_merge(
			array(
				'class_name' => 'has-header-video',
			),
			$args
		);

		add_filter( 'body_class', function( $body_classes ) use ( $args ) {
			if ( has_header_video() ) {
				$body_classes[] = $args['class_name'];
			}
			return $body_classes;
		} );
	}

	/**
	 * Add required styles for video and image headers.
	 *
	 * This is currently used exclusively for Twenty Seventeen.
	 *
	 * @link https://github.com/WordPress/wordpress-develop/blob/1af1f65a21a1a697fb5f33027497f9e5ae638453/src/wp-content/themes/twentyseventeen/style.css#L1687
	 * @link https://github.com/WordPress/wordpress-develop/blob/1af1f65a21a1a697fb5f33027497f9e5ae638453/src/wp-content/themes/twentyseventeen/style.css#L1743
	 */
	public static function add_twentyseventeen_masthead_styles() {
		/*
		 * The following is necessary because the styles in the theme apply to img and video,
		 * and the CSS parser will then convert the selectors to amp-img and amp-video respectively.
		 * Nevertheless, object-fit does not apply on amp-img and it needs to apply on an actual img.
		 */
		add_action( 'wp_enqueue_scripts', function() {
			ob_start();
			?>
			<style>
			.has-header-image .custom-header-media amp-img > img,
			.has-header-video .custom-header-media amp-video > video{
				position: fixed;
				height: auto;
				left: 50%;
				max-width: 1000%;
				min-height: 100%;
				min-width: 100%;
				min-width: 100vw; /* vw prevents 1px gap on left that 100% has */
				width: auto;
				top: 50%;
				padding-bottom: 1px; /* Prevent header from extending beyond the footer */
				-ms-transform: translateX(-50%) translateY(-50%);
				-moz-transform: translateX(-50%) translateY(-50%);
				-webkit-transform: translateX(-50%) translateY(-50%);
				transform: translateX(-50%) translateY(-50%);
			}
			.has-header-image:not(.twentyseventeen-front-page):not(.home) .custom-header-media amp-img > img {
				bottom: 0;
				position: absolute;
				top: auto;
				-ms-transform: translateX(-50%) translateY(0);
				-moz-transform: translateX(-50%) translateY(0);
				-webkit-transform: translateX(-50%) translateY(0);
				transform: translateX(-50%) translateY(0);
			}
			/* For browsers that support object-fit */
			@supports ( object-fit: cover ) {
				.has-header-image .custom-header-media amp-img > img,
				.has-header-video .custom-header-media amp-video > video,
				.has-header-image:not(.twentyseventeen-front-page):not(.home) .custom-header-media amp-img > img {
					height: 100%;
					left: 0;
					-o-object-fit: cover;
					object-fit: cover;
					top: 0;
					-ms-transform: none;
					-moz-transform: none;
					-webkit-transform: none;
					transform: none;
					width: 100%;
				}
			}
			</style>
			<?php
			$styles = str_replace( array( '<style>', '</style>' ), '', ob_get_clean() );
			wp_add_inline_style( get_template() . '-style', $styles );
		}, 11 );
	}

	/**
	 * Adjust header height.
	 *
	 * @todo Implement.
	 * @link https://github.com/WordPress/wordpress-develop/blob/a26c24226c6b131a0ed22c722a836c100d3ba254/src/wp-content/themes/twentyseventeen/assets/js/global.js#L88-L103
	 */
	public function adjust_header_height() {}

	/**
	 * Add styles for the nav menu specifically to deal with AMP running in a no-js context.
	 *
	 * @param array $args Args.
	 */
	public static function add_nav_menu_styles( $args = array() ) {
		$args = array_merge(
			self::get_theme_config( get_template() ),
			$args
		);

		add_action( 'wp_enqueue_scripts', function() use ( $args ) {
			ob_start();
			?>
			<style>

				/* Show the button*/
				.no-js .<?php echo esc_html( $args['menu_button_class'] ); ?> {
					display: block;
				}

				/* Override no-js selector in parent theme. */
				.no-js .main-navigation ul ul,
				.no-js .widget_nav_menu ul ul {
					display: none;
				}

				/* Use sibling selector and re-use class on button instead of toggling toggle-on class on ul.sub-menu */
				.main-navigation ul .<?php echo esc_html( $args['sub_menu_toggle_class'] ); ?> + .sub-menu,
				.widget_nav_menu ul .<?php echo esc_html( $args['sub_menu_toggle_class'] ); ?> + .sub-menu {
					display: block;
				}

				<?php if ( 'twentyseventeen' === get_template() ) : ?>
					.no-js <?php echo esc_html( '#' . $args['nav_container_id'] ); ?> > div > ul {
						display: none;
					}
					.no-js <?php echo esc_html( '#' . $args['nav_container_id'] ); ?>.<?php echo esc_html( $args['nav_container_toggle_class'] ); ?> > div > ul {
						display: block;
					}
					@media screen and (min-width: 48em) {
						.no-js .<?php echo esc_html( $args['menu_button_class'] ); ?>,
						.no-js .<?php echo esc_html( $args['dropdown_class'] ); ?> {
							display: none;
						}
						.no-js .main-navigation ul,
						.no-js .main-navigation ul ul,
						.no-js .main-navigation > div > ul {
							display: block;
						}
					}
				<?php elseif ( 'twentyfifteen' === get_template() ) : ?>
					.widget_nav_menu li {
						position: relative;
					}
					.widget_nav_menu li .dropdown-toggle {
						margin: 0;
						padding: 0;
					}

					@media screen and (min-width: 59.6875em) {
						/* Attempt to emulate https://github.com/WordPress/wordpress-develop/blob/5e9a39baa7d4368f7d3c36dcbcd53db6317677c9/src/wp-content/themes/twentyfifteen/js/functions.js#L108-L149 */
						#sidebar {
							position: sticky;
							top: -9vh;
							max-height: 109vh;
							overflow-y: auto;
						}
					}

				<?php endif; ?>
			</style>
			<?php
			$styles = str_replace( array( '<style>', '</style>' ), '', ob_get_clean() );
			wp_add_inline_style( get_template() . '-style', $styles );
		}, 11 );
	}

	/**
	 * Ensure that JS-only nav menu styles apply to AMP as well since even though scripts are not allowed, there are AMP-bind implementations.
	 *
	 * @param array $args Args.
	 */
	public function add_nav_menu_toggle( $args = array() ) {
		$args = array_merge(
			self::get_theme_config( get_template() ),
			$args
		);

		$nav_el = $this->dom->getElementById( $args['nav_container_id'] );
		if ( ! $nav_el ) {
			return;
		}

		$button_el = $this->xpath->query( $args['menu_button_query'] )->item( 0 );
		if ( ! $button_el ) {
			return;
		}

		$state_id = 'navMenuToggledOn';
		$expanded = false;

		// @todo Not twentyfifteen?
		$nav_el->setAttribute(
			AMP_DOM_Utils::get_amp_bind_placeholder_prefix() . 'class',
			sprintf(
				"%s + ( $state_id ? %s : '' )",
				wp_json_encode( $nav_el->getAttribute( 'class' ) ),
				wp_json_encode( ' ' . $args['nav_container_toggle_class'] )
			)
		);

		$state_el = $this->dom->createElement( 'amp-state' );
		$state_el->setAttribute( 'id', $state_id );
		$script_el = $this->dom->createElement( 'script' );
		$script_el->setAttribute( 'type', 'application/json' );
		$script_el->appendChild( $this->dom->createTextNode( wp_json_encode( $expanded ) ) );
		$state_el->appendChild( $script_el );
		$nav_el->parentNode->insertBefore( $state_el, $nav_el );

		$button_on = sprintf( "tap:AMP.setState({ $state_id: ! $state_id })" );
		$button_el->setAttribute( 'on', $button_on );
		$button_el->setAttribute( 'aria-expanded', 'false' );
		$button_el->setAttribute( AMP_DOM_Utils::get_amp_bind_placeholder_prefix() . 'aria-expanded', "$state_id ? 'true' : 'false'" );
		$button_el->setAttribute(
			AMP_DOM_Utils::get_amp_bind_placeholder_prefix() . 'class',
			sprintf( "%s + ( $state_id ? %s : '' )", wp_json_encode( $button_el->getAttribute( 'class' ) ), wp_json_encode( ' ' . $args['menu_button_toggle_class'] ) )
		);
	}

	/**
	 * Add buttons for nav sub-menu items.
	 *
	 * @link https://github.com/WordPress/wordpress-develop/blob/a26c24226c6b131a0ed22c722a836c100d3ba254/src/wp-content/themes/twentyseventeen/assets/js/navigation.js#L11-L43
	 *
	 * @param array $args Args.
	 */
	public static function add_nav_sub_menu_buttons( $args = array() ) {
		$default_args = self::get_theme_config( get_template() );
		switch ( get_template() ) {
			case 'twentyseventeen':
				if ( function_exists( 'twentyseventeen_get_svg' ) ) {
					$default_args['icon'] = twentyseventeen_get_svg( array(
						'icon'     => 'angle-down',
						'fallback' => true,
					) );
				}
				break;
		}
		$args = array_merge( $default_args, $args );

		/**
		 * Filter the HTML output of a nav menu item to add the AMP dropdown button to reveal the sub-menu.
		 *
		 * @see twentyfifteen_amp_setup_hooks()
		 *
		 * @param string $item_output Nav menu item HTML.
		 * @param object $item        Nav menu item.
		 * @return string Modified nav menu item HTML.
		 */
		add_filter( 'walker_nav_menu_start_el', function( $item_output, $item ) use ( $args ) {
			if ( ! in_array( 'menu-item-has-children', $item->classes, true ) ) {
				return $item_output;
			}
			static $nav_menu_item_number = 0;
			$nav_menu_item_number++;

			$expanded = in_array( 'current-menu-ancestor', $item->classes, true );

			$expanded_state_id = 'navMenuItemExpanded' . $nav_menu_item_number;

			// Create new state for managing storing the whether the sub-menu is expanded.
			$item_output .= sprintf(
				'<amp-state id="%s"><script type="application/json">%s</script></amp-state>',
				esc_attr( $expanded_state_id ),
				wp_json_encode( $expanded )
			);

			$dropdown_button  = '<button';
			$dropdown_button .= sprintf(
				' class="%s" [class]="%s"',
				esc_attr( $args['dropdown_class'] . ( $expanded ? ' ' . $args['sub_menu_toggle_class'] : '' ) ),
				esc_attr( sprintf( "%s + ( $expanded_state_id ? %s : '' )", wp_json_encode( $args['dropdown_class'] ), wp_json_encode( ' ' . $args['sub_menu_toggle_class'] ) ) )
			);
			$dropdown_button .= sprintf(
				' aria-expanded="%s" [aria-expanded]="%s"',
				esc_attr( wp_json_encode( $expanded ) ),
				esc_attr( "$expanded_state_id ? 'true' : 'false'" )
			);
			$dropdown_button .= sprintf(
				' on="%s"',
				esc_attr( "tap:AMP.setState( { $expanded_state_id: ! $expanded_state_id } )" )
			);
			$dropdown_button .= '>';

			if ( isset( $args['icon'] ) ) {
				$dropdown_button .= $args['icon'];
			}
			if ( isset( $args['expand_text'] ) && isset( $args['collapse_text'] ) ) {
				$dropdown_button .= sprintf(
					'<span class="screen-reader-text" [text]="%s">%s</span>',
					esc_attr( sprintf( "$expanded_state_id ? %s : %s", wp_json_encode( $args['collapse_text'] ), wp_json_encode( $args['expand_text'] ) ) ),
					esc_html( $expanded ? $args['collapse_text'] : $args['expand_text'] )
				);
			}

			$dropdown_button .= '</button>';

			$item_output .= $dropdown_button;
			return $item_output;
		}, 10, 2 );
	}
}
