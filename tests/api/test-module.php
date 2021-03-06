<?php

class RedirectionApiModuleTest extends WP_Ajax_UnitTestCase {
	public static $redirection;

	private function get_module( $params = array() ) {
		return json_decode( self::$redirection->ajax_get_module( $params ) );
	}

	private function set_module( $params = array() ) {
		return json_decode( self::$redirection->ajax_set_module( $params ) );
	}

	public static function setupBeforeClass() {
		self::$redirection = Redirection_Admin::init()->api;
	}

	public function setUp() {
		parent::setUp();
		$this->group = Red_Group::create( 'group', 1 );
	}

	private function createRedirect() {
		global $wpdb;

		$wpdb->query( "TRUNCATE {$wpdb->prefix}redirection_items" );

		Red_Item::create( array(
			'url'         => '/from',
			'action_data' => '/to',
			'group_id'    => $this->group->get_id(),
			'match_type'  => 'url',
			'action_type' => 'url',
		) );
	}

	private function getWP( $result ) {
		return isset( $result->items[ 0 ] ) ? $result->items[ 0 ] : false;
	}

	private function getApache( $result ) {
		return isset( $result->items[ 1 ] ) ? $result->items[ 1 ] : false;
	}

	private function getNginx( $result ) {
		return isset( $result->items[ 2 ] ) ? $result->items[ 2 ] : false;
	}

	private function hasWP( $result ) {
		return $this->getWP( $result ) ? $this->getWP( $result )->id === 1 : false;
	}

	private function hasApache( $result ) {
		return $this->getApache( $result ) ? $this->getApache( $result )->id === 2 : false;
	}

	private function hasNginx( $result ) {
		return $this->getNginx( $result ) ? $this->getNginx( $result )->id === 3 : false;
	}

	private function setNonce() {
		$this->_setRole( 'administrator' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'wp_rest' );
	}

	public function testLogNoParams() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->get_module();

		$this->assertTrue( $this->hasWP( $result ) );
		$this->assertTrue( $this->hasApache( $result ) );
		$this->assertTrue( $this->hasNginx( $result ) );
		$this->assertEquals( 1, $this->getWP( $result )->redirects );
		$this->assertTrue( isset( $this->getApache( $result )->data->location ) );
		$this->assertTrue( isset( $this->getApache( $result )->data->installed ) );
		$this->assertTrue( isset( $this->getApache( $result )->data->canonical ) );
	}

	public function testBadModule() {
		$this->setNonce();
		$result = $this->get_module( array( 'id' => 'purple' ) );

		$this->assertTrue( $this->hasWP( $result ) );
		$this->assertTrue( $this->hasApache( $result ) );
		$this->assertTrue( $this->hasNginx( $result ) );
	}

	public function testValidModule() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->get_module( array( 'id' => 1 ) );

		$this->assertTrue( $this->hasWP( $result ) );
		$this->assertTrue( $this->hasApache( $result ) );
		$this->assertTrue( $this->hasNginx( $result ) );
	}

	public function testBadExport() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->get_module( array( 'id' => 1, 'moduleType' => 'purple' ) );

		$this->assertTrue( $this->hasWP( $result ) );
		$this->assertEquals( 1, $this->getWP( $result )->redirects );
		$this->assertFalse( isset( $this->getWP( $result )->data ) );
	}

	public function testGoodExport() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->get_module( array( 'id' => 1, 'moduleType' => 'csv' ) );

		$this->assertTrue( $this->hasWP( $result ) );
		$this->assertEquals( 1, $this->getWP( $result )->redirects );
		$this->assertTrue( isset( $this->getWP( $result )->data ) );
	}

	public function testSetBadModule() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->set_module( array( 'id' => 'purple', 'moduleData_location' => 'test' ) );

		$this->assertTrue( isset( $result->error ) );
	}

	public function testSetWPModule() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->set_module( array( 'id' => 1, 'moduleData_location' => 'test' ) );

		$this->assertTrue( $this->hasWP( $result ) );
		$this->assertFalse( isset( $this->getWP( $result )->data ) );
	}

	public function testSetNginxModule() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->set_module( array( 'id' => 3, 'moduleData_location' => 'test' ) );

		$this->assertTrue( $this->hasNginx( $result ) );
		$this->assertFalse( isset( $result->nginx->data ) );
	}

	public function testSetApacheModule() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->set_module( array( 'id' => 2, 'moduleData_location' => 'test', 'moduleData_canonical' => 'www' ) );

		$this->assertTrue( $this->hasApache( $result ) );
		$this->assertTrue( isset( $this->getApache( $result )->data ) );
		$this->assertEquals( 'test', $this->getApache( $result )->data->location );
		$this->assertEquals( 'www', $this->getApache( $result )->data->canonical );
	}

	public function testSetApacheModuleBadCanonical() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->set_module( array( 'id' => 2, 'moduleData_canonical' => 'xxx' ) );

		$this->assertEquals( '', $this->getApache( $result )->data->canonical );
	}

	public function testSetApacheModuleBadLocation() {
		$this->createRedirect();
		$this->setNonce();
		$result = $this->set_module( array( 'id' => 2, 'moduleData_location' => '/tmp' ) );

		$this->assertEquals( '', $this->getApache( $result )->data->location );
	}
}
