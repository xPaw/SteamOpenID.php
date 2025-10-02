<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use xPaw\Steam\SteamOpenID;

require __DIR__ . '/../vendor/autoload.php';

final class OpenIDTest extends TestCase
{
	private static function GetDefaultInput() : array
	{
		return
		[
			'openid_mode' => 'id_res',
			'openid_ns' => 'http://specs.openid.net/auth/2.0',
			'openid_op_endpoint' => 'https://steamcommunity.com/openid/login',
			'openid_claimed_id' => 'https://steamcommunity.com/openid/id/76561197972494985',
			'openid_identity' => 'https://steamcommunity.com/openid/id/76561197972494985',
			'openid_return_to' => 'https://localhost/SteamOpenID/Example.php',
			'openid_response_nonce' => gmdate( 'Y-m-d\TH:i:s\Z' ) . 'UNIQUE',
			'openid_assoc_handle' => '1234567890',
			'openid_signed' => 'signed,op_endpoint,claimed_id,identity,return_to,response_nonce,assoc_handle',
			'openid_sig' => 'test_hack_validate_as_true',
		];
	}

	public function testSuccessLogin() : void
	{
		$input = self::GetDefaultInput();

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->assertEquals( '76561197972494985', $openid->Validate() );
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLogin() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_validate_as_false';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginBadResponse() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_bad_response';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginBadResponse2() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_is_valid_more_colons';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginBadResponse3() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_is_valid_no_colons';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginFor403Response() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_403';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/rate limit/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginFor201Response() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_201';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify your login with Steam/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginForWrongNamespace() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_wrong_openid_ns';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/valid OpenID/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}


	public function testShouldNotValidateForWrongMode() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_mode' ] = 'id_res2';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertFalse( $openid->ShouldValidate() );
	}

	public function testShouldThrowForUnexpectedArguments() : void
	{
		$inputsToChangeOneAtATime =
		[
			'openid_mode' => 'id_res2',
			'openid_ns' => 'http://specs.openid.net/auth/2.1',
			'openid_op_endpoint' => 'https://steamcommunity.com/idopen/login',
			'openid_return_to' => 'https://localhost/SteamOpenID/Login.php',
			'openid_signed' => 'signed,op_endpoint,claimed_id,identity,return_to,assoc_handle,response_nonce',
		];

		foreach( $inputsToChangeOneAtATime as $key => $value )
		{
			$input = self::GetDefaultInput();
			$input[ $key ] = $value;

			$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

			try
			{
				$openid->Validate();
				$this->fail();
			}
			catch( InvalidArgumentException $e )
			{
				$this->assertMatchesRegularExpression( "/Wrong {$key}/", $e->getMessage() );
			}

			$this->assertFalse( $openid->RequestWasSent );
		}
	}

	public function testShouldThrowForMissingParameter() : void
	{
		foreach( self::GetDefaultInput() as $key => $value )
		{
			$input = self::GetDefaultInput();
			unset( $input[ $key ] );

			$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

			try
			{
				$openid->Validate();
				$this->fail();
			}
			catch( InvalidArgumentException $e )
			{
				$this->assertMatchesRegularExpression( "/{$key} is not a string/", $e->getMessage() );
			}

			$this->assertFalse( $openid->RequestWasSent );
		}
	}

	public function testShouldThrowForNonStringParameter() : void
	{
		$valuesToTry =
		[
			null,
			false,
			[],
			[
				'123'
			]
		];

		foreach( self::GetDefaultInput() as $key => $originalValue )
		{
			foreach( $valuesToTry as $value )
			{
				$input = self::GetDefaultInput();
				$input[ $key ] = $value;

				$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

				try
				{
					$openid->Validate();
					$this->fail();
				}
				catch( InvalidArgumentException $e )
				{
					if( $key === 'openid_identity' )
					{
						$this->assertMatchesRegularExpression( "/Wrong (?:openid_identity|openid_claimed_id)/", $e->getMessage() );
					}
					else
					{
						$this->assertMatchesRegularExpression( "/Wrong {$key}/", $e->getMessage() );
					}
				}

				$this->assertFalse( $openid->RequestWasSent );
			}
		}
	}

	public function testShouldThrowForUnexpectedIdentity() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_identity' ] = 'https://staemcommunity.com/openid/id/76561197972494984';
		$input[ 'openid_claimed_id' ] = $input[ 'openid_identity' ];

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_identity/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testShouldThrowForMismatchingId() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_claimed_id' ] = 'https://steamcommunity.com/openid/id/76561197972494984';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_claimed_id, should equal to openid_identity/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testAuthUrl() : void
	{
		$openid = new SteamOpenID( 'https://localhost/SteamOpenID/Example.php' );

		$this->assertEquals(
			'https://steamcommunity.com/openid/login' .
			'?openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select' .
			'&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select' .
			'&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0' .
			'&openid.mode=checkid_setup' .
			'&openid.return_to=https%3A%2F%2Flocalhost%2FSteamOpenID%2FExample.php',
			$openid->GetAuthUrl()
		);
	}

	public function testAuthParams() : void
	{
		$openid = new SteamOpenID( 'https://localhost/SteamOpenID/Example.php' );

		$this->assertEquals(
			[
				'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
				'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
				'openid.ns' => 'http://specs.openid.net/auth/2.0',
				'openid.mode' => 'checkid_setup',
				'openid.return_to' => 'https://localhost/SteamOpenID/Example.php',
			],
			$openid->GetAuthParameters()
		);
	}

	public function testInvalidSteamIDPattern() : void
	{
		$input = self::GetDefaultInput();
		$input['openid_identity'] = 'https://steamcommunity.com/openid/id/123456789012345'; // Invalid SteamID format
		$input['openid_claimed_id'] = $input['openid_identity'];

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php', $input);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/Wrong openid_identity/');
		$openid->Validate();
		$this->assertFalse($openid->RequestWasSent);
	}

	public function testReturnURLWithQueryParameters() : void
	{
		$input = self::GetDefaultInput();
		$input['openid_return_to'] = 'https://localhost/SteamOpenID/Example.php?param1=value1&param2=value2';

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php', $input);

		$this->assertTrue($openid->ShouldValidate());
		$this->assertEquals('76561197972494985', $openid->Validate());
		$this->assertTrue($openid->RequestWasSent);
	}

	public function testValidReturnURLWithQueryParameters() : void
	{
		$input = self::GetDefaultInput();
		$input['openid_return_to'] = 'https://localhost/SteamOpenID/Example.php?param1=value1&param2=value2';

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php?param1=value1', $input);

		$this->assertTrue($openid->ShouldValidate());
		$this->assertEquals('76561197972494985', $openid->Validate());
		$this->assertTrue($openid->RequestWasSent);
	}

	public function testFailLoginForMalformedKeyValueResponse() : void
	{
		$input = self::GetDefaultInput();
		$input['openid_sig'] = 'test_hack_return_malformed_response';

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php', $input);

		$this->assertTrue($openid->ShouldValidate());
		$this->expectException(Exception::class);
		$this->expectExceptionMessageMatches('/Failed to verify login your with Steam/');
		$openid->Validate();
		$this->assertTrue($openid->RequestWasSent);
	}

	public function testFailLoginForEmptyResponse() : void
	{
		$input = self::GetDefaultInput();
		$input['openid_sig'] = 'test_hack_return_empty_response';

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php', $input);

		$this->assertTrue($openid->ShouldValidate());
		$this->expectException(Exception::class);
		$this->expectExceptionMessageMatches('/Failed to verify login your with Steam, not a valid OpenID 2.0 response/');
		$openid->Validate();
		$this->assertTrue($openid->RequestWasSent);
	}

	public function testFailLoginFor404Response() : void
	{
		$input = self::GetDefaultInput();
		$input['openid_sig'] = 'test_hack_return_404';

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php', $input);

		$this->assertTrue($openid->ShouldValidate());
		$this->expectException(Exception::class);
		$this->expectExceptionMessageMatches('/Failed to verify your login with Steam/');
		$openid->Validate();
		$this->assertTrue($openid->RequestWasSent);
	}

	public function testFailLoginFor500Response() : void
	{
		$input = self::GetDefaultInput();
		$input['openid_sig'] = 'test_hack_return_500';

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php', $input);

		$this->assertTrue($openid->ShouldValidate());
		$this->expectException(Exception::class);
		$this->expectExceptionMessageMatches('/Failed to verify your login with Steam/');
		$openid->Validate();
		$this->assertTrue($openid->RequestWasSent);
	}

	public function testGetAuthParamsWithCustomReturnURL() : void
	{
		$openid = new SteamOpenID('https://example.com/login?param=value');

		$params = $openid->GetAuthParameters();
		$this->assertEquals('https://example.com/login?param=value', $params['openid.return_to']);
	}

	public function testMinimumSteamID() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_identity' ] = 'https://steamcommunity.com/openid/id/76561197960265729';
		$input[ 'openid_claimed_id' ] = $input[ 'openid_identity' ];

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->assertEquals( '76561197960265729', $openid->Validate() );
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testMaximumSteamID() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_identity' ] = 'https://steamcommunity.com/openid/id/76561202255233023';
		$input[ 'openid_claimed_id' ] = $input[ 'openid_identity' ];

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->assertEquals( '76561202255233023', $openid->Validate() );
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testSteamIDWithTrailingSlash() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_identity' ] = 'https://steamcommunity.com/openid/id/76561197972494985/';
		$input[ 'openid_claimed_id' ] = $input[ 'openid_identity' ];

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->assertEquals( '76561197972494985', $openid->Validate() );
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testInvalidSteamIDNotStartingWith76561() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_identity' ] = 'https://steamcommunity.com/openid/id/12345197972494985';
		$input[ 'openid_claimed_id' ] = $input[ 'openid_identity' ];

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_identity/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testInvalidSteamIDWithNonNumericCharacters() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_identity' ] = 'https://steamcommunity.com/openid/id/76561a97972494985';
		$input[ 'openid_claimed_id' ] = $input[ 'openid_identity' ];

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_identity/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testInvalidSteamIDTooShort() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_identity' ] = 'https://steamcommunity.com/openid/id/7656119797249498';
		$input[ 'openid_claimed_id' ] = $input[ 'openid_identity' ];

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_identity/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testInvalidSteamIDTooLong() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_identity' ] = 'https://steamcommunity.com/openid/id/765611979724949851';
		$input[ 'openid_claimed_id' ] = $input[ 'openid_identity' ];

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_identity/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testFailLoginFor429Response() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_429';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/rate limit/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginForMissingNamespaceKey() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_missing_ns_key';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify login your with Steam, not a valid OpenID 2.0 response/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginForMissingIsValidKey() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_missing_is_valid_key';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify login your with Steam/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginForIsValidFalse() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_is_valid_false';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify login your with Steam/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginForIsValidOtherValue() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_is_valid_maybe';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify login your with Steam/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLoginForWhitespaceOnlyResponse() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_sig' ] = 'test_hack_return_whitespace_only';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessageMatches( '/Failed to verify login your with Steam, not a valid OpenID 2.0 response/' );
		$openid->Validate();
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testParameterWithOnlyWhitespace() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_mode' ] = '   ';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_mode/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testReturnURLExactMatch() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_return_to' ] = 'https://localhost/SteamOpenID/Example.php';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->assertEquals( '76561197972494985', $openid->Validate() );
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testReturnURLCaseSensitive() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_return_to' ] = 'https://LOCALHOST/SteamOpenID/Example.php';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_return_to/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testConstructorWithEmptyReturnURL() : void
	{
		$openid = new SteamOpenID( '' );

		$params = $openid->GetAuthParameters();
		$this->assertEquals( '', $params[ 'openid.return_to' ] );
	}

	public function testConstructorWithEmptyParametersArray() : void
	{
		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', [] );

		$this->assertFalse( $openid->ShouldValidate() );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/openid_mode is not a string/' );
		$openid->Validate();
	}

	public function testValidResponseNonceFormat() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = gmdate( 'Y-m-d\TH:i:s\Z' ) . 'UNIQUE123';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->assertEquals( '76561197972494985', $openid->Validate() );
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testInvalidResponseNonceMissingTimestamp() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = 'JUSTUNIQUECHARS';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_response_nonce/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testInvalidResponseNonceInvalidDateFormat() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-02 08:04:10UNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_response_nonce/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testInvalidResponseNonceMissingZSuffix() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-02T08:04:10UNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_response_nonce/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceTimestampTooOld() : void
	{
		$input = self::GetDefaultInput();
		// Timestamp from 10 minutes ago (600 seconds, exceeds 300 second limit)
		$oldTimestamp = gmdate( 'Y-m-d\TH:i:s\Z', time() - 600 );
		$input[ 'openid_response_nonce' ] = $oldTimestamp . 'UNIQUE123';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceTimestampTooFarInFuture() : void
	{
		$input = self::GetDefaultInput();
		// Timestamp from 10 minutes in the future (600 seconds, exceeds 300 second limit)
		$futureTimestamp = gmdate( 'Y-m-d\TH:i:s\Z', time() + 600 );
		$input[ 'openid_response_nonce' ] = $futureTimestamp . 'UNIQUE123';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithOnlyTimestamp() : void
	{
		$input = self::GetDefaultInput();
		// Valid RFC3339 timestamp but no unique characters after it
		$input[ 'openid_response_nonce' ] = gmdate( 'Y-m-d\TH:i:s\Z' );

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->assertEquals( '76561197972494985', $openid->Validate() );
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testResponseNonceWithInvalidDate() : void
	{
		$input = self::GetDefaultInput();
		// Invalid date (month 13)
		$input[ 'openid_response_nonce' ] = '2024-13-02T08:04:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithMalformedTimestamp() : void
	{
		$input = self::GetDefaultInput();
		// Wrong format - single digit day
		$input[ 'openid_response_nonce' ] = '2024-11-2T08:04:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_response_nonce/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceEmptyString() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Wrong openid_response_nonce/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithInvalidHour24() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-02T24:04:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithInvalidHour99() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-02T99:04:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithInvalidMinute60() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-02T08:60:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithInvalidMinute99() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-02T08:99:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithInvalidSecond60() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-02T08:04:60ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithInvalidSecond99() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-02T08:04:99ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithInvalidDayOfMonth() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-11-32T08:04:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithFebruary30() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2024-02-30T08:04:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithFebruary29NonLeapYear() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '2023-02-29T08:04:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}

	public function testResponseNonceWithYear0000() : void
	{
		$input = self::GetDefaultInput();
		$input[ 'openid_response_nonce' ] = '0000-11-02T08:04:10ZUNIQUE';

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Nonce timestamp is too old/' );
		$openid->Validate();
		$this->assertFalse( $openid->RequestWasSent );
	}
}

class TestOpenID extends SteamOpenID
{
	public bool $RequestWasSent = false;

	//#[\Override] - php 8.3
	public function SendSteamRequest( array $Arguments ) : array
	{
		$this->RequestWasSent = true;

		foreach( $Arguments as $Key => $Value )
		{
			if( $Key === 'openid_mode' )
			{
				if( $Value !== 'check_authentication' )
				{
					throw new Exception( 'Wrong openid_mode' );
				}

				continue;
			}

			if( !is_string( $Value ) || (string)$this->InputParameters[ $Key ] !== $Value )
			{
				throw new Exception( 'Unexpected value for ' . $Key );
			}
		}

		switch( $Arguments[ 'openid_sig' ] )
		{
			case 'test_hack_validate_as_true': return [ 200, "ns:http://specs.openid.net/auth/2.0\nis_valid:true" ];
			case 'test_hack_validate_as_false': return [ 200, "ns:http://specs.openid.net/auth/2.0\nis_valid:false" ];
			case 'test_hack_wrong_openid_ns': return [ 200, "ns:http://specs.openid.net/auth/2.1\nis_valid:true" ];
			case 'test_hack_return_bad_response': return [ 200, "ns:http://specs.openid.net/auth/2.0\n_is_valid:true" ];
			case 'test_hack_return_is_valid_more_colons': return [ 200, "ns:http://specs.openid.net/auth/2.0\nis_valid:true:is_valid:true" ];
			case 'test_hack_return_is_valid_no_colons': return [ 200, "ns:http://specs.openid.net/auth/2.0\nis_valid_true" ];
			case 'test_hack_return_201': return [ 201, "ns:http://specs.openid.net/auth/2.0\nis_valid:true" ];
			case 'test_hack_return_403': return [ 403, '' ];
			case 'test_hack_return_404': return [ 404, 'Not Found' ];
			case 'test_hack_return_429': return [ 429, '' ];
			case 'test_hack_return_500': return [ 500, 'Internal Server Error' ];
			case 'test_hack_return_malformed_response': return [ 200, "ns:http://specs.openid.net/auth/2.0\ngarbage data" ];
			case 'test_hack_return_empty_response': return [ 200, "" ];
			case 'test_hack_return_missing_ns_key': return [ 200, "is_valid:true" ];
			case 'test_hack_return_missing_is_valid_key': return [ 200, "ns:http://specs.openid.net/auth/2.0" ];
			case 'test_hack_return_is_valid_false': return [ 200, "ns:http://specs.openid.net/auth/2.0\nis_valid:false" ];
			case 'test_hack_return_is_valid_maybe': return [ 200, "ns:http://specs.openid.net/auth/2.0\nis_valid:maybe" ];
			case 'test_hack_return_whitespace_only': return [ 200, "   \n\t\n   " ];
			default: throw new Exception( 'Unknown test openid_sig: ' . $Arguments[ 'openid_sig' ] );
		}
	}
}
