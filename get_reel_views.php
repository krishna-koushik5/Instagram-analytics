<?php
	include 'load_config.php';

	// Function to extract media ID or shortcode from Instagram URL
	function extractMediaIdFromUrl( $url ) {
		// Patterns for different Instagram URL formats:
		// https://www.instagram.com/reel/ABC123xyz/
		// https://www.instagram.com/p/ABC123xyz/
		// https://instagram.com/reel/ABC123xyz/
		// https://www.instagram.com/reels/ABC123xyz/
		
		$patterns = array(
			'/instagram\.com\/(?:reels?|p)\/([A-Za-z0-9_-]+)/',
			'/instagram\.com\/.*\/reels?\/?([A-Za-z0-9_-]+)/'
		);
		
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				return $matches[1]; // Returns the shortcode
			}
		}
		
		return false;
	}

	// Function to get media ID from shortcode (or use shortcode directly)
	function getMediaFromShortcode( $shortcode, $instagramAccountId, $accessToken ) {
		// First, try to get media info using business_discovery with media fields
		// We'll need to find which account owns this media
		// For now, let's try getting it directly by shortcode
		
		// Alternative: Use the media endpoint with the shortcode
		// Instagram Graph API allows querying by shortcode
		$endpoint = ENDPOINT_BASE . $shortcode;
		$params = array(
			'fields' => 'id,media_type,permalink,like_count,comments_count',
			'access_token' => $accessToken
		);
		
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $endpoint . '?' . http_build_query( $params ) );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		
		$response = curl_exec( $ch );
		curl_close( $ch );
		
		return json_decode( $response, true );
	}

	// Function to get views for a specific media using business_discovery
	function getPublicReelViews( $shortcode, $username, $instagramAccountId, $accessToken ) {
		// Try to get media with view_count from business_discovery
		// Note: video_view_count might not be available for all accounts
		$endpoint = ENDPOINT_BASE . $instagramAccountId;
		$params = array(
			'fields' => 'business_discovery.username(' . $username . '){media{id,media_type,permalink,like_count,comments_count,media_url,video_view_count}}',
			'access_token' => $accessToken
		);
		
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $endpoint . '?' . http_build_query( $params ) );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		
		$response = curl_exec( $ch );
		curl_close( $ch );
		
		$result = json_decode( $response, true );
		
		// Find the media with matching shortcode in permalink
		if ( isset( $result['business_discovery']['media']['data'] ) ) {
			foreach ( $result['business_discovery']['media']['data'] as $media ) {
				if ( isset( $media['permalink'] ) && strpos( $media['permalink'], $shortcode ) !== false ) {
					return $media;
				}
			}
		}
		
		return null;
	}

	// Function to get media by ID with insights (for owned accounts)
	function getMediaInsightsById( $mediaId, $accessToken ) {
		$endpoint = ENDPOINT_BASE . $mediaId . '/insights';
		$params = array(
			'metric' => 'views,reach,likes,comments,shares',
			'access_token' => $accessToken
		);
		
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $endpoint . '?' . http_build_query( $params ) );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		
		$response = curl_exec( $ch );
		curl_close( $ch );
		
		return json_decode( $response, true );
	}

	// Process form submission
	$reelUrl = isset( $_POST['reel_url'] ) ? trim( $_POST['reel_url'] ) : '';
	$username = isset( $_POST['username'] ) ? trim( $_POST['username'] ) : '';
	$result = null;
	$error = null;
	
	if ( !empty( $reelUrl ) ) {
		$shortcode = extractMediaIdFromUrl( $reelUrl );
		
		if ( !$shortcode ) {
			$error = 'Invalid Instagram URL. Please provide a valid reel or post URL.';
		} else {
			// Try method 1: Direct media lookup (if we can get media ID)
			$mediaData = getMediaFromShortcode( $shortcode, $instagramAccountId, $accessToken );
			
			if ( isset( $mediaData['id'] ) ) {
				// Got media ID - try to get insights (only works for owned accounts)
				$insights = getMediaInsightsById( $mediaData['id'], $accessToken );
				
				if ( !isset( $insights['error'] ) && isset( $insights['data'] ) ) {
					// Success! We have insights (owned account)
					$result = array(
						'type' => 'owned',
						'media' => $mediaData,
						'insights' => $insights['data']
					);
				} else {
					// Try public method if we have username
					if ( !empty( $username ) ) {
						$publicData = getPublicReelViews( $shortcode, $username, $instagramAccountId, $accessToken );
						
						if ( $publicData && isset( $publicData['video_view_count'] ) ) {
							$result = array(
								'type' => 'public',
								'media' => $publicData
							);
						} else {
							// Fallback: Return what we have
							$result = array(
								'type' => 'public_basic',
								'media' => $mediaData
							);
						}
					} else {
						// Fallback: Return basic data
						$result = array(
							'type' => 'public_basic',
							'media' => $mediaData
						);
					}
				}
			} else {
				// Try public method with username
				if ( !empty( $username ) ) {
					$publicData = getPublicReelViews( $shortcode, $username, $instagramAccountId, $accessToken );
					
					if ( $publicData ) {
						$result = array(
							'type' => 'public',
							'media' => $publicData
						);
					} else {
						$error = 'Could not find the reel. Make sure the URL is correct and the account is a Business/Creator account.';
					}
				} else {
					$error = 'Please provide the Instagram username of the account that posted this reel.';
				}
			}
		}
	}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Get Reel Views - Instagram Analytics</title>
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
		input[type="text"]:focus {
			outline: none;
			border-color: #3ea6ff;
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
		.result-box {
			background: #212121;
			border: 1px solid #303030;
			border-radius: 8px;
			padding: 20px;
			margin-top: 20px;
		}
		.metric {
			display: inline-block;
			margin: 10px 15px 10px 0;
			padding: 10px 15px;
			background: #181818;
			border-left: 3px solid #3ea6ff;
			border-radius: 4px;
		}
		.metric-label {
			font-size: 12px;
			color: #aaaaaa;
			margin-bottom: 5px;
		}
		.metric-value {
			font-size: 24px;
			font-weight: bold;
			color: #3ea6ff;
		}
		.error {
			color: #ff4444;
			padding: 15px;
			background: #3d1a1a;
			border-left: 3px solid #ff4444;
			border-radius: 4px;
		}
		.success {
			color: #4caf50;
			padding: 15px;
			background: #1a3d1a;
			border-left: 3px solid #4caf50;
			border-radius: 4px;
		}
		a {
			color: #3ea6ff;
			text-decoration: none;
		}
		a:hover {
			text-decoration: underline;
		}
		.note {
			color: #aaaaaa;
			font-size: 12px;
			margin-top: 10px;
			padding: 10px;
			background: #212121;
			border-radius: 4px;
		}
	</style>
</head>
<body>
	<div class="container">
		<h1>🎬 Get Reel Views</h1>
		<p style="color: #aaaaaa;">Enter an Instagram reel URL to get view count and other metrics</p>
		
		<form method="POST">
			<div class="form-group">
				<label for="reel_url">Instagram Reel/Post URL:</label>
				<input type="text" id="reel_url" name="reel_url" placeholder="https://www.instagram.com/reel/ABC123xyz/" value="<?php echo htmlspecialchars( $reelUrl ); ?>" required />
			</div>
			
			<div class="form-group">
				<label for="username">Instagram Username (optional, helps for public reels):</label>
				<input type="text" id="username" name="username" placeholder="username" value="<?php echo htmlspecialchars( $username ); ?>" />
				<div class="note">Enter the username of the account that posted this reel. This helps get views for public reels.</div>
			</div>
			
			<button type="submit">Get Views</button>
		</form>
		
		<?php if ( $error ): ?>
			<div class="error">
				<strong>Error:</strong> <?php echo htmlspecialchars( $error ); ?>
			</div>
		<?php endif; ?>
		
		<?php if ( $result ): ?>
			<div class="result-box">
				<h2>📊 Results</h2>
				
				<?php if ( isset( $result['media']['permalink'] ) ): ?>
					<p>
						<a href="<?php echo htmlspecialchars( $result['media']['permalink'] ); ?>" target="_blank">
							View on Instagram →
						</a>
					</p>
				<?php endif; ?>
				
				<div style="margin-top: 20px;">
					<?php
						// Display views
						if ( $result['type'] == 'owned' && isset( $result['insights'] ) ) {
							// Full insights available (owned account)
							foreach ( $result['insights'] as $insight ) {
								$title = isset( $insight['title'] ) ? $insight['title'] : ( isset( $insight['name'] ) ? $insight['name'] : '' );
								$value = isset( $insight['values'][0]['value'] ) ? $insight['values'][0]['value'] : 0;
								
								if ( strtolower( $title ) == 'views' || strtolower( $title ) == 'video views' ) {
									echo '<div class="metric">';
									echo '<div class="metric-label">Views</div>';
									echo '<div class="metric-value">' . number_format( $value ) . '</div>';
									echo '</div>';
								}
							}
							
							// Display other metrics
							foreach ( $result['insights'] as $insight ) {
								$title = isset( $insight['title'] ) ? $insight['title'] : ( isset( $insight['name'] ) ? $insight['name'] : '' );
								$value = isset( $insight['values'][0]['value'] ) ? $insight['values'][0]['value'] : 0;
								
								if ( strtolower( $title ) != 'views' && strtolower( $title ) != 'video views' ) {
									echo '<div class="metric">';
									echo '<div class="metric-label">' . htmlspecialchars( $title ) . '</div>';
									echo '<div class="metric-value">' . number_format( $value ) . '</div>';
									echo '</div>';
								}
							}
						} elseif ( isset( $result['media']['video_view_count'] ) ) {
							// Public views available
							echo '<div class="metric">';
							echo '<div class="metric-label">Views</div>';
							echo '<div class="metric-value">' . number_format( $result['media']['video_view_count'] ) . '</div>';
							echo '</div>';
						}
						
						// Display likes and comments (always available)
						if ( isset( $result['media']['like_count'] ) ) {
							echo '<div class="metric">';
							echo '<div class="metric-label">Likes</div>';
							echo '<div class="metric-value">' . number_format( $result['media']['like_count'] ) . '</div>';
							echo '</div>';
						}
						
						if ( isset( $result['media']['comments_count'] ) ) {
							echo '<div class="metric">';
							echo '<div class="metric-label">Comments</div>';
							echo '<div class="metric-value">' . number_format( $result['media']['comments_count'] ) . '</div>';
							echo '</div>';
						}
					?>
				</div>
				
				<?php if ( $result['type'] == 'public_basic' ): ?>
					<div class="note">
						<strong>Note:</strong> Only basic metrics (likes, comments) are available for this public reel. 
						Views are only available for reels from accounts you own or for public Business/Creator accounts when you provide the username.
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<hr style="border-color: #303030; margin: 30px 0;" />
		<p><a href="get_multiple_insights.php">← Back to Account Insights</a></p>
	</div>
</body>
</html>

