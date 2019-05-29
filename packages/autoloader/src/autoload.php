<?php
/**
 * This file `autoload_packages.php`was generated by automattic/jetpack-autoloader.
 *
 * From your plugin include this file with:
 * require_once . plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';
 *
 * @package Automattic\Jetpack\Autoloader
 */

if ( ! function_exists( 'jetpack_enqueue_package' ) ) {
	global $jetpack_packages;

	if ( ! is_array( $jetpack_packages ) ) {
		$jetpack_packages = array();
	}
	/**
	 * Adds the version of a package to the $jetpack_packages global array so that
	 * the autoloader is able to find it.
	 *
	 * @param string $class_name Name of the class that you want to autoload.
	 * @param string $version Version of the class.
	 * @param string $path Absolute path to the class so that we can load it.
	 */
	function jetpack_enqueue_package( $class_name, $version, $path ) {
		global $jetpack_packages;

		// Always favour the @dev version. Since that version is the same as bleeding edge.
		// We need to make sure that we don't do this in production!
		if ( ! isset( $jetpack_packages[ $class_name ] ) || '@dev' === $jetpack_packages[ $class_name ]['version'] ) {
			$jetpack_packages[ $class_name ] = array(
				'version' => $version,
				'path'    => $path,
			);
			return;
		}

		if ( ! isset( $jetpack_packages[ $class_name ] ) || version_compare( $jetpack_packages[ $class_name ]['version'], $version, '<' ) ) {
			$jetpack_packages[ $class_name ] = array(
				'version' => $version,
				'path'    => $path,
			);
		}
	}

	// Add the autoloader.
	spl_autoload_register(
		// phpcs:ignore PHPCompatibility.FunctionDeclarations.NewClosure.Found
		function ( $class_name ) {
			global $jetpack_packages;

			if ( isset( $jetpack_packages[ $class_name ] ) ) {
				/**
				 * A way to prevent loading of a package from a particular location or version.
				 *
				 * @since 7.4.0
				 *
				 * @param bool false whether to load a particular class package.
				 * @param string $class_name Name of a particular class to autoload.
				 * @param array $package Array containing the package path and version.
				 */
				if ( apply_filters( 'jetpack_autoload_package_block', false, $class_name, $jetpack_packages[ $class_name ] ) ) {
					return;
				}

				if ( function_exists( 'did_action' ) && ! did_action( 'plugins_loaded' ) ) {
					_doing_it_wrong(
						esc_html( $class_name ),
						sprintf(
							/* translators: %s Name of a PHP Class */
							esc_html__( 'Not all plugins have loaded yet but we requested the class %s', 'jetpack' ),
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$class_name
						),
						esc_html( $jetpack_packages[ $class_name ]['version'] )
					);
				}

				if ( file_exists( $jetpack_packages[ $class_name ]['path'] ) ) {
					require_once $jetpack_packages[ $class_name ]['path'];
				}
			}
		}
	);
}
