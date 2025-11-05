<?php
	// Increase execution time for accounts with many posts
	set_time_limit( 300 ); // 5 minutes
	
	// Load credentials from environment variables (Render) or defines.php (local)
	include 'load_config.php';

	// ============================================
	// CONFIGURATION: Add Instagram usernames here
	// ============================================
	$instagramUsernames = [
		'101xmarketing',  // Your Instagram account
		// Add more Instagram usernames below (without @ symbol):
		'101xfounders',      // Replace with actual Instagram username
		'bizzindia',      // Replace with actual Instagram username
		'startupcoded',      // New account
		'foundersinindia',      // New account
		// Add as many as you want:
		// 'username3',
		// 'username4',
	];
	
	// OPTIONAL: If you know the Instagram Account IDs, add them here for faster processing
	// Format: 'username' => 'instagram_account_id'
	$instagramAccountIds = [
		'101xmarketing' => '17841475978250722',  // Already in defines.php
		// Add IDs for other accounts here (optional - will be fetched automatically if not provided):
		// '101xfounders' => 'YOUR-INSTAGRAM-ACCOUNT-ID-HERE',
		'bizzindia' => '789802470889988',  // Instagram Account ID for bizzindia
	];
	// ============================================

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

	function getUserInsightsTotalValue( $instagramAccountId, $accessToken, $period = 'day' ) {
		$userInsightsEndpoint = ENDPOINT_BASE . $instagramAccountId . '/insights';
		
		// Try each metric individually to avoid failures
		// Some accounts may not support all metrics
		$metrics = ['profile_views', 'website_clicks', 'accounts_engaged'];
		$allData = array();
		
		foreach ( $metrics as $metric ) {
			// Try with metric_type=total_value first
			$userInsightParams = array(
				'metric' => $metric,
				'metric_type' => 'total_value',
				'period' => $period,
				'access_token' => $accessToken
			);
			$response = makeApiCall( $userInsightsEndpoint, 'GET', $userInsightParams );
			
			// If error with metric_type, try without it
			if ( isset( $response['error'] ) ) {
				unset( $userInsightParams['metric_type'] );
				$response = makeApiCall( $userInsightsEndpoint, 'GET', $userInsightParams );
			}
			
			// If successful, add to results
			if ( isset( $response['data'] ) && !empty( $response['data'] ) ) {
				$allData = array_merge( $allData, $response['data'] );
			}
			// If still error, skip this metric silently (some accounts don't support all metrics)
		}
		
		// Return combined results
		if ( !empty( $allData ) ) {
			return array( 'data' => $allData );
		} else {
			// Return empty data (not an error) so the page continues
			return array( 'data' => array() );
		}
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

	function getMonthlyTotalViews( $instagramAccountId, $accessToken ) {
		// Get total views from first of current month to today
		$firstOfMonth = date( 'Y-m-01' );
		$today = date( 'Y-m-d' );
		$firstOfMonthTimestamp = strtotime( $firstOfMonth . ' 00:00:00' );
		$todayTimestamp = strtotime( $today . ' 23:59:59' );
		
		$totalViews = 0;
		$maxProcess = 100; // Process more items to get all from current month
		
		// Get recent media
		$mediaEndpoint = ENDPOINT_BASE . $instagramAccountId . '/media';
		$mediaParams = array(
			'fields' => 'id,media_type,permalink,timestamp',
			'limit' => $maxProcess,
			'access_token' => $accessToken
		);
		
		$mediaResponse = makeApiCall( $mediaEndpoint, 'GET', $mediaParams );
		
		if ( isset( $mediaResponse['error'] ) || !isset( $mediaResponse['data'] ) || empty( $mediaResponse['data'] ) ) {
			return 0;
		}
		
		// Filter for videos/reels from current month
		foreach ( $mediaResponse['data'] as $media ) {
			// Process both VIDEO type (reels and videos)
			if ( $media['media_type'] == 'VIDEO' || $media['media_type'] == 'REELS' ) {
				$mediaTimestamp = strtotime( $media['timestamp'] );
				
				// Check if media is from current month (between first of month and today)
				if ( $mediaTimestamp >= $firstOfMonthTimestamp && $mediaTimestamp <= $todayTimestamp ) {
					// Try Reels metrics first (for reels)
					$mediaInsights = getMediaInsights( $media['id'], $accessToken, 'VIDEO', true );
					
					// If Reels metrics fail, try regular video metrics
					if ( isset( $mediaInsights['error'] ) ) {
						$mediaInsights = getMediaInsights( $media['id'], $accessToken, 'VIDEO', false );
					}
					
					if ( isset( $mediaInsights['data'] ) && !empty( $mediaInsights['data'] ) ) {
						foreach ( $mediaInsights['data'] as $insight ) {
							$insightName = isset( $insight['name'] ) ? $insight['name'] : ( isset( $insight['title'] ) ? strtolower( str_replace( ' ', '_', $insight['title'] ) ) : '' );
							
							// Look for views, video_views, or any metric containing 'view'
							if ( $insightName == 'views' || $insightName == 'video_views' || 
							     strpos( strtolower( $insightName ), 'view' ) !== false ||
							     strpos( strtolower( $insightName ), 'play' ) !== false ) {
								if ( !empty( $insight['values'] ) ) {
									$views = isset( $insight['values'][0]['value'] ) ? intval( $insight['values'][0]['value'] ) : 0;
									$totalViews += $views;
									break; // Found views, stop looking for this media
								}
							}
						}
					}
				}
			}
		}
		
		return $totalViews;
	}

	function getReelsInsights( $instagramAccountId, $accessToken, $limit = null ) {
		// Get top performing reels and carousels from last 30 days only
		$totalViews = 0;
		$reelsData = array();
		$debugInfo = array();
		$processedCount = 0;
		$maxProcess = 50; // Process only first 50 items (recent ones)
		$thirtyDaysAgo = strtotime( '-30 days' );
		
		// Get recent media (last 30 days)
		$mediaEndpoint = ENDPOINT_BASE . $instagramAccountId . '/media';
		$mediaParams = array(
			'fields' => 'id,media_type,permalink,timestamp',
			'limit' => $maxProcess,  // Limit to 50 most recent items
			'access_token' => $accessToken
		);
		
		$mediaResponse = makeApiCall( $mediaEndpoint, 'GET', $mediaParams );
		
		// Check for API errors first
		if ( isset( $mediaResponse['error'] ) ) {
			$errorMsg = 'API Error: ' . $mediaResponse['error']['message'];
			if ( isset( $mediaResponse['error']['code'] ) ) {
				$errorMsg .= ' (Code: ' . $mediaResponse['error']['code'] . ')';
			}
			return array( 'total_views' => 0, 'reels' => array(), 'debug' => array( $errorMsg ), 'total_videos' => 0 );
		}
		
		// Check if data exists
		if ( !isset( $mediaResponse['data'] ) || empty( $mediaResponse['data'] ) ) {
			return array( 'total_views' => 0, 'reels' => array(), 'debug' => array( 'No media data found. Account may have no posts or posts may not be accessible.' ), 'total_videos' => 0 );
		}

		// Filter for videos (reels) from last 30 days
		// Note: Carousels don't support views/video_views metric anymore (deprecated Sept 2024)
		foreach ( $mediaResponse['data'] as $media ) {
			// Only process VIDEO type for view tracking (carousels don't have views)
			if ( $media['media_type'] == 'VIDEO' ) {
				// Check if media is from last 30 days
				$mediaTimestamp = strtotime( $media['timestamp'] );
				if ( $mediaTimestamp < $thirtyDaysAgo ) {
					continue; // Skip media older than 30 days
				}
				
				$views = 0;
				
				// For videos: Try Reels metrics first (views works for Reels)
				$mediaInsights = getMediaInsights( $media['id'], $accessToken, 'VIDEO', true );
				
				// If Reels metrics fail, try regular video metrics
				if ( isset( $mediaInsights['error'] ) ) {
					$mediaInsights = getMediaInsights( $media['id'], $accessToken, 'VIDEO', false );
				}
				
				// Debug: Check if there's still an error
				if ( isset( $mediaInsights['error'] ) ) {
					$debugInfo[] = 'Error for media ' . $media['id'] . ': ' . $mediaInsights['error']['message'];
					continue;
				}
				
				if ( isset( $mediaInsights['data'] ) && !empty( $mediaInsights['data'] ) ) {
					foreach ( $mediaInsights['data'] as $insight ) {
						// Check both 'name' and 'title' fields
						$insightName = isset( $insight['name'] ) ? $insight['name'] : ( isset( $insight['title'] ) ? strtolower( str_replace( ' ', '_', $insight['title'] ) ) : '' );
						
						// Look for views, video_views, or any metric containing 'view' or 'play'
						// For carousels: video_views (if it contains videos)
						// For reels/videos: views or video_views
						if ( $insightName == 'views' || $insightName == 'video_views' || $insightName == 'video views' || 
						     strpos( strtolower( $insightName ), 'view' ) !== false || 
						     strpos( strtolower( $insightName ), 'play' ) !== false ) {
							if ( !empty( $insight['values'] ) ) {
								$views = isset( $insight['values'][0]['value'] ) ? intval( $insight['values'][0]['value'] ) : 0;
								$totalViews += $views;
								break; // Found views, stop looking
							}
						}
					}
				}

				$reelsData[] = array(
					'id' => $media['id'],
					'permalink' => $media['permalink'],
					'views' => $views,
					'timestamp' => $media['timestamp'],
					'type' => $media['media_type'] // Will be VIDEO
				);
				$processedCount++;
			}
		}
		
		// Sort by views (descending) to get top performers
		usort( $reelsData, function( $a, $b ) {
			return $b['views'] - $a['views'];
		});

		return array( 'total_views' => $totalViews, 'reels' => $reelsData, 'debug' => $debugInfo, 'total_videos' => count( $reelsData ) );
	}

	function getMediaInsights( $mediaId, $accessToken, $mediaType = 'IMAGE', $isReel = false ) {
		$mediaInsightsEndpoint = ENDPOINT_BASE . $mediaId . '/insights';
		
		// Different metrics for different media types
		// Note: Reels don't support impressions or video_views - use views instead
		if ( $isReel ) {
			// For Reels: views, reach, saved, likes, comments, shares, total_interactions
			// Reels-specific metrics: ig_reels_aggregated_all_plays_count
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
			// For images: impressions, reach, saved, likes, comments, shares
			$mediaInsightParams = array(
				'metric' => 'impressions,reach,saved,likes,comments,shares',
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

	// Results array
	$results = [];
	
	// ============================================
	// COLLECT DATA FOR LANDING PAGE
	// Only load monthly views (fast) - other detailed metrics load on detail page
	// ============================================
	$accountsData = array();
	$totalMonthlyViews = 0;
	$ownedAccounts = ['101xmarketing', '101xfounders', 'bizzindia', 'startupcoded', 'foundersinindia'];
	
	// Process each Instagram username to collect basic info + monthly views
	foreach ( $instagramUsernames as $username ) {
		$accountData = array(
			'username' => $username,
			'error' => null,
			'account' => null,
			'accountId' => null,
			'monthlyViews' => 0,
			'posts' => 0,
			'followers' => 0,
			'following' => 0
		);
		
		// Get business discovery (basic info)
		$businessInfo = getBusinessDiscovery( $username, $instagramAccountId, $accessToken );
		
		if ( isset( $businessInfo['error'] ) ) {
			$accountData['error'] = $businessInfo['error']['message'];
			$accountsData[] = $accountData;
			continue;
		}
		
		if ( !isset( $businessInfo['business_discovery'] ) ) {
			$accountData['error'] = 'Could not find business account';
			$accountsData[] = $accountData;
			continue;
		}
		
		$account = $businessInfo['business_discovery'];
		$accountId = $account['id'];
		
		$accountData['account'] = $account;
		$accountData['accountId'] = $accountId;
		$accountData['posts'] = isset( $account['media_count'] ) ? $account['media_count'] : 0;
		$accountData['followers'] = isset( $account['followers_count'] ) ? $account['followers_count'] : 0;
		$accountData['following'] = isset( $account['follows_count'] ) ? $account['follows_count'] : 0;
		
		// Get monthly total views only (for owned accounts)
		if ( in_array( $username, $ownedAccounts ) || $accountId == $instagramAccountId ) {
			$monthlyViews = getMonthlyTotalViews( $accountId, $accessToken );
			$accountData['monthlyViews'] = $monthlyViews;
			$totalMonthlyViews += $monthlyViews;
		}
		
		// NOTE: Other detailed insights (reels views, reach, likes) 
		// are NOT loaded here to keep the landing page fast.
		// They will be loaded in account_details.php when user clicks on a card.
		
		$accountsData[] = $accountData;
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Front Seat - Instagram Analytics</title>
		<meta charset="utf-8" />
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link
			href="https://fonts.googleapis.com/css2?family=Inter:wght@700&family=Montserrat:wght@800;900&display=swap"
			rel="stylesheet">
		<style>
			/* Dark Mode Theme - Landing Page Design */
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
		
		/* Landing Section - Hero */
		.landing-section {
			min-height: 100vh;
			display: flex;
			flex-direction: column;
			justify-content: center;
			align-items: center;
			position: relative;
			background: #000000;
			padding: 40px 20px;
		}
		
		/* Purple glow effects in corners */
		.landing-section::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			width: 300px;
			height: 300px;
			background: radial-gradient(circle, rgba(94, 0, 255, 0.3) 0%, transparent 70%);
			pointer-events: none;
		}
		
		.landing-section::after {
			content: '';
			position: absolute;
			top: 0;
			right: 0;
			width: 300px;
			height: 300px;
			background: radial-gradient(circle, rgba(94, 0, 255, 0.3) 0%, transparent 70%);
			pointer-events: none;
		}
		
		/* Company Logo */
		.company-logo {
			margin-bottom: 60px;
			text-align: center;
			z-index: 1;
			opacity: 0;
			transform: scale(0.95);
			animation: fadeIn 1.2s ease-in-out forwards;
		}
		
		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: scale(0.95);
			}
			to {
				opacity: 1;
				transform: scale(1);
			}
		}
		
		.company-logo h1 {
			color: #5E00FF;
			font-family: 'Montserrat', 'Inter', sans-serif;
			font-weight: 900;
			font-size: 25vw;
			line-height: 0.8;
			text-align: center;
			margin: 0;
			letter-spacing: 0;
		}
		
		@media (min-width: 768px) {
			.company-logo h1 {
				font-size: 15vw;
			}
		}
		
		/* Total Views Display */
		.total-views-container {
			text-align: center;
			z-index: 1;
		}
		
		.total-views-number {
			font-size: 64px;
			font-weight: bold;
			color: #ffffff;
			margin: 20px 0;
			letter-spacing: 2px;
		}
		
		.total-views-underline {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 20px;
			margin-top: 20px;
		}
		
		.total-views-line {
			width: 100px;
			height: 2px;
			background: linear-gradient(to right, transparent, #5E00FF, transparent);
		}
		
		/* Account Cards Section */
		.account-cards-section {
			min-height: 100vh;
			background: #000000;
			padding: 80px 20px;
		}
		
		.cards-container {
			max-width: 1400px;
			margin: 0 auto;
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
			gap: 30px;
			padding: 20px;
		}
		
		.account-card {
			background: rgba(33, 33, 33, 0.8);
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 12px;
			padding: 30px;
			backdrop-filter: blur(10px);
			transition: transform 0.3s, box-shadow 0.3s;
		}
		
		.account-card[onclick] {
			cursor: pointer;
		}
		
		.account-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 10px 30px rgba(94, 0, 255, 0.3);
		}
		
		.account-name {
			font-size: 24px;
			font-weight: bold;
			color: #ffffff;
			margin-bottom: 15px;
		}
		
		.account-meta {
			font-size: 14px;
			color: #aaaaaa;
			margin-bottom: 25px;
			padding-bottom: 15px;
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
		}
		
		.metric-row {
			margin: 15px 0;
			color: #ffffff;
		}
		
		.metric-label {
			font-size: 14px;
			color: #aaaaaa;
			margin-bottom: 5px;
		}
		
		.metric-value {
			font-size: 20px;
			font-weight: bold;
			color: #ffffff;
		}
		
		.error {
			color: #ff4444;
			padding: 10px;
		}
			
			.container { 
				max-width: 1200px; 
				margin: 0 auto; 
				background: #181818; 
				padding: 20px; 
				border-radius: 8px; 
				box-shadow: 0 2px 4px rgba(0,0,0,0.3); 
			}
			.page-card { 
				border: 1px solid #303030; 
				margin: 20px 0; 
				padding: 20px; 
				border-radius: 8px; 
				background: #212121; 
			}
			.page-header { 
				display: flex; 
				align-items: center; 
				margin-bottom: 20px; 
			}
			.profile-img { 
				width: 60px; 
				height: 60px; 
				border-radius: 50%; 
				margin-right: 15px; 
			}
			.insight-item { 
				margin: 10px 0; 
				padding: 10px; 
				background: #181818; 
				border-left: 3px solid #3ea6ff; 
				border-radius: 4px;
			}
			.insight-value { 
				font-size: 24px; 
				font-weight: bold; 
				color: #3ea6ff; 
			}
			.error { 
				color: #ff4444; 
				padding: 10px; 
				background: #3d1a1a; 
				border-left: 3px solid #ff4444; 
				border-radius: 4px;
			}
			.success { 
				color: #4caf50; 
				padding: 10px; 
				background: #1a3d1a; 
				border-left: 3px solid #4caf50; 
				border-radius: 4px;
			}
			h1 { 
				color: #ffffff; 
			}
			h2 { 
				color: #3ea6ff; 
				margin-top: 0; 
			}
			h3 {
				color: #ffffff;
			}
			h4 {
				color: #aaaaaa;
			}
			.metric-name { 
				font-weight: bold; 
				color: #aaaaaa; 
			}
			p, li {
				color: #aaaaaa;
			}
			a {
				color: #3ea6ff;
			}
			a:hover {
				color: #5cb8ff;
			}
			hr {
				border-color: #303030;
			}
			
			/* Hamburger Menu Styles - Dark Mode */
			.menu-container { 
				position: relative; 
				margin-bottom: 20px; 
			}
			.hamburger-btn { 
				background: #303030; 
				color: white; 
				border: 1px solid #404040; 
				padding: 12px 20px; 
				border-radius: 5px; 
				cursor: pointer; 
				font-size: 16px;
				display: flex;
				align-items: center;
				gap: 10px;
				transition: background 0.2s;
			}
			.hamburger-btn:hover { 
				background: #404040; 
			}
			.hamburger-icon { 
				width: 20px; 
				height: 20px; 
				display: flex; 
				flex-direction: column; 
				justify-content: space-between; 
			}
			.hamburger-icon span { 
				display: block; 
				height: 3px; 
				width: 100%; 
				background: white; 
				border-radius: 2px; 
				transition: all 0.3s;
			}
			.dropdown-menu { 
				display: none; 
				position: absolute; 
				top: 100%; 
				left: 0; 
				background: #212121; 
				border: 1px solid #303030; 
				border-radius: 5px; 
				box-shadow: 0 4px 8px rgba(0,0,0,0.5); 
				min-width: 200px; 
				z-index: 1000;
				margin-top: 5px;
			}
			.dropdown-menu.show { 
				display: block; 
			}
			.dropdown-item { 
				padding: 12px 20px; 
				cursor: pointer; 
				border-bottom: 1px solid #303030;
				transition: background 0.2s;
				color: #ffffff;
			}
			.dropdown-item:last-child { 
				border-bottom: none; 
			}
			.dropdown-item:hover { 
				background: #303030; 
			}
			.dropdown-item.active { 
				background: #1a5490; 
				color: #ffffff; 
				font-weight: bold;
			}
			.dropdown-item::before { 
				content: '@'; 
				margin-right: 5px; 
				color: #888; 
			}
			.selected-account { 
				margin-left: 10px; 
				font-weight: normal; 
				font-size: 14px; 
				color: rgba(255,255,255,0.9); 
			}
			
			/* Additional dark mode styles for inline elements */
			div[style*="background: white"], div[style*="background:white"] {
				background: #181818 !important;
				color: #ffffff !important;
			}
			div[style*="color: #666"], div[style*="color:#666"] {
				color: #aaaaaa !important;
			}
			div[style*="color: #999"], div[style*="color:#999"] {
				color: #888 !important;
			}
			div[style*="background: #f0f0f0"], div[style*="background:#f0f0f0"] {
				background: #303030 !important;
				color: #ffffff !important;
			}
			div[style*="background: #fafafa"], div[style*="background:#fafafa"] {
				background: #212121 !important;
			}
		</style>
	</head>
	<body style="background: #000000 !important;">
		<!-- Landing Section -->
		<section class="landing-section">
			<div class="company-logo">
				<h1>FRONT<br />SEAT</h1>
						</div>
			<div class="total-views-container">
				<div class="total-views-number" id="totalViews"><?php echo number_format( $totalMonthlyViews ); ?></div>
				<div class="total-views-underline">
					<div class="total-views-line"></div>
					<div class="total-views-line"></div>
				</div>
			</div>
		</section>
		
		<!-- Account Cards Section -->
		<section class="account-cards-section">
			<div class="cards-container">
				<?php foreach ( $accountsData as $accountData ) : ?>
					<div class="account-card" data-account="<?php echo htmlspecialchars( $accountData['username'] ); ?>" <?php if ( !$accountData['error'] ) : ?>onclick="window.location.href='account_details.php?account=<?php echo urlencode( $accountData['username'] ); ?>'"<?php endif; ?>>
						<?php if ( $accountData['error'] ) : ?>
							<div class="account-name">@<?php echo htmlspecialchars( $accountData['username'] ); ?></div>
							<div class="error">Error: <?php echo htmlspecialchars( $accountData['error'] ); ?></div>
						<?php else : ?>
							<div class="account-name">@<?php echo htmlspecialchars( $accountData['username'] ); ?></div>
							<div class="account-meta">
								<?php echo number_format( $accountData['posts'] ); ?> posts | <?php echo number_format( $accountData['followers'] ); ?> followers | <?php echo number_format( $accountData['following'] ); ?> following
							</div>
							
							<div class="metric-row">
								<div class="metric-label">Total Views (this month):</div>
								<div class="metric-value"><?php echo number_format( $accountData['monthlyViews'] ); ?></div>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	</body>
</html>


