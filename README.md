# Steam OpenID Authentication for PHP [![Packagist](https://img.shields.io/packagist/dt/xpaw/steam-openid.svg)](https://packagist.org/packages/xpaw/steam-openid) [![codecov](https://codecov.io/gh/xPaw/SteamOpenID.php/graph/badge.svg?token=TA8tz7bpHy)](https://codecov.io/gh/xPaw/SteamOpenID.php)

A very minimalistic OpenID implementation that hardcodes it for Steam only,
as using a generic OpenID library may do unnecessary steps of “discovering”
OpenID servers, which will end up leaking your origin server address, and worse,
leave your website open to vulnerabilities of claiming wrong Steam profiles if the implementation is bugged.

The described problems are not theoretical, LightOpenID
[has been proven](https://twitter.com/thexpaw/status/1088207320977412097)
to have implementation problems, and even if you use `validate` and use regex on the final
`identity` it can be spoofed and a third-party server can be used to pass the validation.

This library aims to avoid these problems by implementing the bare minimum functionality required
for validating Steam OpenID requests against the hardcoded Steam server. This library only implements
validation, you will need to implement Steam WebAPI calls yourself to fetch user profile information.

Before using this library, [please read Valve's terms here](https://steamcommunity.com/dev).

## Installation

`composer require xpaw/steam-openid`

See [Example.php](Example.php) file for example usage.

### Basic usage

```php
use xPaw\Steam\SteamOpenID;

$SteamOpenID = new SteamOpenID( $ReturnToUrl );

if( $SteamOpenID->ShouldValidate() )
{
	try
	{
		$CommunityID = $SteamOpenID->Validate();
		echo 'Logged in as ' . $SteamID;
	}
	catch( Exception $e )
	{
		echo 'Login failed';
	}
}
else
{
	header( 'Location: ' . $SteamOpenID->GetAuthUrl() );
}
```

If you want to parse SteamIDs, take a look at [SteamID.php](https://github.com/xPaw/SteamID.php).
