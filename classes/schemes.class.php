<?php

/**
 * Schemes module
 */
class schemes
{
	# Register actions
	private function actions ()
	{
		# Specify available actions; URL refers both to the public URL and the template location
		$actions = array (
			'schemeslist' => array (
				'description' => false,
				'url' => '/',
			),
			'schemeadd' => array (
				'description' => false,
				'url' => '/add.html',
			),
			'schemeshow' => array (
				'description' => false,
				'url' => '/%scheme/',
			),
			'schemeedit' => array (
				'description' => false,
				'url' => '/%scheme/edit.html',
			),
			'visionadd' => array (
				'description' => false,
				'url' => '/%scheme/addvision.html',
			),
			'visionshow' => array (
				'description' => false,
				'url' => '/%scheme/vision%vision/',
			),
			'visionedit' => array (
				'description' => false,
				'url' => '/%scheme/vision%vision/edit.html',
			),
		);
		
		#  Return the list
		return $actions;
	}
	
	
	# Class properties
	private $template = array ();
	
	
	# Constructor
	public function __construct ($baseUrl, $databaseConnection, $settings, $do)
	{
		# Create handles to the main class properties
		$this->baseUrl = $baseUrl;
		$this->databaseConnection = $databaseConnection;
		$this->settings = $settings;
		
		# Get the action
		$this->actions = $this->actions ();
		
		# Validate the specified local action
		$this->action = (isSet ($this->actions[$do]) ? $do : 'page404');
		
		# Run the action
		$this->{$this->action} ();
	}
	
	
	# Function to get the template path
	public function getTemplatePath ()
	{
		return $this->baseUrl . '/' . $this->action . '.html';
	}
	
	
	# Function to get the template values
	public function getTemplate ()
	{
		return $this->template;
	}
	
	
	
	# List schemes
	public function schemeslist ()
	{
		$this->template['function'] = __FUNCTION__;
	}
	
	
	# Add scheme
	public function schemeadd ()
	{
		$this->template['function'] = __FUNCTION__;
	}
	
	
	# Show scheme
	public function schemeshow ()
	{
		$this->template['function'] = __FUNCTION__;
	}
	
	
	# Edit scheme
	public function schemeedit ()
	{
		$this->template['function'] = __FUNCTION__;
	}
	
	
	# Add vision
	public function visionadd ()
	{
		$this->template['function'] = __FUNCTION__;
	}
	
	
	# Show vision
	public function visionshow ()
	{
		$this->template['function'] = __FUNCTION__;
	}
	
	
	# Edit vision
	public function visionedit ()
	{
		$this->template['function'] = __FUNCTION__;
	}
	
	
	# 404 page
	public function page404 ()
	{
		$this->template['function'] = __FUNCTION__;
	}
}
