<?php
/**
 * Autoloader Generator.
 *
 * @package Automattic\Jetpack\Autoloader
 */

// phpcs:disable PHPCompatibility.Keywords.NewKeywords.t_useFound
// phpcs:disable PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ns_separatorFound
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewClosure.Found
// phpcs:disable PHPCompatibility.Keywords.NewKeywords.t_namespaceFound
// phpcs:disable PHPCompatibility.Keywords.NewKeywords.t_dirFound
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName
// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fopen
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.InterpolatedVariableNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

namespace Automattic\Jetpack\Autoloader;

use Composer\Autoload\AutoloadGenerator as BaseGenerator;
use Composer\Autoload\ClassMapGenerator;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

/**
 * Class AutoloadGenerator.
 */
class AutoloadGenerator extends BaseGenerator {

	/**
	 * Whether or not generated autoloader considers the class map authoritative.
	 *
	 * @var bool Whether or not generated autoloader considers the class map authoritative.
	 */
	private $classMapAuthoritative = false;

	/**
	 * Instantiate an AutoloadGenerator object.
	 *
	 * @param IOInterface $io IO object.
	 */
	public function __construct( $io ) {
		$this->io = $io;
	}

	/**
	 * Dump the autoloader.
	 *
	 * @param Config                       $config Config object.
	 * @param InstalledRepositoryInterface $localRepo Installed Reposetories object.
	 * @param PackageInterface             $mainPackage Main Package object.
	 * @param InstallationManager          $installationManager Manager for installing packages.
	 * @param string                       $targetDir Path to the current target directory.
	 * @param bool                         $scanPsr0Packages Whether to search for packages. Currently hard coded to always be false.
	 * @param string                       $suffix The autoloader suffix ignored since we want our autolaoder to only be included once.
	 */
	public function dump(
		Config $config,
		InstalledRepositoryInterface $localRepo,
		PackageInterface $mainPackage,
		InstallationManager $installationManager,
		$targetDir,
		$scanPsr0Packages = null, // Not used we always optimize.
		$suffix = null // Not used since we create our own autoloader.
	) {

		$filesystem = new Filesystem();
		$filesystem->ensureDirectoryExists( $config->get( 'vendor-dir' ) );

		$basePath   = $filesystem->normalizePath( realpath( getcwd() ) );
		$vendorPath = $filesystem->normalizePath( realpath( $config->get( 'vendor-dir' ) ) );
		$targetDir  = $vendorPath . '/' . $targetDir;
		$filesystem->ensureDirectoryExists( $targetDir );

		$vendorPathCode = $filesystem->findShortestPathCode( realpath( $targetDir ), $vendorPath, true );

		$appBaseDirCode = $filesystem->findShortestPathCode( $vendorPath, $basePath, true );
		$appBaseDirCode = str_replace( '__DIR__', '$vendorDir', $appBaseDirCode );

		$packageMap = $this->buildPackageMap( $installationManager, $mainPackage, $localRepo->getCanonicalPackages() );
		$autoloads  = $this->parseAutoloads( $packageMap, $mainPackage );

		$classMap = $this->getClassMapFromAutoloads( $autoloads, $filesystem, $vendorPath, $basePath );

		// Write out the autoload_classmap_package.php file.
		$classmapFile  = <<<EOF
<?php

// autoload_classmap_package.php @generated by automattic/autoload

\$vendorDir = $vendorPathCode;
\$baseDir = $appBaseDirCode;


EOF;
		$classmapFile .= 'return ' . $this->classMapToPHPArrayString( $classMap );
		file_put_contents( $targetDir . '/autoload_classmap_package.php', $classmapFile );

		// Copy over the autoload.php file into autoload_packages.php.
		copy( __DIR__ . '/autoload.php', $vendorPath . '/autoload_packages.php' );

	}

	/**
	 * Takes a classMap and returns the array string representation.
	 *
	 * @param array $classMap Map of all the package classes and paths and versions.
	 *
	 * @return string
	 */
	private function classMapToPHPArrayString( $classMap ) {
		$classmapString = ' array( ';
		ksort( $classMap );
		foreach ( $classMap as $class => $code ) {
			$classmapString .= '    ' . var_export( $class, true ) . ' => ' . $code;
		}
		$classmapString .= ");\n";
		return $classmapString;
	}

	/**
	 * This function differs from the composer parseAutoloadsType in that beside returning the path.
	 * It also return the path and the version of a package.
	 *
	 * @param array            $packageMap Map of all the packages.
	 * @param string           $type Type of autoloader to use, currently not used, since we only support psr-4.
	 * @param PackageInterface $mainPackage Instance of the Package Object.
	 *
	 * @return array
	 */
	protected function parseAutoloadsType( array $packageMap, $type, PackageInterface $mainPackage ) {
		$autoloads = array();
		foreach ( $packageMap as $item ) {
			list($package, $installPath) = $item;
			$autoload                    = $package->getAutoload();

			if ( $package === $mainPackage ) {
				$autoload = array_merge_recursive( $autoload, $package->getDevAutoload() );
			}

			// Skip packages that are not 'psr-4' since we only support them for now.
			if ( ! isset( $autoload['psr-4'] ) || ! is_array( $autoload['psr-4'] ) ) {
				continue;
			}

			if ( null !== $package->getTargetDir() && $package !== $mainPackage ) {
				$installPath = substr( $installPath, 0, -strlen( '/' . $package->getTargetDir() ) );
			}
			foreach ( $autoload['psr-4'] as $namespace => $paths ) {
				foreach ( (array) $paths as $path ) {
					$relativePath              = empty( $installPath ) ? ( empty( $path ) ? '.' : $path ) : $installPath . '/' . $path;
					$autoloads[ $namespace ][] = array(
						'path'    => $relativePath,
						'version' => $package->getVersion(), // Version of the class comes from the package - should we try to parse it?
					);
				}
			}
		}
		return $autoloads;
	}

	/**
	 * Take the autoloads array and return the classMap that contains the path and the version for each namespace.
	 *
	 * @param array      $autoloads Array of autoload settings defined defined by the packages.
	 * @param Filesystem $filesystem Filesystem class instance.
	 * @param string     $vendorPath Path to the vendor directory.
	 * @param string     $basePath Base Path.
	 *
	 * @return array $classMap
	 */
	private function getClassMapFromAutoloads( $autoloads, $filesystem, $vendorPath, $basePath ) {

		$classMap = array();

		$namespacesToScan = array();
		$blacklist        = null; // not supportered for now.

		// Scan the PSR-4 directories for class files, and add them to the class map.
		foreach ( $autoloads['psr-4'] as $namespace => $info ) {
			$version = array_reduce(
				array_map(
					function( $item ) {
						return $item['version']; },
					$info
				),
				function( $carry, $version ) {
					return version_compare( $version, $carry, '>' ) ? $version : $carry;
				},
				0
			);

			$namespacesToScan[ $namespace ][] = array(
				'paths'   => array_map(
					function( $item ) {
						return $item['path']; },
					$info
				),
				'version' => $version,
			);
		}

		krsort( $namespacesToScan );

		foreach ( $namespacesToScan as $namespace => $groups ) {

			foreach ( $groups as $group ) {

				foreach ( $group['paths'] as $dir ) {
					$dir = $filesystem->normalizePath( $filesystem->isAbsolutePath( $dir ) ? $dir : $basePath . '/' . $dir );

					if ( ! is_dir( $dir ) ) {
						continue;
					}

					$namespaceFilter = '' === $namespace ? null : $namespace;
					$classMap        = $this->addClassMapCode(
						$filesystem,
						$basePath,
						$vendorPath,
						$dir,
						$blacklist,
						$namespaceFilter,
						$group['version'],
						$classMap
					);
				}
			}
		}

		return $classMap;
	}

	/**
	 * Add a single class map resolution.
	 *
	 * @param Filesystem $filesystem Filesystem class instance.
	 * @param string     $basePath Base path.
	 * @param string     $vendorPath Path to the vendor diretory.
	 * @param string     $dir Direcotry path.
	 * @param null       $blacklist Blacklist of namespaces set to be ignored currently not used.
	 * @param null       $namespaceFilter Namespace being used.
	 * @param string     $version The version of the package.
	 * @param array      $classMap The current classMap.
	 *
	 * @return array
	 */
	private function addClassMapCode(
		$filesystem,
		$basePath,
		$vendorPath,
		$dir,
		$blacklist = null,
		$namespaceFilter = null,
		$version,

		array $classMap = array()
	) {

		foreach ( $this->generateClassMap( $dir, $blacklist, $namespaceFilter ) as $class => $path ) {
			$pathCode = "array( 'path' => " . $this->getPathCode( $filesystem, $basePath, $vendorPath, $path ) . ", 'version'=>'" . $version . "' ),\n";

			if ( ! isset( $classMap[ $class ] ) ) {
				$classMap[ $class ] = $pathCode;
			} elseif ( $this->io && $classMap[ $class ] !== $pathCode && ! preg_match(
				'{/(test|fixture|example|stub)s?/}i',
				strtr( $classMap[ $class ] . ' ' . $path, '\\', '/' )
			)
			) {
				$this->io->writeError(
					'<warning>Warning: Ambiguous class resolution, "' . $class . '"' .
					' was found in both "' . str_replace(
						array( '$vendorDir . \'', "',\n" ),
						array( $vendorPath, '' ),
						$classMap[ $class ]
					) . '" and "' . $path . '", the first will be used.</warning>'
				);
			}
		}

		return $classMap;
	}

	/**
	 * Trigger the class map generation.
	 *
	 * @param string $dir  Directory path.
	 * @param null   $blacklist Blacklist of namespaces set to be ignored currently not used.
	 * @param null   $namespaceFilter Namespace being used.
	 * @param bool   $showAmbiguousWarning Whether to show a warning in the console.
	 *
	 * @return array
	 */
	private function generateClassMap( $dir, $blacklist = null, $namespaceFilter = null, $showAmbiguousWarning = true ) {
		return ClassMapGenerator::createMap(
			$dir,
			$blacklist,
			$showAmbiguousWarning ? $this->io : null,
			$namespaceFilter
		);
	}

}
