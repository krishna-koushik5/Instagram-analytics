<?php
	// Exchange token and automatically update defines.php
	$shortLivedToken = 'EAALhtWZA8t3wBP4ZCkVZBIXYpAkEZAZCE27lo5QzU1S0G2OF8fVdRAVOBjckzJ1mmL5bx3HP1KlXwIctrZBj31sWpdEi5mXhFstEQ9ZCwz8Q6jdghWwsZCH90BFCXpediYG6DdEqV6JMa69zpwJmuXmYHYZBbYZAl1A48vbD55wDNKKsDKVmbthOgWKcmZCjX5b45GH1VZC3ZA9jLwx3xMwU2';
	
	$appId = '811119178200956';
	$appSecret = '3786a2ce284d62ef2652851bfa6b0dff';
	
	// Exchange endpoint
	$exchangeEndpoint = 'https://graph.facebook.com/v5.0/oauth/access_token';
	
	// Parameters
	$params = array(
		'grant_type' => 'fb_exchange_token',
		'client_id' => $appId,
		'client_secret' => $appSecret,
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
	
?>
<!DOCTYPE html>
<html>
<head>
	<title>Auto Update Token</title>
	<meta charset="utf-8" />
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		.success { color: green; padding: 10px; background: #e6ffe6; border-left: 3px solid green; }
		.error { color: red; padding: 10px; background: #ffe6e6; border-left: 3px solid red; }
		.token-box { background: #f0f0f0; padding: 15px; border-radius: 4px; margin: 20px 0; word-break: break-all; font-family: monospace; font-size: 12px; }
	</style>
</head>
<body>
	<div class="container">
		<h1>🔑 Auto Update Token</h1>
		<hr />
		
		<?php
		if ( isset( $responseArray['access_token'] ) ) {
			$newToken = $responseArray['access_token'];
			$expiresIn = isset( $responseArray['expires_in'] ) ? number_format( $responseArray['expires_in'] / 86400, 0 ) : 'N/A';
			
			// Read defines.php
			$definesFile = 'defines.php';
			$definesContent = file_get_contents( $definesFile );
			
			// Replace the token
			$pattern = "/\\\$accessToken = '[^']*';/";
			$replacement = "\$accessToken = '" . $newToken . "';";
			
			if ( preg_match( $pattern, $definesContent ) ) {
				$newContent = preg_replace( $pattern, $replacement, $definesContent );
				
				// Write to file
				if ( file_put_contents( $definesFile, $newContent ) ) {
					echo '<div class="success">';
					echo '<h3>✅ SUCCESS! Token automatically updated in defines.php</h3>';
					echo '<p><strong>Token expires in:</strong> ' . $expiresIn . ' days</p>';
					echo '<p><strong>New Long-Lived Token:</strong></p>';
					echo '<div class="token-box">' . htmlspecialchars( $newToken ) . '</div>';
					echo '<p>✅ The token has been automatically updated in <code>defines.php</code></p>';
					echo '<p>🔄 <strong>Refresh your insights page now!</strong></p>';
					echo '</div>';
				} else {
					echo '<div class="error">';
					echo '<h3>❌ Error: Could not write to defines.php</h3>';
					echo '<p>Please check file permissions.</p>';
					echo '<p><strong>New Token (copy this manually):</strong></p>';
					echo '<div class="token-box">' . htmlspecialchars( $newToken ) . '</div>';
					echo '</div>';
				}
			} else {
				echo '<div class="error">';
				echo '<h3>❌ Error: Could not find token pattern in defines.php</h3>';
				echo '<p><strong>New Token (copy this manually):</strong></p>';
				echo '<div class="token-box">' . htmlspecialchars( $newToken ) . '</div>';
				echo '</div>';
			}
		} else {
			echo '<div class="error">';
			echo '<h3>❌ Error: Could not exchange token</h3>';
			if ( isset( $responseArray['error'] ) ) {
				echo '<p><strong>Message:</strong> ' . htmlspecialchars( $responseArray['error']['message'] ) . '</p>';
				if ( isset( $responseArray['error']['code'] ) ) {
					echo '<p><strong>Code:</strong> ' . htmlspecialchars( $responseArray['error']['code'] ) . '</p>';
				}
			} else {
				echo '<pre>' . print_r( $responseArray, true ) . '</pre>';
			}
			echo '<p>💡 The token might be expired. Get a fresh token from <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></p>';
			echo '</div>';
		}
		?>
		
		<hr />
		<p><a href="get_multiple_insights.php">← Back to Insights</a></p>
	</div>
</body>
</html>

