<?php
/**
 * Unit tests for SRC_Sunrise_Installer.
 *
 * Covers the pure string-transform functions (stub generation and detection,
 * wp-config.php define insertion/removal) that the automatic sunrise
 * installation relies on. Filesystem-touching methods are covered by
 * integration/manual testing.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Unit
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Sunrise_Installer
 */

use WP_Mock\Tools\TestCase;

/**
 * Class SunriseInstallerTest
 *
 * @group unit
 * @group sunrise
 */
class SunriseInstallerTest extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		if ( ! class_exists( 'SRC_Sunrise_Installer' ) ) {
			require_once SRC_PLUGIN_DIR . 'includes/class-src-sunrise-installer.php';
		}
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Test the generated stub is valid PHP, carries the marker, and self-guards.
	 *
	 * @covers SRC_Sunrise_Installer::build_stub_contents
	 * @return void
	 */
	public function test_stub_contents_are_valid_and_marked(): void {
		$stub = SRC_Sunrise_Installer::build_stub_contents();

		$this->assertStringStartsWith( '<?php', $stub );
		$this->assertStringContainsString( SRC_Sunrise_Installer::STUB_MARKER . ' v' . SRC_Sunrise_Installer::STUB_VERSION, $stub );
		$this->assertStringContainsString( "defined( 'ABSPATH' )", $stub );
		$this->assertStringContainsString( 'file_exists', $stub );
		$this->assertStringContainsString( '/subdomain-redirect-counter/sunrise.php', $stub );
	}

	/**
	 * Test stub recognition round-trips.
	 *
	 * @covers SRC_Sunrise_Installer::is_our_stub
	 * @covers SRC_Sunrise_Installer::get_stub_version
	 * @return void
	 */
	public function test_stub_recognition(): void {
		$stub = SRC_Sunrise_Installer::build_stub_contents();

		$this->assertTrue( SRC_Sunrise_Installer::is_our_stub( $stub ) );
		$this->assertSame( SRC_Sunrise_Installer::STUB_VERSION, SRC_Sunrise_Installer::get_stub_version( $stub ) );

		$foreign = "<?php\n// Some other domain mapping plugin.\n";
		$this->assertFalse( SRC_Sunrise_Installer::is_our_stub( $foreign ) );
		$this->assertSame( 0, SRC_Sunrise_Installer::get_stub_version( $foreign ) );
	}

	/**
	 * Test the SUNRISE define is inserted after the opening PHP tag.
	 *
	 * @covers SRC_Sunrise_Installer::insert_sunrise_define
	 * @return void
	 */
	public function test_insert_sunrise_define(): void {
		$config = "<?php\ndefine( 'DB_NAME', 'wp' );\nrequire_once ABSPATH . 'wp-settings.php';\n";

		$modified = SRC_Sunrise_Installer::insert_sunrise_define( $config );

		$this->assertNotNull( $modified );
		$this->assertStringContainsString( "define( 'SUNRISE', true );", $modified );
		$this->assertStringContainsString( SRC_Sunrise_Installer::CONFIG_COMMENT, $modified );

		// The define must appear before wp-settings.php is loaded.
		$this->assertLessThan(
			strpos( $modified, 'wp-settings.php' ),
			strpos( $modified, "define( 'SUNRISE'" )
		);
	}

	/**
	 * Test insertion is a no-op when SUNRISE is already defined (any style).
	 *
	 * @covers SRC_Sunrise_Installer::insert_sunrise_define
	 * @return void
	 */
	public function test_insert_sunrise_define_already_defined(): void {
		$variants = array(
			"<?php\ndefine( 'SUNRISE', true );\n",
			"<?php\ndefine('SUNRISE', 'on');\n",
			"<?php\ndefine  (  \"SUNRISE\"  ,  true  );\n",
		);

		foreach ( $variants as $config ) {
			$this->assertSame( $config, SRC_Sunrise_Installer::insert_sunrise_define( $config ) );
		}
	}

	/**
	 * Test insertion bails safely when there is no usable opening tag.
	 *
	 * @covers SRC_Sunrise_Installer::insert_sunrise_define
	 * @return void
	 */
	public function test_insert_sunrise_define_no_insertion_point(): void {
		$this->assertNull( SRC_Sunrise_Installer::insert_sunrise_define( 'not a php file' ) );
		$this->assertNull( SRC_Sunrise_Installer::insert_sunrise_define( '<?php define( "X", 1 );' ) );
	}

	/**
	 * Test removal strips exactly the line this plugin added.
	 *
	 * @covers SRC_Sunrise_Installer::remove_sunrise_define
	 * @return void
	 */
	public function test_remove_sunrise_define_removes_our_line(): void {
		$config   = "<?php\ndefine( 'DB_NAME', 'wp' );\n";
		$modified = SRC_Sunrise_Installer::insert_sunrise_define( $config );

		$result = SRC_Sunrise_Installer::remove_sunrise_define( $modified );

		$this->assertSame( 'removed', $result['status'] );
		$this->assertSame( $config, $result['contents'] );
	}

	/**
	 * Test removal refuses to touch a user-authored SUNRISE define.
	 *
	 * @covers SRC_Sunrise_Installer::remove_sunrise_define
	 * @return void
	 */
	public function test_remove_sunrise_define_keeps_foreign_line(): void {
		$config = "<?php\ndefine( 'SUNRISE', true ); // Required by my domain mapper.\n";

		$result = SRC_Sunrise_Installer::remove_sunrise_define( $config );

		$this->assertSame( 'foreign', $result['status'] );
		$this->assertSame( $config, $result['contents'] );
	}

	/**
	 * Test removal reports absence when no SUNRISE define exists.
	 *
	 * @covers SRC_Sunrise_Installer::remove_sunrise_define
	 * @return void
	 */
	public function test_remove_sunrise_define_absent(): void {
		$config = "<?php\ndefine( 'DB_NAME', 'wp' );\n";

		$result = SRC_Sunrise_Installer::remove_sunrise_define( $config );

		$this->assertSame( 'absent', $result['status'] );
		$this->assertSame( $config, $result['contents'] );
	}

	/**
	 * Test a full install/uninstall round-trip leaves wp-config.php byte-identical.
	 *
	 * @covers SRC_Sunrise_Installer::insert_sunrise_define
	 * @covers SRC_Sunrise_Installer::remove_sunrise_define
	 * @return void
	 */
	public function test_config_round_trip_is_lossless(): void {
		$config = "<?php\n/**\n * Custom config.\n */\ndefine( 'DB_NAME', 'wp' );\ndefine( 'WP_ALLOW_MULTISITE', true );\n\n/* That's all, stop editing! */\nrequire_once ABSPATH . 'wp-settings.php';\n";

		$inserted = SRC_Sunrise_Installer::insert_sunrise_define( $config );
		$removed  = SRC_Sunrise_Installer::remove_sunrise_define( $inserted );

		$this->assertSame( 'removed', $removed['status'] );
		$this->assertSame( $config, $removed['contents'] );
	}
}
