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
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Instagram Page Insights - Multiple Accounts</title>
		<meta charset="utf-8" />
		<style>
			/* Dark Mode Theme - YouTube Style */
			body { 
				font-family: Arial, sans-serif; 
				margin: 20px; 
				background: #0f0f0f; 
				color: #ffffff; 
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
	<body>
		<div class="container">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<h1 style="margin: 0;">📊 Instagram Page Insights</h1>
				<div class="menu-container">
					<button class="hamburger-btn" onclick="toggleDropdown()">
						<div class="hamburger-icon">
							<span></span>
							<span></span>
							<span></span>
						</div>
						<span>Select Account</span>
						<span class="selected-account" id="selectedAccount">All Accounts</span>
					</button>
					<div class="dropdown-menu" id="dropdownMenu">
						<div class="dropdown-item active" onclick="selectAccount('all')">All Accounts</div>
						<?php foreach ( $instagramUsernames as $username ) : ?>
							<div class="dropdown-item" onclick="selectAccount('<?php echo htmlspecialchars( $username ); ?>')"><?php echo htmlspecialchars( $username ); ?></div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<hr />

			<?php
				// Process each Instagram username
				foreach ( $instagramUsernames as $username ) {
					echo '<div class="page-card" data-account="' . htmlspecialchars( $username ) . '">';
					echo '<h2>@' . htmlspecialchars( $username ) . '</h2>';

					// Get business discovery (basic info)
					$businessInfo = getBusinessDiscovery( $username, $instagramAccountId, $accessToken );

					if ( isset( $businessInfo['error'] ) ) {
						echo '<div class="error">';
						echo '<strong>Error:</strong> ' . htmlspecialchars( $businessInfo['error']['message'] );
						echo '</div>';
						echo '</div>';
						continue;
					}

					if ( !isset( $businessInfo['business_discovery'] ) ) {
						echo '<div class="error">';
						echo '<strong>Error:</strong> Could not find business account. Make sure the username is correct and it\'s a business account.';
						echo '</div>';
						echo '</div>';
						continue;
					}

					$account = $businessInfo['business_discovery'];
					// ALWAYS use the ID from business_discovery - it's the correct Instagram Business Account ID
					// The provided ID might be a Page ID, which won't work for media/insights
					$accountId = $account['id']; // This is always the correct Instagram Business Account ID

					// Display basic info
					echo '<div class="page-header">';
					if ( isset( $account['profile_picture_url'] ) ) {
						echo '<img class="profile-img" src="' . htmlspecialchars( $account['profile_picture_url'] ) . '" alt="Profile" />';
					}
					echo '<div>';
					echo '<h3><a href="https://www.instagram.com/' . htmlspecialchars( $account['username'] ) . '" target="_blank">' . htmlspecialchars( $account['username'] ) . '</a></h3>';
					echo '<p><strong>Posts:</strong> ' . ( isset( $account['media_count'] ) ? $account['media_count'] : 'N/A' ) . ' | ';
					echo '<strong>Followers:</strong> ' . ( isset( $account['followers_count'] ) ? number_format( $account['followers_count'] ) : 'N/A' ) . ' | ';
					echo '<strong>Following:</strong> ' . ( isset( $account['follows_count'] ) ? number_format( $account['follows_count'] ) : 'N/A' ) . '</p>';
					echo '</div>';
					echo '</div>';

					// Get insights (only works for your own account or accounts you manage)
					// List of your owned account usernames
					$ownedAccounts = ['101xmarketing', '101xfounders', 'bizzindia'];
					
					// Check if this is one of your accounts
					if ( in_array( $username, $ownedAccounts ) || $accountId == $instagramAccountId ) {
						// Use the account's own ID for insights (from business_discovery - always correct)
						// This allows each account to get its own insights
						$accountInsightsId = $accountId;
						
						// Debug: Show which ID is being used
						echo '<div style="font-size: 11px; color: #aaaaaa; margin-bottom: 10px;">Using Instagram Account ID: ' . htmlspecialchars( $accountInsightsId ) . ' for insights</div>';
						
						// Try getting insights using the account's Instagram Account ID
						$userInsights = getUserInsights( $accountInsightsId, $accessToken );
						$userInsightsTotal = getUserInsightsTotalValue( $accountInsightsId, $accessToken );
						$userInsights30Days = getUserInsights30Days( $accountInsightsId, $accessToken );
						$reelsInsights = getReelsInsights( $accountInsightsId, $accessToken );

						// Show errors but continue processing other insights
						if ( isset( $userInsights['error'] ) ) {
							echo '<div class="error">';
							echo '<strong>Note:</strong> Some metrics may not be available for this account type. ' . htmlspecialchars( $userInsights['error']['message'] );
							if ( isset( $userInsights['error']['code'] ) ) {
								echo ' (Code: ' . $userInsights['error']['code'] . ')';
							}
							echo '</div>';
						}
						
						// Continue showing insights even if some fail
						// Always try to show available insights
						
						// Display daily insights
						if ( isset( $userInsights['data'] ) && !empty( $userInsights['data'] ) ) {
							echo '<h3>📈 Account Insights (Last 24 Hours)</h3>';
							foreach ( $userInsights['data'] as $insight ) {
								echo '<div class="insight-item">';
								echo '<div class="metric-name">' . htmlspecialchars( $insight['title'] ) . '</div>';
								if ( !empty( $insight['values'] ) ) {
									$latestValue = end( $insight['values'] );
									echo '<div class="insight-value">' . number_format( $latestValue['value'] ) . '</div>';
									if ( isset( $latestValue['end_time'] ) ) {
										echo '<div style="font-size: 12px; color: #888;">Last updated: ' . date( 'Y-m-d H:i:s', strtotime( $latestValue['end_time'] ) ) . '</div>';
									}
								}
								echo '</div>';
							}
						}

						// Display 30-day reach
						if ( isset( $userInsights30Days['data'] ) && !empty( $userInsights30Days['data'] ) ) {
							echo '<h3>📅 Reach - Last 30 Days</h3>';
							$totalReach = 0;
							foreach ( $userInsights30Days['data'] as $insight ) {
								if ( $insight['name'] == 'reach' && !empty( $insight['values'] ) ) {
									foreach ( $insight['values'] as $value ) {
										$totalReach += $value['value'];
									}
								}
							}
							echo '<div class="insight-item">';
							echo '<div class="metric-name">Total People Reached (Last 30 Days)</div>';
							echo '<div class="insight-value" style="font-size: 32px; color: #3ea6ff;">' . number_format( $totalReach ) . '</div>';
							echo '</div>';
						}

						// Display Top Performing Reels (Last 30 Days)
						// Note: Carousels don't support views/video_views metric anymore (deprecated Sept 2024)
						if ( isset( $reelsInsights['reels'] ) && !empty( $reelsInsights['reels'] ) ) {
							echo '<h3>🎬 Top Performing Reels (Last 30 Days)</h3>';
							
							// Show total views from last 30 days
							if ( isset( $reelsInsights['total_views'] ) && $reelsInsights['total_views'] > 0 ) {
								echo '<div class="insight-item">';
								echo '<div class="metric-name">Total Views (Last 30 Days)</div>';
								echo '<div class="insight-value" style="font-size: 32px; color: #3ea6ff;">' . number_format( $reelsInsights['total_views'] ) . '</div>';
								echo '<div style="font-size: 12px; color: #888; margin-top: 5px;">Based on ' . count( $reelsInsights['reels'] ) . ' videos/reels from last 30 days</div>';
								echo '<div style="font-size: 11px; color: #ff4444; margin-top: 5px;">Note: Carousel posts don\'t support views/video_views metric anymore (removed Sept 2024)</div>';
								echo '</div>';
							}
							
							// Show top 10 reels (already sorted by views)
							$topReels = array_slice( $reelsInsights['reels'], 0, 10 );
							
							if ( !empty( $topReels ) ) {
								echo '<h4 style="margin-top: 20px;">Top 10 Reels by Views:</h4>';
								$rank = 1;
								foreach ( $topReels as $reel ) {
									if ( $reel['views'] > 0 ) {  // Only show items with views
										echo '<div style="padding: 10px; background: #181818; margin: 5px 0; border-left: 3px solid #3ea6ff; border-radius: 4px;">';
										echo '<span style="font-weight: bold; color: #3ea6ff; margin-right: 10px;">#' . $rank . '</span>';
										echo '<a href="' . htmlspecialchars( $reel['permalink'] ) . '" target="_blank" style="color: #3ea6ff;">View Reel →</a> ';
										echo '<strong style="margin-left: 10px; color: #ffffff;">Views:</strong> <span style="color: #ffffff;">' . number_format( $reel['views'] ) . '</span>';
										if ( isset( $reel['timestamp'] ) ) {
											echo ' <span style="color: #888; font-size: 12px;">| ' . date( 'M d, Y', strtotime( $reel['timestamp'] ) ) . '</span>';
										}
										echo '</div>';
										$rank++;
									}
								}
							}
							
							// Show debug info if there are errors
							if ( isset( $reelsInsights['debug'] ) && !empty( $reelsInsights['debug'] ) ) {
								$debugCount = is_array( $reelsInsights['debug'] ) ? count( $reelsInsights['debug'] ) : 1;
								echo '<div style="font-size: 11px; color: #ff4444; margin-top: 10px;">Note: Some videos could not be processed. ' . $debugCount . ' errors.</div>';
							}
						} else if ( isset( $reelsInsights['debug'] ) && !empty( $reelsInsights['debug'] ) ) {
							echo '<h3>🎬 Reels Insights</h3>';
							echo '<div class="error">Could not retrieve reels insights. Check debug info below.</div>';
							// Handle both string and array debug info
							if ( is_array( $reelsInsights['debug'] ) ) {
								echo '<div style="font-size: 11px; color: #ff4444; margin-top: 5px;">Debug: ' . htmlspecialchars( implode( ', ', array_slice( $reelsInsights['debug'], 0, 3 ) ) ) . '</div>';
							} else {
								echo '<div style="font-size: 11px; color: #ff4444; margin-top: 5px;">Debug: ' . htmlspecialchars( $reelsInsights['debug'] ) . '</div>';
							}
						}

						// Get recent media and their insights (views/engagement)
						echo '<h3>📊 Recent Posts Insights</h3>';
						$userMedia = getUserMedia( $accountInsightsId, $accessToken, 5 );
						
						if ( isset( $userMedia['data'] ) && !empty( $userMedia['data'] ) ) {
							foreach ( $userMedia['data'] as $media ) {
								// Try Reels metrics first for videos, then fall back
								$isReel = ( $media['media_type'] == 'VIDEO' );
								$mediaInsights = getMediaInsights( $media['id'], $accessToken, $media['media_type'], $isReel );
								
								// If Reels metrics fail, try regular video metrics
								if ( isset( $mediaInsights['error'] ) && $isReel ) {
									$mediaInsights = getMediaInsights( $media['id'], $accessToken, $media['media_type'], false );
								}
								
								echo '<div class="insight-item" style="margin-bottom: 15px;">';
								echo '<div style="margin-bottom: 10px;">';
								echo '<a href="' . htmlspecialchars( $media['permalink'] ) . '" target="_blank" style="color: #3ea6ff;">View Post →</a>';
								echo ' <span style="color: #888;">| Type: ' . htmlspecialchars( $media['media_type'] ) . '</span>';
								echo '</div>';
								
								if ( isset( $mediaInsights['data'] ) && !empty( $mediaInsights['data'] ) ) {
									foreach ( $mediaInsights['data'] as $insight ) {
										echo '<div style="display: inline-block; margin-right: 15px; padding: 5px 10px; background: #303030; border-radius: 4px; color: #ffffff;">';
										$insightTitle = isset( $insight['title'] ) ? $insight['title'] : ( isset( $insight['name'] ) ? $insight['name'] : 'N/A' );
										echo '<strong>' . htmlspecialchars( $insightTitle ) . ':</strong> ';
										if ( !empty( $insight['values'] ) ) {
											$value = isset( $insight['values'][0]['value'] ) ? $insight['values'][0]['value'] : 0;
											echo number_format( $value );
										} else {
											echo '0';
										}
										echo '</div>';
									}
								} else if ( isset( $mediaInsights['error'] ) ) {
									echo '<div style="color: #ff4444; font-size: 12px;">Error: ' . htmlspecialchars( $mediaInsights['error']['message'] ) . '</div>';
								}
								echo '<div style="margin-top: 5px; color: #aaaaaa;">';
								echo 'Likes: ' . number_format( $media['like_count'] ) . ' | ';
								echo 'Comments: ' . number_format( $media['comments_count'] );
								echo '</div>';
								echo '</div>';
							}
						} else if ( isset( $userMedia['error'] ) ) {
							echo '<div style="color: #888; font-size: 12px;">Could not load media insights: ' . htmlspecialchars( $userMedia['error']['message'] ) . '</div>';
						}
					} else {
						echo '<div class="success">';
						echo '<strong>Note:</strong> This is not your account. You can only view public metrics (followers, posts, etc.) via business_discovery. ';
						echo 'To get detailed insights, you need to own or manage this Instagram Business account.';
						echo '</div>';
					}

					echo '</div>';
				}
			?>

			<hr />
			<div style="margin-top: 30px; padding: 15px; background: #181818; border-radius: 5px; border: 1px solid #303030;">
				<h3 style="color: #ffffff;">ℹ️ Important Notes:</h3>
				<ul style="color: #aaaaaa;">
					<li><strong>Own Account:</strong> You can only get detailed insights (impressions, reach, profile views) for Instagram accounts that you own or manage.</li>
					<li><strong>Other Accounts:</strong> For other accounts, you can only see public data (followers, posts count, etc.) via business_discovery.</li>
					<li><strong>Permissions:</strong> Make sure your access token has <code style="background: #303030; padding: 2px 6px; border-radius: 3px;">instagram_manage_insights</code> permission.</li>
					<li><strong>Business Account:</strong> The account must be an Instagram Business or Creator account.</li>
				</ul>
			</div>
		</div>
		
		<script>
			let currentAccount = 'all';
			
			function toggleDropdown() {
				const dropdown = document.getElementById('dropdownMenu');
				dropdown.classList.toggle('show');
			}
			
			function selectAccount(account) {
				currentAccount = account;
				
				// Update selected account display
				const selectedAccountEl = document.getElementById('selectedAccount');
				if (account === 'all') {
					selectedAccountEl.textContent = 'All Accounts';
				} else {
					selectedAccountEl.textContent = '@' + account;
				}
				
				// Update dropdown active state
				const dropdownItems = document.querySelectorAll('.dropdown-item');
				dropdownItems.forEach(item => {
					item.classList.remove('active');
					if ((account === 'all' && item.textContent.trim() === 'All Accounts') ||
						(account !== 'all' && item.textContent.trim() === account)) {
						item.classList.add('active');
					}
				});
				
				// Show/hide account cards
				const accountCards = document.querySelectorAll('.page-card');
				accountCards.forEach(card => {
					if (account === 'all') {
						card.style.display = 'block';
					} else {
						const cardAccount = card.getAttribute('data-account');
						if (cardAccount === account) {
							card.style.display = 'block';
						} else {
							card.style.display = 'none';
						}
					}
				});
				
				// Close dropdown
				document.getElementById('dropdownMenu').classList.remove('show');
			}
			
			// Close dropdown when clicking outside
			document.addEventListener('click', function(event) {
				const menuContainer = document.querySelector('.menu-container');
				if (!menuContainer.contains(event.target)) {
					document.getElementById('dropdownMenu').classList.remove('show');
				}
			});
		</script>
	</body>
</html>


