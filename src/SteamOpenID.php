<?php
declare(strict_types=1);

namespace xPaw\Steam;

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
	/*
	 * Validates OpenID data, and verifies with Steam
	 *
	 * @param string $SelfURL Full URL of where the login page is
	 *
	 * @return ?string Returns the 64-bit SteamID if successful or null on failure
	 */
	public static function ValidateLogin( string $SelfURL ) : ?string
	{
		// PHP automatically replaces dots with underscores in GET parameters
		// See https://www.php.net/variables.external#language.variables.external.dot-in-names
		if( filter_input( INPUT_GET, 'openid_mode' ) !== 'id_res' )
		{
			return null;
		}

		// See http://openid.net/specs/openid-authentication-2_0.html#positive_assertions
		$Arguments = filter_input_array( INPUT_GET, [
			'openid_ns' => FILTER_SANITIZE_URL,
			'openid_op_endpoint' => FILTER_SANITIZE_URL,
			'openid_claimed_id' => FILTER_SANITIZE_URL,
			'openid_identity' => FILTER_SANITIZE_URL,
			'openid_return_to' => FILTER_SANITIZE_URL, // Should equal to url we sent
			'openid_response_nonce' => FILTER_SANITIZE_SPECIAL_CHARS,
			'openid_assoc_handle' => FILTER_SANITIZE_SPECIAL_CHARS, // Steam just sends 1234567890
			'openid_signed' => FILTER_SANITIZE_SPECIAL_CHARS,
			'openid_sig' => FILTER_SANITIZE_SPECIAL_CHARS
		], true );

		if( !is_array( $Arguments ) )
		{
			return null;
		}

		foreach( $Arguments as $Value )
		{
			// An array value will be FALSE if the filter fails, or NULL if the variable is not set.
			// In our case we want everything to be a string.
			if( !is_string( $Value ) )
			{
				return null;
			}
		}

		if( $Arguments[ 'openid_claimed_id' ] !== $Arguments[ 'openid_identity' ]
		|| $Arguments[ 'openid_op_endpoint' ] !== 'https://steamcommunity.com/openid/login'
		|| $Arguments[ 'openid_ns' ] !== 'http://specs.openid.net/auth/2.0'
		|| strpos( $Arguments[ 'openid_return_to' ], $SelfURL ) !== 0
		|| preg_match( '/^https?:\/\/steamcommunity.com\/openid\/id\/(7656119[0-9]{10})\/?$/', $Arguments[ 'openid_identity' ], $CommunityID ) !== 1
		)
		{
			return null;
		}

		$Arguments[ 'openid_mode' ] = 'check_authentication';

		$c = curl_init( );

		curl_setopt_array( $c, [
			CURLOPT_USERAGENT      => 'OpenID Verification (+https://github.com/xPaw/SteamOpenID.php)',
			CURLOPT_URL            => 'https://steamcommunity.com/openid/login',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_TIMEOUT        => 6,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $Arguments,
		] );

		$Response = curl_exec( $c );
		$Code = curl_getinfo( $c, CURLINFO_HTTP_CODE );

		curl_close( $c );

		if( $Code !== 200 )
		{
			return null;
		}

		$KeyValues = self::ParseKeyValues( (string)$Response );

		if( ( $KeyValues[ 'ns' ] ?? null ) !== 'http://specs.openid.net/auth/2.0' )
		{
			return null;
		}

		if( ( $KeyValues[ 'is_valid' ] ?? null ) !== 'true' )
		{
			return null;
		}

		return $CommunityID[ 1 ];
	}

	/** @return array<string, string> */
	private static function ParseKeyValues( string $Response ) : array
	{
		// A message in Key-Value form is a sequence of lines. Each line begins with a key,
		// followed by a colon, and the value associated with the key. The line is terminated
		// by a single newline (UCS codepoint 10, "\n"). A key or value MUST NOT contain a
		// newline and a key also MUST NOT contain a colon.
		$ResponseLines = explode( "\n", (string)$Response );
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
