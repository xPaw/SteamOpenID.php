<?php
declare(strict_types=1);

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
			'openid_response_nonce' => FILTER_SANITIZE_STRING,
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
			CURLOPT_USERAGENT      => 'Steam Database OpenID Login',
			CURLOPT_URL            => 'https://steamcommunity.com/openid/login',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_TIMEOUT        => 6,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $Arguments,
		] );

		$Response = curl_exec( $c );

		StatsD::curlRequest( 'steam.openid', $c );

		curl_close( $c );

		if( $Response !== false && strrpos( $Response, 'is_valid:true' ) !== false )
		{
			return $CommunityID[ 1 ];
		}

		return null;
	}
}
