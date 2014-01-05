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
		
		
		
		# Show the HTML
		echo $this->html;
	}
	
	
	# Function to determine the style directory in use
	private function getStyleDirectory ($style)
	{
		# Check the existence of the directory
		$location = '/style/' . $style . '/';
		$directory = $_SERVER['DOCUMENT_ROOT'] . $location;
		if (!is_dir ($directory)) {return false;}
		
		# Return the location
		return $location;
	}
	
}


?>