// Indentation of source code - use a tab rather than the default spaces
FCKConfig.FormatIndentator	= '\t' ;

// Protect PHP source
FCKConfig.ProtectedSource.Add( /<\?[\s\S]*?\?>/g ) ;	// PHP style server side code

// Force <strong> and <em>
FCKConfig.CoreStyles.Bold = { Element : 'strong', Overrides : 'b' }
FCKConfig.CoreStyles.Italic = { Element : 'em', Overrides : 'i' }

// Set a cut-down toolbar
FCKConfig.ToolbarSets['pureContent'] = [
	['Source'],
	['Cut','Copy','Paste','PasteText','PasteWord','-','SpellCheck'],
	['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
	['Bold','Italic','StrikeThrough','-','Subscript','Superscript'],
	['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote'],
	['Link','Unlink','Anchor'],
	['Image','Flash','Table','Rule','SpecialChar'/*,'ImageManager','UniversalKey'*/],
	/*['Form','Checkbox','Radio','Input','Textarea','Select','Button','ImageButton','Hidden']*/
	[/*'FontStyleAdv','-','FontStyle','-',*/'FontFormat','Style'/*,'-','-'*/],
	[/*'Print',*/'FitWindow','ShowBlocks','-','About']
] ;

// Set a mostly cut-down toolbar
FCKConfig.ToolbarSets['pureContentPlusFormatting'] = [
	['Source'],
	['Cut','Copy','Paste','PasteText','PasteWord','-','SpellCheck'],
	['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
	['Bold','Italic',/*'Underline','StrikeThrough',*/'-','Subscript','Superscript'],
	['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote'],
	['JustifyLeft','JustifyCenter','JustifyRight','JustifyFull'],
	['Link','Unlink','Anchor'],
	['Image','Flash','Table','Rule',/*'Smiley',*/'SpecialChar'/*,'UniversalKey'*/],
	/*['Form','Checkbox','Radio','TextField','Textarea','Select','Button','ImageButton','HiddenField'],
	'/',*/
	[/*'Style',*/'FontFormat'/*,'FontName','FontSize'*/],
	['TextColor'/*,'BGColor'*/],
	[/*'Print',*/'FitWindow','ShowBlocks','-','About']
] ;

// Set a slightly more extensive version of the basic toolbar
FCKConfig.ToolbarSets["BasicLonger"] = [
	['Source','Bold','Italic','RemoveFormat','-','OrderedList','UnorderedList','-','Link','Unlink','Anchor','-','About']
] ;

// Set a slightly more extensive version of the basic toolbar, plus formatting
FCKConfig.ToolbarSets["BasicLongerFormat"] = [
	['Source','FontFormat','Bold','Italic','RemoveFormat','-','OrderedList','UnorderedList','-','Link','Unlink','Anchor','-','About']
] ;

FCKConfig.CustomStyles =
{
	'Warning (paragraph)'	: { Element : 'p', Attributes : { 'class' : 'warning' } },
	'Warning (inline)'	: { Element : 'span', Attributes : { 'class' : 'warning' } }
};


