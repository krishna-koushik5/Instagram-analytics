<?php
	include 'defines.php';

	/**
	 * Make a curl call to an endpoint with params
	 *
	 * @param string $endpoint we are hitting
	 * @param string $type of request
	 * @param array $params to send along with the request
	 *
	 * @return array with the api response
	 */
	function makeApiCall( $endpoint, $type, $params ) {
		// initialize curl
		$ch = curl_init();

		// combine endpoint and params and set other curl options
		curl_setopt( $ch, CURLOPT_URL, $endpoint . '?' . http_build_query( $params ) );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		// get response
		$response = curl_exec( $ch );

		// close curl
		curl_close( $ch );

		// json decode and return response
		return json_decode( $response, true );
	}

	// Get long-lived access token endpoint
	$endpointFormat = 'https://graph.facebook.com/v5.0/oauth/access_token?grant_type=fb_exchange_token&client_id={app-id}&client_secret={app-secret}&fb_exchange_token={short-lived-token}';
	$longLivedTokenEndpoint = 'https://graph.facebook.com/v5.0/oauth/access_token';

	// endpoint params
	$params = array(
		'grant_type' => 'fb_exchange_token',
		'client_id' => FACEBOOK_APP_ID,
		'client_secret' => FACEBOOK_APP_SECRET,
		'fb_exchange_token' => $accessToken  // Your current short-lived token
	);

	// Get long-lived token
	$response = makeApiCall( $longLivedTokenEndpoint, 'GET', $params );

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Get Long-Lived Access Token</title>
		<meta charset="utf-8" />
		<style>
			body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
			.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
			.token-box { background: #f0f0f0; padding: 15px; border-radius: 4px; margin: 20px 0; word-break: break-all; font-family: monospace; }
			.error { color: red; padding: 10px; background: #ffe6e6; border-left: 3px solid red; }
			.success { color: green; padding: 10px; background: #e6ffe6; border-left: 3px solid green; }
			.info { color: #666; padding: 10px; background: #f0f0f0; border-left: 3px solid #666; }
			.button { display: inline-block; padding: 10px 20px; background: #4267B2; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
			.button:hover { background: #365899; }
		</style>
	</head>
	<body>
		<div class="container">
			<h1>🔑 Get Long-Lived Access Token</h1>
			<hr />

			<?php if ( isset( $response['error'] ) ) : ?>
				<div class="error">
					<h3>Error:</h3>
					<p><strong>Message:</strong> <?php echo htmlspecialchars( $response['error']['message'] ); ?></p>
					<?php if ( isset( $response['error']['code'] ) ) : ?>
						<p><strong>Code:</strong> <?php echo htmlspecialchars( $response['error']['code'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="info">
					<h3>⚠️ If your token has expired:</h3>
					<p>You need to get a <strong>new short-lived token</strong> first, then exchange it for a long-lived one.</p>
					<p><strong>Option 1: Use Graph API Explorer</strong></p>
					<ol>
						<li>Go to <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
						<li>Select your app (ID: <?php echo FACEBOOK_APP_ID; ?>)</li>
						<li>Click "Generate Access Token"</li>
						<li>Select these permissions:
							<ul>
								<li><code>instagram_basic</code></li>
								<li><code>instagram_manage_insights</code></li>
								<li><code>pages_show_list</code></li>
								<li><code>pages_read_engagement</code></li>
								<li><code>business_management</code></li>
							</ul>
						</li>
						<li>Copy the generated token</li>
						<li>Paste it below and click "Exchange for Long-Lived Token"</li>
					</ol>
					<p><strong>Option 2: Use OAuth Login</strong></p>
					<p>If you have <code>obtaining_access_token.php</code> set up with the Facebook SDK, you can use that instead.</p>
				</div>

				<hr />
				<h3>🔄 Exchange Short-Lived Token for Long-Lived Token</h3>
				<form method="POST" action="">
					<p>
						<label for="short_token">Enter your short-lived token (from Graph API Explorer):</label><br />
						<textarea id="short_token" name="short_token" rows="3" style="width: 100%; padding: 10px; font-family: monospace;" placeholder="Paste your short-lived token here"></textarea>
					</p>
					<button type="submit" class="button">Exchange for Long-Lived Token</button>
				</form>

				<?php
				// Handle form submission
				if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['short_token'] ) ) {
					$shortToken = trim( $_POST['short_token'] );
					
					if ( !empty( $shortToken ) ) {
						$params['fb_exchange_token'] = $shortToken;
						$response = makeApiCall( $longLivedTokenEndpoint, 'GET', $params );
						
						if ( isset( $response['access_token'] ) ) {
							echo '<div class="success">';
							echo '<h3>✅ Success! Long-Lived Token Generated</h3>';
							echo '<p><strong>Token expires in:</strong> ' . ( isset( $response['expires_in'] ) ? number_format( $response['expires_in'] / 86400, 0 ) . ' days' : 'N/A' ) . '</p>';
							echo '<p><strong>Copy this token and paste it into <code>defines.php</code>:</strong></p>';
							echo '<div class="token-box">' . htmlspecialchars( $response['access_token'] ) . '</div>';
							echo '</div>';
						} else {
							echo '<div class="error">';
							echo '<h3>Error exchanging token:</h3>';
							if ( isset( $response['error'] ) ) {
								echo '<p>' . htmlspecialchars( $response['error']['message'] ) . '</p>';
							} else {
								echo '<pre>' . print_r( $response, true ) . '</pre>';
							}
							echo '</div>';
						}
					}
				}
				?>

			<?php elseif ( isset( $response['access_token'] ) ) : ?>
				<div class="success">
					<h3>✅ Success! Long-Lived Token Generated</h3>
					<p><strong>Token expires in:</strong> <?php echo isset( $response['expires_in'] ) ? number_format( $response['expires_in'] / 86400, 0 ) . ' days' : 'N/A'; ?></p>
					<p><strong>Copy this token and paste it into <code>defines.php</code>:</strong></p>
					<div class="token-box"><?php echo htmlspecialchars( $response['access_token'] ); ?></div>
					
					<h4>📝 Instructions:</h4>
					<ol>
						<li>Copy the token above</li>
						<li>Open <code>defines.php</code></li>
						<li>Find the line: <code>$accessToken = '...';</code></li>
						<li>Replace the token with the new one above</li>
						<li>Save the file</li>
					</ol>
				</div>

				<div class="info">
					<h3>ℹ️ About Long-Lived Tokens:</h3>
					<ul>
						<li><strong>Expiration:</strong> Long-lived tokens typically last 60 days</li>
						<li><strong>Renewal:</strong> You can exchange a long-lived token for a new one before it expires</li>
						<li><strong>Extension:</strong> If you use the token regularly, it may be automatically extended</li>
					</ul>
				</div>

			<?php else : ?>
				<div class="error">
					<h3>Unexpected Response:</h3>
					<pre><?php print_r( $response ); ?></pre>
				</div>
			<?php endif; ?>

			<hr />
			<div class="info">
				<h3>📚 Additional Resources:</h3>
				<ul>
					<li><a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a> - Generate short-lived tokens</li>
					<li><a href="https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived" target="_blank">Facebook Documentation - Long-Lived Tokens</a></li>
				</ul>
			</div>
		</div>
	</body>
</html>

