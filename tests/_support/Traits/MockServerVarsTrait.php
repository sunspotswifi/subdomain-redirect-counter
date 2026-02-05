<?php
/**
 * Trait for mocking $_SERVER variables in tests.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SRC\Tests\Support\Traits;

/**
 * Trait MockServerVarsTrait
 *
 * Provides helper methods for setting and clearing $_SERVER variables
 * commonly used in subdomain detection and request handling.
 */
trait MockServerVarsTrait {

	/**
	 * Backup of original $_SERVER values.
	 *
	 * @var array
	 */
	protected array $original_server_vars = array();

	/**
	 * Set up server variable mocking.
	 *
	 * Call this in setUp() to preserve original values.
	 *
	 * @return void
	 */
	protected function setUpServerVars(): void {
		$this->original_server_vars = $_SERVER;
	}

	/**
	 * Restore original $_SERVER values.
	 *
	 * Call this in tearDown() to restore state.
	 *
	 * @return void
	 */
	protected function tearDownServerVars(): void {
		$_SERVER = $this->original_server_vars;
	}

	/**
	 * Set HTTP_HOST server variable.
	 *
	 * @param string $host The hostname (e.g., 'tickets.example.com').
	 * @return void
	 */
	protected function setHttpHost( string $host ): void {
		$_SERVER['HTTP_HOST'] = $host;
	}

	/**
	 * Set REMOTE_ADDR server variable.
	 *
	 * @param string $ip The IP address.
	 * @return void
	 */
	protected function setRemoteAddr( string $ip ): void {
		$_SERVER['REMOTE_ADDR'] = $ip;
	}

	/**
	 * Set HTTP_X_FORWARDED_FOR server variable.
	 *
	 * @param string $ip The forwarded IP address(es).
	 * @return void
	 */
	protected function setForwardedFor( string $ip ): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = $ip;
	}

	/**
	 * Set REQUEST_URI server variable.
	 *
	 * @param string $uri The request URI (e.g., '/page/subpage?query=1').
	 * @return void
	 */
	protected function setRequestUri( string $uri ): void {
		$_SERVER['REQUEST_URI'] = $uri;
	}

	/**
	 * Set HTTP_USER_AGENT server variable.
	 *
	 * @param string $ua The user agent string.
	 * @return void
	 */
	protected function setUserAgent( string $ua ): void {
		$_SERVER['HTTP_USER_AGENT'] = $ua;
	}

	/**
	 * Set HTTP_REFERER server variable.
	 *
	 * @param string $referer The referer URL.
	 * @return void
	 */
	protected function setReferer( string $referer ): void {
		$_SERVER['HTTP_REFERER'] = $referer;
	}

	/**
	 * Set SERVER_NAME server variable.
	 *
	 * @param string $name The server name.
	 * @return void
	 */
	protected function setServerName( string $name ): void {
		$_SERVER['SERVER_NAME'] = $name;
	}

	/**
	 * Set multiple server variables at once.
	 *
	 * @param array $vars Associative array of variable names and values.
	 * @return void
	 */
	protected function setServerVars( array $vars ): void {
		foreach ( $vars as $key => $value ) {
			$_SERVER[ $key ] = $value;
		}
	}

	/**
	 * Clear specific server variables.
	 *
	 * @param array $keys Array of variable names to unset.
	 * @return void
	 */
	protected function clearServerVars( array $keys = array() ): void {
		if ( empty( $keys ) ) {
			// Clear commonly mocked variables.
			$keys = array(
				'HTTP_HOST',
				'REMOTE_ADDR',
				'HTTP_X_FORWARDED_FOR',
				'REQUEST_URI',
				'HTTP_USER_AGENT',
				'HTTP_REFERER',
				'SERVER_NAME',
			);
		}

		foreach ( $keys as $key ) {
			unset( $_SERVER[ $key ] );
		}
	}

	/**
	 * Set up a complete request environment.
	 *
	 * @param string $host       The hostname.
	 * @param string $uri        The request URI.
	 * @param string $ip         The client IP.
	 * @param string $user_agent The user agent.
	 * @param string $referer    The referer URL.
	 * @return void
	 */
	protected function setUpRequest(
		string $host,
		string $uri = '/',
		string $ip = '192.168.1.100',
		string $user_agent = 'PHPUnit Test Agent',
		string $referer = ''
	): void {
		$this->setHttpHost( $host );
		$this->setRequestUri( $uri );
		$this->setRemoteAddr( $ip );
		$this->setUserAgent( $user_agent );

		if ( $referer ) {
			$this->setReferer( $referer );
		}
	}
}
