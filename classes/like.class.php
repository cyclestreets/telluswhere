<?php

/**
 * Helper class to handle Like functionality
 */
class like
{
	# Class properties
	var $likeCookieName = 'photomap-like';
	
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
		$likeCookieValue = $this->likeCookieValue ();
		
		# Determine if the ID is in the list
		$isLiked = (in_array ($id, $likeCookieValue['liked']));
		
		# Return the state
		return $is;
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
		$hasCookie = (isSet ($_COOKIE[$this->likeCookieName]));
		if ($hasCookie) {
			$cookieValue = $this->likeCookieValue ();
			$token = $cookieValue['token'];
			$liked = $cookieValue['liked'];
		} else {
			$token = md5 ('like' . time ());
			$liked = array ();
		}
		
		# Create the browser fingerprint
		$fingerprint = hash ('sha256', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
		
		# Post to the public API
		$like = array (
			'id' => $id,
			'token' => $token,
			'fingerprint' => $fingerprint,
		);
		$apiUrl = $this->settings['apiBase'] . '/v2/' . 'photomap.like' . '?key=' . $this->settings['apiKey'];
		$result = application::file_post_contents ($apiUrl, $like);
		$result = json_decode ($result, true);
		
		# Handle error
		if (isSet ($result['error'])) {
			return array ('error' => $result['error']);
		}
		
		# Set whether the user Liked the location (rather than removed the Like)
		if ($result['liked']) {
			$liked[] = $id;
			$liked = array_unique ($liked);
		} else {
			$liked = array_diff ($liked, array ($id));	// Remove if present
		}
		
		# Set the new cookie value
		$cookieValue = $token . ':' . implode (',', $liked);
		$validityDays = 365;
		$timePeriod = time () + 60*60*24 * $validityDays;
		$wholeServer = $this->baseUrl . '/';
		setcookie ($this->likeCookieName, $cookieValue, $timePeriod, $wholeServer);
		
		# Return the data obtained from the API
		return $result;
	}
	
	
	# Helper function to return the Like cookie state
	private function likeCookieValue ()
	{
		# End if no cookie
		if (!isSet ($_COOKIE[$this->likeCookieName])) {
			return array (
				'token' => false,
				'liked' => array (),
			);
		}
		
		# Unpack and return the value
		$data = array ();
		list ($data['token'], $data['liked']) = explode (':', $_COOKIE[$this->likeCookieName]);
		
		# Convert the liked list into an array
		if ($data['liked']) {
			$data['liked'] = explode (',', $data['liked']);
		} else {
			$data['liked'] = array ();
		}
		
		# Return the data
		return $data;
	}
}
