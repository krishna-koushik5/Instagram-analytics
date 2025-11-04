<?php
	// Load credentials from environment variables (Render) or defines.php (local)
	// This file should be included instead of defines.php
	
	if ( !defined( 'FACEBOOK_APP_ID' ) ) {
		// Check if running on Render (environment variables) or local (defines.php)
		if ( getenv( 'FACEBOOK_APP_ID' ) || getenv( 'accessToken' ) ) {
			// Running on Render - use environment variables
			define( 'FACEBOOK_APP_ID', getenv( 'FACEBOOK_APP_ID' ) ?: '811119178200956' );
			define( 'FACEBOOK_APP_SECRET', getenv( 'FACEBOOK_APP_SECRET' ) ?: '3786a2ce284d62ef2652851bfa6b0dff' );
			define( 'FACEBOOK_REDIRECT_URI', getenv( 'FACEBOOK_REDIRECT_URI' ) ?: 'https://instagram-analytics-1h8x.onrender.com/obtaining_access_token.php' );
			define( 'ENDPOINT_BASE', 'https://graph.facebook.com/v5.0/' );
			
			$accessToken = getenv( 'accessToken' ) ?: getenv( 'ACCESS_TOKEN' );
			$pageId = getenv( 'pageId' ) ?: getenv( 'PAGE_ID' );
			$instagramAccountId = getenv( 'instagramAccountId' ) ?: getenv( 'INSTAGRAM_ACCOUNT_ID' );
		} else {
			// Running locally - use defines.php
			if ( file_exists( 'defines.php' ) ) {
				include 'defines.php';
			} elseif ( file_exists( __DIR__ . '/defines.php' ) ) {
				include __DIR__ . '/defines.php';
			} else {
				// Try to load from parent directory (for files in subdirectories)
				$parentDefines = dirname( __DIR__ ) . '/defines.php';
				if ( file_exists( $parentDefines ) ) {
					include $parentDefines;
				}
			}
		}
	}
	
	// Start session if not already started
	if ( session_status() === PHP_SESSION_NONE ) {
		session_start();
	}
?>

