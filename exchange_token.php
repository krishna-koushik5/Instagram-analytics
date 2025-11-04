<?php
	include 'defines.php';

	// Token to exchange (from user)
	$shortLivedToken = 'EAALhtWZA8t3wBPZB0kGAWb1dQ7lKsTC8XyZAOz0LpX2ZAQJIatqTqzvv4XOvpeQtsZA0hekYQUN63SeZCg8wQnV2FkKCyIkAAoCy36bFQvgz1JZCZBZArXRxxsC3fT046XbRiPYzRcsEkWrcVDJwOfMIcvh9k7QRqlgWEAgWVSMTEUZAvtZC5vt0wmiI3OcfRkk43wL1C1f11TBfSZBWswOtfsfU6RyB5NIIdieqx6NZBstniakHAOECEtZABP5ihwtEkkDJdZBZBIYdg5ZCMgqK4RGZAh8L02fgmNq4ZCG';

	// Exchange endpoint
	$exchangeEndpoint = 'https://graph.facebook.com/v5.0/oauth/access_token';

	// Parameters for exchange
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

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Token Exchange Result</title>
		<meta charset="utf-8" />
		<style>
			body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
			.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
			.token-box { background: #f0f0f0; padding: 15px; border-radius: 4px; margin: 20px 0; word-break: break-all; font-family: monospace; font-size: 12px; }
			.error { color: red; padding: 10px; background: #ffe6e6; border-left: 3px solid red; }
			.success { color: green; padding: 10px; background: #e6ffe6; border-left: 3px solid green; }
			.info { color: #666; padding: 10px; background: #f0f0f0; border-left: 3px solid #666; }
			.code { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
		</style>
	</head>
	<body>
		<div class="container">
			<h1>🔑 Token Exchange Result</h1>
			<hr />

			<?php if ( isset( $responseArray['access_token'] ) ) : ?>
				<div class="success">
					<h3>✅ Success! Long-Lived Token Generated</h3>
					<?php if ( isset( $responseArray['expires_in'] ) ) : ?>
						<p><strong>Token expires in:</strong> <?php echo number_format( $responseArray['expires_in'] / 86400, 0 ); ?> days (<?php echo number_format( $responseArray['expires_in'] / 3600, 0 ); ?> hours)</p>
					<?php endif; ?>
					
					<p><strong>Your Long-Lived Access Token:</strong></p>
					<div class="token-box"><?php echo htmlspecialchars( $responseArray['access_token'] ); ?></div>
					
					<div class="info">
						<h3>📝 Next Steps:</h3>
						<ol>
							<li>Copy the token above</li>
							<li>Open <code>defines.php</code> file</li>
							<li>Find the line: <code>$accessToken = '...';</code></li>
							<li>Replace it with:</li>
						</ol>
						<div class="code">
							$accessToken = '<?php echo htmlspecialchars( $responseArray['access_token'] ); ?>';
						</div>
						<ol start="5">
							<li>Save the file</li>
							<li>Your token will be valid for ~60 days!</li>
						</ol>
					</div>
				</div>

			<?php elseif ( isset( $responseArray['error'] ) ) : ?>
				<div class="error">
					<h3>❌ Error:</h3>
					<p><strong>Message:</strong> <?php echo htmlspecialchars( $responseArray['error']['message'] ); ?></p>
					<?php if ( isset( $responseArray['error']['code'] ) ) : ?>
						<p><strong>Code:</strong> <?php echo htmlspecialchars( $responseArray['error']['code'] ); ?></p>
					<?php endif; ?>
					
					<?php if ( isset( $responseArray['error']['type'] ) ) : ?>
						<p><strong>Type:</strong> <?php echo htmlspecialchars( $responseArray['error']['type'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="info">
					<h3>💡 Troubleshooting:</h3>
					<ul>
						<li>Make sure your token is valid and not expired</li>
						<li>Get a fresh token from <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
						<li>Make sure your App ID and App Secret in <code>defines.php</code> are correct</li>
					</ul>
				</div>

			<?php else : ?>
				<div class="error">
					<h3>⚠️ Unexpected Response:</h3>
					<pre><?php print_r( $responseArray ); ?></pre>
				</div>
			<?php endif; ?>

			<hr />
			<div class="info">
				<h3>ℹ️ About Long-Lived Tokens:</h3>
				<ul>
					<li><strong>Duration:</strong> Long-lived tokens typically last 60 days</li>
					<li><strong>Renewal:</strong> You can exchange a long-lived token for a new one before it expires</li>
					<li><strong>Extension:</strong> Using the token regularly may extend its expiration automatically</li>
				</ul>
			</div>
		</div>
	</body>
</html>

