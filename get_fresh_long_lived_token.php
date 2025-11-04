<?php
	// Get a fresh long-lived token from a short-lived token
	// This script will help you exchange your token
	
	$appId = '811119178200956';
	$appSecret = '3786a2ce284d62ef2652851bfa6b0dff';
	
	// Get short-lived token from user input or use current token
	$shortLivedToken = isset( $_POST['token'] ) ? trim( $_POST['token'] ) : '';
	
	if ( empty( $shortLivedToken ) && isset( $_GET['token'] ) ) {
		$shortLivedToken = trim( $_GET['token'] );
	}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Get Long-Lived Token</title>
	<meta charset="utf-8" />
	<style>
		body { 
			font-family: Arial, sans-serif; 
			margin: 20px; 
			background: #0f0f0f; 
			color: #ffffff; 
		}
		.container { 
			max-width: 800px; 
			margin: 0 auto; 
			background: #181818; 
			padding: 20px; 
			border-radius: 8px; 
			box-shadow: 0 2px 4px rgba(0,0,0,0.3); 
		}
		.form-group {
			margin-bottom: 20px;
		}
		label {
			display: block;
			margin-bottom: 5px;
			color: #aaaaaa;
		}
		input[type="text"] {
			width: 100%;
			padding: 12px;
			background: #212121;
			border: 1px solid #303030;
			border-radius: 4px;
			color: #ffffff;
			font-size: 14px;
			box-sizing: border-box;
		}
		button {
			background: #3ea6ff;
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 4px;
			cursor: pointer;
			font-size: 16px;
			font-weight: bold;
		}
		button:hover {
			background: #5cb8ff;
		}
		.token-box {
			background: #212121;
			border: 1px solid #303030;
			border-radius: 4px;
			padding: 15px;
			margin: 20px 0;
			word-break: break-all;
			font-family: monospace;
			font-size: 12px;
			color: #3ea6ff;
		}
		.success {
			color: #4caf50;
			padding: 15px;
			background: #1a3d1a;
			border-left: 3px solid #4caf50;
			border-radius: 4px;
			margin: 20px 0;
		}
		.error {
			color: #ff4444;
			padding: 15px;
			background: #3d1a1a;
			border-left: 3px solid #ff4444;
			border-radius: 4px;
			margin: 20px 0;
		}
		.info {
			color: #aaaaaa;
			padding: 15px;
			background: #212121;
			border-left: 3px solid #3ea6ff;
			border-radius: 4px;
			margin: 20px 0;
		}
		a {
			color: #3ea6ff;
			text-decoration: none;
		}
		a:hover {
			text-decoration: underline;
		}
	</style>
</head>
<body>
	<div class="container">
		<h1>🔑 Get Long-Lived Access Token</h1>
		<p style="color: #aaaaaa;">Exchange a short-lived token for a long-lived token (60 days)</p>
		
		<?php if ( empty( $shortLivedToken ) ): ?>
			<div class="info">
				<h3>📝 How to Get a Short-Lived Token:</h3>
				<ol>
					<li>Go to <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
					<li>Select your app: <strong>811119178200956</strong></li>
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
					<li>Copy the generated token and paste it below</li>
				</ol>
			</div>
			
			<form method="POST">
				<div class="form-group">
					<label for="token">Short-Lived Access Token:</label>
					<input type="text" id="token" name="token" placeholder="EAALhtWZA8t3wBP..." required />
				</div>
				<button type="submit">Exchange for Long-Lived Token</button>
			</form>
		<?php else: ?>
			<?php
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
				
				if ( isset( $responseArray['access_token'] ) ) {
					$longLivedToken = $responseArray['access_token'];
					$expiresIn = isset( $responseArray['expires_in'] ) ? $responseArray['expires_in'] : 0;
					$expiresInDays = number_format( $expiresIn / 86400, 0 );
					
					echo '<div class="success">';
					echo '<h3>✅ SUCCESS! Long-Lived Token Generated</h3>';
					echo '<p><strong>Token expires in:</strong> ' . $expiresInDays . ' days (' . number_format( $expiresIn / 3600, 0 ) . ' hours)</p>';
					echo '<p><strong>Copy this token and add it to Render environment variables:</strong></p>';
					echo '<div class="token-box">' . htmlspecialchars( $longLivedToken ) . '</div>';
					echo '<p><strong>Steps:</strong></p>';
					echo '<ol>';
					echo '<li>Copy the token above</li>';
					echo '<li>Go to Render Dashboard → Your Service → Environment</li>';
					echo '<li>Edit the <code>accessToken</code> variable</li>';
					echo '<li>Paste the new token (without quotes)</li>';
					echo '<li>Save and wait for redeploy</li>';
					echo '</ol>';
					echo '</div>';
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
					echo '<p>💡 The token might be expired or invalid. Get a fresh token from <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></p>';
					echo '</div>';
				}
			?>
			
			<hr style="border-color: #303030; margin: 30px 0;" />
			<p><a href="get_fresh_long_lived_token.php">← Try Again</a></p>
		<?php endif; ?>
		
		<hr style="border-color: #303030; margin: 30px 0;" />
		<p><a href="get_multiple_insights.php">← Back to Insights</a></p>
	</div>
</body>
</html>

