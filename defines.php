<?php
	session_start();

	define( 'FACEBOOK_APP_ID', '811119178200956' );
	define( 'FACEBOOK_APP_SECRET', '3786a2ce284d62ef2652851bfa6b0dff' );
	define( 'FACEBOOK_REDIRECT_URI', 'http://localhost/instagram_graph_api/obtaining_access_token.php' );
	define( 'ENDPOINT_BASE', 'https://graph.facebook.com/v5.0/' );

	// accessToken
	$accessToken = 'EAALhtWZA8t3wBP3ZBYqCxU5nXN3VmgjZB0doLoqiMLatsvM3AsmzZB9sqzhP7HYFfrf6fOcjBhNERQussTWtZBjGQaagSivxG3ZB0BQ2ZCDC36yPNpKDarSfEZBYi4wY8Xs9vwK1dYenQZAIMUW6iNRfw8Ptuypm5ujII03Mt1DD2z90wGE7kf4rZAvTh7yXhWgEo5VH8Iop3VUvlNwmGRhuIQ0tVJkykyrq1K9yMzwNFOrCPvtsDGjxcJr5ZAgmPc7Qpp0kKq7zwzPodoS5ZAcwmqqEDaw3HaQLqMx9joJZBnwZDZD';

	// page id
	$pageId = '794967310373861';

	// instagram business account id
	$instagramAccountId = '17841475978250722';