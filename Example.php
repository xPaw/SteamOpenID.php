<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use xPaw\Steam\SteamOpenID;

// The URL for the user to return to (this page), this is also validated that the return_to parameter starts with this
$ReturnToUrl = 'https://' . $_SERVER[ 'HTTP_HOST' ] . '/SteamOpenID/Example.php';

$SteamOpenID = new SteamOpenID( $ReturnToUrl );

if( $SteamOpenID->ShouldValidate() )
{
	try
	{
		$CommunityID = $SteamOpenID->Validate();

		// Login succeeded, $CommunityID is the 64-bit SteamID
		echo 'Logged in as ' . $CommunityID;
	}
	catch( InvalidArgumentException $e )
	{
		// If user messed with the url, you probably shouldn't display this message to the user
		echo 'Invalid argument: ' . $e->getMessage();
	}
	catch( Exception $e )
	{
		// Login failed because failed to validate it against Steam
		echo 'Login failed: ' . $e->getMessage();
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
		<input type="hidden" name="openid.return_to" value="<?php echo $ReturnToUrl; ?>">
		<input type="image" name="submit" src="https://steamcommunity-a.akamaihd.net/public/images/signinthroughsteam/sits_01.png" border="0" alt="Submit">
	</form>
<?php
}
