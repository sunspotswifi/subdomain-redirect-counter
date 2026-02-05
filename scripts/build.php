<?php
/**
 * Build script for Subdomain Redirect Counter plugin.
 *
 * Creates a distribution-ready version of the plugin by copying
 * only the necessary files to a build directory.
 *
 * Usage: composer build
 *
 * @package Subdomain_Redirect_Counter
 */

// Ensure we're running from command line.
if ( PHP_SAPI !== 'cli' ) {
	die( 'This script must be run from the command line.' );
}

$root_dir  = dirname( __DIR__ );
$build_dir = $root_dir . '/build/subdomain-redirect-counter';

// Files and directories to include in the build.
$include = array(
	'admin',
	'includes',
	'languages',
	'subdomain-redirect-counter.php',
	'uninstall.php',
	'readme.txt',
	'LICENSE',
);

// Clean previous build.
if ( is_dir( $root_dir . '/build' ) ) {
	echo "Cleaning previous build...\n";
	delete_directory( $root_dir . '/build' );
}

// Create build directory.
echo "Creating build directory...\n";
mkdir( $build_dir, 0755, true );

// Copy files.
echo "Copying files...\n";
foreach ( $include as $item ) {
	$source = $root_dir . '/' . $item;

	if ( ! file_exists( $source ) ) {
		echo "  Skipping (not found): {$item}\n";
		continue;
	}

	$destination = $build_dir . '/' . $item;

	if ( is_dir( $source ) ) {
		copy_directory( $source, $destination );
		echo "  Copied directory: {$item}\n";
	} else {
		copy( $source, $destination );
		echo "  Copied file: {$item}\n";
	}
}

echo "\nBuild complete! Output: build/subdomain-redirect-counter\n";

/**
 * Recursively copy a directory.
 *
 * @param string $source      Source directory.
 * @param string $destination Destination directory.
 */
function copy_directory( string $source, string $destination ): void {
	if ( ! is_dir( $destination ) ) {
		mkdir( $destination, 0755, true );
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$target = $destination . '/' . $iterator->getSubPathname();

		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) ) {
				mkdir( $target, 0755, true );
			}
		} else {
			copy( $item->getPathname(), $target );
		}
	}
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory to delete.
 */
function delete_directory( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}

	rmdir( $dir );
}
