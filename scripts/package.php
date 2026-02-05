<?php
/**
 * Package script for Subdomain Redirect Counter plugin.
 *
 * Creates a ZIP archive suitable for WordPress plugin installation.
 * Runs the build script first, then packages the result.
 *
 * Usage: composer package
 *
 * @package Subdomain_Redirect_Counter
 */

// Ensure we're running from command line.
if ( PHP_SAPI !== 'cli' ) {
	die( 'This script must be run from the command line.' );
}

$root_dir  = dirname( __DIR__ );
$build_dir = $root_dir . '/build/subdomain-redirect-counter';
$dist_dir  = $root_dir . '/dist';

// Run the build script first.
echo "Running build script...\n";
echo str_repeat( '-', 40 ) . "\n";
require __DIR__ . '/build.php';
echo str_repeat( '-', 40 ) . "\n\n";

// Check if build was successful.
if ( ! is_dir( $build_dir ) ) {
	die( "Error: Build directory not found. Build may have failed.\n" );
}

// Get version from main plugin file.
$plugin_file    = $build_dir . '/subdomain-redirect-counter.php';
$plugin_content = file_get_contents( $plugin_file );
preg_match( '/Version:\s*([^\s]+)/i', $plugin_content, $matches );
$version = $matches[1] ?? 'unknown';

// Create dist directory.
if ( ! is_dir( $dist_dir ) ) {
	mkdir( $dist_dir, 0755, true );
}

// Create ZIP file.
$zip_filename = "subdomain-redirect-counter-{$version}.zip";
$zip_path     = $dist_dir . '/' . $zip_filename;

echo "Creating ZIP archive: {$zip_filename}\n";

// Remove existing ZIP if present.
if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}

$zip = new ZipArchive();
if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
	die( "Error: Could not create ZIP file.\n" );
}

// Add files to ZIP.
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $build_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $iterator as $item ) {
	$relative_path = 'subdomain-redirect-counter/' . $iterator->getSubPathname();

	if ( $item->isDir() ) {
		$zip->addEmptyDir( $relative_path );
	} else {
		$zip->addFile( $item->getPathname(), $relative_path );
	}
}

$zip->close();

// Get file size.
$size = filesize( $zip_path );
$size_formatted = round( $size / 1024, 2 ) . ' KB';

echo "\nPackage created successfully!\n";
echo "  File: dist/{$zip_filename}\n";
echo "  Size: {$size_formatted}\n";
echo "  Version: {$version}\n";
