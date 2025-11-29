<?php
	include 'load_config.php';

	// Managed Instagram accounts that this token can access (username => account ID)
	// Add or update entries here when new accounts are connected.
	$managedInstagramAccounts = array(
		'101xmarketing'   => '17841475978250722',
		'101xfounders'    => '660517977141799',
		'bizzindia'       => '789802470889988',
		'startupcoded'    => null,
		'foundersinindia' => null,
	);

	function normalizeUsername( $username ) {
		return strtolower( ltrim( $username, '@' ) );
	}

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

	// Function to search owned media for a matching shortcode
	function getOwnedMediaByShortcode( $shortcode, $accountId, $accessToken ) {
		if ( empty( $accountId ) ) {
			return null;
		}

		$endpoint = ENDPOINT_BASE . $accountId . '/media';
		$params = array(
			'fields' => 'id,media_type,permalink,like_count,comments_count,video_view_count',
			'limit' => 50,
			'access_token' => $accessToken
		);
		
		$attempts = 0;
		
		while ( $endpoint && $attempts < 5 ) {
			$attempts++;
			
			$ch = curl_init();
			$url = $endpoint . ( strpos( $endpoint, '?' ) === false ? '?' . http_build_query( $params ) : '' );
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			
			$response = curl_exec( $ch );
			curl_close( $ch );
			
			$result = json_decode( $response, true );
			
			if ( isset( $result['data'] ) ) {
				foreach ( $result['data'] as $media ) {
					if ( isset( $media['permalink'] ) && strpos( $media['permalink'], $shortcode ) !== false ) {
						return $media;
					}
				}
			}
			
			if ( isset( $result['paging']['next'] ) ) {
				$endpoint = $result['paging']['next'];
				$params = array(); // next already has query params
			} else {
				$endpoint = null;
			}
		}
		
		return null;
	}

	// Function to fetch additional media details (including video_view_count)
	function getMediaDetailsById( $mediaId, $accessToken ) {
		$endpoint = ENDPOINT_BASE . $mediaId;
		$params = array(
			'fields' => 'id,media_type,permalink,video_view_count,like_count,comments_count',
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

	// Function to get media by ID with insights (for owned/public accounts when permitted)
	function getMediaInsightsById( $mediaId, $mediaType, $accessToken ) {
		$endpoint = ENDPOINT_BASE . $mediaId . '/insights';
		
		// Choose metrics based on media type to avoid unsupported metric errors
		$baseMetrics = array( 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions' );
		$metrics = $baseMetrics;
		
		if ( $mediaType === 'CAROUSEL_ALBUM' ) {
			$metrics = array( 'reach', 'saved', 'likes', 'comments', 'shares', 'total_interactions' );
		} elseif ( $mediaType === 'IMAGE' ) {
			$metrics = array( 'impressions', 'reach', 'engagement', 'saved' );
		} elseif ( in_array( $mediaType, array( 'VIDEO', 'REELS' ), true ) ) {
			// Try to request video views along with other metrics
			$metrics = array( 'views', 'reach', 'video_views', 'likes', 'comments', 'shares', 'saved', 'total_interactions' );
		}
		
		$params = array(
			'metric' => implode( ',', $metrics ),
			'access_token' => $accessToken
		);
		
		$makeRequest = function( $params ) use ( $endpoint ) {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $endpoint . '?' . http_build_query( $params ) );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			
			$response = curl_exec( $ch );
			curl_close( $ch );
			
			return json_decode( $response, true );
		};
		
		$response = $makeRequest( $params );
		
		// Fallback: if video_views is not supported, retry without it
		if ( isset( $response['error'] ) && strpos( strtolower( $response['error']['message'] ), 'video_views' ) !== false ) {
			$params['metric'] = implode( ',', array_diff( $metrics, array( 'video_views' ) ) );
			$response = $makeRequest( $params );
			$response['_fallback_without_video_views'] = true;
		}
		
		return $response;
	}

	// Function to save tracking data
	function saveTrackingData( $data ) {
		$trackingFile = __DIR__ . '/tracking_data.json';
		$trackingData = array();
		
		// Load existing data
		if ( file_exists( $trackingFile ) ) {
			$existingData = file_get_contents( $trackingFile );
			$trackingData = json_decode( $existingData, true ) ?: array();
		}
		
		// Add new entry
		$trackingData[] = $data;
		
		// Save back to file
		file_put_contents( $trackingFile, json_encode( $trackingData, JSON_PRETTY_PRINT ) );
	}

	// Process form submission
	$reelUrl = isset( $_POST['reel_url'] ) ? trim( $_POST['reel_url'] ) : '';
	$username = isset( $_POST['username'] ) ? trim( $_POST['username'] ) : '';
	$mediaIdInput = isset( $_POST['media_id'] ) ? trim( $_POST['media_id'] ) : '';
	$uploaderName = isset( $_POST['uploader_name'] ) ? trim( $_POST['uploader_name'] ) : '';
	$selectedAccount = isset( $_POST['account'] ) ? trim( $_POST['account'] ) : '';
	$result = null;
	$error = null;
	$debugLogs = array();
	$accountIdsTried = array();

	if ( !empty( $username ) ) {
		$username = ltrim( $username, '@' );
	}

	$normalizedUsername = !empty( $username ) ? normalizeUsername( $username ) : '';

	/**
	 * Resolve which Instagram account IDs to search for owned media.
	 *
	 * @return array list of account IDs
	 */
	function resolveAccountIdsToSearch( $normalizedUsername, $managedAccounts, $defaultAccountId, $accessToken, &$debugLogs ) {
		$ids = array();

		if ( !empty( $normalizedUsername ) ) {
			if ( isset( $managedAccounts[ $normalizedUsername ] ) && !empty( $managedAccounts[ $normalizedUsername ] ) ) {
				$ids[] = $managedAccounts[ $normalizedUsername ];
			} else {
				// Try business discovery to find the account ID dynamically
				$endpoint = ENDPOINT_BASE . $defaultAccountId;
				$params = array(
					'fields' => 'business_discovery.username(' . $normalizedUsername . '){id,ig_id,username}',
					'access_token' => $accessToken
				);

				$ch = curl_init();
				curl_setopt( $ch, CURLOPT_URL, $endpoint . '?' . http_build_query( $params ) );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

				$response = curl_exec( $ch );
				curl_close( $ch );

				$discovery = json_decode( $response, true );

				$debugLogs[] = array(
					'step' => 'account_id_discovery',
					'payload' => $discovery
				);

				if ( isset( $discovery['business_discovery']['id'] ) ) {
					$ids[] = $discovery['business_discovery']['id'];
				}
			}
		}

		// Always include any known managed IDs (excluding empty) to widen the search
		foreach ( $managedAccounts as $mappedId ) {
			if ( !empty( $mappedId ) ) {
				$ids[] = $mappedId;
			}
		}

		// Ensure the default account ID is part of the search
		if ( !empty( $defaultAccountId ) ) {
			$ids[] = $defaultAccountId;
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}
	
	if ( !empty( $mediaIdInput ) ) {
		// Direct media ID lookup
		$mediaData = getMediaDetailsById( $mediaIdInput, $accessToken );
		$debugLogs[] = array(
			'step' => 'direct_media_id_lookup',
			'payload' => $mediaData
		);
		
		if ( isset( $mediaData['error'] ) ) {
			$error = isset( $mediaData['error']['message'] ) ? $mediaData['error']['message'] : 'Unable to fetch media for this ID.';
		} elseif ( isset( $mediaData['id'] ) ) {
			// Fetch insights for additional metrics (if permitted)
			$mediaType = isset( $mediaData['media_type'] ) ? $mediaData['media_type'] : null;
			$insights = getMediaInsightsById( $mediaData['id'], $mediaType, $accessToken );
			$debugLogs[] = array(
				'step' => 'media_insights_lookup',
				'payload' => $insights
			);
			
			if ( !isset( $insights['error'] ) && isset( $insights['data'] ) ) {
				$result = array(
					'type' => 'owned',
					'media' => $mediaData,
					'insights' => $insights['data'],
					'insights_meta' => array(
						'fallback_without_video_views' => !empty( $insights['_fallback_without_video_views'] )
					)
				);
				
				// Save tracking data if uploader and account are provided
				if ( !empty( $uploaderName ) && !empty( $selectedAccount ) ) {
					// Extract view count from insights
					$viewCount = 0;
					foreach ( $insights['data'] as $insight ) {
						$name = isset( $insight['name'] ) ? strtolower( $insight['name'] ) : '';
						if ( in_array( $name, array( 'video_views', 'views', 'plays' ), true ) ) {
							$viewCount = isset( $insight['values'][0]['value'] ) ? $insight['values'][0]['value'] : 0;
							break;
						}
					}
					
					// If no views in insights, try video_view_count from media
					if ( $viewCount == 0 && isset( $mediaData['video_view_count'] ) ) {
						$viewCount = $mediaData['video_view_count'];
					}
					
					saveTrackingData( array(
						'timestamp' => date( 'Y-m-d H:i:s' ),
						'uploader_name' => $uploaderName,
						'account' => $selectedAccount,
						'reel_url' => isset( $mediaData['permalink'] ) ? $mediaData['permalink'] : '',
						'media_id' => $mediaIdInput,
						'views' => $viewCount,
						'likes' => isset( $mediaData['like_count'] ) ? $mediaData['like_count'] : 0,
						'comments' => isset( $mediaData['comments_count'] ) ? $mediaData['comments_count'] : 0,
					) );
				}
			} else {
				$result = array(
					'type' => 'public',
					'media' => $mediaData,
					'error' => isset( $insights['error'] ) ? $insights['error'] : null
				);
			}
		} else {
			$error = 'Could not find media for this ID. Please verify the ID belongs to an Instagram media object you have access to.';
		}
	} elseif ( !empty( $reelUrl ) ) {
		$shortcode = extractMediaIdFromUrl( $reelUrl );
		
		if ( !$shortcode ) {
			$error = 'Invalid Instagram URL. Please provide a valid reel or post URL.';
		} else {
			$accountIdsToSearch = resolveAccountIdsToSearch( $normalizedUsername, $managedInstagramAccounts, $instagramAccountId, $accessToken, $debugLogs );
			$mediaData = null;

			foreach ( $accountIdsToSearch as $accountIdToTry ) {
				$accountIdsTried[] = $accountIdToTry;
				$mediaData = getOwnedMediaByShortcode( $shortcode, $accountIdToTry, $accessToken );
				$debugLogs[] = array(
					'step' => 'owned_media_lookup',
					'account_id' => $accountIdToTry,
					'payload' => $mediaData
				);

				if ( $mediaData ) {
					break;
				}
			}
			
			// If not found in owned accounts, require username to look up publicly
			if ( !$mediaData ) {
				if ( empty( $username ) ) {
					$error = 'Please provide the Instagram username of the account that posted this reel.';
				} else {
					// Fetch media data (includes media ID) using business discovery
					$publicData = getPublicReelViews( $shortcode, $username, $instagramAccountId, $accessToken );
					$debugLogs[] = array(
						'step' => 'business_discovery_lookup',
						'payload' => $publicData
					);
					
					if ( $publicData && !isset( $publicData['error'] ) ) {
						$mediaData = $publicData;
					}
				}
			}
			
			if ( $mediaData && isset( $mediaData['id'] ) ) {
				// Ensure we have video_view_count when possible
				if ( !isset( $mediaData['video_view_count'] ) ) {
					$moreDetails = getMediaDetailsById( $mediaData['id'], $accessToken );
					$debugLogs[] = array(
						'step' => 'media_details_lookup',
						'payload' => $moreDetails
					);
					
					if ( isset( $moreDetails['video_view_count'] ) ) {
						$mediaData['video_view_count'] = $moreDetails['video_view_count'];
					}
					
					// Preserve other useful fields if missing
					foreach ( array( 'like_count', 'comments_count', 'permalink', 'media_type' ) as $field ) {
						if ( !isset( $mediaData[ $field ] ) && isset( $moreDetails[ $field ] ) ) {
							$mediaData[ $field ] = $moreDetails[ $field ];
						}
					}
				}
				
				// Got media ID - try to get insights (only works for owned accounts or if access permitted)
				$mediaType = isset( $mediaData['media_type'] ) ? $mediaData['media_type'] : null;
				$insights = getMediaInsightsById( $mediaData['id'], $mediaType, $accessToken );
				$debugLogs[] = array(
					'step' => 'media_insights_lookup',
					'payload' => $insights
				);
				
				if ( !isset( $insights['error'] ) && isset( $insights['data'] ) ) {
					// Success! We have insights (owned or permitted account)
					$result = array(
						'type' => 'owned',
						'media' => $mediaData,
						'insights' => $insights['data'],
						'insights_meta' => array(
							'fallback_without_video_views' => !empty( $insights['_fallback_without_video_views'] )
						)
					);
					
					// Save tracking data if uploader and account are provided
					if ( !empty( $uploaderName ) && !empty( $selectedAccount ) ) {
						// Extract view count from insights
						$viewCount = 0;
						foreach ( $insights['data'] as $insight ) {
							$name = isset( $insight['name'] ) ? strtolower( $insight['name'] ) : '';
							if ( in_array( $name, array( 'video_views', 'views', 'plays' ), true ) ) {
								$viewCount = isset( $insight['values'][0]['value'] ) ? $insight['values'][0]['value'] : 0;
								break;
							}
						}
						
						// If no views in insights, try video_view_count from media
						if ( $viewCount == 0 && isset( $mediaData['video_view_count'] ) ) {
							$viewCount = $mediaData['video_view_count'];
						}
						
						saveTrackingData( array(
							'timestamp' => date( 'Y-m-d H:i:s' ),
							'uploader_name' => $uploaderName,
							'account' => $selectedAccount,
							'reel_url' => $reelUrl ?: ( isset( $mediaData['permalink'] ) ? $mediaData['permalink'] : '' ),
							'media_id' => isset( $mediaData['id'] ) ? $mediaData['id'] : $mediaIdInput,
							'views' => $viewCount,
							'likes' => isset( $mediaData['like_count'] ) ? $mediaData['like_count'] : 0,
							'comments' => isset( $mediaData['comments_count'] ) ? $mediaData['comments_count'] : 0,
						) );
					}
				} else {
					// Try to use public data (if available) for views
					if ( isset( $mediaData['video_view_count'] ) ) {
						$result = array(
							'type' => 'public',
							'media' => $mediaData
						);
						
						// Save tracking data if uploader and account are provided
						if ( !empty( $uploaderName ) && !empty( $selectedAccount ) ) {
							saveTrackingData( array(
								'timestamp' => date( 'Y-m-d H:i:s' ),
								'uploader_name' => $uploaderName,
								'account' => $selectedAccount,
								'reel_url' => $reelUrl ?: ( isset( $mediaData['permalink'] ) ? $mediaData['permalink'] : '' ),
								'media_id' => isset( $mediaData['id'] ) ? $mediaData['id'] : $mediaIdInput,
								'views' => isset( $mediaData['video_view_count'] ) ? $mediaData['video_view_count'] : 0,
								'likes' => isset( $mediaData['like_count'] ) ? $mediaData['like_count'] : 0,
								'comments' => isset( $mediaData['comments_count'] ) ? $mediaData['comments_count'] : 0,
							) );
						}
					} else {
						$result = array(
							'type' => 'public_basic',
							'media' => $mediaData,
							'error' => isset( $insights['error'] ) ? $insights['error'] : null
						);
					}
				}
			} else {
				if ( !$error ) {
					$error = 'Could not find the reel. Make sure the URL and username are correct and the account is a Business/Creator account.';
				}
			}
		}
	} elseif ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
		$error = 'Please provide either an Instagram reel URL or a media ID.';
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
		
		/* Hamburger Menu */
		.hamburger-menu {
			position: fixed;
			top: 20px;
			right: 20px;
			z-index: 1000;
		}
		
		.hamburger-btn {
			width: 50px;
			height: 50px;
			background: rgba(33, 33, 33, 0.9);
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 8px;
			cursor: pointer;
			display: flex;
			flex-direction: column;
			justify-content: center;
			align-items: center;
			gap: 6px;
			transition: all 0.3s;
			backdrop-filter: blur(10px);
		}
		
		.hamburger-btn:hover {
			background: rgba(62, 166, 255, 0.2);
			border-color: #3ea6ff;
		}
		
		.hamburger-btn span {
			width: 25px;
			height: 3px;
			background: #ffffff;
			border-radius: 2px;
			transition: all 0.3s;
		}
		
		.hamburger-btn.active span:nth-child(1) {
			transform: rotate(45deg) translate(8px, 8px);
		}
		
		.hamburger-btn.active span:nth-child(2) {
			opacity: 0;
		}
		
		.hamburger-btn.active span:nth-child(3) {
			transform: rotate(-45deg) translate(7px, -7px);
		}
		
		.menu-overlay {
			position: fixed;
			top: 0;
			right: -100%;
			width: 300px;
			height: 100vh;
			background: rgba(18, 18, 18, 0.98);
			backdrop-filter: blur(20px);
			border-left: 1px solid rgba(255, 255, 255, 0.1);
			z-index: 999;
			transition: right 0.3s ease;
			padding: 80px 30px 30px;
			box-shadow: -5px 0 30px rgba(0, 0, 0, 0.5);
		}
		
		.menu-overlay.active {
			right: 0;
		}
		
		.menu-header {
			margin-bottom: 40px;
			padding-bottom: 20px;
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
		}
		
		.menu-header h2 {
			color: #3ea6ff;
			font-size: 24px;
			font-weight: bold;
			margin: 0;
		}
		
		.menu-items {
			list-style: none;
			padding: 0;
			margin: 0;
		}
		
		.menu-items li {
			margin-bottom: 15px;
		}
		
		.menu-items a {
			display: flex;
			align-items: center;
			padding: 15px 20px;
			color: #ffffff;
			text-decoration: none;
			border-radius: 8px;
			transition: all 0.3s;
			font-size: 16px;
			background: rgba(33, 33, 33, 0.5);
			border: 1px solid rgba(255, 255, 255, 0.05);
		}
		
		.menu-items a:hover {
			background: rgba(62, 166, 255, 0.2);
			border-color: #3ea6ff;
			transform: translateX(5px);
		}
		
		.menu-items a.active {
			background: rgba(62, 166, 255, 0.3);
			border-color: #3ea6ff;
		}
		
		.menu-items a .menu-icon {
			margin-right: 15px;
			font-size: 20px;
		}
		
		.menu-close-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.5);
			z-index: 998;
			opacity: 0;
			visibility: hidden;
			transition: all 0.3s;
		}
		
		.menu-close-overlay.active {
			opacity: 1;
			visibility: visible;
		}
	</style>
</head>
<body>
	<!-- Hamburger Menu -->
	<div class="hamburger-menu">
		<button class="hamburger-btn" id="hamburgerBtn" onclick="toggleMenu()">
			<span></span>
			<span></span>
			<span></span>
		</button>
	</div>
	
	<!-- Menu Overlay -->
	<div class="menu-close-overlay" id="menuOverlay" onclick="closeMenu()"></div>
	<nav class="menu-overlay" id="menuNav">
		<div class="menu-header">
			<h2>FRONT SEAT</h2>
		</div>
			<ul class="menu-items">
				<li>
					<a href="get_multiple_insights.php">
						<span class="menu-icon">🏠</span>
						<span>Dashboard</span>
					</a>
				</li>
				<li>
					<a href="scoreboard.php">
						<span class="menu-icon">🏆</span>
						<span>Scoreboard</span>
					</a>
				</li>
				<li>
					<a href="get_reel_views.php" class="active">
						<span class="menu-icon">🎬</span>
						<span>Get Reel Views</span>
					</a>
				</li>
			</ul>
	</nav>
	
	<script>
		function toggleMenu() {
			const btn = document.getElementById('hamburgerBtn');
			const nav = document.getElementById('menuNav');
			const overlay = document.getElementById('menuOverlay');
			
			btn.classList.toggle('active');
			nav.classList.toggle('active');
			overlay.classList.toggle('active');
		}
		
		function closeMenu() {
			const btn = document.getElementById('hamburgerBtn');
			const nav = document.getElementById('menuNav');
			const overlay = document.getElementById('menuOverlay');
			
			btn.classList.remove('active');
			nav.classList.remove('active');
			overlay.classList.remove('active');
		}
		
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				closeMenu();
			}
		});
		
		// Close menu when clicking menu links
		document.querySelectorAll('.menu-items a').forEach(function(link) {
			link.addEventListener('click', function() {
				closeMenu();
			});
		});
	</script>
	
	<div class="container">
		<h1>🎬 Get Reel Views</h1>
		<p style="color: #aaaaaa;">Enter an Instagram reel URL to get view count and other metrics</p>
		
		<form method="POST">
			<div class="form-group">
				<label for="media_id">Instagram Media ID (optional):</label>
				<input type="text" id="media_id" name="media_id" placeholder="17911066731246904" value="<?php echo htmlspecialchars( $mediaIdInput ); ?>" />
				<div class="note">If you already know the media ID, enter it here to pull the view count directly. Leave blank to use the URL instead.</div>
			</div>
			
			<div class="form-group">
				<label for="reel_url">Instagram Reel/Post URL:</label>
				<input type="text" id="reel_url" name="reel_url" placeholder="https://www.instagram.com/reel/ABC123xyz/" value="<?php echo htmlspecialchars( $reelUrl ); ?>" />
			</div>
			
			<div class="form-group">
				<label for="username">Instagram Username (optional, helps for public reels):</label>
				<input type="text" id="username" name="username" placeholder="username" value="<?php echo htmlspecialchars( $username ); ?>" />
				<div class="note">Enter the username of the account that posted this reel. This helps get views for public reels.</div>
			</div>
			
			<div class="form-group">
				<label for="uploader_name">Uploader Name (for tracking):</label>
				<input type="text" id="uploader_name" name="uploader_name" placeholder="Enter your name" value="<?php echo htmlspecialchars( isset( $_POST['uploader_name'] ) ? $_POST['uploader_name'] : '' ); ?>" />
				<div class="note">Enter the name of the person uploading this link. This helps track who is generating views.</div>
			</div>
			
			<div class="form-group">
				<label for="account">Instagram Account/Page:</label>
				<select id="account" name="account" style="width: 100%; padding: 12px; background: #212121; border: 1px solid #303030; border-radius: 4px; color: #ffffff; font-size: 14px; box-sizing: border-box;">
					<option value="">Select an account...</option>
					<option value="101xmarketing" <?php echo ( isset( $_POST['account'] ) && $_POST['account'] == '101xmarketing' ) ? 'selected' : ''; ?>>101xmarketing</option>
					<option value="101xfounders" <?php echo ( isset( $_POST['account'] ) && $_POST['account'] == '101xfounders' ) ? 'selected' : ''; ?>>101xfounders</option>
					<option value="bizzindia" <?php echo ( isset( $_POST['account'] ) && $_POST['account'] == 'bizzindia' ) ? 'selected' : ''; ?>>bizzindia</option>
					<option value="startupcoded" <?php echo ( isset( $_POST['account'] ) && $_POST['account'] == 'startupcoded' ) ? 'selected' : ''; ?>>startupcoded</option>
					<option value="foundersinindia" <?php echo ( isset( $_POST['account'] ) && $_POST['account'] == 'foundersinindia' ) ? 'selected' : ''; ?>>foundersinindia</option>
				</select>
				<div class="note">Select which Instagram account/page this reel belongs to.</div>
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
							$formattedMetrics = array();
							$viewMetric = null;
							
							foreach ( $result['insights'] as $insight ) {
								$value = isset( $insight['values'][0]['value'] ) ? $insight['values'][0]['value'] : 0;
								$name = isset( $insight['name'] ) ? strtolower( $insight['name'] ) : '';
								$title = isset( $insight['title'] ) ? $insight['title'] : '';
								$titleLower = strtolower( $title );
								
								if ( empty( $title ) && !empty( $name ) ) {
									$title = ucwords( str_replace( '_', ' ', $name ) );
									$titleLower = strtolower( $title );
								}
								
								$metricEntry = array(
									'name' => $name,
									'title' => $title,
									'value' => $value
								);
								
								// Identify view metrics
								if (
									in_array( $name, array( 'video_views', 'views', 'plays' ), true ) ||
									strpos( $titleLower, 'view' ) !== false ||
									strpos( $titleLower, 'play' ) !== false
								) {
									if ( !$viewMetric || $value > $viewMetric['value'] ) {
										$viewMetric = $metricEntry;
									}
									continue;
								}
								
								// Avoid duplicate labels
								$key = !empty( $name ) ? $name : $titleLower;
								$formattedMetrics[ $key ] = $metricEntry;
							}
							
							if ( $viewMetric ) {
								echo '<div class="metric">';
								echo '<div class="metric-label">Views</div>';
								echo '<div class="metric-value">' . number_format( $viewMetric['value'] ) . '</div>';
								echo '</div>';
							} elseif ( isset( $result['insights_meta']['fallback_without_video_views'] ) && $result['insights_meta']['fallback_without_video_views'] ) {
								// When insights call falls back without video views, note it
								echo '<div class="metric">';
								echo '<div class="metric-label">Views</div>';
								echo '<div class="metric-value">—</div>';
								echo '</div>';
							}
							
							foreach ( $formattedMetrics as $metric ) {
								echo '<div class="metric">';
								echo '<div class="metric-label">' . htmlspecialchars( $metric['title'] ) . '</div>';
								echo '<div class="metric-value">' . number_format( $metric['value'] ) . '</div>';
								echo '</div>';
							}
						}
						
						// If video_view_count is available (owned or public), show it explicitly
						if ( isset( $result['media']['video_view_count'] ) ) {
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
				
				<?php if ( !empty( $debugLogs ) ): ?>
					<div class="note" style="margin-top: 20px;">
						<strong>Debug Log:</strong>
						<pre style="white-space: pre-wrap; word-break: break-word; background: #181818; padding: 10px; border-radius: 6px; color: #cccccc; max-height: 300px; overflow-y: auto;"><?php echo htmlspecialchars( json_encode( $debugLogs, JSON_PRETTY_PRINT ) ); ?></pre>
					</div>
				<?php endif; ?>
				
				<?php if ( $result['type'] == 'public_basic' ): ?>
					<div class="note">
						<strong>Note:</strong> Only basic metrics (likes, comments) are available for this public reel. 
						Views are only available for reels from accounts you own or for public Business/Creator accounts when you provide the username.
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<hr style="border-color: #303030; margin: 30px 0;" />
		<p>
			<a href="scoreboard.php">🏆 View Scoreboard</a> | 
			<a href="tracking_dashboard.php">📊 View Tracking Dashboard</a> | 
			<a href="get_multiple_insights.php">← Back to Account Insights</a>
		</p>
	</div>
</body>
</html>

