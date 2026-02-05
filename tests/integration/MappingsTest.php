<?php
/**
 * Integration tests for SRC_Mappings class.
 *
 * Tests CRUD operations for subdomain mappings.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Integration
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Mappings
 */

/**
 * Class MappingsTest
 *
 * @group integration
 * @group mappings
 */
class MappingsTest extends WP_UnitTestCase {

	/**
	 * Clean up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear mappings table.
		global $wpdb;
		$table = SRC_Database::get_mappings_table();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
	}

	/**
	 * Test adding a post-type mapping.
	 *
	 * @covers SRC_Mappings::add
	 * @covers SRC_Mappings::get_by_subdomain
	 * @return void
	 */
	public function test_add_mapping_post_type(): void {
		// Create a test post.
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$result = SRC_Mappings::add( 'tickets', $post_id, 'post', '', 301 );

		$this->assertNotFalse( $result );

		$mapping = SRC_Mappings::get_by_subdomain( 'tickets' );

		$this->assertNotNull( $mapping );
		$this->assertEquals( 'tickets', $mapping->subdomain );
		$this->assertEquals( 'post', $mapping->mapping_type );
		$this->assertEquals( $post_id, $mapping->post_id );
		$this->assertEquals( 301, $mapping->redirect_code );
		$this->assertEquals( 1, $mapping->is_active );
	}

	/**
	 * Test adding a URL-type mapping.
	 *
	 * @covers SRC_Mappings::add
	 * @covers SRC_Mappings::get_redirect_url
	 * @return void
	 */
	public function test_add_mapping_url_type(): void {
		$result = SRC_Mappings::add( 'external', 0, 'url', 'https://external.com/path', 302 );

		$this->assertNotFalse( $result );

		$redirect_url = SRC_Mappings::get_redirect_url( 'external' );

		$this->assertEquals( 'https://external.com/path', $redirect_url );
	}

	/**
	 * Test adding a home-type mapping.
	 *
	 * @covers SRC_Mappings::add
	 * @covers SRC_Mappings::is_home_redirect
	 * @return void
	 */
	public function test_add_mapping_home_type(): void {
		$result = SRC_Mappings::add( 'home', 0, 'home', '', 301 );

		$this->assertNotFalse( $result );

		$is_home = SRC_Mappings::is_home_redirect( 'home' );

		$this->assertTrue( $is_home );
	}

	/**
	 * Test get_all with pagination.
	 *
	 * @covers SRC_Mappings::get_all
	 * @return void
	 */
	public function test_get_all_with_pagination(): void {
		// Create multiple mappings.
		for ( $i = 1; $i <= 5; $i++ ) {
			SRC_Mappings::add( "sub{$i}", 0, 'home', '', 301 );
		}

		$page1 = SRC_Mappings::get_all( array( 'limit' => 2, 'offset' => 0 ) );
		$page2 = SRC_Mappings::get_all( array( 'limit' => 2, 'offset' => 2 ) );

		$this->assertCount( 2, $page1 );
		$this->assertCount( 2, $page2 );
	}

	/**
	 * Test get_all with active_only filter.
	 *
	 * @covers SRC_Mappings::get_all
	 * @return void
	 */
	public function test_get_all_active_only_filter(): void {
		SRC_Mappings::add( 'active1', 0, 'home', '', 301 );
		SRC_Mappings::add( 'active2', 0, 'home', '', 301 );
		SRC_Mappings::add( 'inactive', 0, 'home', '', 301 );

		// Get the inactive mapping and disable it.
		$mapping = SRC_Mappings::get_by_subdomain( 'inactive' );
		SRC_Mappings::set_active( $mapping->id, false );

		$active_only = SRC_Mappings::get_all( array( 'active_only' => true ) );
		$all         = SRC_Mappings::get_all( array( 'active_only' => false ) );

		$this->assertCount( 2, $active_only );
		$this->assertCount( 3, $all );
	}

	/**
	 * Test updating a mapping.
	 *
	 * @covers SRC_Mappings::update
	 * @return void
	 */
	public function test_update_mapping(): void {
		SRC_Mappings::add( 'original', 0, 'home', '', 301 );
		$mapping = SRC_Mappings::get_by_subdomain( 'original' );

		$result = SRC_Mappings::update(
			$mapping->id,
			'updated',
			0,
			'url',
			'https://example.com/',
			302
		);

		$this->assertTrue( $result );

		$updated = SRC_Mappings::get_by_id( $mapping->id );

		$this->assertEquals( 'updated', $updated->subdomain );
		$this->assertEquals( 'url', $updated->mapping_type );
		$this->assertEquals( 'https://example.com/', $updated->redirect_url );
		$this->assertEquals( 302, $updated->redirect_code );
	}

	/**
	 * Test deleting a mapping.
	 *
	 * @covers SRC_Mappings::delete
	 * @return void
	 */
	public function test_delete_mapping(): void {
		SRC_Mappings::add( 'todelete', 0, 'home', '', 301 );
		$mapping = SRC_Mappings::get_by_subdomain( 'todelete' );

		$result = SRC_Mappings::delete( $mapping->id );

		$this->assertTrue( $result );

		$deleted = SRC_Mappings::get_by_subdomain( 'todelete' );
		$this->assertNull( $deleted );
	}

	/**
	 * Test set_active toggle.
	 *
	 * @covers SRC_Mappings::set_active
	 * @return void
	 */
	public function test_set_active_toggle(): void {
		SRC_Mappings::add( 'toggle', 0, 'home', '', 301 );
		$mapping = SRC_Mappings::get_by_subdomain( 'toggle' );

		// Initially active.
		$this->assertEquals( 1, $mapping->is_active );

		// Deactivate.
		SRC_Mappings::set_active( $mapping->id, false );
		$mapping = SRC_Mappings::get_by_id( $mapping->id );
		$this->assertEquals( 0, $mapping->is_active );

		// Reactivate.
		SRC_Mappings::set_active( $mapping->id, true );
		$mapping = SRC_Mappings::get_by_id( $mapping->id );
		$this->assertEquals( 1, $mapping->is_active );
	}

	/**
	 * Test subdomain_exists check.
	 *
	 * @covers SRC_Mappings::subdomain_exists
	 * @return void
	 */
	public function test_subdomain_exists_check(): void {
		SRC_Mappings::add( 'exists', 0, 'home', '', 301 );

		$this->assertTrue( SRC_Mappings::subdomain_exists( 'exists' ) );
		$this->assertFalse( SRC_Mappings::subdomain_exists( 'notexists' ) );
	}

	/**
	 * Test subdomain_exists with exclude_id.
	 *
	 * @covers SRC_Mappings::subdomain_exists
	 * @return void
	 */
	public function test_subdomain_exists_excludes_self(): void {
		SRC_Mappings::add( 'mysubdomain', 0, 'home', '', 301 );
		$mapping = SRC_Mappings::get_by_subdomain( 'mysubdomain' );

		// Should not exist when excluding self.
		$this->assertFalse( SRC_Mappings::subdomain_exists( 'mysubdomain', $mapping->id ) );

		// Should exist when not excluding.
		$this->assertTrue( SRC_Mappings::subdomain_exists( 'mysubdomain' ) );
	}

	/**
	 * Test get_redirect_code.
	 *
	 * @covers SRC_Mappings::get_redirect_code
	 * @return void
	 */
	public function test_get_redirect_code(): void {
		SRC_Mappings::add( 'code301', 0, 'home', '', 301 );
		SRC_Mappings::add( 'code302', 0, 'home', '', 302 );

		$this->assertEquals( 301, SRC_Mappings::get_redirect_code( 'code301' ) );
		$this->assertEquals( 302, SRC_Mappings::get_redirect_code( 'code302' ) );
	}

	/**
	 * Test get_redirect_code returns 301 for non-existent subdomain.
	 *
	 * @covers SRC_Mappings::get_redirect_code
	 * @return void
	 */
	public function test_get_redirect_code_defaults_to_301(): void {
		$this->assertEquals( 301, SRC_Mappings::get_redirect_code( 'nonexistent' ) );
	}

	/**
	 * Test get_total_count.
	 *
	 * @covers SRC_Mappings::get_total_count
	 * @return void
	 */
	public function test_get_total_count(): void {
		SRC_Mappings::add( 'count1', 0, 'home', '', 301 );
		SRC_Mappings::add( 'count2', 0, 'home', '', 301 );
		SRC_Mappings::add( 'count3', 0, 'home', '', 301 );

		$mapping = SRC_Mappings::get_by_subdomain( 'count3' );
		SRC_Mappings::set_active( $mapping->id, false );

		$this->assertEquals( 3, SRC_Mappings::get_total_count( false ) );
		$this->assertEquals( 2, SRC_Mappings::get_total_count( true ) );
	}
}
