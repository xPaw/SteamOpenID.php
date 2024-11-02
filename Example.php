<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use xPaw\Steam\SteamOpenID;

// The URL for the user to return to (this page), this is also validated that the return_to parameter starts with this
// Recommend hardcoding this to the actual login url instead of dynamically constructing it
$ReturnToUrl = 'https://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'DOCUMENT_URI' ];

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
	// As a simple url:
	echo 'Url: <a href="' . $SteamOpenID->GetAuthUrl() . '">Sign in through Steam</a>';

	// Show login form, you can also do "get" method instead of "post" here
	echo '<br><br>Form: <form action="' . SteamOpenID::SERVER . '" method="post">';

	foreach( $SteamOpenID->GetAuthParameters() as $Key => $Value )
	{
		echo '<input type="hidden" name="' . $Key . '" value="' . $Value . '">';
	}

	echo '<input type="image" name="submit" src="https://steamcommunity-a.akamaihd.net/public/images/signinthroughsteam/sits_01.png" alt="Sign in through Steam">';
	echo '</form>';
}
