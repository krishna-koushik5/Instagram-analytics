from defines import getCreds, makeApiCall

def getUserMedia( params, limit=25 ) :
	""" Get users media with likes and comments
	
	API Endpoint:
		https://graph.instagram.com/{graph-api-version}/{ig-user-id}/media?fields={fields}&limit={limit}
	
	Returns:
		object: data from the endpoint
	
	"""
	
	endpointParams = dict() # parameter to send to the endpoint
	endpointParams['fields'] = 'id,media_type,caption,like_count,comments_count' # fields to get back
	endpointParams['limit'] = str(limit) # limit results
	endpointParams['access_token'] = params['access_token'] # access token
	
	url = params['endpoint_base'] + params['instagram_account_id'] + '/media' # endpoint url
	
	return makeApiCall( url, endpointParams, params['debug'] ) # make the api call

def getMediaInsights( params, media_id, media_type ) :
	""" Get insights for a specific media id
	
	API Endpoint:
		https://graph.facebook.com/{graph-api-version}/{ig-media-id}/insights?metric={metric}
	
	Returns:
		object: data from the endpoint
	
	"""
	endpointParams = dict() # parameter to send to the endpoint
	
	# Set metrics based on media type
	if media_type == 'VIDEO' or media_type == 'CAROUSEL_ALBUM':
		endpointParams['metric'] = 'engagement,impressions,reach,saved,video_views'
	else:
		endpointParams['metric'] = 'engagement,impressions,reach,saved'
	
	endpointParams['access_token'] = params['access_token'] # access token
	
	# Use graph.facebook.com for insights (not graph.instagram.com)
	insights_base = 'https://graph.facebook.com/' + params['graph_version'] + '/'
	url = insights_base + media_id + '/insights' # endpoint url
	
	return makeApiCall( url, endpointParams, params['debug'] ) # make the api call

# Get credentials
params = getCreds()

# Get user media
print "\n" + "="*80
print "FETCHING MEDIA WITH LIKES AND COMMENTS"
print "="*80
response = getUserMedia( params, limit=25 )

if 'data' in response['json_data']:
	media_list = response['json_data']['data']
	print "\nFound {} posts\n".format(len(media_list))
	
	# Loop through each media item
	for i, media in enumerate(media_list, 1):
		print "\n" + "-"*80
		print "POST #{}".format(i)
		print "-"*80
		
		# Basic info
		print "\nMedia ID: {}".format(media.get('id', 'N/A'))
		print "Media Type: {}".format(media.get('media_type', 'N/A'))
		print "Likes: {}".format(media.get('like_count', 0))
		print "Comments: {}".format(media.get('comments_count', 0))
		
		# Engagement = likes + comments
		engagement = media.get('like_count', 0) + media.get('comments_count', 0)
		print "Engagement: {}".format(engagement)
		
		# Caption preview
		caption = media.get('caption', '')
		if caption:
			caption_preview = caption[:100] + "..." if len(caption) > 100 else caption
			print "\nCaption: {}".format(caption_preview)
		
		# Get insights for views and detailed engagement
		print "\nFetching insights..."
		insights_response = getMediaInsights( params, media['id'], media.get('media_type', ''))
		
		if 'data' in insights_response['json_data']:
			print "\n--- Insights ---"
			for insight in insights_response['json_data']['data']:
				insight_name = insight.get('name', '')
				insight_value = insight.get('values', [{}])[0].get('value', 0)
				
				if insight_name == 'video_views':
					print "Views: {}".format(insight_value)
				elif insight_name == 'impressions':
					print "Impressions: {}".format(insight_value)
				elif insight_name == 'reach':
					print "Reach: {}".format(insight_value)
				elif insight_name == 'engagement':
					print "Total Engagement: {}".format(insight_value)
				elif insight_name == 'saved':
					print "Saves: {}".format(insight_value)
		else:
			print "Could not fetch insights (may require additional permissions)"
			if 'error' in insights_response['json_data']:
				print "Error: {}".format(insights_response['json_data']['error'].get('message', 'Unknown error'))
		
		print "\n"
else:
	print "Error fetching media:"
	if 'error' in response['json_data']:
		print response['json_data']['error'].get('message', 'Unknown error')
	else:
		print json.dumps(response['json_data'], indent=2)

print "\n" + "="*80
print "COMPLETE"
print "="*80
