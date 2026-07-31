<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\FileUploads;
use CoreLabs\CheckoutFields\Checkout\Sanitizer;
use Brain\Monkey;

class FileUploadsTest extends TestCase {

	private const TOKEN = 'aabbccddeeff00112233445566778899';

	private function group(): array {
		return array( 'fields' => array( array( 'id' => 'doc', 'type' => 'file', 'label' => 'Document' ) ) );
	}

	public function test_sanitizer_keeps_valid_token_and_drops_junk(): void {
		$out = Sanitizer::sanitize( $this->group(), array( 'doc' => self::TOKEN ) );
		$this->assertSame( self::TOKEN, $out['doc'] );

		$bad = Sanitizer::sanitize( $this->group(), array( 'doc' => '../../etc/passwd' ) );
		$this->assertArrayNotHasKey( 'doc', $bad );
	}

	public function test_validate_tokens_accepts_existing_upload(): void {
		Monkey\Functions\when( 'get_option' )->alias(
			static fn( $key ) => 'clcf_upload_' . self::TOKEN === $key ? array( 'url' => 'https://x/f.pdf', 'time' => 1 ) : false
		);
		$this->assertSame( array(), FileUploads::validate_tokens( $this->group(), array( 'doc' => self::TOKEN ) ) );
	}

	public function test_validate_tokens_rejects_unknown_or_malformed(): void {
		Monkey\Functions\when( 'get_option' )->justReturn( false );
		$this->assertCount( 1, FileUploads::validate_tokens( $this->group(), array( 'doc' => self::TOKEN ) ) );
		$this->assertCount( 1, FileUploads::validate_tokens( $this->group(), array( 'doc' => 'short' ) ) );
		// Empty is fine — required-ness belongs to Validator.
		$this->assertSame( array(), FileUploads::validate_tokens( $this->group(), array() ) );
	}

	public function test_consume_returns_url_and_deletes_row(): void {
		$deleted = null;
		Monkey\Functions\when( 'get_option' )->alias(
			static fn( $key ) => 'clcf_upload_' . self::TOKEN === $key ? array( 'url' => 'https://x/f.pdf', 'name' => 'f.pdf', 'time' => 1 ) : false
		);
		Monkey\Functions\when( 'delete_option' )->alias(
			static function ( $key ) use ( &$deleted ) {
				$deleted = $key;
				return true;
			}
		);
		$row = FileUploads::consume( self::TOKEN );
		$this->assertSame( 'https://x/f.pdf', $row['url'] );
		$this->assertSame( 'clcf_upload_' . self::TOKEN, $deleted );

		$this->assertNull( FileUploads::consume( str_repeat( '0', 32 ) ) );
	}

	public function test_defaults_are_sane(): void {
		$this->assertContains( 'pdf', FileUploads::allowed_extensions() );
		$this->assertNotContains( 'php', FileUploads::allowed_extensions() );
		$this->assertGreaterThan( 1024 * 1024, FileUploads::max_bytes() );
	}

	public function test_webp_allowed_by_default(): void {
		$this->assertContains( 'webp', FileUploads::allowed_extensions() );
	}

	public function test_parse_tokens_handles_one_and_many(): void {
		$a = str_repeat( 'a1', 16 );
		$b = str_repeat( 'b2', 16 );
		$this->assertSame( array( $a ), FileUploads::parse_tokens( $a ) );
		$this->assertSame( array( $a, $b ), FileUploads::parse_tokens( "$a,$b" ) );
		$this->assertSame( array( $a, $b ), FileUploads::parse_tokens( " $a , $b " ) );
	}

	public function test_parse_tokens_drops_garbage_and_duplicates(): void {
		$a = str_repeat( 'a1', 16 );
		$this->assertSame( array( $a ), FileUploads::parse_tokens( "$a,$a,nope,," ) );
		$this->assertSame( array(), FileUploads::parse_tokens( '' ) );
		$this->assertSame( array(), FileUploads::parse_tokens( null ) );
		$this->assertSame( array(), FileUploads::parse_tokens( array( $a ) ) );
	}
}
