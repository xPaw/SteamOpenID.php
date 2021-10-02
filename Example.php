<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use xPaw\Steam\SteamOpenID;

if( isset( $_GET[ 'openid_claimed_id' ] ) )
{
	$CommunityID = SteamOpenID::ValidateLogin( 'https://localhost/login/Example.php' );

	if( $CommunityID === null )
	{
		// Login failed because of invalid data or Steam server failed to reply
	}
	else
	{
		// Login succeeded, $CommunityID is the 64-bit SteamID
	}
}
else
{
	// Show login form
?>
	<form action="https://steamcommunity.com/openid/login" method="post">
		<input type="hidden" name="openid.identity" value="http://specs.openid.net/auth/2.0/identifier_select">
		<input type="hidden" name="openid.claimed_id" value="http://specs.openid.net/auth/2.0/identifier_select">
		<input type="hidden" name="openid.ns" value="http://specs.openid.net/auth/2.0">
		<input type="hidden" name="openid.mode" value="checkid_setup">
		<input type="hidden" name="openid.realm" value="https://localhost/">
		<input type="hidden" name="openid.return_to" value="https://localhost/login/Example.php">
		<input type="image" name="submit" src="https://steamcommunity-a.akamaihd.net/public/images/signinthroughsteam/sits_01.png" border="0" alt="Submit">
	</form>
<?php
}
