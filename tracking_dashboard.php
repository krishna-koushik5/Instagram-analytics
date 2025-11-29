<?php
	include 'load_config.php';
	
	// Load tracking data
	$trackingFile = __DIR__ . '/tracking_data.json';
	$trackingData = array();
	
	if ( file_exists( $trackingFile ) ) {
		$existingData = file_get_contents( $trackingFile );
		$trackingData = json_decode( $existingData, true ) ?: array();
	}
	
	// Calculate analytics
	$uploaderStats = array();
	$accountStats = array();
	$totalViews = 0;
	$totalReels = count( $trackingData );
	
	foreach ( $trackingData as $entry ) {
		$uploader = isset( $entry['uploader_name'] ) ? $entry['uploader_name'] : 'Unknown';
		$account = isset( $entry['account'] ) ? $entry['account'] : 'Unknown';
		$views = isset( $entry['views'] ) ? intval( $entry['views'] ) : 0;
		
		// Uploader stats
		if ( !isset( $uploaderStats[ $uploader ] ) ) {
			$uploaderStats[ $uploader ] = array(
				'total_views' => 0,
				'total_reels' => 0,
				'accounts' => array()
			);
		}
		$uploaderStats[ $uploader ]['total_views'] += $views;
		$uploaderStats[ $uploader ]['total_reels']++;
		if ( !in_array( $account, $uploaderStats[ $uploader ]['accounts'] ) ) {
			$uploaderStats[ $uploader ]['accounts'][] = $account;
		}
		
		// Account stats
		if ( !isset( $accountStats[ $account ] ) ) {
			$accountStats[ $account ] = array(
				'total_views' => 0,
				'total_reels' => 0,
				'uploaders' => array()
			);
		}
		$accountStats[ $account ]['total_views'] += $views;
		$accountStats[ $account ]['total_reels']++;
		if ( !in_array( $uploader, $accountStats[ $account ]['uploaders'] ) ) {
			$accountStats[ $account ]['uploaders'][] = $uploader;
		}
		
		$totalViews += $views;
	}
	
	// Sort by total views (descending)
	uasort( $uploaderStats, function( $a, $b ) {
		return $b['total_views'] - $a['total_views'];
	} );
	
	uasort( $accountStats, function( $a, $b ) {
		return $b['total_views'] - $a['total_views'];
	} );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Tracking Dashboard - Instagram Analytics</title>
	<meta charset="utf-8" />
	<style>
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
		h1 {
			color: #3ea6ff;
			margin-bottom: 10px;
		}
		h2 {
			color: #aaaaaa;
			margin-top: 30px;
			margin-bottom: 15px;
			border-bottom: 2px solid #303030;
			padding-bottom: 10px;
		}
		.stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
			font-size: 36px;
			font-weight: bold;
			color: #3ea6ff;
			margin: 10px 0;
		}
		.stat-label {
			font-size: 14px;
			color: #aaaaaa;
			text-transform: uppercase;
		}
		.table-container {
			overflow-x: auto;
			margin-top: 20px;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			background: #212121;
		}
		th {
			background: #303030;
			color: #ffffff;
			padding: 12px;
			text-align: left;
			font-weight: bold;
		}
		td {
			padding: 12px;
			border-bottom: 1px solid #303030;
			color: #cccccc;
		}
		tr:hover {
			background: #2a2a2a;
		}
		.views-cell {
			color: #3ea6ff;
			font-weight: bold;
		}
		.reels-cell {
			color: #4caf50;
		}
		.accounts-cell, .uploaders-cell {
			color: #aaaaaa;
			font-size: 12px;
		}
		.recent-entries {
			margin-top: 30px;
		}
		.entry-item {
			background: #212121;
			border: 1px solid #303030;
			border-radius: 8px;
			padding: 15px;
			margin-bottom: 10px;
		}
		.entry-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 10px;
		}
		.entry-meta {
			font-size: 12px;
			color: #aaaaaa;
		}
		.entry-link {
			color: #3ea6ff;
			text-decoration: none;
		}
		.entry-link:hover {
			text-decoration: underline;
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
	</style>
</head>
<body>
	<div class="container">
		<h1>📊 Tracking Dashboard</h1>
		<p style="color: #aaaaaa;">View analytics on who is uploading links and generating views</p>
		
		<div class="stats-grid">
			<div class="stat-card">
				<div class="stat-label">Total Views</div>
				<div class="stat-value"><?php echo number_format( $totalViews ); ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Total Reels Tracked</div>
				<div class="stat-value"><?php echo number_format( $totalReels ); ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Active Uploaders</div>
				<div class="stat-value"><?php echo count( $uploaderStats ); ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Accounts Tracked</div>
				<div class="stat-value"><?php echo count( $accountStats ); ?></div>
			</div>
		</div>
		
		<?php if ( count( $uploaderStats ) > 0 ): ?>
			<h2>👤 Views by Uploader</h2>
			<div class="table-container">
				<table>
					<thead>
						<tr>
							<th>Uploader Name</th>
							<th>Total Views</th>
							<th>Reels Uploaded</th>
							<th>Avg Views/Reel</th>
							<th>Accounts</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $uploaderStats as $uploader => $stats ): ?>
							<tr>
								<td><strong><?php echo htmlspecialchars( $uploader ); ?></strong></td>
								<td class="views-cell"><?php echo number_format( $stats['total_views'] ); ?></td>
								<td class="reels-cell"><?php echo number_format( $stats['total_reels'] ); ?></td>
								<td><?php echo number_format( $stats['total_reels'] > 0 ? round( $stats['total_views'] / $stats['total_reels'] ) : 0 ); ?></td>
								<td class="accounts-cell"><?php echo htmlspecialchars( implode( ', ', $stats['accounts'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		
		<?php if ( count( $accountStats ) > 0 ): ?>
			<h2>📱 Views by Account</h2>
			<div class="table-container">
				<table>
					<thead>
						<tr>
							<th>Account</th>
							<th>Total Views</th>
							<th>Reels Tracked</th>
							<th>Avg Views/Reel</th>
							<th>Uploaders</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $accountStats as $account => $stats ): ?>
							<tr>
								<td><strong>@<?php echo htmlspecialchars( $account ); ?></strong></td>
								<td class="views-cell"><?php echo number_format( $stats['total_views'] ); ?></td>
								<td class="reels-cell"><?php echo number_format( $stats['total_reels'] ); ?></td>
								<td><?php echo number_format( $stats['total_reels'] > 0 ? round( $stats['total_views'] / $stats['total_reels'] ) : 0 ); ?></td>
								<td class="uploaders-cell"><?php echo htmlspecialchars( implode( ', ', $stats['uploaders'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		
		<?php if ( count( $trackingData ) > 0 ): ?>
			<h2>📋 Recent Entries</h2>
			<div class="recent-entries">
				<?php 
					$recentEntries = array_slice( array_reverse( $trackingData ), 0, 10 );
					foreach ( $recentEntries as $entry ): 
				?>
					<div class="entry-item">
						<div class="entry-header">
							<div>
								<strong><?php echo htmlspecialchars( isset( $entry['uploader_name'] ) ? $entry['uploader_name'] : 'Unknown' ); ?></strong>
								<span style="color: #aaaaaa; margin: 0 10px;">→</span>
								<strong>@<?php echo htmlspecialchars( isset( $entry['account'] ) ? $entry['account'] : 'Unknown' ); ?></strong>
							</div>
							<div class="views-cell"><?php echo number_format( isset( $entry['views'] ) ? $entry['views'] : 0 ); ?> views</div>
						</div>
						<div class="entry-meta">
							<?php if ( isset( $entry['reel_url'] ) && !empty( $entry['reel_url'] ) ): ?>
								<a href="<?php echo htmlspecialchars( $entry['reel_url'] ); ?>" target="_blank" class="entry-link"><?php echo htmlspecialchars( $entry['reel_url'] ); ?></a>
								<span style="margin: 0 10px;">•</span>
							<?php endif; ?>
							<?php echo htmlspecialchars( isset( $entry['timestamp'] ) ? $entry['timestamp'] : 'Unknown time' ); ?>
							<?php if ( isset( $entry['likes'] ) || isset( $entry['comments'] ) ): ?>
								<span style="margin: 0 10px;">•</span>
								<?php if ( isset( $entry['likes'] ) ): ?>
									<span style="color: #4caf50;"><?php echo number_format( $entry['likes'] ); ?> likes</span>
								<?php endif; ?>
								<?php if ( isset( $entry['comments'] ) ): ?>
									<span style="color: #4caf50; margin-left: 10px;"><?php echo number_format( $entry['comments'] ); ?> comments</span>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="empty-state">
				<p>No tracking data yet. Start uploading links in the <a href="get_reel_views.php">Get Reel Views</a> tool to see analytics here.</p>
			</div>
		<?php endif; ?>
		
		<hr style="border-color: #303030; margin: 30px 0;" />
		<p>
			<a href="get_reel_views.php">← Back to Get Reel Views</a> | 
			<a href="get_multiple_insights.php">← Back to Account Insights</a>
		</p>
	</div>
</body>
</html>

