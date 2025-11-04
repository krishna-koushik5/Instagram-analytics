<?php
	include 'defines.php';

	// Your short-lived token
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

	// Display result
	if ( isset( $responseArray['access_token'] ) ) {
		echo "✅ SUCCESS! Long-Lived Token Generated\n";
		echo "==========================================\n\n";
		echo "Token expires in: " . ( isset( $responseArray['expires_in'] ) ? number_format( $responseArray['expires_in'] / 86400, 0 ) . " days" : "N/A" ) . "\n\n";
		echo "Your Long-Lived Access Token:\n";
		echo "----------------------------------------\n";
		echo $responseArray['access_token'] . "\n";
		echo "----------------------------------------\n\n";
		echo "📝 Next Steps:\n";
		echo "1. Copy the token above\n";
		echo "2. Open defines.php\n";
		echo "3. Replace the \$accessToken value with the new token\n";
		echo "4. Save the file\n\n";
		
		// Also update defines.php automatically
		$definesContent = file_get_contents( 'defines.php' );
		$newToken = $responseArray['access_token'];
		$pattern = "/\\\$accessToken = '[^']*';/";
		$replacement = "\$accessToken = '" . $newToken . "';";
		
		if ( preg_match( $pattern, $definesContent ) ) {
			$newContent = preg_replace( $pattern, $replacement, $definesContent );
			file_put_contents( 'defines.php', $newContent );
			echo "✅ Token automatically updated in defines.php!\n";
		} else {
			echo "⚠️  Could not automatically update defines.php. Please update it manually.\n";
		}
	} else {
		echo "❌ ERROR:\n";
		if ( isset( $responseArray['error'] ) ) {
			echo "Message: " . $responseArray['error']['message'] . "\n";
			if ( isset( $responseArray['error']['code'] ) ) {
				echo "Code: " . $responseArray['error']['code'] . "\n";
			}
		} else {
			print_r( $responseArray );
		}
	}

