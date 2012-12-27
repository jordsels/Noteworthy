<?php

/**
 * @author Jason F. Irwin
 * @copyright 2012
 * 
 * Class contains the rules and methods called for the Manifest Theme
 */
require_once( LIB_DIR . '/content.php' );

class miTheme extends theme_main {
    var $settings;
    var $messages;
    var $content;
    var $perf;

    function __construct( $settings ) {
        $this->settings = $settings;

        // Set the Resource Prefix
        $this->settings['resource_prefix'] = 'desktop';
        $this->messages = getLangDefaults( $this->settings['DispLang'] );

        // Load the User-Specified Language Files for this theme
        $LangFile = dirname(__FILE__) . "/lang/" . strtolower($this->settings['DispLang']) . ".php";

        if ( file_exists($LangFile) ){
            require_once( $LangFile );
            $LangClass = 'theme_' . strtolower( $this->settings['DispLang'] );
            $Lang = new $LangClass();

            // Append the List of Strings to the End of the Messages Array
            //      and replace any existing ones that may need the update
            foreach( $Lang->getStrings() as $Key=>$Val ) {
                $this->messages[ $Key ] = $Val;
            }

            // Kill the Class
            unset( $Lang );
        }

        // Prep the Content
        $this->content = new Content( $settings, $this->messages, dirname(__FILE__) );

        // Load the Page Data if this is Valid, otherwise redirect
        if ( !$this->_isValidPage() ) {
            redirectTo($this->settings['HomeURL']);
        }
    }

    public function getHeader() {
        return $this->BuildHeaderData();
    }

    public function getContent() {
        return $this->BuildBodyData();
    }

    public function getSuffix() {
        return $this->BuildFooterData();
    }

    /***********************************************************************
     *                          Content Functions
     ***********************************************************************/
    /**
     * Function constructs the header data and returns the formatted HTML
     */
    private function BuildHeaderData() {
        $ReplStr = array( '[HOMEURL]'	  => $this->settings['HomeURL'],
                      	  '[SITEURL]'	  => $this->settings['URL'],
                      	  '[HOME_LOC]'    => APP_ROOT,
                      	  '[APPINFO]'	  => APP_NAME . " | " . APP_VER,
                      	  '[APP_VER]'	  => APP_VER,
                      	  '[GENERATOR]'	  => GENERATOR,
                          '[COPYRIGHT]'   => date('Y') . " - " . NoNull($this->messages['company_name']),
                          '[SITEDESCR]'   => $this->messages['site_descr'],
                          '[PAGE_TITLE]'  => $this->_getPageTitle( NoNull($this->settings['mpage']) ),
                          '[LANG_CD]'     => strtoupper($this->messages['lang_cd']),
                          '[ERROR_MSG]'   => '',
                          '[CONF_DIR]'    => $this->settings['HomeURL'] . "/conf",
                          '[CSS_DIR]'     => CSS_DIR,
                          '[IMG_DIR]'     => IMG_DIR,
                          '[JS_DIR]'      => JS_DIR,
                          '[TOKEN]'       => $this->settings['token']
                         );

        return readResource( RES_DIR . '/' . $this->settings['resource_prefix'] . '_head.html', $ReplStr );
    }

    /**
     * Function constructs the body data and returns the formatted HTML
     */
    private function BuildBodyData() {
        $ResFile = '/' . $this->settings['resource_prefix'] . '_body.html';

        // Collect the Resource Data
        $data = $this->_collectPageData();
        $rVal = readResource( RES_DIR . $ResFile, $data );

        // Return the Body Content
        return $rVal;
    }

    /**
     * Function constructs the footer data and returns the formatted HTML
     */
    private function BuildFooterData() {
        $precision = 6;
        $GLOBALS['Perf']['app_f'] = getMicroTime();
        $App = round(( $GLOBALS['Perf']['app_f'] - $GLOBALS['Perf']['app_s'] ), $precision);
        $SQL = nullInt( $GLOBALS['Perf']['queries'] );
        $Api = nullInt( $GLOBALS['Perf']['apiHits'] );
        $Cch = nullInt( $GLOBALS['Perf']['caches'] );
        $Analytics = ( ANALYTICS_ENABLED == 1 ) ? getGoogleAnalyticsCode( GA_ACCOUNT ) : '';
        $CopyYear = date('Y');
        $LangList = ( ENABLE_MULTILANG == 1 ) ? $this->_listLanguages() . " | " : "";
        
        $lblSecond = ( $App == 1 ) ? "Second" : "Seconds";
        $lblCalls  = ( $Api == 1 ) ? "Call"   : "Calls";
        $lblQuery  = ( $SQL == 1 ) ? "Query"  : "Queries";
        $lblCache  = ( $Cch == 1 ) ? "Object"  : "Objects";

        $ReplStr = array( '[JS_DIR]'     => JS_DIR,
                          '[CopyYear]'   => $CopyYear,
                          '[ANALYTICS]'  => $Analytics,
                          '[HOMEURL]'    => $this->settings['HomeURL'],
                          '[MULTILANG]'  => $LangList,
                          '[GenTime]'    => "<!-- Page generated in roughly: $App $lblSecond, $Api API $lblCalls, $SQL SQL $lblQuery, $Cch Cache $lblCache -->",
                         );
        // Add the Language-Specific Items
        foreach( $this->messages as $key=>$val ) {
            if ( !array_key_exists( $key, $ReplStr ) ) {
                $ReplStr[ "[$key]" ] = $val;
            }
        }

        // Collect the Resource
        $rVal = readResource( RES_DIR . '/' . $this->settings['resource_prefix'] . '_footer.html', $ReplStr );

        // Return the Closure
        return $rVal;
    }

    /***********************************************************************
     *                          Internal Functions
     *
     *   The following code should only be called by the above functions
     ***********************************************************************/
    /**
     * Function returns an HTML Formatted String containing Language Options.
     * Note: The Current Language will appear as "Selected"
     */
    private function _listLanguages() {
        $Langs = listThemeLangs();
        $rVal = "";

        foreach ($Langs as $key=>$val) {
            if ( strtolower($this->settings['DispLang']) != strtolower($key) ) {
                $rVal .= "<a onClick=\"javascript:switchLang('$key');\">$val</a>";
            }
        }

        // Return the List
        return $rVal;
    }

    /**
     * Function Returns a Boolean Response whether the MPage Requested
     *       is Valid or Not 
     * 
     * Note: This needs to be made a bit more automatic, as it's high
     *       maintenance in the long-term.
     * 
     * Change Log
     * ----------
     * 2012.04.14 - Created Function (J2fi)
     */
    private function _isValidPage() {
        $rVal = false;
        $validPg = array('about', 'archives', 'blog', 'search', 'tags', '');

        // Append the Valid Years of Content
        $years = $this->content->getValidPostYears();
        if ( is_array($years) ) {
	        $validPg = array_merge($validPg, $years);
        }

        // Determine if the Page Requested is in the Array
        if ( in_array(NoNull($this->settings['mpage']), $validPg) ) {
            $rVal = true;
        }

        // Return the Boolean Response
        return $rVal;
    }
    
    /**
     * Function Loads the Entire ReplStr Array for Use Throughout the Page and
     *      Returns the Array
     * 
     * Change Log
     * ----------
     * 2012.05.27 - Created Function (J2fi)
     */
    private function _collectPageData() {
        $ReplStr = array( '[HOMEURL]'     => $this->settings['HomeURL'],
                          '[COPYRIGHT]'   => date('Y') . " - " . NoNull($this->messages['company_name'], NoNull($this->messages['site_name'])),
                          '[SITEDESCR]'   => NoNull($this->settings['SiteDescr'], $this->settings['SiteName']),
                          '[CONF_DIR]'    => $this->settings['HomeURL'] . "/conf",
                          '[CSS_DIR]'     => CSS_DIR,
                          '[IMG_DIR]'     => IMG_DIR,
                          '[JS_DIR]'      => JS_DIR,

                          /* Body Content */
                          '[BLOG_BODY]'   => $this->_getBlogContent( 5 ),
                          '[NAVIGATION]'  => $this->_getNavigationMenu(),
                          '[PAGE_TITLE]'  => $this->_getPageTitle( NoNull($this->settings['mpage']) ),
                          '[EXTEND_HDR]'  => '',
                         );

        // Read In the Language Strings
        foreach( $this->messages as $key=>$val ) {
            if ( !array_key_exists( $key, $ReplStr ) ) {
                $ReplStr[ "[$key]" ] = $val;
            }
        }

        // Add any Extra Data
        $Extras = $this->_getExtraContent();
        foreach( $Extras as $key=>$val ) {
            if ( !array_key_exists( $key, $ReplStr ) ) {
                $ReplStr[ $key ] = $val;
            }
        }

        // Read the Appropriate Template File if the Page Requested is Valid
        if ( $this->_isValidPage() ) {
            $ReqFile = $this->_getReqFileName();
            $ReplStr[ '[CONTENT_BODY]' ] = readResource( RES_DIR . $ReqFile, $ReplStr );
        }

        // Return the Array
        return $ReplStr;
    }

    /**
     * Function Returns the Appropriate Page Title for a Section
     */
    private function _getPageTitle( $Section ) {
        $rVal = NoNull($this->messages['site_name']);
        $rSuffix = "";

        switch ( strtolower($Section) ) {
            case 'blog':
                $pURL = $this->settings['year']  . '/' .$this->settings['month'] . '/' .
                        $this->settings['day']   . '/' . $this->settings['title'];
                $rVal = $this->content->getPageTitle( $pURL );
                break;
            
            default:
                $rSuffix = $this->messages['ttl_' . strtolower(NoNull($this->settings[mpage])) ];
        }

        // Append the Page Title if it's Applicable
        if ( $rSuffix != '' ) { $rVal .= " | $rSuffix"; }

        // Return the Page Title
        return $rVal;
    }

    /**
     * Function Returns the Additional Resource Requirements for the Requested Page
     */
    private function _getExtendedHeaderInfo() {
        $rVal = '';
        
        switch ( NoNull($this->settings['mpage']) ) {
            case 'contact':
                $rVal = tabSpace(4) . "<link rel=\"stylesheet\" href=\"" . CSS_DIR . "/contact.css\" type=\"text/css\" />";
                break;

            case '':
                $rVal = tabSpace(4) . "";

            default:
                $rVal = '';
        }

        // Return the Extended Header Information
        return $rVal;
    }

    /**
     * Function Returns any Extra Content Fields that Need to Appear
     *      in the $ReplStr Array
     */
    private function _getExtraContent() {
        $rVal = array( '[ARCHIVE-LIST]' => '',
                       '[SOCIAL-LINK]'  => '',
                       '[RESULTS]'      => '',
                      );

        switch ( $this->settings['mpage'] ) {
            case 'archives':
            case 'archive':
                //$rVal['[ARCHIVE-LIST]'] = $this->_getArchivesHTML();
                break;

            case 'blog':
                $tArr = array( '[PAGE-URL]' => $this->settings['HomeURL'] . "/" .
                                               $this->settings['year']  . "/" . 
                                               $this->settings['month'] . "/" .
                                               $this->settings['day']   . "/" . 
                                               $this->settings['title'] . "/",
                              );
                $rVal['[SOCIAL-LINK]'] = readResource( RES_DIR . '/content-blog-social.html', $tArr );
                break;

            case 'search':
                //$rVal['[RESULTS]'] = $this->_getSearchHTML();
                break;
            
            default:
                
        }

        // Return the Extra Content Data
        return $rVal;
    }

    /**
     * Function Returns the Appropriate .html Content File Required for a
     *      given mPage / sPage Combination.
     * 
     * Change Log
     * ----------
     * 2012.04.14 - Created Function (J2fi)
     */
    private function _getReqFileName() {
        $rVal = '';
        $FileName = '/content-' . strtolower(NoNull($this->settings['mpage'])) . '.html';
        if ( NoNull($this->settings['mpage']) == 'page' ) {
            $FileName = '/content-blog.html';
        }
        
        if ( file_exists( RES_DIR . $FileName ) ) {
            $rVal = $FileName;
        } else {
            $rVal = '/content-landing.html';
        }

        // Return the Required FileName
        return $rVal;        
    }

    private function _getNavigationMenu() {
        $pages = array("about"		=> $this->messages['lblAbout'],
                       "archives"	=> $this->messages['lblArchives'],
                       "links"		=> $this->messages['lblLink'],
                       );
        $rVal = "";
        $i = 1;

        foreach ( $pages as $url=>$title ) {
            $suffix = ( NoNull($url) == "" ) ? "" : "/$url";
            $rVal .= "<li class=\"page_item page-item\"><a href=\"" . $this->settings['HomeURL'] . $suffix . "\" title=\"$title\">$title</a></li>";
            $i++;
        }

        // Return the Top Navigation Menu
        return $rVal;
    }

    /* ********************************************************************* *
     *  Blog Content Component
     * ********************************************************************* */
    private function _getBlogContent( $PostNum ) {
    	$data = $this->content->getContent( $PostNum );
	    $rVal = "";

	    if ( is_array($data) ) {
            $RecordTotal = nullInt($data['RecordTotal']);
            $RecordCount = nullInt($data['RecordCount']);
            $ResourceFile = NoNull($data['Resource']);
            $Records = nullInt($data['Records']);
            $i = 1;

            foreach ($data as $Key=>$Entry ) {
            	if ( $Key == $i ) {
	                $ReplStr = array( '[HOMEURL]'     => $this->settings['HomeURL'],
	                                  '[POST-FOOTER]' => "",
	                                  '[DISQUS_ID]'	  => NoNull($this->settings['disqus_name']),
	                                  '[COMMENTS]'    => "",
	                                  '[DIV-CLASS]'   => "",
	                                 );
	                foreach ( $Entry as $Item=>$Value ) {
		                $ReplStr[ $Item ] = $Value;
	                }

	                // Clean up the Content (If Necessary)
	                if ( $ReplStr['[ARCHIVE-LIST]'] ) {
		                $ReplStr['[ARCHIVE-LIST]'] = $this->_prepCustoms( $ReplStr['[ARCHIVE-LIST]'] );
	                }

	                // Append the Footnotes (If Necessary)
		            if ( $ReplStr['[POST-FOOTER]'] ) {
			            $ReplStr['[POST-FOOTER]'] = readResource( RES_DIR . '/content-blog-footer.html', $ReplStr);
		            }

		            // Construct the Comments (If Necessary)
		            if ( $ReplStr['[DISQUS_ID]'] ) {
			            $ReplStr['[COMMENTS]'] = readResource( RES_DIR . '/content-blog-comments.html', $ReplStr);	            
		            }
	                $i++;

	                // Replace the Template Content Accordingly
	                $rVal .= readResource( RES_DIR . "/$ResourceFile", $ReplStr);
            	}
            }

            // Save Post Count Setting
            $MaxPages = round($RecordTotal / $PostNum) + 1;
            if ( $MaxPages <= 0 ) { $MaxPages = 1; }
            saveSetting( 'core_archives', $this->content->getReadableURI(), $MaxPages );

            // Write the Data to the Cache
            $this->content->saveCacheHTML( $rVal );

        } else {
        	// HTML Was Returned, So Show It
        	if ( $data ) {
	        	$rVal = $data;
        	}
        }

        // Return the Page Data
        return $rVal;
    }
    
    /**
     *	Function Prepares The Content for Custom Filtering (If Necessary)
     */
    private function _prepCustoms( $Content ) {
        $ReplStr = array( '[HOMEURL]'     => $this->settings['HomeURL'],
                          '[DIV-CLASS]'   => "",

                          '[ARCHIVE-CLASS-YEAR-MONTH]'	=> "",
                          '[ARCHIVE-CLASS-MONTH]'		=> "",
                         );
        $rVal = $Content;

        $Search = array_keys( $ReplStr );
        $Replace = array_values( $ReplStr );

        // Perform the Search/Replace Actions
        $rVal = str_replace( $Search, $Replace, $Content );

        // Return the Content
        return $rVal;
    }






    private function _getMonthlyArchives() {
        $rVal = "";
        $totalCount = 0;
        $i = 0;

        $inCache = $this->content->getContent("monthlyArchives", 300);
        if ( $inCache ) {
            $rVal = $inCache;
        } else {
            $postData = apiRequest( "content/summary", array('Show' => 'monthly') );
            if ( $postData ) {
                $rVal = "<ul>";
                foreach( $postData->data as $item ) {
                    if ( $i <= 15 ) {
                        $URL = $this->settings['HomeURL'] . "/" . $item->PostYear . "/" . $item->PostMonth . "/";
                        $MonthName = $this->messages['lblMonth' . $item->PostMonth];
                        $Title = "$MonthName (" . nullInt($item->PostCount) . " Posts)";
                        $Display = "$MonthName " . $item->PostYear;
                        $rVal .= "<li><a href=\"$URL\" title=\"$Title\">$Display</a></li>";
                    }

                    $i++;
                    $totalCount += nullInt($item->PostCount);
                }
                if ( $totalCount > 8 ) { $totalCount = $totalCount - 8; }
                $rVal .= "<li><a href=\"" . $this->settings['HomeURL'] . "/archives/\" title=\"Show All " . number_format($totalCount, 0) . " Posts\">Show All Archives</a></li>";
                $rVal .= "</ul>";

                // Save the Data to Cache
                $this->content->saveContent("monthlyArchives", $rVal);
            }            
        }

        // Return the Monthly Archives Item
        return $rVal;
    }
    
    private function _getTagListings() {
        $rVal = "";
        $inCache = $this->content->getContent("tagListings", 3600);
        
        if ( $inCache ) {
            $rVal = $inCache;
        } else {
            $postData = apiRequest( "content/summary", array('Show' => 'tags', 'Count' => 40) );
            if ( $postData ) {
                $rVal = "<div class=\"tagcloud\">\n";
                foreach( $postData->data as $item ) {
                    $TagName = $item->TagName;
                    $URL = $this->settings['HomeURL'] . "/tags/" . urlencode($TagName) . "/";
                    $Title = nullInt($item->Posts) . " Posts";
                    $rVal .= "<a href=\"$URL\" class='tag-link' title=\"$Title\" style=\"font-size: 8pt;\">$TagName</a>\n";
                }
                $rVal .= "</div>";

                // Save the Data to Cache
                $this->content->saveContent("tagListings", $rVal);            
            }
        }
        
        // Return the Tags Listing
        return $rVal;
    }
    
    private function _getPostsListings( $Count = 10 ) {
        $rVal = "";
        $inCache = $this->content->getContent("postListings_$Count", 300);

        if ( $inCache ) {
            $rVal = $inCache;
        } else {
            $postData = apiRequest( "content/summary", array('Show' => 'posts', 'Count' => $Count) );
            if ( $postData ) {
                $rVal = "<ul>\n";
                foreach( $postData->data as $item ) {
                    $URL = $this->settings['HomeURL'] . $item->Value;
                    $Title = $item->Title;
                    
                    $rVal .= "<li><a href=\"$URL\" title=\"$Title\">$Title</a></li>\n";
                }
                $rVal .= "</ul>";

                // Save the Content to Cache
                $this->content->saveContent( "postListings_$Count", $rVal);
            }
        }
        
        // Return the Tags Listing
        return $rVal;
    }




}
?>
