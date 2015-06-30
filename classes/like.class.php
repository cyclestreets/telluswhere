<?php

/**
 * Helper class to handle Like functionality
 */
class like
{
	# Constructor
	public function __construct ($telluswhere)
	{
		# Create handles to the properties
		$this->telluswhere = $telluswhere;
		$this->baseUrl = $telluswhere->baseUrl;
		$this->databaseConnection = $telluswhere->databaseConnection;
		$this->settings = $telluswhere->settings;
		$this->user = $telluswhere->user;
		
	}
	
	
	# Function to get the HTML
	public function getHtml ()
	{
		return $this->html;
	}
	
	
	# Function to determine if the location is already Liked
	public function isLiked ($id)
	{
		# Read the cookie value
		$likeCookieName = $this->likeCookieName ($id);
		$likeCookieValue = $this->likeCookieValue ($likeCookieName);
		$state = $likeCookieValue['state'];
		
		# Return the augmented template array
		return $state;
	}
	
	
	# Likes processor endpoint, accessed as a normal page or via AJAX
	public function main ($location)
	{
		# Get the data
		$data = $this->likeAux ($location);
		
		# If requested over AJAX, echo then end
		$isAjaxRequest = (isset ($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower ($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
		if ($isAjaxRequest) {
			header ('Content-type: application/json; charset=UTF-8');
			if (isSet ($data['error'])) {
				header ('HTTP/1.1 500 Internal Server Error');	// Necessary for proper jQuery A error handling
			}
			$response = json_encode ($data);
			echo $response;
			die;
		}
		
		# If an error occured, show the error page with custom text
		if (isSet ($data['error'])) {
			$errorHtml = "\n<p>" . htmlspecialchars ($data['error']) . '</p>';
			$this->html = $this->maps->error404 ($errorHtml);
			return false;
		}
		
		# Redirect to the same page to avoid reload
		$redirectTo = "{$this->baseUrl}/location/{$location['id']}/";
		$_SERVER['_SITE_URL'] = $_SERVER['_SERVER_PROTOCOL_TYPE'] . '://' . $_SERVER['HTTP_HOST'];	// #!# In case using a development machine; this needs to be fixed at top-level
		echo application::sendHeader (302, $_SERVER['_SITE_URL'] . $redirectTo, true);
	}
	
	
	# Likes addition/undo function, using cookie security
	private function likeAux ($data)
	{
		# End if no data supplied
		if (!$data) {
			return array ('error' => 'No ID was supplied.');
		}
		
		# Filter to required fields only
		$fields = array ('id', 'likes');
		$data = application::arrayFields ($data, $fields);
		
		# Define shortcut for ID
		$id = $data['id'];
		
		# Retrieve the token from the cookie if set, or create the token
		$cookieName = $this->likeCookieName ($id);
		$hasCookie = (isSet ($_COOKIE[$cookieName]));
		if ($hasCookie) {
			$cookieValue = $this->likeCookieValue ($cookieName);
			$token = $cookieValue['token'];
		} else {
			$token = md5 ('like' . time ());
		}
		
		# Post to the public API
		$like = array ('id' => $id, 'token' => $token);
		$apiUrl = $this->settings['apiBase'] . '/v2/' . 'photomap.like' . '?key=' . $this->settings['apiKey'];
		$result = application::file_post_contents ($apiUrl, $like);
		$result = json_decode ($result, true);
		
		# Handle error
		if (isSet ($result['error'])) {
			return array ('error' => $result['error']);
		}
		
		# Set the new total
		$data['likes'] = $result['total'];
		
		# Set whether the user Liked the location (rather than removed the Like)
		$data['liked'] = $result['liked'];
		
		# Set the new cookie value
		$cookieValue = $token . ':' . ($result['liked'] ? '1' : '0');
		$validityDays = 365;
		$timePeriod = time () + 60*60*24 * $validityDays;
		$wholeServer = $this->baseUrl . '/';
		setcookie ($cookieName, $cookieValue, $timePeriod, $wholeServer);
		
		# Return the data
		return $result;
	}
	
	
	# Helper function to return the Like cookie name for an ID
	private function likeCookieName ($id)
	{
		return "photomap-like-{$id}";
	}
	
	
	# Helper function to return the Like cookie state
	private function likeCookieValue ($cookieName)
	{
		# End if no cookie
		if (!isSet ($_COOKIE[$cookieName])) {
			return array (
				'token' => false,
				'state' => '0',
			);
		}
		
		# Unpack and return the value
		$data = array ();
		list ($data['token'], $data['state']) = explode (':', $_COOKIE[$cookieName]);
		return $data;
	}
}
