<?php
declare(strict_types=1);

namespace xPaw\Steam;

use Exception;
use InvalidArgumentException;

/**
 * A correct and simple implementation of OpenID authentication for Steam.
 *
 * I suggest using SteamID.php {@link https://github.com/xPaw/SteamID.php}
 * to interact with the SteamID.
 *
 * This library requires cURL module to be installed.
 *
 * GitHub: {@link https://github.com/xPaw/SteamOpenID.php}
 * Website: {@link https://xpaw.me}
 *
 * @author xPaw
 * @license MIT
 */
class SteamOpenID
{
	public const STEAM_LOGIN = 'https://steamcommunity.com/openid/login';
	private const OPENID_NS = 'http://specs.openid.net/auth/2.0';
	private const EXPECTED_SIGNED = 'signed,op_endpoint,claimed_id,identity,return_to,response_nonce,assoc_handle';

	private string $SelfURL;

	/** @var ?array<string, string> */
	protected ?array $InputParameters;

	/**
	 * @param string $SelfURL URL to return to from Steam, this will also validate the "openid.return_to" parameter
	 * @param ?array<string, string> $Params Request parameters provided in the GET parameters
	 */
	public function __construct( string $SelfURL, ?array $Params = null )
	{
		$this->SelfURL = $SelfURL;
		$this->InputParameters = $Params ?? $_GET;
	}

	/**
	 * Validates OpenID data, and verifies with Steam.
	 *
	 * @throws InvalidArgumentException Thrown when manipulation of the input parameters is detected.
	 * @throws Exception Login failed to be validated against Steam servers.
	 *
	 * @return string Returns the CommunityID when validation succeeds
	 */
	public function Validate() : string
	{
		$Arguments = $this->GetArguments();

		if( $Arguments[ 'openid_mode' ] !== 'id_res' )
		{
			throw new InvalidArgumentException( 'Wrong openid_mode.' );
		}

		if( $Arguments[ 'openid_claimed_id' ] !== $Arguments[ 'openid_identity' ] )
		{
			throw new InvalidArgumentException( 'Mismatched claimed_id to identity.' );
		}

		if( $Arguments[ 'openid_ns' ] !== self::OPENID_NS )
		{
			throw new InvalidArgumentException( 'Wrong openid_ns.' );
		}

		if( $Arguments[ 'openid_op_endpoint' ] !== self::STEAM_LOGIN )
		{
			throw new InvalidArgumentException( 'Wrong openid_op_endpoint.' );
		}

		if( $Arguments[ 'openid_signed' ] !== self::EXPECTED_SIGNED )
		{
			throw new InvalidArgumentException( 'Wrong openid_signed.' );
		}

		if( !str_starts_with( $Arguments[ 'openid_return_to' ], $this->SelfURL ) )
		{
			throw new InvalidArgumentException( 'Wrong openid_return_to.' );
		}

		if( preg_match( '/^https:\/\/steamcommunity.com\/openid\/id\/(?<id>7656119[0-9]{10})\/?$/', $Arguments[ 'openid_identity' ], $CommunityID ) !== 1 )
		{
			throw new InvalidArgumentException( 'Wrong openid_identity.' );
		}

		$Arguments[ 'openid_mode' ] = 'check_authentication'; // Add mode for verification

		[ $Code, $Response ] = $this->SendSteamRequest( $Arguments );

		if( $Code !== 200 )
		{
			if( $Code === 403 || $Code === 429 )
			{
				throw new Exception( 'For some bizzare reason Valve rate limits the OpenID endpoint, and thus we are currently unable to verify your login. Please try again in a couple of minutes.' );
			}

			throw new Exception( 'Failed to verify your login with Steam, it could be down (HTTP ' . $Code . '). Please try again in a couple of minutes. Check Steam\'s status at https://steamstat.us.' );
		}

		$KeyValues = self::ParseKeyValues( $Response );

		if( ( $KeyValues[ 'ns' ] ?? null ) !== self::OPENID_NS )
		{
			throw new Exception( 'Failed to verify login your with Steam, not a valid OpenID 2.0 response.' );
		}

		if( ( $KeyValues[ 'is_valid' ] ?? '' ) !== 'true' )
		{
			throw new Exception( 'Failed to verify login your with Steam.' );
		}

		return $CommunityID[ 'id' ];
	}

	/**
	 * Checks whether the query parameters indicate that the login should be verified.
	 */
	public function ShouldValidate() : bool
	{
		if( $this->InputParameters === null )
		{
			$Mode = filter_input( INPUT_GET, 'openid_mode' );
		}
		else
		{
			$Mode = $this->InputParameters[ 'openid_mode' ] ?? null;
		}

		return $Mode === 'id_res';
	}

	/**
	 * Sends a request to the Steam OpenID server and returns the response to be validated.
	 *
	 * You can override this method to send the request yourself.
	 *
	 * @param array<string, string> $Arguments Parameters to send as POST fields.
	 * @return array{0: int, 1: string} A tuple that contains [http code as int, response as string]
	 */
	public function SendSteamRequest( array $Arguments ) : array
	{
		$c = curl_init( );

		curl_setopt_array( $c, [
			CURLOPT_USERAGENT      => 'OpenID Verification (+https://github.com/xPaw/SteamOpenID.php)',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL            => self::STEAM_LOGIN,
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_TIMEOUT        => 6,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $Arguments,
		] );

		$Response = (string)curl_exec( $c );
		$Code = curl_getinfo( $c, CURLINFO_HTTP_CODE );

		return [ $Code, $Response ];
	}

	/** @return array<string, string> */
	private function GetArguments() : array
	{
		// See https://openid.net/specs/openid-authentication-2_0.html#positive_assertions
		$Filters =
		[
			'openid_mode' => FILTER_UNSAFE_RAW,
			'openid_ns' => FILTER_UNSAFE_RAW,
			'openid_op_endpoint' => FILTER_UNSAFE_RAW,
			'openid_claimed_id' => FILTER_UNSAFE_RAW,
			'openid_identity' => FILTER_UNSAFE_RAW,
			'openid_return_to' => FILTER_UNSAFE_RAW, // Should equal to url we sent
			'openid_response_nonce' => FILTER_UNSAFE_RAW,
			'openid_assoc_handle' => FILTER_UNSAFE_RAW, // Steam just sends 1234567890
			'openid_signed' => FILTER_UNSAFE_RAW,
			'openid_sig' => FILTER_SANITIZE_URL
		];

		if( $this->InputParameters === null )
		{
			$Arguments = filter_input_array( INPUT_GET, $Filters );
		}
		else
		{
			$Arguments = filter_var_array( $this->InputParameters, $Filters );
		}

		if( !is_array( $Arguments ) ) // @phpstan-ignore-line
		{
			throw new InvalidArgumentException( 'Parameter filter failed.' );
		}

		foreach( $Arguments as $Value )
		{
			// An array value will be FALSE if the filter fails, or NULL if the variable is not set.
			// In our case we want everything to be a string.
			if( !is_string( $Value ) )
			{
				throw new InvalidArgumentException( 'One of the parameters were unexpected.' );
			}
		}

		return $Arguments;
	}

	/** @return array<string, string> */
	private static function ParseKeyValues( string $Response ) : array
	{
		// A message in Key-Value form is a sequence of lines. Each line begins with a key,
		// followed by a colon, and the value associated with the key. The line is terminated
		// by a single newline (UCS codepoint 10, "\n"). A key or value MUST NOT contain a
		// newline and a key also MUST NOT contain a colon.
		$ResponseLines = explode( "\n", $Response );
		$ResponseKeys = [];

		foreach( $ResponseLines as $Line )
		{
			$Pair = explode( ':', $Line, 2 );

			if( !isset( $Pair[ 1 ] ) )
			{
				continue;
			}

			$ResponseKeys[ $Pair[ 0 ] ] = $Pair[ 1 ];
		}

		return $ResponseKeys;
	}
}
