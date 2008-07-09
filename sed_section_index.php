<?php

$plugin['name'] = 'sed_section_index';
$plugin['version'] = '0.1';
$plugin['author'] = 'Netcarver';
$plugin['author_uri'] = 'http://txp-plugins.netcarving.com';
$plugin['description'] = 'Provides indexed access to sections.';
$plugin['type'] = '1';
$plugin['order'] = 3;

@include_once('../zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

@require_plugin('sed_plugin_library');

if( !defined('sed_si_prefix') )
	define( 'sed_si_prefix' , 'sed_si' );

#===============================================================================
#	Admin interface features...
#===============================================================================
if( @txpinterface === 'admin' )
	{
	add_privs('sed_si', '1,2,3,4,5,6');

	global $prefs, $textarray , $_sed_si_l18n , $sed_si_prefs;

	#===========================================================================
	#	Strings for internationalisation...
	#===========================================================================
	$_sed_si_l18n = array(
		'alter_section_tab'		=> 'Alter Presentation > Section tab?',
		'filter_label'			=> 'Filter&#8230;',
		'filter_limit'			=> 'Show section index filter after how many sections?',
		);
	$mlp = new sed_lib_mlp( 'sed_section_index' , $_sed_si_l18n , '' , 'admin' );

	#===========================================================================
	#	Plugin preferences...
	#===========================================================================
	$sed_si_prefs = array
		(
		'alter_section_tab'	=> array( 'type'=>'yesnoradio' , 'val'=>'0' ) ,
		'filter_limit' 		=> array( 'type'=>'text_input' , 'val'=>'18' ) ,
		);
	foreach( $sed_si_prefs as $key=>$data )
		_sed_si_install_pref( $key , $data['val'] , $data['type'] );

	#===========================================================================
	#	Textpattern event handlers...
	#===========================================================================
	register_callback( '_sed_si_section_post' , 'section' );
	register_callback( '_sed_si_section_pre' , 'section' , '' , 1 );

	#===========================================================================
	#	Serve resource requests...
	#===========================================================================
	switch(gps('sed_resources') )
		{
		case 'sed_si_section_js':
			require_privs( 'section' );
			_sed_si_section_js();
			break;

		default:
			break;
		}
	}

#===============================================================================
#	Data access routines...
#===============================================================================
function _sed_si_prefix_key($key)
	{
	return sed_si_prefix.'-'.$key;
	}
function _sed_si_install_pref($key,$value,$type)
	{
	global $prefs , $textarray , $_sed_si_l18n;
	$k = _sed_si_prefix_key( $key );
	if( !array_key_exists( $k , $prefs ) )
		{
		set_pref( $k , $value , sed_si_prefix , 1 , $type );
		$prefs[$k] = $value;
		}
	# Insert the preference strings for non-mlp sites...
	if( !array_key_exists( $k , $textarray ) )
		$textarray[$k] = $_sed_si_l18n[$key];
	}
function _sed_si_remove_prefs()
	{
	safe_delete( 'txp_prefs' , "`event`='".sed_si_prefix."'" );
	}




#===============================================================================
#	Routines to handle admin presentation > sections tab...
#===============================================================================
function _sed_si_section_post( $event , $step )
	{
	if( !has_privs( 'section' ) )
		return;

	echo n."<script src='" .hu."textpattern/index.php?sed_resources=sed_si_section_js' type='text/javascript'></script>".n;
	_sed_si_css();
	}

function _sed_si_inject_section_admin( $page )
	{
	global $DB , $prefs , $_sed_si_l18n , $step , $mlp;

	if( !isset( $DB ) )
		$DB = new db;

	if( !isset( $prefs ) )
		$prefs = get_prefs();

	$mlp = new sed_lib_mlp( 'sed_section_fields' , $_sed_si_l18n , '' , 'admin' );

	$section_index = '';
	$rows = safe_rows_start( '*' , 'txp_section' , "name != 'default' order by name" );
	$c = @mysql_num_rows($rows);
	if( $rows && $c > 0 )
		{
		while( $row = nextRow($rows) )
			{
			$name  = $row['name'];
			#$title = $row['title'];
			#$title = strtr( $title , array( "'"=>'&#39;' , '"'=>'&#34;' ) );

			# Build the list of sections for the section-tab index
			$section_index .= '<li id="sed_section-'.$name.'"><a href="#section-'.$name.'" class="sed_si_hide_all_but_one">'.$name.'</a></li>';
			}

		#
		#	Insert a JS variable holding the index of sections...
		#
		$newsection = '';
		if( $step == 'section_create' || $step == 'section_save' )
			$newsection = ps('name');

		$filter = '';
		$limit = $prefs[ _sed_si_prefix_key('filter_limit') ];
		if( !is_numeric( $limit ) )
			$limit = 18;
		if( $c >= $limit )
			$filter = '<label for="sed_si_section_index_filter">'.$mlp->gTxt('filter_label').'</label><br /><input id="sed_si_section_index_filter" type="text" class="edit" />';

		$section_index =	'<div id="sed_si_section_index_div">'.
							'<form id="sed_si_filter_form">'.$filter.'</form>'.
						 	'<ol id="sed_si_section_index" class="sed_si_section_index">'.
							'<li  id="sed_section-default"><a href="#section-default" class="sed_si_hide_all_but_one">default</a></li>'.
							$section_index.
							'</ol>'.
							'</div>';
		$section_index = str_replace('"', '\"', $section_index);
		$r = '<script type=\'text/javascript\'> var sed_si_new_section = "#section-'.$newsection.'"; var sed_si_section_index = "'.$section_index.'";</script>';
		$f = '<script src=\''.hu.'textpattern/index.php?sed_resources=sed_si_section_js\' type=\'text/javascript\'></script>';
		$page = str_replace( $f , $r.n.$f , $page );
		}

	return $page;
	}

function _sed_si_section_pre( $event , $step )
	{
	if( !has_privs( 'section' ) )
		return;

	ob_start( '_sed_si_inject_section_admin' );
	}

#===============================================================================
#	CSS resources...
#===============================================================================
function _sed_si_css()
	{
	echo <<<css
		<style>
		div#sed_si_section_index_div {
		float: left;
		margin: 2em;
		margin-top: 0;
		border-right: 1px solid #ccc;
		padding: 20px 20px 20px 0;
		}
		div#sed_si_section_index_div ul , div#sed_si_section_index_div ol {
		margin: 2em 0;
		}
		form#sed_si_filter_form {
		margin-top: 1em;
		}
		.sed_si_normal {
		cursor: default;
		}
		</style>
css;
	}

#===============================================================================
#	Javascript resources...
#===============================================================================
function _sed_si_js_headers()
	{
	while( @ob_end_clean() );
	header( "Content-Type: text/javascript; charset=utf-8" );
	header( "Expires: ".date("r", time()+3600) );
	header( "Cache-Control: public" );
	}

function _sed_si_section_js()
	{
	global $prefs;

	if( $prefs[_sed_si_prefix_key('alter_section_tab')] != '1' )
		return;

	_sed_si_js_headers();
	echo <<<js
	/*
	Idea based on "hide all except one" jQuery code by Charles Stuart...
	*/
	function sed_si_hide_all_but_one(el)
		{
		$('table#list>tbody>tr').hide();
		$('table#list tr' + el).show();
		}

	function sed_si_filter_list( list , filter )
		{
		if( filter == '' )
			list.find('li').show();
		else
			{
			list.find('li').hide();
			var select = "li[@id^='sed_section-"+filter+"']";
			list = list.find( select );
			list.show();
			}
		}

	$(document).ready
		(
		function()
			{
			// Insert index of sections...
			$("#list").before( sed_si_section_index );

			// Insert an #section-default id into the row containing the Default form...
			var row = $('table#list>tbody>tr:nth-child(2)');
			row.attr( "id" , "section-default" );

			var replace_point = $('#sed_si_filter_form');

			// Move the h1 and create form from the table to the index...
			var source = $('table#list>tbody>tr:first>td:first');
			replace_point.before( source.html() );

			// Filter the list every time the filter text is updated...
			$('input#sed_si_section_index_filter').keyup
				(
				function()
					{
					var filter = $('input#sed_si_section_index_filter').val();
					var index = $('ol#sed_si_section_index');
					sed_si_filter_list( index , filter );
					}
				);

			// Add click handlers that show only that section's row..
			$('a.sed_si_hide_all_but_one').click
				(
				function()
					{
					var href = $(this).attr('href');
					sed_si_hide_all_but_one(href);
					}
				);

			//	Setup initial state of the section table...
			if( sed_si_new_section == '#section-' )	// New section
				sed_si_new_section = window.location.hash;
			if( sed_si_new_section == '' )				// No section so show default
				sed_si_new_section = '#section-default';
			sed_si_hide_all_but_one(sed_si_new_section);
			//window.scrollTo(0,0); // Doesn't align exactly on save but gives access to nav withouth scrolling up
			window.location.hash = sed_si_new_section;
			}
		);
js;
	exit();
	}

# --- END PLUGIN CODE ---
/*
# --- BEGIN PLUGIN CSS ---
	<style type="text/css">
	div#sed_si_help td { vertical-align:top; }
	div#sed_si_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #FFFFCC;}
	div#sed_si_help code.sed_code_tag { font-weight:normal; border:1px dotted #999; background-color: #f0e68c; display:block; margin:10px 10px 20px; padding:10px; }
	div#sed_si_help a:link, div#sed_si_help a:visited { color: blue; text-decoration: none; border-bottom: 1px solid blue; padding-bottom:1px;}
	div#sed_si_help a:hover, div#sed_si_help a:active { color: blue; text-decoration: none; border-bottom: 2px solid blue; padding-bottom:1px;}
	div#sed_si_help h1 { color: #369; font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
	div#sed_si_help h2 { border-bottom: 1px solid black; padding:10px 0 0; color: #369; font: 17px Georgia, sans-serif; }
	div#sed_si_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
	div#sed_si_help ul ul { font-size:85%; }
	div#sed_si_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
	</style>
# --- END PLUGIN CSS ---
# --- BEGIN PLUGIN HELP ---
<div id="sed_si_help">

h1(#top). SED Section Index Help.

Introduces Indexed Section Editing.

h2(#changelog). Change Log

h3. v0.1

* Split out from sed_section_fields.

 <span style="float:right"><a href="#top" title="Jump to the top">top</a></span>

</div>
# --- END PLUGIN HELP ---
*/
?>