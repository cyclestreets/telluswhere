<?php

# Class to implement a website asking visitors to say where public infrastructure changes are needed and to report on existing infrastructure
class telluswhere
{
	# Settings
	function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'style'					=> 'default',
			'feedbackRecipient'		=> NULL,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	# Register actions
	private function actions ()
	{
		# Specify available actions
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '/',
			),
			'current' => array (
				'description' => false,
				'url' => '/current/',
			),
			'suggest' => array (
				'description' => false,
				'url' => '/suggest/',
			),
			'about' => array (
				'description' => false,
				'url' => '/about/',
			),
			'terms' => array (
				'description' => false,
				'url' => '/terms/',
			),
			'contacts' => array (
				'description' => false,
				'url' => '/contacts/',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	# Class properties
	private $template = array ();	// Associative array of fragments to be replaced
	
	
	
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
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults (), __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Determine the style directory in use
		if (!$this->styleDirectory = $this->getStyleDirectory ($this->settings['style'])) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error.</p>";
			echo $this->html;
			return false;
		}
		
		# If a file is requested, serve the file directly, then end
		if (isSet ($_GET['file'])) {
			$this->serveFile ($_GET['file']);
			return;
		}
		
		# End if no action specified
		if (!isSet ($_GET['action']) || !strlen ($_GET['action'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Ensure the action is valid
		$this->action = $_GET['action'];
		$this->actions = $this->actions ();
		if (!isSet ($this->actions[$this->action])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Perform the action, which will write into a page template
		$this->{$this->action} ();
		
		# Render the page
		$html = $this->renderPage ();
		
		# Show the HTML
		echo $html;
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
		# Send the header
		application::sendHeader (404);
		
		# Show generic text if custom 404 not available
		$page = '/404.html';
		$path = $this->styleDirectory . $page;
		$file = $_SERVER['DOCUMENT_ROOT'] . $path;
		if (!is_readable ($file)) {
			$html  = "\n<h1>Page not found</h1>";
			$html .= "\n<p>Sorry, that page was not found. Please check the URL or use the menu to navigate elsewhere.</p>";
			return $html;
		}
		
		# Get the HTML
		$html = $this->getHtmlPage ($page);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to load a templatised HTML page; the htmlClean.. functions are present to enable a template to be dropped in from a designer without making changes first
	private function getHtmlPage ($page)
	{
		# Determine the location
		$path = $this->styleDirectory . $page;
		$file = $_SERVER['DOCUMENT_ROOT'] . $path;
		
		# Load the file
		$html = file_get_contents ($file);
		
		# Chop trailing directory index; e.g. "/path/index.html" becomes "/path/"
		$html = $this->htmlCleanChopDirectoryIndex ($html);
		
		# Make HTML paths absolute; e.g. "css/" becomes "/css/"
		$html = $this->htmlCleanPathsAbsolute ($html, $path);
		
		# Replace templated sections with placeholders
		$html = $this->htmlCleanInsertPlaceholders ($html);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to chop directory indexes from links
	private function htmlCleanChopDirectoryIndex ($html)
	{
		# Define directory index filename(s)
		$supported = array ('index.html', );		// More can be added if found necessary, e.g. index.php
		$supportedDirectoryIndexesRegexp = implode ('|', $supported);
		
		# HTML href links
		$html = preg_replace ('@(\s+)(href)="(' . $supportedDirectoryIndexesRegexp . ')"@', '$1$2="./"', $html);			// Special case: href="index.html" becomes href="./"
		$html = preg_replace ('@(\s+)(href)="([^"]*)/(' . $supportedDirectoryIndexesRegexp . ')"@', '$1$2="$3/"', $html);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to rewrite HTML paths to be absolute
	private function htmlCleanPathsAbsolute ($html, $path)
	{
		# Extract all URL references or return HTML as-is)
		#!# No support for single-quoted (var='text') attributes quotes yet
		#!# Will currently catch href="path"/src="path" appearing within the HTML as plain text
		if (!preg_match_all ('@\s+(href|src)="([^"]+)"(\s|>)@', $html, $pathsOriginal, PREG_SET_ORDER)) {return $html;}
		
		# Determine the path prefix that needs to be inserted
		$path = dirname ($path . '.bogus') . '/';	// .bogus ensures that dirname doesn't convert "/foo/bar" (which should not be supplied anyway) to /foo
		$delimiter = '@';
		$prefix = preg_replace ($delimiter . '^' . addcslashes ($this->styleDirectory, $delimiter) . $delimiter, '', $path);	// i.e. replace /style/default/ with /, leaving e.g. /contacts/
		
		# Work through each path to determine the replacement path for each match
		$paths = array ();
		foreach ($pathsOriginal as $i => $match) {
			$paths[$i] = $match[2];		// By default, start with unamended original
			
			# Full URLs should be left unchanged
			if (preg_match ('|^https?://.+$|', $paths[$i])) {continue;}
			
			# Pure anchors should be left changed
			if (preg_match ('|^#.*$|', $paths[$i])) {continue;}
			
			# Current-directory only URLs ( ./ or ./something ) should be substituted with the absolute equivalent
			if ($paths[$i] == '.') {$paths[$i] = './';}	// Normalise
			if (preg_match ('|^\./(.*)$|', $paths[$i], $matches)) {
				$paths[$i] = $prefix . $matches[1];
				continue;
			}
			
			# Directory-traversal URLs - chop prefix for each, i.e. ../contacts/ => /prefix/../contacts/ => /contacts/
			if ($paths[$i] == '..') {$paths[$i] = '../';}	// Normalise
			if (preg_match ('|^\.\./(.*)$|', $paths[$i], $matches)) {
				$newPrefix = $prefix;
				while (preg_match ('|^\.\./(.*)$|', $paths[$i], $matches)) {
					if (strlen ($newPrefix)) {	// Never traverse higher than / - if HTML of ../../ should have been ../ then treat it as such
						$newPrefix = str_replace ('\\', '/', dirname ($newPrefix));	// Chop last component
					}
					$paths[$i] = $newPrefix . $matches[1];
				}
				continue;
			}
			
			# Prefix remainder, which are "from here" paths, e.g. "path/to" becomes "/prefix/path/to"
			$paths[$i] = $prefix . $paths[$i];
		}
		
		# Construct the find/replace entry; $match[1] is href/src; $match[2] is the original path
		$replacements = array ();
		foreach ($pathsOriginal as $i => $match) {
			$find    = sprintf (' %s="%s"', $match[1], $match[2]);
			$replace = sprintf (' %s="%s"', $match[1], $paths[$i]);
			$replacements[$find] = $replace;
		}
		
		# Perform replacements
		$html = strtr ($html, $replacements);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to insert placeholders, by replacing comments in the HTML where the placeholders go; the aim here is that a designer can supply code with both sample HTML and the placeholders in
	# This looks for "<!-- {$placeholdername} --> then lines of HTML here, then <!-- {/$placeholdername} -->"
	private function htmlCleanInsertPlaceholders ($html)
	{
		# Replace placeholder comments with actual placeholders; note \1 is a backreference to ensure the opening and closing tags match, and the s modifier enables multiple-line matches
		$html = preg_replace ('|' . '<!--\s+\{\$([^}]+)\}\s+-->.+<!--\s+/\{\$\1\}\s+-->' . '|s', '{\$\1}', $html);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to serve a file as per a standard webserver
	private function serveFile ($location)
	{
		# Throw 404 if none
		if (!strlen ($location)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Prevent directory traversal attacks
		if (substr_count ($location, '../')) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Ensure page exists
		$file = $_SERVER['DOCUMENT_ROOT'] . $this->styleDirectory . $location;
		if (!is_file ($file) || !is_readable ($file)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Enable caching to improve browser performance; see: http://stackoverflow.com/a/1583753/180733
		$lastModifiedTime = filemtime ($file);
		$etag = md5_file ($file);
		header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s', $lastModifiedTime) . ' GMT');
		header ('Etag: ' . $etag);
	    if (isset ($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset ($_SERVER['HTTP_IF_NONE_MATCH'])) {
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
		
		# Set a header for the MIME type of the file
		$mimeType = $this->getMimeType ($file);
		header ('Content-Type: ' . $mimeType);
		
		# Set a header for the length of the file
		header ('Content-Length: ' . filesize ($file));
		
		# Serve the file
		readfile ($file);
	}
	
	
	# Function to get the MIME type; this is basically a wrapper to finfo_file because of a PHP bug; see: http://stackoverflow.com/a/17736797/180733
	private function getMimeType ($file)
	{
		# Workaround for bug with finfo_file(); see http://bugs.php.net/53035
		$extension = pathinfo ($file, PATHINFO_EXTENSION);
		switch ($extension) {
			case 'css':
				return 'text/css';
			case 'js':
				return 'application/javascript';
		}
		
		# Get the MIME type
		$finfo = finfo_open (FILEINFO_MIME_TYPE);
		$mimeType = finfo_file ($finfo, $file);
		
		# Return the MIME type
		return $mimeType;
	}
	
	
	# Function to render the page
	private function renderPage ()
	{
		# Determine the location of the template
		$templateLocation = $this->actions[$this->action]['url'] . (substr ($this->actions[$this->action]['url'], -1) == '/' ? 'index.html' : '');	// Convert /path/ to /path/index.html
		
		# Obtain the template
		$html = $this->getHtmlPage ($templateLocation);
		
		# Convert to Smarty-format placeholders
		$substitutions = array ();
		foreach ($this->template as $find => $replace) {
			$find = '{$' . $find . '}';
			$substitutions[$find] = $replace;
		}
		
		# Perform substitutions
		$html = strtr ($html, $substitutions);
		
		# Return the HTML
		return $html;
	}
	
	
	/* Content pages */
	
	
	# Home page
	private function home ()
	{
		# Start the HTML
		$html = '';
		
		// #!# TODO
		$this->template['find'] = '<p>Replace</p>';		// Expects a placeholder {$find} in the HTML
		
		
		# Return the HTML
		return $html;
	}
	
	
	# Suggest a location page
	private function suggest ()
	{
		# Start the HTML
		$html = '';
		
		// #!# TODO
		
		
		# Return the HTML
		return $html;
	}
	
	
	# Page for auditing of current locations
	private function current ()
	{
		# Start the HTML
		$html = '';
		
		// #!# TODO
		
		
		# Return the HTML
		return $html;
	}
	
	
	# About page
	private function about ()
	{
		# Start the HTML
		$html = '';
		
		// #!# TODO
		
		
		# Return the HTML
		return $html;
	}
	
	
	# Terms and conditions page
	private function terms ()
	{
		# Start the HTML
		$html = '';
		
		// #!# TODO
		
		
		# Return the HTML
		return $html;
	}
	
	
	# Contacts page
	private function contacts ()
	{
		# Start the HTML
		$html = '';
		
		# Add in e-mail address
		$this->template['feedbackRecipient'] = application::encodeEmailAddress ($this->settings['feedbackRecipient']);
		
		// #!# TODO
		
		
		# Return the HTML
		return $html;
	}
}

?>