<?php

namespace WpPageTemplateLoader;
/**
 * Page Template loader.
 *
 * Originally based on functions in Easy Digital Downloads (thanks Pippin!).
 *
 * When using in a plugin, create a new class that extends this one and just overrides the properties.
 *
 * @package alexrah/page-template-loader
 * @author  VN Team
 */
class WpPageTemplateLoader {
	/**
	 * Prefix for filter names.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $filter_prefix;

	/**
	 * Directory name where custom templates for this plugin should be found in the theme.
	 *
	 * For example: 'your-plugin-templates'.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $theme_template_parts_directory;

	/**
	 * Reference to the root directory path of this plugin.
	 *
	 * Can either be a defined constant, or a relative reference from where the subclass lives.
	 *
	 * e.g. YOUR_PLUGIN_TEMPLATE or plugin_dir_path( dirname( __FILE__ ) ); etc.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $plugin_directory;

	/**
	 * Directory name where templates are found in this plugin.
	 *
	 * Can either be a defined constant, or a relative reference from where the subclass lives.
	 *
	 * e.g. 'templates' or 'includes/templates', etc.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	protected $plugin_template_parts_directory;


	protected $plugin_template_pages_path;

	/**
	 * Array of absolute paths pointing to template pages within the plugin
	 *
	 * */
	protected array $template_pages;

	/**
	 * Internal use only: Store located template paths.
	 *
	 * @var array
	 */
	private $template_path_cache = array();

	/**
	 * Internal use only: Store variable names used for template data.
	 *
	 * Means unset_template_data() can remove all custom references from $wp_query.
	 *
	 * Initialized to contain the default 'data'.
	 *
	 * @var array
	 */
	private $template_data_var_names = array( 'data' );


	public function __construct( $sPluginRootFile, $aArgs = [] ) {

		$aArgsDefault = [
			'filter_prefix'                   => '',
			'theme_template_directory'        => '',
			'plugin_template_pages_directory' => 'template-pages',
			'plugin_template_parts_directory' => 'template-parts'
		];


		$aFinalArgs = array_merge( $aArgsDefault, $aArgs );

		$this->filter_prefix                  = empty( $aFinalArgs['filter_prefix'] ) ? $sPluginRootFile : $aFinalArgs['filter_prefix'];
		$this->plugin_directory               = plugin_dir_path( $sPluginRootFile );
		$this->theme_template_parts_directory = $aFinalArgs['plugin_template_parts_directory'];
		$this->plugin_template_pages_path     = $this->plugin_directory . $aFinalArgs['plugin_template_pages_directory'];

		$this->set_template_pages();;

		// Add a filter to the wp 4.7 version attributes metabox
		add_filter( 'theme_page_templates', [ $this, 'add_template_pages' ] );


		// Add a filter to the save post to inject out template into the page cache
		add_filter( 'wp_insert_post_data', [ $this, 'register_project_templates' ] );


		// Add a filter to the template include to determine if the page has our
		// template assigned and return it's path
		add_filter( 'template_include', [ $this, 'view_project_template' ] );

	}


	/**
	 * Clean up template data.
	 *
	 * @since 1.2.0
	 */
	public function __destruct() {
		$this->unset_template_data();
	}


	protected function set_template_pages(): void {

		try {

			$oDirectory = new \RecursiveDirectoryIterator( $this->plugin_template_pages_path );
			$oIterator  = new \RecursiveIteratorIterator( $oDirectory );

			/** @var \SplFileInfo $oInfo */
			foreach ( $oIterator as $oInfo ) {
				if ( $oInfo->getFilename() == "." || $oInfo->getFilename() == ".." ) {
					continue;
				}

				if ( ! preg_match( '|Template Name:(.*)$|mi', file_get_contents( $oInfo->getPathName() ), $header ) ) {
					continue;
				}

				$this->template_pages[ $oInfo->getPathName() ] = _cleanup_header_comment( $header[1] );
			}

		} catch ( \Exception $exception ) {

			error_log( __FILE__ . ":" . $exception->getMessage() );

		}


	}

	/**
	 * Adds our template to the page dropdown for v4.7+
	 *
	 */
	public function add_template_pages( $posts_templates ): array {
		$posts_templates = array_merge( $posts_templates, $this->template_pages );

//		print_r($posts_templates);
//		die();

		return $posts_templates;
	}

	/**
	 * Adds our template to the pages cache in order to trick WordPress
	 * into thinking the template file exists where it doens't really exist.
	 */
	public function register_project_templates( $atts ) {

		// Create the key used for the themes cache
//		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );
		$cache_key = 'page_templates-' . md5( get_raw_theme_root( get_stylesheet() ) . '/' . get_stylesheet() );

		// Retrieve the cache list.
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = array();
		}

		// New cache, therefore remove the old one
		wp_cache_delete( $cache_key, 'themes' );

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge( $templates, $this->template_pages );

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;

	}


	/**
	 * Checks if the template is assigned to the page
	 */
	public function view_project_template( $template ) {

		// Get global post
		global $post;

		// Return template if post is empty
		if ( ! $post ) {
			return $template;
		}

		// Return default template if we don't have a custom one defined
		if ( ! isset( $this->template_pages[ get_post_meta(
				$post->ID, '_wp_page_template', true
			) ] ) ) {
			return $template;
		}

//		$file = plugin_dir_path( __FILE__ ). get_post_meta(
		$file = get_post_meta( $post->ID, '_wp_page_template', true );

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) ) {
			return $file;
		} else {
			echo $file;
		}

		// Return template
		return $template;

	}

	/**
	 * Retrieve a template part.
	 *
	 * @param string $slug Template slug.
	 * @param string $name Optional. Template variation name. Default null.
	 * @param bool $load Optional. Whether to load template. Default true.
	 *
	 * @return string
	 * @since 1.0.0
	 *
	 */
	public function get_template_part( $slug, $name = null, $load = true ) {
		// Execute code for this part.
		do_action( 'get_template_part_' . $slug, $slug, $name );
		do_action( $this->filter_prefix . '_get_template_part_' . $slug, $slug, $name );

		// Get files names of templates, for given slug and name.
		$templates = $this->get_template_file_names( $slug, $name );

		// Return the part that is found.
		return $this->locate_template_part( $templates, $load, false );
	}

	/**
	 * Make custom data available to template.
	 *
	 * Data is available to the template as properties under the `$data` variable.
	 * i.e. A value provided here under `$data['foo']` is available as `$data->foo`.
	 *
	 * When an input key has a hyphen, you can use `$data->{foo-bar}` in the template.
	 *
	 * @param mixed $data Custom data for the template.
	 * @param string $var_name Optional. Variable under which the custom data is available in the template.
	 *                         Default is 'data'.
	 *
	 * @return WpPageTemplateLoader
	 * @since 1.2.0
	 *
	 */
	public function set_template_data( $data, $var_name = 'data' ) {
		global $wp_query;

		$wp_query->query_vars[ $var_name ] = (object) $data;

		// Add $var_name to custom variable store if not default value.
		if ( 'data' !== $var_name ) {
			$this->template_data_var_names[] = $var_name;
		}

		return $this;
	}

	/**
	 * Remove access to custom data in template.
	 *
	 * Good to use once the final template part has been requested.
	 *
	 * @return WpPageTemplateLoader
	 * @since 1.2.0
	 *
	 */
	public function unset_template_data() {
		global $wp_query;

		// Remove any duplicates from the custom variable store.
		$custom_var_names = array_unique( $this->template_data_var_names );

		// Remove each custom data reference from $wp_query.
		foreach ( $custom_var_names as $var ) {
			if ( isset( $wp_query->query_vars[ $var ] ) ) {
				unset( $wp_query->query_vars[ $var ] );
			}
		}

		return $this;
	}

	/**
	 * Given a slug and optional name, create the file names of templates.
	 *
	 * @param string $slug Template slug.
	 * @param string $name Template variation name.
	 *
	 * @return array
	 * @since 1.0.0
	 *
	 */
	protected function get_template_file_names( $slug, $name ) {
		$templates = array();
		if ( isset( $name ) ) {
			$templates[] = $slug . '-' . $name . '.php';
		}
		$templates[] = $slug . '.php';

		/**
		 * Allow template choices to be filtered.
		 *
		 * The resulting array should be in the order of most specific first, to least specific last.
		 * e.g. 0 => recipe-instructions.php, 1 => recipe.php
		 *
		 * @param array $templates Names of template files that should be looked for, for given slug and name.
		 * @param string $slug Template slug.
		 * @param string $name Template variation name.
		 *
		 * @since 1.0.0
		 *
		 */
		return apply_filters( $this->filter_prefix . '_get_template_part', $templates, $slug, $name );
	}

	/**
	 * Retrieve the name of the highest priority template file that exists.
	 *
	 * Searches in the STYLESHEETPATH before TEMPLATEPATH so that themes which
	 * inherit from a parent theme can just overload one file. If the template is
	 * not found in either of those, it looks in the theme-compat folder last.
	 *
	 * @param string|array $template_names Template file(s) to search for, in order.
	 * @param bool $load If true the template file will be loaded if it is found.
	 * @param bool $require_once Whether to require_once or require. Default true.
	 *                                     Has no effect if $load is false.
	 *
	 * @return string The template filename if one is located.
	 * @since 1.0.0
	 *
	 */
	public function locate_template_part( $template_names, $load = false, $require_once = true ) {

		// Use $template_names as a cache key - either first element of array or the variable itself if it's a string.
		$cache_key = is_array( $template_names ) ? $template_names[0] : $template_names;

		// If the key is in the cache array, we've already located this file.
		if ( isset( $this->template_path_cache[ $cache_key ] ) ) {
			$located = $this->template_path_cache[ $cache_key ];
		} else {

			// No file found yet.
			$located = false;

			// Remove empty entries.
			$template_names = array_filter( (array) $template_names );
			$template_paths = $this->get_template_part_paths();

			// Try to find a template file.
			foreach ( $template_names as $template_name ) {
				// Trim off any slashes from the template name.
				$template_name = ltrim( $template_name, '/' );

				// Try locating this template file by looping through the template paths.
				foreach ( $template_paths as $template_path ) {

					if ( file_exists( $template_path . $template_name ) ) {
						$located = $template_path . $template_name;
						// Store the template path in the cache.
						$this->template_path_cache[ $cache_key ] = $located;
						break 2;
					}
				}
			}
		}

		if ( $load && $located ) {
			load_template( $located, $require_once );
		}

		return $located;
	}

	/**
	 * Return a list of paths to check for template locations.
	 *
	 * Default is to check in a child theme (if relevant) before a parent theme, so that themes which inherit from a
	 * parent theme can just overload one file. If the template is not found in either of those, it looks in the
	 * theme-compat folder last.
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 *
	 */
	protected function get_template_part_paths() {

		$file_paths = [
			100 => $this->get_template_parts_dir()
		];

		if ( ! empty( $this->theme_template_parts_directory ) ) {

			$theme_directory = trailingslashit( $this->theme_template_parts_directory );
			$file_paths[10]  = trailingslashit( get_template_directory() ) . $theme_directory;

			// Only add this conditionally, so non-child themes don't redundantly check active theme twice.
			if ( get_stylesheet_directory() !== get_template_directory() ) {
				$file_paths[1] = trailingslashit( get_stylesheet_directory() ) . $theme_directory;
			}

		}

		/**
		 * Allow ordered list of template paths to be amended.
		 *
		 * @param array $var Default is directory in child theme at index 1, parent theme at 10, and plugin at 100.
		 *
		 * @since 1.0.0
		 *
		 */
		$file_paths = apply_filters( $this->filter_prefix . '_template_paths', $file_paths );

		// Sort the file paths based on priority.
		ksort( $file_paths, SORT_NUMERIC );

		return array_map( 'trailingslashit', $file_paths );
	}

	/**
	 * /**
	 * Return the path to the templates directory in this plugin.
	 *
	 * May be overridden in subclass.
	 *
	 * @return string
	 * @since 1.0.0
	 *
	 */
	protected function get_template_parts_dir() {
		return trailingslashit( $this->plugin_directory ) . $this->plugin_template_parts_directory;
	}
}