<?php

# Class to handle the news section
class news
{
	# Class properties
	private $html = '';
	
	# Constructor
	public function __construct ($telluswhere, $areas)
	{
		# Create handles to the properties
		$this->telluswhere = $telluswhere;
		$this->baseUrl = $telluswhere->baseUrl;
		$this->databaseConnection = $telluswhere->databaseConnection;
		$this->settings = $telluswhere->settings;
		$this->user = $telluswhere->user;
		$this->userIsAdministrator = $telluswhere->userIsAdministrator;
		
		# Get the areas
		$this->areas = $areas;
		
	}
	
	
	# Function to get the HTML
	public function getHtml ()
	{
		return $this->html;
	}
	
	
	# Main entry point
	public function main ()
	{
		# Start the HTML
		$html = '';
		
		# Styles
		$html .= "\n<style type=\"text/css\">
			
			/* Droplist */
			div.droplist {float: right;}
			div.droplist select {width: auto; display: inline;}
			div.droplist input {display: inline;}
			
			/* Article listing */
			p.actionbutton a {border: 1px solid #eee; padding: 5px 10px; border-radius: 3px; background-color: #f7f7f7;}
			div.article {margin-bottom: 3em;}
			div.graybox {border: 1px solid #ddd; padding: 10px 15px; margin: 0 10px 10px 0; background-color: #fcfcfc; overflow: hidden; /* overflow prevents floats not being enclosed - see http://gtwebdev.com/workshop/floats/enclosing-floats.php */}
			div.graybox:hover {background-color: #fafafa; border-color: #aaa;}
			div.graybox p {text-align: left; margin-top: 10px;}
			
			/* Article form */
			body form input#form_urlMoniker {display: inline; width: auto;}
			/* \'Lines\' table style */
			table.lines {border-collapse: collapse; /* width: 95%; */}
			.lines td, .lines th {border-bottom: 1px solid #e9e9e9; padding: 6px 8px 2px 1px; vertical-align: top; text-align: left;}
			.lines tr:first-child {border-top: 1px solid #e9e9e9;}
			table.lines td.value p:first-child {margin-top: 0;}
			table.lines td.value p:last-child {margin-bottom: 0;}
			table.lines td:last-child ul:first-child {margin-top: 0;}
			table.lines td:last-child ul:first-child li:first-child {margin-top: 0;}
		</style>";
		
		# If an area is selected, validate and confirm it
		$area = false;
		if (isSet ($_GET['area'])) {
			if (!array_key_exists ($_GET['area'], $this->areas)) {
				$html = $this->telluswhere->page404 ();
				echo $html;
				return false;
			}
			$area = $_GET['area'];
		}
		
		# Add droplist
		$html .= $this->areaDroplist ($this->areas, $area);
		
		# Edit article if required
		if ($this->articleManipulate ($html, array (), $area)) {return;}
		
		# Title of page
		$html .= "\n<h2>News" . ($area ? ' from ' . htmlspecialchars ($this->areas[$area]) . ' ' : '') . "</h2>";
		
		# Link to add news
		$html .= $this->createButton ('/news/' . ($area ? "{$area}/" : '') . 'add.html', '<strong>+</strong> Add news');
		
		# Start list of retrieval conditions
		$conditions = array ();
		$isSingle = false;
		
		# Limit to area if required
		if ($area) {
			$conditions = array ();
			$conditions['area'] = $area;
		}
		
		# Limit to specific article if required
		if (isSet ($_GET['date']) && isSet ($_GET['urlMoniker'])) {
			$conditions = array ();
			$conditions['date'] = $_GET['date'];
			$conditions['urlMoniker'] = $_GET['urlMoniker'];
			$isSingle = true;
		}
		
		# Get articles
		$data = $this->databaseConnection->select ('main', 'news', $conditions, array (), $associative = false, $orderBy = 'id DESC', $limit = 10);
		
		# Add permalink data
		foreach ($data as $index => $article) {
			$data[$index]['permalink'] = "{$this->baseUrl}/news/" . date ('Y/m/d/', strtotime ($article['date'] . ' 12:00:00')) . "{$article['urlMoniker']}/";
		}
		
		# If no article but single one expected, create 404
		if ($isSingle) {
			if (!$data) {
				$html = $this->telluswhere->page404 ();
				echo $html;
				return false;
			}
			$area = $data[0]['area'];
		}
		
		# Edit article if required
		if ($this->articleManipulate ($html, $data[0], $area)) {return;}
		
		# Show each article
		$html .= $this->createList ($data, $area, $isSingle);
		
		# Register the HTML
		$this->html = $html;
	}
	
	
	# News article addition/editing
	private function articleManipulate ($html, $article, $area)
	{
		# Determine the action
		$action = ($article ? 'edit' : 'add');
		
		# Do not run unless triggered
		if (!isSet ($_GET['mode'])) {return;}
		if ($_GET['mode'] != $action) {return;}
		
		# Take no action if the user is not an administrator
		if (!$this->userIsAdministrator) {
			$html = $this->telluswhere->page404 ();
			echo $html;
			return false;
		}
		
		# Start the HTML
		$html .= "\n<h2>" . ucfirst ($action) . " article</h2>";
		$html .= $this->createButton ('/news/' . ($area ? "{$area}/" : ''), 'Cancel');
		
		# Show the form
		$do = ($article ? 'update' : 'insert');
		$constraint = ($article ? array ('id' => $article['id']) : false);
		$actionDone = ($article ? 'updated' : 'created');
		if ($article = $this->form ($html, $article, $area)) {
			
			# Update the article
			if (!$this->databaseConnection->{$do} ('main', 'news', $article, $constraint)) {
				$html = "\n<p>The database operation failed.</p>";
				$this->html = $html;
				return true;
			}
			$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// http://www.fileformat.info/info/unicode/char/2714/
			
			# Reset the HTML
			$link = "{$this->baseUrl}/news/" . ($do == 'update' ? date ('Y/m/d/', strtotime ($article['date'] . ' 12:00:00')) . "{$article['urlMoniker']}/" : ($article['area'] ? "{$article['area']}/" : ''));	// When adding, take the user to the front page
			$html  = "\n<h2>" . ucfirst ($action) . " article</h2>";
			$html .= "\n<p>{$unicodeTick} The <a href=\"" . $link . "\">article</a> has been {$actionDone}.</p>";
		}
		
		# Register the HTML
		$this->html = $html;
		
		# Signal editing has been occuring
		return true;
	}
	
	
	# Function to create an news area droplist
	private function areaDroplist ($areas, $area)
	{
		# Create the droplist
		$droplist = array ();
		$droplist["{$this->baseUrl}/news/"] = 'Select area:';
		foreach ($areas as $moniker => $name) {
			$url = "{$this->baseUrl}/news/{$moniker}/";
			$droplist[$url] = htmlspecialchars ($name);
		}
		$selected = "{$this->baseUrl}/news/" . ($area ? "{$area}/" : '');
		
		# Compile the HTML and register a processor
		$html  = pureContent::htmlJumplist ($droplist, $selected, $this->baseUrl . '/news/', $name = 'droplist', $parentTabLevel = 0, $class = 'droplist', false);
		pureContent::jumplistProcessor ($name);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a button
	private function createButton ($url, $text)
	{
		# Take no action if the user is not an administrator
		if (!$this->userIsAdministrator) {return false;}
		
		# Compile the HTML
		$html = "\n<p class=\"actionbutton\"><a href=\"{$this->baseUrl}{$url}\">{$text}</a></p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to show articles
	private function createList ($articles, $area = false, $isSingle = false)
	{
		# Start the HTML
		$html = '';
		
		# End if no articles
		if (!$articles) {
			$html .= "\n<p><em>No news" . ($area ? ' from ' . htmlspecialchars ($this->areas[$area]) . ' ' : '') . " yet - do check back soon!</em></p>";
			return $html;
		}
		
		# Show each article
		foreach ($articles as $article) {
			$html .= "\n<div class=\"graybox article\">";
			$html .= "\n<h3>" . htmlspecialchars ($article['title']) . '</h3>';
			$html .= "\n<p><em><a href=\"{$article['permalink']}\">#</a> Posted by " . htmlspecialchars ($article['name']) . ' on ' . date ('jS F, Y', strtotime ($article['date'] . ' 12:00:00')) . ($area ? '' : " in <a href=\"{$this->baseUrl}/news/{$article['area']}/\">" . htmlspecialchars ($this->areas[$article['area']]) . '</a>') . '.</em></p>';
			$html .= $article['articleRichtext'];
			
			# Link to add news
			$html .= $this->createButton ($article['permalink'] . 'edit.html', 'Edit article');
			
			$html .= "\n</div>";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# News article form
	private function form (&$html = '', $data = array (), $area = false)
	{
		# Implement CKFinder auth control
		$_SESSION['IsAuthorized'] = true;
		$editorFileBrowserACL = array (
			'/' => array (
				'role' => '*',
				'resourceType' => '*',
				'folder' => '/',
				'folderView' => true,	// This is necessary as otherwise it is not possible to traverse into lower folders
				'folderCreate' => false,
				'folderRename' => false,
				'folderDelete' => false,
				'fileView' => false,
				'fileUpload' => false,
				'fileRename' => false,
				'fileDelete' => false,
			),
			'/images/news/' => array (
				'role' => '*',
				'resourceType' => '*',
				'folder' => '/images/news/',
				'folderView' => true,
				'folderCreate' => false,
				'folderRename' => false,
				'folderDelete' => false,
				'fileView' => true,
				'fileUpload' => true,
				'fileRename' => true,
				'fileDelete' => true,
			),
		);
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'div'						=> 'ultimateform lines',
			'autofocus'					=> true,
			'formCompleteText'			=> false,
			'reappear'					=> true,
			'databaseConnection'		=> $this->databaseConnection,
			'picker'					=> true,
			'displayRestrictions'		=> false,
			'errorsCssClass'			=> 'notification error',
			'unsavedDataProtection'		=> true,
			// 'jQuery'					=> false,
		));
		$form->dataBinding (array (
			'database' => 'main',
			'table' => 'news',
			'intelligence' => true,
			'data' => $data,
			'exclude' => array ('id'),
			'attributes' => array (
				'area'				=> array ('type' => 'select', 'values' => $this->areas, 'nullText' => 'No specific area', 'default' => $area),
				'urlMoniker'		=> array ('regexp' => '^([-_a-z0-9]+)$', 'placeholder' => 'E.g. title-of-article', 'prepend' => '/news/&lt;date&gt;/ ', 'size' => 40, ),
				'articleRichtext'	=> array (
					'editorBasePath'	=> $this->baseUrl . '/js/ckeditor/',
					'editorToolbarSet'	=> 'pureContent',
					'editorAreaCSS'		=> $this->settings['cssFileLocation'],
					'width'				=> 800,
					'height'			=> 300,
					'editorFileBrowser'	=> $this->baseUrl . '/js/ckfinder/',
					'editorFileBrowserACL'	=> $editorFileBrowserACL,
					'editorFileBrowserStartupPath'		=> '/images/news/',
				),
				'date'				=> array ('default' => 'timestamp', ),
				'name'				=> array ('default' => $this->user['name'], ),
			),
		));
		
		# Process the form
		$result = $form->process ($html);
		
		# Return the result
		return $result;
	}
}

?>
