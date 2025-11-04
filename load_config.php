<?php
	// Load credentials from environment variables (Render) or defines.php (local)
	// This file should be included instead of defines.php
	
	if ( !defined( 'FACEBOOK_APP_ID' ) ) {
		// Helper function to get env var and strip quotes
		$getEnv = function( $key, $default = null ) {
			$value = getenv( $key ) ?: getenv( strtoupper( $key ) ) ?: getenv( strtolower( $key ) );
			if ( $value === false || $value === null || $value === '' ) {
				return $default;
			}
			// Remove surrounding quotes if present
			$value = trim( $value, " \t\n\r\0\x0B\"'" );
			return $value;
		};
		
		// Check if running on Render (environment variables) or local (defines.php)
		$envToken = $getEnv( 'accessToken' ) ?: $getEnv( 'ACCESS_TOKEN' );
		
		if ( $envToken || $getEnv( 'FACEBOOK_APP_ID' ) ) {
			// Running on Render - use environment variables
			define( 'FACEBOOK_APP_ID', $getEnv( 'FACEBOOK_APP_ID', '811119178200956' ) );
			define( 'FACEBOOK_APP_SECRET', $getEnv( 'FACEBOOK_APP_SECRET', '3786a2ce284d62ef2652851bfa6b0dff' ) );
			define( 'FACEBOOK_REDIRECT_URI', $getEnv( 'FACEBOOK_REDIRECT_URI', 'https://instagram-analytics-1h8x.onrender.com/obtaining_access_token.php' ) );
			define( 'ENDPOINT_BASE', 'https://graph.facebook.com/v5.0/' );
			
			$accessToken = $envToken;
			$pageId = $getEnv( 'pageId' ) ?: $getEnv( 'PAGE_ID', '794967310373861' );
			$instagramAccountId = $getEnv( 'instagramAccountId' ) ?: $getEnv( 'INSTAGRAM_ACCOUNT_ID', '17841475978250722' );
			
			// Validate that we have the token
			if ( empty( $accessToken ) ) {
				die( 'Error: accessToken environment variable is not set or is empty. Please set it in Render dashboard.' );
			}
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

