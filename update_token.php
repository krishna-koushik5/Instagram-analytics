<?php
	include 'defines.php';

	// Your short-lived token to exchange
	$shortLivedToken = 'EAALhtWZA8t3wBPZB0kGAWb1dQ7lKsTC8XyZAOz0LpX2ZAQJIatqTqzvv4XOvpeQtsZA0hekYQUN63SeZCg8wQnV2FkKCyIkAAoCy36bFQvgz1JZCZBZArXRxxsC3fT046XbRiPYzRcsEkWrcVDJwOfMIcvh9k7QRqlgWEAgWVSMTEUZAvtZC5vt0wmiI3OcfRkk43wL1C1f11TBfSZBWswOtfsfU6RyB5NIIdieqx6NZBstniakHAOECEtZABP5ihwtEkkDJdZBZBIYdg5ZCMgqK4RGZAh8L02fgmNq4ZCG';

	// Exchange endpoint
	$exchangeEndpoint = 'https://graph.facebook.com/v5.0/oauth/access_token';

	// Parameters
	$params = array(
		'grant_type' => 'fb_exchange_token',
		'client_id' => FACEBOOK_APP_ID,
		'client_secret' => FACEBOOK_APP_SECRET,
		'fb_exchange_token' => $shortLivedToken
	);

	// Make API call
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $exchangeEndpoint . '?' . http_build_query( $params ) );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

	$response = curl_exec( $ch );
	curl_close( $ch );

	$responseArray = json_decode( $response, true );

	// If successful, update defines.php
	if ( isset( $responseArray['access_token'] ) ) {
		$newToken = $responseArray['access_token'];
		$definesFile = 'defines.php';
		
		// Read the file
		$definesContent = file_get_contents( $definesFile );
		
		// Replace the token
		$pattern = "/\\\$accessToken = '[^']*';/";
		$replacement = "\$accessToken = '" . $newToken . "';";
		
		if ( preg_match( $pattern, $definesContent ) ) {
			$newContent = preg_replace( $pattern, $replacement, $definesContent );
			file_put_contents( $definesFile, $newContent );
			
			echo "✅ SUCCESS! Token updated in defines.php\n";
			echo "Token expires in: " . ( isset( $responseArray['expires_in'] ) ? number_format( $responseArray['expires_in'] / 86400, 0 ) . " days" : "N/A" ) . "\n";
			echo "New token: " . substr( $newToken, 0, 50 ) . "...\n";
		} else {
			echo "❌ ERROR: Could not find token pattern in defines.php\n";
		}
	} else {
		echo "❌ ERROR: Could not exchange token\n";
		if ( isset( $responseArray['error'] ) ) {
			echo "Message: " . $responseArray['error']['message'] . "\n";
		} else {
			print_r( $responseArray );
		}
	}

