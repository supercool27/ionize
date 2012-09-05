<?php
/**
 * Ionize
 *
 * @package		Ionize
 * @author		Ionize Dev Team
 * @license		http://ionizecms.com/doc-license
 * @link		http://ionizecms.com
 * @since		Version 0.9.7
 *
 */

/**
 * Ionize Tagmanager Navigation Class
 *
 * @package		Ionize
 * @subpackage	Libraries
 * @category	TagManager Libraries
 *
 */

require_once APPPATH.'libraries/Pages.php';


class TagManager_Navigation extends TagManager
{
	static protected $_current_language = NULL;

	public static $tag_definitions = array
	(
		'navigation' => 					'tag_navigation',
		'navigation:is_active' =>			'tag_is_active',

		'navigation:active_class' =>		'tag_navigation_active_class',
		'navigation:url' =>					'tag_navigation_url',
		'navigation:href' =>				'tag_navigation_href',

		'tree_navigation' => 				'tag_tree_navigation',
		'tree_navigation:active_class' =>	'tag_navigation_active_class',			
		'sub_navigation' => 				'tag_sub_navigation',
		'sub_navigation_title' => 			'tag_sub_navigation_title',

		// Languages
		'languages' =>				'tag_languages',
		'languages:language' =>		'tag_languages_language',
		'language' =>				'tag_language',

		'language:name' =>			'tag_language_name',
		'language:code' =>			'tag_language_code',
		'language:active_class' =>	'tag_language_active_class',
		'language:is_default' =>	'tag_language_is_default',
		'language:is_active' =>		'tag_is_active',
	);
	
	
	// ------------------------------------------------------------------------

	
	/**
	 * Navigation tag definition
	 * @usage	
	 *
	 */
	public static function tag_navigation(FTL_Binding $tag)
	{
		$cache = ($tag->getAttribute('cache') == 'off' ) ? FALSE : TRUE;
		
		$error_message = '';
		
		// Tag cache
		if ($cache == TRUE && ($str = self::get_cache($tag)) !== FALSE)
			return $str;

		// Final string to print out.
		$str = '';

		// Helper / No helper ?
		// $tag->attr['no_helper'] : Will disapear in next versions... replaced by $tag->attr['helper']
		$helper = ( is_null($tag->getAttribute('helper'))) ? 'navigation' : $tag->getAttribute('helper');
		
		// Get the asked lang if any
		$lang = $tag->getAttribute('lang');

		// Menu : Main menu by default
		$menu_name = isset($tag->attr['menu']) ? $tag->attr['menu'] : 'main';
		$id_menu = 1;
		foreach($tag->globals->menus as $menu)
		{
			if ($menu_name == $menu['name'])
				$id_menu = $menu['id_menu'];
		}
		
		// Navigation level. FALSE if not defined
		$asked_level = isset($tag->attr['level']) ? $tag->attr['level'] : FALSE;

		// Display hidden navigation elements ?
		$display_hidden = isset($tag->attr['display_hidden']) ? TRUE : FALSE;


		// Current page
		$current_page = $tag->get('_page');

		// Attribute : active CSS class
		$active_class = (isset($tag->attr['active_class']) ) ? $tag->attr['active_class'] : 'active';
		if (strpos($active_class, 'class') !== FALSE) $active_class= str_replace('\'', '"', $active_class);
		

		/*
		 * Getting menu data
		 *
		 */
		// Pages : Current lang OR asked lang code pages.
		$global_pages = ( ! is_null($lang) && Settings::get_lang() != $lang) ? Pages::get_pages($lang) : $tag->globals->pages;

		// Add the active class key
		$id_current_page = ( ! empty($current_page['id_page'])) ? $current_page['id_page'] : FALSE;
		
		$active_pages = Structure::get_active_pages($global_pages, $id_current_page);

		foreach($global_pages as &$page)
		{
			// Add the active_class key
			$page['active_class'] = in_array($page['id_page'], $active_pages) ? $active_class : '';
			$page['active'] = in_array($page['id_page'], $active_pages) ? TRUE : FALSE;
			$page['id_navigation'] = $page['id_page'];
		}

		// Filter by menu and asked level : We only need the asked level pages !
		// $pages = array_filter($global_pages, create_function('$row','return ($row["level"] == "'. $asked_level .'" && $row["id_menu"] == "'. $id_menu .'") ;'));
		$pages = array();
		$parent_page = array();
		
		// Asked Level exists
		if ($asked_level !== FALSE)
		{
			foreach($global_pages as $p)
			{
				if ($p['level'] == $asked_level && $p['id_menu'] == $id_menu)
					$pages[] = $p;
			}
		}
		// Get navigation from current page
		else
		{
			foreach($global_pages as $p)
			{
				// Child pages of id_subnav
				if ($p['id_parent'] == $tag->locals->_page['id_subnav'])
					$pages[] = $p;

				// Parent page is the id_subnav page
				if ($p['id_page'] == $tag->locals->_page['id_subnav'])
					$parent_page = $p;
			}
		}
		
		// Filter on 'appears'=>'1'
		if ($display_hidden == FALSE)
			$pages = array_values(array_filter($pages, array('TagManager_Page', '_filter_appearing_pages')));
		
		// Get the parent page from one level upper
		if ($asked_level > 0)
		{
			// $parent_pages = array_filter($global_pages, create_function('$row','return $row["level"] == "'. ($asked_level-1) .'";'));
			$parent_pages = array();
			foreach($global_pages as $p)
			{
				if ($p['level'] == ($asked_level-1))
					$parent_pages[] = $p;
			}
			
			foreach($parent_pages as $p)
			{
				if ($p['active_class'] != '')
					$parent_page = $p;
			}
		}
		
		// Filter the current level pages on the link with parent page
		if ( ! empty($parent_page ))
		{
			$o_pages = $pages;
			$pages = array();
			foreach($o_pages as $p)
			{
				if ($p['id_parent'] == $parent_page['id_page'])
					$pages[] = $p;
			}
		}
		else
		{
			if ($asked_level > 0)
				$pages = array();
		}

		if ($helper !== FALSE)
		{
			// Get helper method
			$helper_function = (substr(strrchr($helper, ':'), 1 )) ? substr(strrchr($helper, ':'), 1 ) : 'get_navigation';
			$helper = (strpos($helper, ':') !== FALSE) ? substr($helper, 0, strpos($helper, ':')) : $helper;
	
			// load the helper
			self::$ci->load->helper($helper);
			
			// Return the helper function result
			if (function_exists($helper_function))
			{
				//$nav = call_user_func($helper_function, $pages);
				$tag->attr['helper'] = $helper.':'.$helper_function;
				
				$output = self::wrap($tag, $pages);
				
				// Tag cache
				self::set_cache($tag, $output);
	
				return $output;
			}
			$error_message = 'Helper ' . $helper.':'.$helper_function.'() not found';
		}
		else
		{
			foreach($pages as $index => $p)
			{
				// $tag->set('_page', $p);
				// $tag->locals->_page = $p;
				$tag->set('navigation', $p);
				$tag->set('active', $p['active']);

				$tag->locals->index = $index;
				$str .= $tag->expand();
			}

			$output = self::wrap($tag, $str);
			
			// Tag cache
			self::set_cache($tag, $output);

			return $output;
		}
		
		return self::show_tag_error($tag->name, $error_message);
	}


	// ------------------------------------------------------------------------


	public static function tag_is_active(FTL_Binding $tag)
	{
		$is_active = ($tag->getAttribute('is') === FALSE) ? FALSE : TRUE;

		if ($is_active == $tag->get('active'))
			return $tag->expand();

		return '';
	}

	
	// ------------------------------------------------------------------------


	public static function tag_sub_navigation_title(FTL_Binding $tag)
	{
		if ($tag->locals->_page['subnav_title']  != '')
		{
			return self::wrap($tag, $tag->locals->_page['subnav_title']);
		}
		else
		{
			foreach($tag->globals->pages as $page)
			{
				if ($page['id_page'] == $tag->locals->_page['id_subnav'])
				{
					return self::wrap($tag, $page['subnav_title']);
				}
			}
		}		
		return '';		
	}
	
	
	// ------------------------------------------------------------------------


	/**
	 * Return a tree navigation based on the given helper.
	 *
	 * @param	FTL_Binding object
	 *
	 * @return string
	 *
	 */
	public static function tag_tree_navigation(FTL_Binding $tag)
	{
		// Current page
		$page = $tag->locals->_page;
	
		// If 404 : Put empty vars, so the menu will prints out without errors
		if ( !isset($page['id_page']))
		{
			$page = array(
				'id_page' => '',
				'id_parent' => ''
			);
		}

		// Menu : Main menu by default
		$menu_name = isset($tag->attr['menu']) ? $tag->attr['menu'] : 'main';
		$id_menu = 1;
		foreach($tag->globals->menus as $menu)
		{
			if ($menu_name == $menu['name'])
			{
				$id_menu = $menu['id_menu'];
			}	
		}
		
		// If set, attribute level, else parent page level + 1
		$from_level = (isset($tag->attr['level']) ) ? $tag->attr['level'] : 0 ;
//		$from_level = (isset($tag->attr['level']) ) ? $tag->attr['level'] : FALSE ;

		// If set, depth
		$depth = (isset($tag->attr['depth']) ) ? $tag->attr['depth'] : -1;
		
		// Attribute : active class, first_class, last_class
		$active_class = (isset($tag->attr['active_class']) ) ? $tag->attr['active_class'] : 'active';
		$first_class = (isset($tag->attr['first_class']) ) ? $tag->attr['first_class'] : '';
		$last_class = (isset($tag->attr['last_class']) ) ? $tag->attr['last_class'] : '';

		// Display hidden navigation elements ?
		$display_hidden = isset($tag->attr['display_hidden']) ? TRUE : FALSE;

		// Includes articles as menu elements
		$with_articles = isset($tag->attr['articles']) ? TRUE : FALSE;

		// Attribute : HTML Tree container ID & class attribute
		$id = (isset($tag->attr['id']) ) ? $tag->attr['id'] : NULL ;
		if (strpos($id, 'id') !== FALSE) $id= str_replace('\'', '"', $id);

		$class = (isset($tag->attr['class']) ) ? $tag->attr['class'] : NULL ;
		if (strpos($active_class, 'class') !== FALSE) $active_class= str_replace('\'', '"', $active_class);
		
		// Attribute : Helper to use to print out the tree navigation
		$helper = (isset($tag->attr['helper']) && $tag->attr['helper'] != '' ) ? $tag->attr['helper'] : 'navigation';
		
		// Get helper method
		$helper_function = (substr(strrchr($helper, ':'), 1 )) ? substr(strrchr($helper, ':'), 1 ) : 'get_tree_navigation';
		$helper = (strpos($helper, ':') !== FALSE) ? substr($helper, 0, strpos($helper, ':')) : $helper;
		// load the helper
		self::$ci->load->helper($helper);

		// Page from locals : By ref because of active_class definition
		$pages =&  $tag->locals->_pages;

		/* Get the reference parent page ID
		 * Note : this is depending on the whished level.
		 * If the curent page level > asked level, we need to find recursively the parent page which has the good level.
		 * This is done to avoid tree cut when navigation to a child page
		 *
		 * e.g :
		 *
		 * On the "services" page and each subpage, we want the tree navigation composed by the sub-pages of "services"
		 * We are in the page "offer"
		 * We have to find out that the level 1 parent is "services"
		 *
		 *	Page structure				Level
		 *
		 *	home						0
		 *	 |_ about					1		
		 *	 |_ services				1		<- We want all the nested nav starting at level 1 from this parent page
		 *	 	   |_ development		2
		 *		   |_ design			2
		 *				|_ offer		3		<- We are here.
		 *				|_ portfolio	3	
		 *
		 */
		$page_level = (isset($page['level'])) ? $page['level'] : 0;
		$parent_page = array();

		// Asked Level exists
	 
		$parent_page = array(
			'id_page' => ($from_level > 0) ? $page['id_page'] : 0,
			'id_parent' => isset($page['id_parent']) ? $page['id_parent'] : 0
		);

		if ($from_level !== FALSE)
		{
			$parent_page = array(
				'id_page' => ($from_level > 0) ? $page['id_page'] : 0,
				'id_parent' => isset($page['id_parent']) ? $page['id_parent'] : 0
			);
		}
		// Get navigation from current page
		else
		{
			foreach($pages as $p)
			{
				// Parent page is the id_subnav page
				if ($p['id_page'] == $page['id_subnav'])
					$parent_page = $p;
			}
		}

		// Find out the wished parent page 
		while ($page_level >= $from_level && $from_level > 0)
		{
			// $potential_parent_page = array_values(array_filter($pages, create_function('$row','return $row["id_page"] == "'. $parent_page['id_parent'] .'";')));
			$potential_parent_page = array();
			foreach($pages as $p)
			{
				if($p['id_page'] == $parent_page['id_parent'])
				{
					$potential_parent_page = $p;
					break;
				}
			}
			// if (isset($potential_parent_page[0]))
			if ( ! empty($potential_parent_page))
			{
				$parent_page = $potential_parent_page;
				$page_level = $parent_page['level'];
			}
			else
			{
				$page_level--;
			}
		}
		// Active pages array. Array of ID
		$active_pages = Structure::get_active_pages($pages, $page['id_page']);
		
		foreach($pages as $key => $p)
		{
			$pages[$key]['active_class'] = in_array($p['id_page'], $active_pages) ? $active_class : '';
		}

		// Filter on 'appears'=>'1'
		$nav_pages = $pages;
		if ($display_hidden === FALSE)
			$nav_pages = array_values(array_filter($pages, array('TagManager_Page', '_filter_appearing_pages')));

		// $nav_pages = array_filter($nav_pages, create_function('$row','return ($row["id_menu"] == "'. $id_menu .'") ;'));
		$final_nav_pages = $nav_pages_list = array();
		foreach($nav_pages as $k => $np)
		{
			if ($np['id_menu'] == $id_menu )
			{
				$final_nav_pages[] = $np;
				$nav_pages_list[] = $np['id_page'];
			}
		}
		
		// Should we include articles ?
		$articles = FALSE;
		if ($with_articles == TRUE)
		{
			$uri = preg_replace("|/*(.+?)/*$|", "\\1", self::$ci->uri->uri_string);
			$uri_segments = explode('/', $uri);
			$current_article_uri = array_pop($uri_segments);

			$tag->attr['scope'] = 'global';
			$articles = TagManager_Page::get_articles($tag);
			
			foreach($articles as &$article)
			{
				if (array_pop(explode('/', $article['url'])) == $current_article_uri)
					$article['active_class'] = $active_class;
			}
		}

		// Get the tree navigation array
		$tree = Structure::get_tree_navigation($final_nav_pages, $parent_page['id_page'], $from_level, $depth, $articles);

		// Return the helper function
		if (function_exists($helper_function))
			return call_user_func($helper_function, $tree, $id, $class, $first_class, $last_class);
	}


	// ------------------------------------------------------------------------

	/**
	 * Returns the active class string, as set through the <ion:navigation active_class="" /> attribute
	 *
	 * @param $tag
	 *
	 * @return string
	 */
	public static function tag_navigation_active_class(FTL_Binding $tag)
	{
		return self::_get_from_locals($tag, 'active_class');
	}
	

	// ------------------------------------------------------------------------


	/** 
	 * Return the URL of a navigation item.
	 *
	 * @param	FTL_Binding
	 *
	 * @return 	null|string
	 *
	 * @usage	<ion:languages [helper="helper:helper_method"]>
	 * 				...
	 * 			<ion:languages>
	 *
	 */
	public static function tag_navigation_url(FTL_Binding $tag)
	{
		$has_url = self::_get_from_locals($tag, 'has_url');

		if (intval($has_url) == 1)
			return self::_get_from_locals($tag, 'absolute_url');
		else
			return '#';
	}


	public static function tag_navigation_href(FTL_Binding $tag)
	{

	}



	/**
	 * Languages tag
	 * 
	 * @param	FTL_Binding
	 *
	 * @return 	null|string
	 *
	 * @usage	<ion:languages [helper="helper:helper_method"]>
	 * 				...
	 * 			<ion:languages>
	 *
	 */
	public static function tag_languages(FTL_Binding $tag)
	{
		$languages = Settings::get_online_languages();

//		$infos = self::get_url_infos();
		
		// Current active language class
		$active_class = (isset($tag->attr['active_class']) ) ? $tag->attr['active_class'] : 'active';

		// helper
		$helper = $tag->getAttribute('helper');

		$str = '';

		foreach($languages as &$lang)
		{
			// Lang send to helper
			$lang['absolute_url'] = $tag->locals->_page['absolute_urls'][$lang['lang']];
			$lang['active_class'] = ($lang['lang'] == Settings::get_lang('current')) ? $active_class : '';
			$lang['active'] = $lang['lang'] == Settings::get_lang('current');
			$lang['id'] = $lang['lang'];

			// Tag locals
			$tag->set('language', $lang);
			$tag->set('id', $lang['lang']);
			$tag->set('absolute_url', $lang['absolute_url']);
			$tag->set('active_class', $lang['active_class']);
			$tag->set('active', $lang['active']);

			if (Connect()->is('editors', TRUE) OR $lang['online'] == 1)
			{
				$str .= $tag->expand();
			}
		}

		// Try to return the helper function result
		if ( ! is_null($helper))
		{
			$helper_function = (substr(strrchr($helper, ':'), 1 )) ? substr(strrchr($helper, ':'), 1 ) : 'get_language_navigation';
			$helper = (strpos($helper, ':') !== FALSE) ? substr($helper, 0, strpos($helper, ':')) : $helper;

			self::$ci->load->helper($helper);

			if (function_exists($helper_function))
			{
				$nav = call_user_func($helper_function, $languages);
			
				return self::wrap($tag, $nav);
			}
		}

		return self::wrap($tag, $str);
	}


	/**
	 * Language tag in the context of its parent 'languages"
	 *
	 * @param 	FTL_Binding $tag
	 *
	 * @return 	string
	 *
	 * @usage	<ion:languages>
	 * 				<ion:language>
	 * 					<ion:name />
	 * 				</ion:language>
	 * 			<ion:languages>
	 *
	 * 			Shortcut mode :
	 * 			<ion:languages>
	 * 				<ion:language:name />
	 * 			<ion:languages>
	 *
	 */
	public static function tag_languages_language(FTL_Binding $tag)
	{
		return $tag->expand();
	}


	/**
	 * Standalone language tag
	 *
	 * @param 	FTL_Binding $tag
	 *
	 * @return 	string
	 *
	 * @usage	<ion:language>
	 * 				<ion:code />
	 * 				<ion:name />
	 * 				<ion:url />
	 * 				<ion:is_default />
	 * 				<ion:is_active />
	 * 			</ion:language>
	 */
	public static function tag_language(FTL_Binding $tag)
	{
		if (is_null(self::$_current_language))
		{
			foreach(Settings::get_languages() as $language)
			{
				if ($language['lang'] == Settings::get_lang())
				{
					$language['id'] = $language['lang'];
					$language['absolute_url'] = $tag->locals->_page['absolute_urls'][$language['lang']];
					self::$_current_language = $language;
					break;
				}
			}
		}
		$tag->set('language', self::$_current_language);

		return $tag->expand();
	}


	/**
	 * Returns the long language name
	 * Example : English
	 *
	 * @param 	FTL_Binding $tag
	 *
	 * @return 	null|string
	 *
	 * @usage	<ion:language>
	 * 				<ion:name [tag="span" class="colored"] />
	 * 			</ion:language>
	 *
	 * 			Shortcut mode :
	 * 			<ion:language:name [tag="span" class="colored"] />
	 */
	public static function tag_language_name(FTL_Binding $tag)
	{
		return self::_get_formatted_from_locals($tag, 'name');
	}


	/**
	 * Returns the language code
	 * Example : en
	 *
	 * @param 	FTL_Binding $tag
	 *
	 * @return 	null|string
	 *
	 * @usage	<ion:language>
	 * 				<ion:code [tag="span" class="colored"] />
	 * 			</ion:language>
	 *
	 * 			Shortcut mode :
	 * 			<ion:language:code [tag="span" class="colored"] />
	 */
	public static function tag_language_code(FTL_Binding $tag)
	{
		return self::_get_formatted_from_locals($tag, 'lang');
	}


	/**
	 * Returns the language active class
	 *
	 * @param 	FTL_Binding $tag
	 *
	 * @return 	null|string
	 *
	 * @usage	<ion:language>
	 * 				<li class="lang <ion:active_class />">...</li>
	 * 			</ion:language>
	 *
	 * 			Shortcut mode :
	 * 			<ion:language:active_class />
	 *
	 */
	public static function tag_language_active_class(FTL_Binding $tag)
	{
		return self::_get_from_locals($tag, 'active_class');
	}


	/**
	 * Displays the nested HTML if the current language is the default one.
	 *
	 * @param 	FTL_Binding $tag
	 *
	 * @return 	null|string
	 *
	 * @usage	<ion:language>
	 * 				<ion:is_default>
	 * 					This language is the default one
	 * 				</ion:is_default>
	 *
	 * 				<ion:is_default is="false">
	 * 					This language is not the default one
	 * 				</ion:is_default>
	 * 			</ion:language>
	 *
	 * 			Shortcut mode :
	 * 			<ion:language:is_default [is=false]></ion:language:is_default>
	 *
	 */
	public static function tag_language_is_default(FTL_Binding $tag)
	{
		$is_default = ($tag->getAttribute('is') === FALSE) ? 0 : 1;

		if ($is_default == intval(self::_get_from_locals($tag, 'def')))
			return $tag->expand();

		return '';
	}


	/**
	 * Get the current URL and feed the URL infos
	 * 
	 */
	public static function get_url_infos()
	{
		self::$ci =& get_instance(); 
		
		self::$uri_segments = explode('/', self::$ci->uri->uri_string());

		// Returned data
		$infos = array(
			'type' => 'page',
			'page' => self::$uri_segments[0],
			'article' => ''
		);
		
		// Get the special URI config array (see /config/ionize.php)
		$uri_config = self::$ci->config->item('special_uri');

		// Get the potential special URI
		$special_uri = (isset(self::$uri_segments[1]) && array_key_exists(self::$uri_segments[1], $uri_config)) ? self::$uri_segments[1] : FALSE;
		
		// If a special URI exists, get the articles from it.
		if ($special_uri !== FALSE)
		{
			$infos['type'] = 'special';
			$infos['page'] = self::$uri_segments[0];
		}
		// Get one article through his name in the URL
		else if (isset(self::$uri_segments[1]))
		{
			$infos['type'] = 'article';
			$infos['page'] = $uri_segments[0];
			$infos['article'] = self::$uri_segments[1];
		}

		return $infos;		
	}

}

/* End of file Navigation.php */
/* Location: /application/libraries/Tagmanager/Navigation.php */