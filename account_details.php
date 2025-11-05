<?php
	// Increase execution time for detailed analytics
	set_time_limit( 300 ); // 5 minutes
	
	// Load credentials from environment variables (Render) or defines.php (local)
	include 'load_config.php';
	
	// Get account parameter from URL
	$accountUsername = isset( $_GET['account'] ) ? trim( $_GET['account'] ) : '';
	
	if ( empty( $accountUsername ) ) {
		header( 'Location: get_multiple_insights.php' );
		exit;
	}
	
	// Include all functions from get_multiple_insights.php
	// We'll copy the functions here or include them
	// For now, let's include the functions directly
	
	function makeApiCall( $endpoint, $type, $params ) {
		$ch = curl_init();

		if ( 'POST' == $type ) {
			curl_setopt( $ch, CURLOPT_URL, $endpoint );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params ) );
			curl_setopt( $ch, CURLOPT_POST, 1 );
		} elseif ( 'GET' == $type ) {
			curl_setopt( $ch, CURLOPT_URL, $endpoint . '?' . http_build_query( $params ) );
		}

		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		$response = curl_exec( $ch );
		curl_close( $ch );

		return json_decode( $response, true );
	}
	
	function getUserInsights( $instagramAccountId, $accessToken, $period = 'day' ) {
		$userInsightsEndpoint = ENDPOINT_BASE . $instagramAccountId . '/insights';
		$userInsightParams = array(
			'metric' => 'follower_count,reach',
			'period' => $period,
			'access_token' => $accessToken
		);
		return makeApiCall( $userInsightsEndpoint, 'GET', $userInsightParams );
	}
	
	function getUserInsights30Days( $instagramAccountId, $accessToken ) {
		$userInsightsEndpoint = ENDPOINT_BASE . $instagramAccountId . '/insights';
		$userInsightParams = array(
			'metric' => 'reach',
			'period' => 'day',
			'since' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'until' => date( 'Y-m-d' ),
			'access_token' => $accessToken
		);
		return makeApiCall( $userInsightsEndpoint, 'GET', $userInsightParams );
	}
	
	function getReelsInsights( $instagramAccountId, $accessToken, $limit = null ) {
		$totalViews = 0;
		$reelsData = array();
		$maxProcess = 50;
		$thirtyDaysAgo = strtotime( '-30 days' );
		
		$mediaEndpoint = ENDPOINT_BASE . $instagramAccountId . '/media';
		$mediaParams = array(
			'fields' => 'id,media_type,permalink,timestamp',
			'limit' => $maxProcess,
			'access_token' => $accessToken
		);
		
		$mediaResponse = makeApiCall( $mediaEndpoint, 'GET', $mediaParams );
		
		if ( isset( $mediaResponse['error'] ) || !isset( $mediaResponse['data'] ) || empty( $mediaResponse['data'] ) ) {
			return array( 'total_views' => 0, 'reels' => array(), 'total_videos' => 0 );
		}

		foreach ( $mediaResponse['data'] as $media ) {
			if ( $media['media_type'] == 'VIDEO' ) {
				$mediaTimestamp = strtotime( $media['timestamp'] );
				if ( $mediaTimestamp < $thirtyDaysAgo ) {
					continue;
				}
				
				$views = 0;
				$mediaInsights = getMediaInsights( $media['id'], $accessToken, 'VIDEO', true );
				
				if ( isset( $mediaInsights['error'] ) ) {
					$mediaInsights = getMediaInsights( $media['id'], $accessToken, 'VIDEO', false );
				}
				
				if ( isset( $mediaInsights['data'] ) && !empty( $mediaInsights['data'] ) ) {
					foreach ( $mediaInsights['data'] as $insight ) {
						$insightName = isset( $insight['name'] ) ? $insight['name'] : ( isset( $insight['title'] ) ? strtolower( str_replace( ' ', '_', $insight['title'] ) ) : '' );
						
						if ( $insightName == 'views' || $insightName == 'video_views' || 
						     strpos( strtolower( $insightName ), 'view' ) !== false || 
						     strpos( strtolower( $insightName ), 'play' ) !== false ) {
							if ( !empty( $insight['values'] ) ) {
								$views = isset( $insight['values'][0]['value'] ) ? intval( $insight['values'][0]['value'] ) : 0;
								$totalViews += $views;
								break;
							}
						}
					}
				}

				$reelsData[] = array(
					'id' => $media['id'],
					'permalink' => $media['permalink'],
					'views' => $views,
					'timestamp' => $media['timestamp'],
					'type' => $media['media_type']
				);
			}
		}
		
		// Sort by views (descending)
		usort( $reelsData, function( $a, $b ) {
			return $b['views'] - $a['views'];
		});
		
		// Limit to top 10 if specified
		if ( $limit && $limit > 0 ) {
			$reelsData = array_slice( $reelsData, 0, $limit );
		}

		return array( 'total_views' => $totalViews, 'reels' => $reelsData, 'total_videos' => count( $reelsData ) );
	}
	
	function getMediaInsights( $mediaId, $accessToken, $mediaType = 'IMAGE', $isReel = false ) {
		$mediaInsightsEndpoint = ENDPOINT_BASE . $mediaId . '/insights';
		
		// Different metrics for different media types
		// Note: Reels don't support impressions or video_views - use views instead
		if ( $isReel ) {
			// For Reels: views, reach, saved, likes, comments, shares, total_interactions
			$mediaInsightParams = array(
				'metric' => 'views,reach,saved,likes,comments,shares,total_interactions',
				'access_token' => $accessToken
			);
		} elseif ( $mediaType == 'CAROUSEL_ALBUM' ) {
			// For Carousel Albums: impressions and video_views are deprecated (removed Sept 2024)
			// Only these metrics are supported: reach, saved, likes, comments, shares, total_interactions
			$mediaInsightParams = array(
				'metric' => 'reach,saved,likes,comments,shares,total_interactions',
				'access_token' => $accessToken
			);
		} elseif ( $mediaType == 'VIDEO' ) {
			// For regular videos (not reels): reach, saved, video_views, likes, comments, shares, total_interactions
			$mediaInsightParams = array(
				'metric' => 'reach,saved,video_views,likes,comments,shares,total_interactions',
				'access_token' => $accessToken
			);
		} else {
			// For images: reach, saved, likes, comments, shares (impressions deprecated)
			$mediaInsightParams = array(
				'metric' => 'reach,saved,likes,comments,shares',
				'access_token' => $accessToken
			);
		}
		return makeApiCall( $mediaInsightsEndpoint, 'GET', $mediaInsightParams );
	}
	
	function getUserMedia( $instagramAccountId, $accessToken, $limit = 10 ) {
		$mediaEndpoint = ENDPOINT_BASE . $instagramAccountId . '/media';
		$mediaParams = array(
			'fields' => 'id,media_type,like_count,comments_count,permalink,timestamp,video_title',
			'limit' => $limit,
			'access_token' => $accessToken
		);
		return makeApiCall( $mediaEndpoint, 'GET', $mediaParams );
	}
	
	function getBusinessDiscovery( $username, $instagramAccountId, $accessToken ) {
		$endpoint = ENDPOINT_BASE . $instagramAccountId;
		$params = array(
			'fields' => 'business_discovery.username(' . $username . '){username,website,name,ig_id,id,profile_picture_url,biography,follows_count,followers_count,media_count}',
			'access_token' => $accessToken
		);
		return makeApiCall( $endpoint, 'GET', $params );
	}
	
	// Get account data
	$businessInfo = getBusinessDiscovery( $accountUsername, $instagramAccountId, $accessToken );
	
	if ( isset( $businessInfo['error'] ) || !isset( $businessInfo['business_discovery'] ) ) {
		die( 'Error: Could not find account ' . htmlspecialchars( $accountUsername ) . '. <a href="get_multiple_insights.php">← Back to Insights</a>' );
	}
	
	$account = $businessInfo['business_discovery'];
	$accountId = $account['id'];
	
	// Get all detailed analytics
	$last24HoursInsights = getUserInsights( $accountId, $accessToken, 'day' );
	$reach30Days = getUserInsights30Days( $accountId, $accessToken );
	$reelsInsights = getReelsInsights( $accountId, $accessToken, 10 ); // Top 10 reels
	$recentMedia = getUserMedia( $accountId, $accessToken, 10 ); // Recent 10 posts
	
	// Get detailed insights for recent posts
	$recentPostsWithInsights = array();
	if ( isset( $recentMedia['data'] ) && !empty( $recentMedia['data'] ) ) {
		foreach ( $recentMedia['data'] as $media ) {
			$mediaInsights = getMediaInsights( $media['id'], $accessToken, $media['media_type'], $media['media_type'] == 'VIDEO' );
			
			$postData = array(
				'id' => $media['id'],
				'permalink' => $media['permalink'],
				'media_type' => $media['media_type'],
				'timestamp' => $media['timestamp'],
				'like_count' => isset( $media['like_count'] ) ? $media['like_count'] : 0,
				'comments_count' => isset( $media['comments_count'] ) ? $media['comments_count'] : 0,
				'insights' => array()
			);
			
			if ( isset( $mediaInsights['data'] ) && !empty( $mediaInsights['data'] ) ) {
				foreach ( $mediaInsights['data'] as $insight ) {
					$insightName = isset( $insight['name'] ) ? $insight['name'] : ( isset( $insight['title'] ) ? strtolower( str_replace( ' ', '_', $insight['title'] ) ) : '' );
					$value = isset( $insight['values'][0]['value'] ) ? intval( $insight['values'][0]['value'] ) : 0;
					$postData['insights'][$insightName] = $value;
				}
			}
			
			$recentPostsWithInsights[] = $postData;
		}
	}
	
	// Calculate 30-day reach total
	$reach30DaysTotal = 0;
	if ( isset( $reach30Days['data'] ) && !empty( $reach30Days['data'] ) ) {
		foreach ( $reach30Days['data'] as $insight ) {
			if ( $insight['name'] == 'reach' && !empty( $insight['values'] ) ) {
				foreach ( $insight['values'] as $value ) {
					$reach30DaysTotal += $value['value'];
				}
			}
		}
	}
	
	// Get last 24 hours data
	$last24HoursFollowerCount = 0;
	$last24HoursReach = 0;
	$last24HoursUpdateTime = '';
	
	if ( isset( $last24HoursInsights['data'] ) && !empty( $last24HoursInsights['data'] ) ) {
		foreach ( $last24HoursInsights['data'] as $insight ) {
			if ( $insight['name'] == 'follower_count' && !empty( $insight['values'] ) ) {
				$last24HoursFollowerCount = isset( $insight['values'][0]['value'] ) ? intval( $insight['values'][0]['value'] ) : 0;
				$last24HoursUpdateTime = isset( $insight['values'][0]['end_time'] ) ? $insight['values'][0]['end_time'] : '';
			}
			if ( $insight['name'] == 'reach' && !empty( $insight['values'] ) ) {
				$last24HoursReach = isset( $insight['values'][0]['value'] ) ? intval( $insight['values'][0]['value'] ) : 0;
				if ( empty( $last24HoursUpdateTime ) ) {
					$last24HoursUpdateTime = isset( $insight['values'][0]['end_time'] ) ? $insight['values'][0]['end_time'] : '';
				}
			}
		}
	}
?>
<!DOCTYPE html>
<html>
<head>
	<title><?php echo htmlspecialchars( $account['username'] ); ?> - Instagram Analytics</title>
	<meta charset="utf-8" />
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link
		href="https://fonts.googleapis.com/css2?family=Inter:wght@700&family=Montserrat:wght@800;900&display=swap"
		rel="stylesheet">
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		
		body {
			font-family: Arial, sans-serif;
			background: #000000 !important;
			color: #ffffff;
			overflow-x: hidden;
		}
		
		.header {
			background: #000000;
			padding: 20px 40px;
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		
		.header h1 {
			color: #5E00FF;
			font-family: 'Montserrat', 'Inter', sans-serif;
			font-weight: 900;
			font-size: 32px;
		}
		
		.back-link {
			color: #5E00FF;
			text-decoration: none;
			font-size: 16px;
			transition: opacity 0.3s;
		}
		
		.back-link:hover {
			opacity: 0.8;
		}
		
		.container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 40px 20px;
		}
		
		.account-overview {
			background: rgba(33, 33, 33, 0.8);
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 12px;
			padding: 30px;
			margin-bottom: 30px;
		}
		
		.account-header {
			display: flex;
			align-items: center;
			margin-bottom: 20px;
		}
		
		.profile-picture {
			width: 80px;
			height: 80px;
			border-radius: 50%;
			margin-right: 20px;
			border: 2px solid #5E00FF;
		}
		
		.account-info h2 {
			color: #5E00FF;
			font-size: 28px;
			margin-bottom: 10px;
		}
		
		.account-meta {
			color: #aaaaaa;
			font-size: 14px;
			margin-bottom: 10px;
		}
		
		.account-id {
			color: #888888;
			font-size: 12px;
			font-family: monospace;
		}
		
		.section {
			background: rgba(33, 33, 33, 0.8);
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 12px;
			padding: 30px;
			margin-bottom: 30px;
		}
		
		.section-title {
			color: #5E00FF;
			font-size: 24px;
			margin-bottom: 20px;
			border-bottom: 2px solid rgba(94, 0, 255, 0.3);
			padding-bottom: 10px;
		}
		
		.insight-item {
			margin: 15px 0;
			padding: 15px;
			background: rgba(0, 0, 0, 0.3);
			border-radius: 8px;
			border-left: 3px solid #5E00FF;
		}
		
		.insight-label {
			color: #aaaaaa;
			font-size: 14px;
			margin-bottom: 5px;
		}
		
		.insight-value {
			color: #ffffff;
			font-size: 28px;
			font-weight: bold;
		}
		
		.insight-time {
			color: #888888;
			font-size: 12px;
			margin-top: 5px;
		}
		
		.reel-item {
			padding: 15px;
			margin: 10px 0;
			background: rgba(0, 0, 0, 0.3);
			border-radius: 8px;
			border-left: 3px solid #5E00FF;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		
		.reel-info {
			flex: 1;
		}
		
		.reel-rank {
			color: #5E00FF;
			font-weight: bold;
			font-size: 18px;
			margin-right: 15px;
		}
		
		.reel-link {
			color: #5E00FF;
			text-decoration: none;
			margin-right: 15px;
		}
		
		.reel-link:hover {
			text-decoration: underline;
		}
		
		.reel-views {
			color: #ffffff;
			font-weight: bold;
			font-size: 18px;
		}
		
		.reel-date {
			color: #aaaaaa;
			font-size: 14px;
		}
		
		.post-item {
			padding: 20px;
			margin: 15px 0;
			background: rgba(0, 0, 0, 0.3);
			border-radius: 8px;
			border-left: 3px solid #5E00FF;
		}
		
		.post-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 15px;
		}
		
		.post-link {
			color: #5E00FF;
			text-decoration: none;
		}
		
		.post-link:hover {
			text-decoration: underline;
		}
		
		.post-type {
			color: #aaaaaa;
			font-size: 14px;
		}
		
		.post-metrics {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 15px;
			margin-top: 15px;
		}
		
		.post-metric {
			text-align: center;
		}
		
		.post-metric-label {
			color: #aaaaaa;
			font-size: 12px;
			margin-bottom: 5px;
		}
		
		.post-metric-value {
			color: #ffffff;
			font-size: 20px;
			font-weight: bold;
		}
		
		.error {
			color: #ff4444;
			padding: 15px;
			background: rgba(255, 68, 68, 0.1);
			border-radius: 8px;
			border-left: 3px solid #ff4444;
		}
	</style>
</head>
<body>
	<div class="header">
		<h1>Instagram Page Insights</h1>
		<a href="get_multiple_insights.php" class="back-link">← Back to All Accounts</a>
	</div>
	
	<div class="container">
		<!-- Account Overview -->
		<div class="account-overview">
			<div class="account-header">
				<?php if ( isset( $account['profile_picture_url'] ) ) : ?>
					<img src="<?php echo htmlspecialchars( $account['profile_picture_url'] ); ?>" alt="<?php echo htmlspecialchars( $account['username'] ); ?>" class="profile-picture" />
				<?php endif; ?>
				<div class="account-info">
					<h2>@<?php echo htmlspecialchars( $account['username'] ); ?></h2>
					<div class="account-meta">
						Posts: <?php echo number_format( $account['media_count'] ); ?> | 
						Followers: <?php echo number_format( $account['followers_count'] ); ?> | 
						Following: <?php echo number_format( $account['follows_count'] ); ?>
					</div>
					<div class="account-id">
						Using Instagram Account ID: <?php echo htmlspecialchars( $accountId ); ?> for insights
					</div>
				</div>
			</div>
		</div>
		
		<!-- Last 24 Hours Insights -->
		<div class="section">
			<h3 class="section-title">Account Insights (Last 24 Hours)</h3>
			<?php if ( isset( $last24HoursInsights['error'] ) ) : ?>
				<div class="error">Error: <?php echo htmlspecialchars( $last24HoursInsights['error']['message'] ); ?></div>
			<?php else : ?>
				<div class="insight-item">
					<div class="insight-label">Follower Count:</div>
					<div class="insight-value"><?php echo number_format( $last24HoursFollowerCount ); ?></div>
					<?php if ( $last24HoursUpdateTime ) : ?>
						<div class="insight-time">Last updated: <?php echo date( 'Y-m-d H:i:s', strtotime( $last24HoursUpdateTime ) ); ?></div>
					<?php endif; ?>
				</div>
				<div class="insight-item">
					<div class="insight-label">Reach:</div>
					<div class="insight-value"><?php echo number_format( $last24HoursReach ); ?></div>
					<?php if ( $last24HoursUpdateTime ) : ?>
						<div class="insight-time">Last updated: <?php echo date( 'Y-m-d H:i:s', strtotime( $last24HoursUpdateTime ) ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		
		<!-- Reach Last 30 Days -->
		<div class="section">
			<h3 class="section-title">Reach - Last 30 Days</h3>
			<div class="insight-item">
				<div class="insight-label">Total People Reached (Last 30 Days):</div>
				<div class="insight-value"><?php echo number_format( $reach30DaysTotal ); ?></div>
			</div>
		</div>
		
		<!-- Top Performing Reels -->
		<div class="section">
			<h3 class="section-title">Top Performing Reels (Last 30 Days)</h3>
			<?php if ( isset( $reelsInsights['reels'] ) && !empty( $reelsInsights['reels'] ) ) : ?>
				<p style="color: #aaaaaa; margin-bottom: 20px;">
					Total Views (Last 30 Days): <strong style="color: #ffffff;"><?php echo number_format( $reelsInsights['total_views'] ); ?></strong>
				</p>
				<p style="color: #aaaaaa; margin-bottom: 20px; font-size: 14px;">
					Based on <?php echo count( $reelsInsights['reels'] ); ?> videos/reels from last 30 days. 
					Note: Carousel posts don't support views/video_views metric anymore (removed Sept 2024)
				</p>
				<h4 style="color: #ffffff; margin: 20px 0 10px 0;">Top 10 Reels by Views:</h4>
				<?php foreach ( $reelsInsights['reels'] as $index => $reel ) : ?>
					<div class="reel-item">
						<div class="reel-info">
							<span class="reel-rank">#<?php echo $index + 1; ?></span>
							<a href="<?php echo htmlspecialchars( $reel['permalink'] ); ?>" target="_blank" class="reel-link">View Reel →</a>
							<span class="reel-views">Views: <?php echo number_format( $reel['views'] ); ?></span>
							<span class="reel-date"><?php echo date( 'M d, Y', strtotime( $reel['timestamp'] ) ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="error">No reels data available for the last 30 days.</div>
			<?php endif; ?>
		</div>
		
		<!-- Recent Posts Insights -->
		<div class="section">
			<h3 class="section-title">Recent Posts Insights</h3>
			<?php if ( !empty( $recentPostsWithInsights ) ) : ?>
				<?php foreach ( $recentPostsWithInsights as $post ) : ?>
					<div class="post-item">
						<div class="post-header">
							<a href="<?php echo htmlspecialchars( $post['permalink'] ); ?>" target="_blank" class="post-link">View Post →</a>
							<span class="post-type">Type: <?php echo htmlspecialchars( $post['media_type'] ); ?></span>
						</div>
						<div class="post-metrics">
							<?php if ( isset( $post['insights']['views'] ) ) : ?>
								<div class="post-metric">
									<div class="post-metric-label">Views:</div>
									<div class="post-metric-value"><?php echo number_format( $post['insights']['views'] ); ?></div>
								</div>
							<?php endif; ?>
							<?php if ( isset( $post['insights']['reach'] ) ) : ?>
								<div class="post-metric">
									<div class="post-metric-label">Accounts reached:</div>
									<div class="post-metric-value"><?php echo number_format( $post['insights']['reach'] ); ?></div>
								</div>
							<?php endif; ?>
							<?php if ( isset( $post['insights']['saved'] ) ) : ?>
								<div class="post-metric">
									<div class="post-metric-label">Saved:</div>
									<div class="post-metric-value"><?php echo number_format( $post['insights']['saved'] ); ?></div>
								</div>
							<?php endif; ?>
							<div class="post-metric">
								<div class="post-metric-label">Likes:</div>
								<div class="post-metric-value"><?php echo number_format( $post['like_count'] ); ?></div>
							</div>
							<div class="post-metric">
								<div class="post-metric-label">Comments:</div>
								<div class="post-metric-value"><?php echo number_format( $post['comments_count'] ); ?></div>
							</div>
							<?php if ( isset( $post['insights']['shares'] ) ) : ?>
								<div class="post-metric">
									<div class="post-metric-label">Shares:</div>
									<div class="post-metric-value"><?php echo number_format( $post['insights']['shares'] ); ?></div>
								</div>
							<?php endif; ?>
							<?php if ( isset( $post['insights']['total_interactions'] ) ) : ?>
								<div class="post-metric">
									<div class="post-metric-label"><?php echo $post['media_type'] == 'CAROUSEL_ALBUM' ? 'Post interactions' : 'Reels Interactions'; ?>:</div>
									<div class="post-metric-value"><?php echo number_format( $post['insights']['total_interactions'] ); ?></div>
								</div>
							<?php endif; ?>
							<?php if ( isset( $post['insights']['video_views'] ) ) : ?>
								<div class="post-metric">
									<div class="post-metric-label">Video Views:</div>
									<div class="post-metric-value"><?php echo number_format( $post['insights']['video_views'] ); ?></div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="error">No recent posts data available.</div>
			<?php endif; ?>
		</div>
	</div>
</body>
</html>

