<?php
	include 'load_config.php';
	
	// Function to get media details by ID (from get_reel_views.php)
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
	
	// Function to get media insights by ID (from get_reel_views.php)
	function getMediaInsightsById( $mediaId, $mediaType, $accessToken ) {
		$endpoint = ENDPOINT_BASE . $mediaId . '/insights';
		
		$baseMetrics = array( 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions' );
		$metrics = $baseMetrics;
		
		if ( $mediaType === 'CAROUSEL_ALBUM' ) {
			$metrics = array( 'reach', 'saved', 'likes', 'comments', 'shares', 'total_interactions' );
		} elseif ( $mediaType === 'IMAGE' ) {
			$metrics = array( 'impressions', 'reach', 'engagement', 'saved' );
		} elseif ( in_array( $mediaType, array( 'VIDEO', 'REELS' ), true ) ) {
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
		}
		
		return $response;
	}
	
	// Function to extract shortcode from URL
	function extractShortcodeFromUrl( $url ) {
		$patterns = array(
			'/instagram\.com\/(?:reels?|p)\/([A-Za-z0-9_-]+)/',
			'/instagram\.com\/.*\/reels?\/?([A-Za-z0-9_-]+)/'
		);
		
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				return $matches[1];
			}
		}
		
		return false;
	}
	
	// Function to get public reel views using business discovery (same as initial upload)
	function getPublicReelViews( $shortcode, $username, $instagramAccountId, $accessToken ) {
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
	
	// Function to refresh all views - uses same method as initial upload (URL + username)
	function refreshAllViews( $accessToken, $instagramAccountId ) {
		$trackingFile = __DIR__ . '/tracking_data.json';
		if ( !file_exists( $trackingFile ) ) {
			return array( 'success' => false, 'message' => 'No tracking data found' );
		}
		
		$trackingData = json_decode( file_get_contents( $trackingFile ), true ) ?: array();
		$updated = 0;
		$errors = 0;
		
		foreach ( $trackingData as &$entry ) {
			$reelUrl = isset( $entry['reel_url'] ) ? $entry['reel_url'] : '';
			$username = isset( $entry['account'] ) ? $entry['account'] : '';
			$viewCount = null;
			$mediaData = null;
			
			// Use the same method as initial upload: URL + username via business discovery
			if ( !empty( $reelUrl ) && !empty( $username ) ) {
				$shortcode = extractShortcodeFromUrl( $reelUrl );
				
				if ( $shortcode ) {
					// Fetch media using business discovery (same as when you first upload)
					$mediaData = getPublicReelViews( $shortcode, $username, $instagramAccountId, $accessToken );
					
					if ( $mediaData && isset( $mediaData['id'] ) ) {
						// Get insights using the media ID from business discovery
						$mediaType = isset( $mediaData['media_type'] ) ? $mediaData['media_type'] : null;
						$insights = getMediaInsightsById( $mediaData['id'], $mediaType, $accessToken );
						
						if ( !isset( $insights['error'] ) && isset( $insights['data'] ) ) {
							// Extract view count from insights
							foreach ( $insights['data'] as $insight ) {
								$name = isset( $insight['name'] ) ? strtolower( $insight['name'] ) : '';
								if ( in_array( $name, array( 'video_views', 'views', 'plays' ), true ) ) {
									$viewCount = isset( $insight['values'][0]['value'] ) ? intval( $insight['values'][0]['value'] ) : 0;
									break;
								}
							}
						}
						
						// Fallback to video_view_count if insights didn't work
						if ( ( $viewCount === null || $viewCount == 0 ) && isset( $mediaData['video_view_count'] ) ) {
							$viewCount = intval( $mediaData['video_view_count'] );
						}
						
						// Update entry with fresh data
						if ( $viewCount !== null && $viewCount >= 0 ) {
							$entry['views'] = $viewCount;
							$entry['last_updated'] = date( 'Y-m-d H:i:s' );
							$entry['media_id'] = $mediaData['id']; // Update media ID too
							$entry['likes'] = isset( $mediaData['like_count'] ) ? intval( $mediaData['like_count'] ) : ( isset( $entry['likes'] ) ? intval( $entry['likes'] ) : 0 );
							$entry['comments'] = isset( $mediaData['comments_count'] ) ? intval( $mediaData['comments_count'] ) : ( isset( $entry['comments'] ) ? intval( $entry['comments'] ) : 0 );
							$updated++;
						} else {
							$errors++;
						}
					} else {
						$errors++;
					}
				} else {
					$errors++;
				}
			} else {
				// Missing URL or username
				$errors++;
			}
		}
		
		// Save updated data
		file_put_contents( $trackingFile, json_encode( $trackingData, JSON_PRETTY_PRINT ) );
		
		return array( 
			'success' => true, 
			'updated' => $updated, 
			'errors' => $errors,
			'total' => count( $trackingData )
		);
	}
	
	// Handle refresh request
	$refreshResult = null;
	if ( isset( $_GET['refresh'] ) && $_GET['refresh'] == '1' ) {
		$refreshResult = refreshAllViews( $accessToken, $instagramAccountId );
	}
	
	// Load tracking data
	$trackingFile = __DIR__ . '/tracking_data.json';
	$trackingData = array();
	
	if ( file_exists( $trackingFile ) ) {
		$existingData = file_get_contents( $trackingFile );
		$trackingData = json_decode( $existingData, true ) ?: array();
	}
	
	// Sort by views (descending)
	usort( $trackingData, function( $a, $b ) {
		$viewsA = isset( $a['views'] ) ? intval( $a['views'] ) : 0;
		$viewsB = isset( $b['views'] ) ? intval( $b['views'] ) : 0;
		return $viewsB - $viewsA;
	} );
	
	// Calculate totals
	$totalViews = 0;
	$uploaderTotals = array();
	foreach ( $trackingData as $entry ) {
		$views = isset( $entry['views'] ) ? intval( $entry['views'] ) : 0;
		$totalViews += $views;
		$uploader = isset( $entry['uploader_name'] ) ? $entry['uploader_name'] : 'Unknown';
		if ( !isset( $uploaderTotals[ $uploader ] ) ) {
			$uploaderTotals[ $uploader ] = 0;
		}
		$uploaderTotals[ $uploader ] += $views;
	}
	
	// Sort uploaders by total views
	arsort( $uploaderTotals );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Scoreboard - Instagram Analytics</title>
	<meta charset="utf-8" />
	<meta http-equiv="refresh" content="300" />
	<style>
		body { 
			font-family: Arial, sans-serif; 
			margin: 0;
			padding: 20px; 
			background: #0f0f0f; 
			color: #ffffff; 
		}
		.container { 
			max-width: 1400px; 
			margin: 0 auto; 
			background: #181818; 
			padding: 30px; 
			border-radius: 8px; 
			box-shadow: 0 2px 4px rgba(0,0,0,0.3); 
		}
		.header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 30px;
			flex-wrap: wrap;
		}
		h1 {
			color: #3ea6ff;
			margin: 0;
			font-size: 36px;
		}
		.refresh-btn {
			background: #3ea6ff;
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 4px;
			cursor: pointer;
			font-size: 16px;
			font-weight: bold;
			text-decoration: none;
			display: inline-block;
		}
		.refresh-btn:hover {
			background: #5cb8ff;
		}
		.refresh-btn:disabled {
			background: #666;
			cursor: not-allowed;
		}
		.stats-bar {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 20px;
			margin-bottom: 30px;
		}
		.stat-card {
			background: #212121;
			border: 1px solid #303030;
			border-radius: 8px;
			padding: 20px;
			text-align: center;
		}
		.stat-value {
			font-size: 32px;
			font-weight: bold;
			color: #3ea6ff;
			margin: 10px 0;
		}
		.stat-label {
			font-size: 14px;
			color: #aaaaaa;
			text-transform: uppercase;
		}
		.leaderboard {
			background: #212121;
			border: 1px solid #303030;
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 30px;
		}
		.leaderboard h2 {
			color: #3ea6ff;
			margin-top: 0;
			margin-bottom: 20px;
		}
		.leaderboard-item {
			display: flex;
			align-items: center;
			padding: 15px;
			border-bottom: 1px solid #303030;
		}
		.leaderboard-item:last-child {
			border-bottom: none;
		}
		.rank {
			font-size: 24px;
			font-weight: bold;
			color: #3ea6ff;
			width: 50px;
			text-align: center;
		}
		.rank.gold { color: #ffd700; }
		.rank.silver { color: #c0c0c0; }
		.rank.bronze { color: #cd7f32; }
		.leaderboard-content {
			flex: 1;
			margin-left: 20px;
		}
		.leaderboard-name {
			font-size: 18px;
			font-weight: bold;
			color: #ffffff;
			margin-bottom: 5px;
		}
		.leaderboard-meta {
			font-size: 14px;
			color: #aaaaaa;
		}
		.leaderboard-views {
			font-size: 24px;
			font-weight: bold;
			color: #3ea6ff;
			text-align: right;
			min-width: 120px;
		}
		.scoreboard-table {
			width: 100%;
			border-collapse: collapse;
			background: #212121;
			margin-top: 20px;
		}
		.scoreboard-table th {
			background: #303030;
			color: #ffffff;
			padding: 15px;
			text-align: left;
			font-weight: bold;
			border-bottom: 2px solid #3ea6ff;
		}
		.scoreboard-table td {
			padding: 15px;
			border-bottom: 1px solid #303030;
			color: #cccccc;
		}
		.scoreboard-table tr:hover {
			background: #2a2a2a;
		}
		.views-col {
			color: #3ea6ff;
			font-weight: bold;
			font-size: 18px;
		}
		.reel-link {
			color: #3ea6ff;
			text-decoration: none;
			max-width: 300px;
			display: inline-block;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.reel-link:hover {
			text-decoration: underline;
		}
		.account-badge {
			display: inline-block;
			background: #303030;
			padding: 4px 8px;
			border-radius: 4px;
			font-size: 12px;
			color: #aaaaaa;
			margin-left: 10px;
		}
		.success-message {
			background: #1a3d1a;
			border: 1px solid #4caf50;
			border-radius: 4px;
			padding: 15px;
			margin-bottom: 20px;
			color: #4caf50;
		}
		.error-message {
			background: #3d1a1a;
			border: 1px solid #ff4444;
			border-radius: 4px;
			padding: 15px;
			margin-bottom: 20px;
			color: #ff4444;
		}
		.last-updated {
			font-size: 12px;
			color: #666;
			margin-top: 5px;
		}
		a {
			color: #3ea6ff;
			text-decoration: none;
		}
		a:hover {
			text-decoration: underline;
		}
		.empty-state {
			text-align: center;
			padding: 40px;
			color: #aaaaaa;
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
					<a href="scoreboard.php" class="active">
						<span class="menu-icon">🏆</span>
						<span>Scoreboard</span>
					</a>
				</li>
				<li>
					<a href="get_reel_views.php">
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
		<div class="header">
			<h1>🏆 Scoreboard</h1>
			<a href="?refresh=1" class="refresh-btn" onclick="this.innerHTML='Refreshing...'; this.style.pointerEvents='none'; return true;">🔄 Refresh All Views</a>
		</div>
		
		<?php if ( $refreshResult ): ?>
			<?php if ( $refreshResult['success'] ): ?>
				<?php if ( $refreshResult['updated'] > 0 ): ?>
					<div class="success-message">
						✅ Successfully refreshed <?php echo $refreshResult['updated']; ?> of <?php echo $refreshResult['total']; ?> entries. 
						<?php if ( $refreshResult['errors'] > 0 ): ?>
							<?php echo $refreshResult['errors']; ?> entries couldn't be updated (may not have access to insights API).
						<?php endif; ?>
					</div>
				<?php else: ?>
					<div class="error-message">
						⚠️ Could not refresh any entries. This might be because:
						<ul style="margin: 10px 0 0 20px;">
							<li>The media is not accessible via Insights API (ShadowIGMedia issue)</li>
							<li>API rate limits or permissions</li>
							<li>Media IDs might need to be re-fetched</li>
						</ul>
						<p style="margin-top: 10px;">Try uploading the reel link again to get fresh data.</p>
					</div>
				<?php endif; ?>
			<?php else: ?>
				<div class="error-message">
					❌ <?php echo htmlspecialchars( $refreshResult['message'] ); ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		
		<div class="stats-bar">
			<div class="stat-card">
				<div class="stat-label">Total Views</div>
				<div class="stat-value"><?php echo number_format( $totalViews ); ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Total Reels</div>
				<div class="stat-value"><?php echo count( $trackingData ); ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Active Uploaders</div>
				<div class="stat-value"><?php echo count( $uploaderTotals ); ?></div>
			</div>
		</div>
		
		<?php if ( count( $uploaderTotals ) > 0 ): ?>
			<div class="leaderboard">
				<h2>👤 Top Uploaders</h2>
				<?php 
					$rank = 1;
					foreach ( $uploaderTotals as $uploader => $totalViews ): 
						$rankClass = '';
						if ( $rank == 1 ) $rankClass = 'gold';
						elseif ( $rank == 2 ) $rankClass = 'silver';
						elseif ( $rank == 3 ) $rankClass = 'bronze';
				?>
					<div class="leaderboard-item">
						<div class="rank <?php echo $rankClass; ?>">#<?php echo $rank; ?></div>
						<div class="leaderboard-content">
							<div class="leaderboard-name"><?php echo htmlspecialchars( $uploader ); ?></div>
							<div class="leaderboard-meta">Total views across all reels</div>
						</div>
						<div class="leaderboard-views"><?php echo number_format( $totalViews ); ?></div>
					</div>
				<?php 
					$rank++;
					if ( $rank > 10 ) break;
				endforeach; 
				?>
			</div>
		<?php endif; ?>
		
		<?php if ( count( $trackingData ) > 0 ): ?>
			<div class="leaderboard">
				<h2>📊 All Reels (Sorted by Views)</h2>
				<table class="scoreboard-table">
					<thead>
						<tr>
							<th>Rank</th>
							<th>Uploader</th>
							<th>Reel Link</th>
							<th>Account</th>
							<th style="text-align: right;">Views</th>
							<th style="text-align: right;">Likes</th>
							<th style="text-align: right;">Comments</th>
							<th>Last Updated</th>
						</tr>
					</thead>
					<tbody>
						<?php 
							$rank = 1;
							foreach ( $trackingData as $entry ): 
								$views = isset( $entry['views'] ) ? intval( $entry['views'] ) : 0;
								$rankClass = '';
								if ( $rank == 1 ) $rankClass = 'gold';
								elseif ( $rank == 2 ) $rankClass = 'silver';
								elseif ( $rank == 3 ) $rankClass = 'bronze';
						?>
							<tr>
								<td><span class="rank <?php echo $rankClass; ?>">#<?php echo $rank; ?></span></td>
								<td><strong><?php echo htmlspecialchars( isset( $entry['uploader_name'] ) ? $entry['uploader_name'] : 'Unknown' ); ?></strong></td>
								<td>
									<?php if ( isset( $entry['reel_url'] ) && !empty( $entry['reel_url'] ) ): ?>
										<a href="<?php echo htmlspecialchars( $entry['reel_url'] ); ?>" target="_blank" class="reel-link"><?php echo htmlspecialchars( $entry['reel_url'] ); ?></a>
									<?php else: ?>
										<span style="color: #666;">No URL</span>
									<?php endif; ?>
								</td>
								<td>
									<span class="account-badge">@<?php echo htmlspecialchars( isset( $entry['account'] ) ? $entry['account'] : 'Unknown' ); ?></span>
								</td>
								<td class="views-col" style="text-align: right;"><?php echo number_format( $views ); ?></td>
								<td style="text-align: right; color: #4caf50;"><?php echo number_format( isset( $entry['likes'] ) ? $entry['likes'] : 0 ); ?></td>
								<td style="text-align: right; color: #4caf50;"><?php echo number_format( isset( $entry['comments'] ) ? $entry['comments'] : 0 ); ?></td>
								<td style="font-size: 12px; color: #666;">
									<?php 
										if ( isset( $entry['last_updated'] ) ) {
											echo htmlspecialchars( $entry['last_updated'] );
										} elseif ( isset( $entry['timestamp'] ) ) {
											echo htmlspecialchars( $entry['timestamp'] );
										} else {
											echo 'Never';
										}
									?>
								</td>
							</tr>
						<?php 
							$rank++;
						endforeach; 
						?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<div class="empty-state">
				<p>No tracking data yet. Start uploading links in the <a href="get_reel_views.php">Get Reel Views</a> tool to see the scoreboard.</p>
			</div>
		<?php endif; ?>
		
		<hr style="border-color: #303030; margin: 30px 0;" />
		<p>
			<a href="get_reel_views.php">← Back to Get Reel Views</a> | 
			<a href="tracking_dashboard.php">📊 View Detailed Dashboard</a> | 
			<a href="get_multiple_insights.php">← Back to Account Insights</a>
		</p>
	</div>
</body>
</html>

