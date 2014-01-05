<?php

# Class to implement a website asking visitors to say where public infrastructure changes are needed and to report on existing infrastructure
class telluswhere
{
	# Settings
	private $defaults = array (
		'style'		=> 'default',
		
	);
	
	# Class properties
	var $html = '';
	
	
	# Constructor
	public function __construct ($settings)
	{
		# Set the include path to include libraries
		set_include_path (get_include_path () . PATH_SEPARATOR . $_SERVER['DOCUMENT_ROOT'] . '/libraries/');
		
		# Load required libraries
		require_once ('application.php');
		
		# Define the location of the stub launching file
		$this->baseUrl = application::getBaseUrl ();
		
		# Function to merge the arguments; note that $errors returns the errors by reference and not as a result from the method
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults, __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Determine the style directory in use
		if (!$this->styleDirectory = $this->getStyleDirectory ($this->settings['style'])) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error.</p>";
			echo $this->html;
			return false;
		}
		
		
		
		// # Show the HTML
		// echo $this->html;
		
		# If no other action has been set, pass through file requests and serve directly
		$this->serveFile ();
	}
	
	
	# Function to determine the style directory in use
	private function getStyleDirectory ($style)
	{
		# Check the existence of the directory
		$location = '/style/' . $style;
		$directory = $_SERVER['DOCUMENT_ROOT'] . $location;
		if (!is_dir ($directory)) {return false;}
		
		# Return the location, not slash-terminated
		return $location;
	}
	
	
	# 404 page
	private function page404 ()
	{
		# End here
		application::sendHeader (404);
		include ($this->styleDirectory . '/404.html');
		return false;
	}
	
	
	# Function to serve a file as per a standard webserver
	private function serveFile ()
	{
		# Throw 404 if no page
		if (!isSet ($_GET['page']) || !strlen ($_GET['page'])) {
			$this->page404 ();
			return false;
		}
		
		# Prevent directory traversal attacks
		if (substr_count ($_GET['page'], '../')) {
			$this->page404 ();
			return false;
		}
		
		# Ensure page exists
		$page = $this->styleDirectory . $_GET['page'];
		$file = $_SERVER['DOCUMENT_ROOT'] . $page;
		if (!is_file ($file) || !is_readable ($file)) {
			$this->page404 ();
			return false;
		}
		
		# Enable caching to improve browser performance; see: http://stackoverflow.com/a/1583753/180733
		$lastModifiedTime = filemtime ($file);
		$etag = md5_file ($file);
		header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s', $lastModifiedTime) . ' GMT');
		header ('Etag: ' . $etag);
	    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset ($_SERVER['HTTP_IF_NONE_MATCH'])) {
			if (strtotime ($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModifiedTime || trim ($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
				header ('HTTP/1.1 304 Not Modified');
				return;
			}
		}
		
		# Ensure the fileinfo extension is loaded
		if (!function_exists ('finfo_open')) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error.</p>";
			echo $this->html;
			return false;
		}
		
		# Set a header for the MIMEtype of the file
		$finfo = finfo_open (FILEINFO_MIME_TYPE);
		$mimeType = finfo_file ($finfo, $file);
		header ('Content-Type: ' . $mimeType);
		
		# Set a header for the length of the file
		header ('Content-Length: ' . filesize ($file));
		
		# Serve the file
		readfile ($file);
	}
}


?>