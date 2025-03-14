<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use xPaw\Steam\SteamOpenID;

require __DIR__ . '/../vendor/autoload.php';

final class OpenIDTest extends TestCase
{
	private const DefaultInput = // TODO: php 8.3 type to "array"
	[
		'openid_mode' => 'id_res',
		'openid_ns' => 'http://specs.openid.net/auth/2.0',
		'openid_op_endpoint' => 'https://steamcommunity.com/openid/login',
		'openid_claimed_id' => 'https://steamcommunity.com/openid/id/76561197972494985',
		'openid_identity' => 'https://steamcommunity.com/openid/id/76561197972494985',
		'openid_return_to' => 'https://localhost/SteamOpenID/Example.php',
		'openid_response_nonce' => '2024-11-02T08:04:10ZUNIQUE',
		'openid_assoc_handle' => '1234567890',
		'openid_signed' => 'signed,op_endpoint,claimed_id,identity,return_to,response_nonce,assoc_handle',
		'openid_sig' => 'test_hack_validate_as_true',
	];

	public function testSuccessLogin() : void
	{
		$input = self::DefaultInput;

		$openid = new TestOpenID( 'https://localhost/SteamOpenID/Example.php', $input );

		$this->assertTrue( $openid->ShouldValidate() );
		$this->assertEquals( '76561197972494985', $openid->Validate() );
		$this->assertTrue( $openid->RequestWasSent );
	}

	public function testFailLogin() : void
	{
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
			$input = self::DefaultInput;
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
		foreach( self::DefaultInput as $key => $value )
		{
			$input = self::DefaultInput;
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

		foreach( self::DefaultInput as $key => $originalValue )
		{
			foreach( $valuesToTry as $value )
			{
				$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
		$input['openid_return_to'] = 'https://localhost/SteamOpenID/Example.php?param1=value1&param2=value2';

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php', $input);

		$this->assertTrue($openid->ShouldValidate());
		$this->assertEquals('76561197972494985', $openid->Validate());
		$this->assertTrue($openid->RequestWasSent);
	}

	public function testValidReturnURLWithQueryParameters() : void
	{
		$input = self::DefaultInput;
		$input['openid_return_to'] = 'https://localhost/SteamOpenID/Example.php?param1=value1&param2=value2';

		$openid = new TestOpenID('https://localhost/SteamOpenID/Example.php?param1=value1', $input);

		$this->assertTrue($openid->ShouldValidate());
		$this->assertEquals('76561197972494985', $openid->Validate());
		$this->assertTrue($openid->RequestWasSent);
	}

	public function testFailLoginForMalformedKeyValueResponse() : void
	{
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
		$input = self::DefaultInput;
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
			case 'test_hack_return_500': return [ 500, 'Internal Server Error' ];
			case 'test_hack_return_malformed_response': return [ 200, "ns:http://specs.openid.net/auth/2.0\ngarbage data" ];
			case 'test_hack_return_empty_response': return [ 200, "" ];
			default: throw new Exception( 'Unknown test openid_sig: ' . $Arguments[ 'openid_sig' ] );
		}
	}
}
