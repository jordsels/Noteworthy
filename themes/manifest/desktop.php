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
                          '[SITEDESCR]'   => NoNull($this->settings['SiteDescr'], $this->messages['site_descr']),
                          '[PAGE_TITLE]'  => $this->_getPageTitle( NoNull($this->settings['PgRoot']) ),
                          '[LANG_CD]'     => strtoupper($this->messages['lang_cd']),
                          '[FEEDTITLE]'	  => NoNull($this->messages['lblFeedTitle'], "Subscribe via RSS"),
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
                          '[FOOTLINKS]'	 => $this->_getSiteLinks(),
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
     * Function Returns a Boolean Response whether the PgRoot Requested
     *       is Valid or Not 
     * 
     * Note: This needs to be made a bit more automatic, as it's high
     *       maintenance in the long-term.
     */
    private function _isValidPage() {
        $rVal = false;
        $validPg = array('archives', 'blog', 'search', 'tags', '');

        // Append the Valid Years of Content
        $years = $this->content->getValidPostYears();
        if ( is_array($years) ) {
	        $validPg = array_merge($validPg, $years);
        }

        // Determine if the Page Requested is in the Array
        if ( in_array(NoNull($this->settings['PgRoot']), $validPg) ) {
            $rVal = true;
        }

        // Return the Boolean Response
        return $rVal;
    }
    
    /**
     * Function Loads the Entire ReplStr Array for Use Throughout the Page and
     *      Returns the Array
     */
    private function _collectPageData() {
    	$CopyStr = date('Y');
    	$CopyStr .= ( array_key_exists('company_name', $this->messages) ) ? ' - ' . NoNull($this->messages['company_name']) :
    																		' - ' . NoNull($this->messages['site_name']);
        $ReplStr = array( '[HOMEURL]'		=> $this->settings['HomeURL'],
                          '[COPYRIGHT]'		=> $CopyStr,
                          '[SITENAME]'		=> NoNull($this->settings['SiteName'], $this->messages['SiteName']),
                          '[SITEDESCR]'		=> NoNull($this->settings['SiteDescr'], $this->settings['SiteName']),
                          '[CONF_DIR]'		=> $this->settings['HomeURL'] . "/conf",
                          '[CSS_DIR]'		=> CSS_DIR,
                          '[IMG_DIR]'		=> IMG_DIR,
                          '[JS_DIR]'		=> JS_DIR,

                          /* Body Content */
                          '[NAVIGATION]'	=> $this->_getNavigationMenu(),
                          '[PAGE_BODY]'		=> $this->_getBlogContent( 5 ),
                          '[PAGE_TITLE]'	=> $this->_getPageTitle( NoNull($this->settings['PgRoot']) ),
                          '[PAGINATION]'	=> $this->_getPageNavigation(),
                          '[EXTEND_HDR]'	=> '',
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
        $rVal = NoNull($this->settings['SiteName']);
        $rSuffix = $PgRoot = "";
        if ( array_key_exists('PgRoot', $this->settings) ) {
	        $PgRoot = $this->settings['PgRoot'];
        }

        switch ( strtolower($Section) ) {
            case 'blog':
                $pURL = $this->settings['year']  . '/' .$this->settings['month'] . '/' .
                        $this->settings['day']   . '/' . $this->settings['title'];
                $rVal = $this->content->getPageTitle( $pURL );
                break;

            default:
            	$MsgIDX = 'ttl_' . strtolower($PgRoot);
            	if ( array_key_exists($MsgIDX, $this->messages) ) {
	                $rSuffix = $this->messages[$MsgIDX];	            	
            	}
        }

        // Append the Page Title if it's Applicable
        if ( $rSuffix != '' ) { $rVal .= " | $rSuffix"; }

        // Return the Page Title
        return $rVal;
    }

    /**
     * Function Returns any Extra Content Fields that Need to Appear
     *      in the $ReplStr Array
     */
    private function _getExtraContent() {
        $rVal = array( '[ARCHIVE-LIST]' => '',
        			   '[COMMENTS]'		=> '',
                       '[SOCIAL-LINK]'  => '',
                       '[RESULTS]'      => '',
                      );

        switch ( $this->settings['PgRoot'] ) {
            case 'archives':
            case 'archive':
                $rVal['[MONTHLY-LIST]'] = $this->_getMonthListings();
                $rVal['[TAG-LIST]'] = $this->_getTagListings( 45 );
                break;

            case 'blog':
                $tArr = array( '[PAGE-URL]' => $this->settings['HomeURL'] . "/" .
                                               $this->settings['year']  . "/" . 
                                               $this->settings['month'] . "/" .
                                               $this->settings['day']   . "/" . 
                                               $this->settings['title'] . "/",
                              );
                foreach ( $this->messages as $Key=>$Val ) {
	                $tArr[ "[$Key]" ] = $Val;
                }
                $rVal['[SOCIAL-LINK]'] = readResource( RES_DIR . '/content-blog-social.html', $tArr );
                if ( $this->settings['DisqusID'] != "" ) {
	                $rVal['[COMMENTS]'] = readResource( RES_DIR . '/content-blog-comments.html', $tArr );
                }
                break;

            default:
                
        }

        // Return the Extra Content Data
        return $rVal;
    }

    /**
     * Function Returns the Appropriate .html Content File Required for a
     *      given PgRoot / PgSub1 Combination.
     */
    private function _getReqFileName() {
        $rVal = '';
        $FileName = '/content-' . strtolower(NoNull($this->settings['PgRoot'])) . '.html';
        if ( NoNull($this->settings['PgRoot']) == 'page' ) {
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
        $pages = array( "archives"	=> $this->messages['lblArchives'],
                       );
        $rVal = "";
        $i = 1;

        foreach ( $pages as $url=>$title ) {
            $suffix = ( NoNull($url) == "" ) ? "" : "/$url/";
            $rVal .= "<li class=\"page_item page-item\"><a href=\"" . $this->settings['HomeURL'] . $suffix . "\" title=\"$title\">$title</a></li>";
            $i++;
        }

        // Return the Top Navigation Menu
        return $rVal;
    }

    /* ********************************************************************* *
     *  Blog Content Component
     * ********************************************************************* */
    private function _parseBlogContent( $Entry ) {
        $rVal = array( '[HOMEURL]'		 => $this->settings['HomeURL'],
                       '[POST-FOOTER]'	 => "",
                       '[DISQUS_ID]'	 => NoNull($this->settings['DisqusID']),
                       '[COMMENTS]'		 => "",
                       '[PAGINATION]'	 => "",
                       '[IMG_DIR]'		 => IMG_DIR,
                       '[SEARCH-PHRASE]' => NoNull($this->settings['s']),
                       '[SEARCH-RESULT]' => "",
                       '[DIV-CLASS]'	 => "",
                      );
        foreach ( $Entry as $Item=>$Value ) {
            $rVal[ $Item ] = $Value;
        }
        foreach ( $this->messages as $Key=>$Msg ) {
            $rVal[ "[$Key]" ] = $Msg;
        }
        
        if ( count($Entry) == 0 ) {
	        $rVal['[SEARCH-RESULT]'] = "<div class=\"post hentry\"><div class=\"postContent\">" .
									   "<p>No Results Found</p>" .
									   "</div></div>";
        }


        // Return the Parsed Entry
        return $rVal;
    }

    private function _getBlogContent( $PostNum ) {
    	$data = $this->content->getContent( $PostNum, true );
	    $rVal = "";

	    if ( is_array($data) ) {
            $RecordTotal = nullInt($data['RecordTotal']);
            $RecordCount = nullInt($data['RecordCount']);
            $ResourceFile = NoNull($data['Resource']);
            $Records = nullInt($data['Records']);
            $i = 1;

            if ( $RecordCount > 0 ) {
	            $this->settings['RecordCount'] = $RecordCount;
	            foreach ($data as $Key=>$Entry ) {
	            	if ( $Key == $i ) {
		                $ReplStr = $this->_parseBlogContent( $Entry );

		                switch ( $this->settings['PgRoot'] ) {
			                case 'search':
			                	if ( $ReplStr['[PostURL]'] != "" ) {
			                		$SearchResource = '/content-search-post.html';
			                		$ReplStr['[POST-URL]'] = str_replace('[HOMEURL]', $this->settings['HomeURL'], $ReplStr['[PostURL]'] );
			                		if ( $ReplStr['[TYPE-CODE]'] == 'TWEET' ) {
			                			$ReplStr['[CONTENT]'] = parseTweet( $ReplStr['[CONTENT]'] );
			                			$ReplStr['[TITLE]'] = strip_tags( $ReplStr['[CONTENT]'] );
				                		$SearchResource = '/content-search-tweet.html';
			                		}
			                		$rVal .= readResource( RES_DIR . $SearchResource, $ReplStr);
			                	}
			                	break;

			                default:
				                // Clean up the Content (If Necessary)
				                if ( $ReplStr['[ARCHIVE-LIST]'] ) {
					                $ReplStr['[ARCHIVE-LIST]'] = $this->_prepCustoms( $ReplStr['[ARCHIVE-LIST]'] );
				                }
				                
				                if ( $ReplStr['[POST-TAG]'] != "" ) {
					                $ReplStr['[POST-TAG]'] = $this->messages['lblTags'] . ": " . $ReplStr['[POST-TAG]'];
				                }
			
				                // Append the Footnotes (If Necessary)
					            if ( $ReplStr['[POST-FOOTER]'] ) {
						            $ReplStr['[POST-FOOTER]'] = readResource( RES_DIR . '/content-blog-footer.html', $ReplStr);
					            }
			
					            // Construct the Comments (If Necessary)
					            $doComments = YNBool( readSetting('core', 'doComments') );
					            if ( $doComments && $ReplStr['[DISQUS_ID]'] && intval($data['RecordCount']) == 1 ) {
						            $ReplStr['[COMMENTS]'] = readResource( RES_DIR . '/content-blog-comments.html', $ReplStr);
					            }
	
					            // Replace the Template Content Accordingly
					            $rVal .= readResource( RES_DIR . "/$ResourceFile", $ReplStr);
		                }
		                $i++;
	            	}
	            }

            } else {
                $ReplStr = $this->_parseBlogContent( array() );
            }

            if ( $this->settings['PgRoot'] == 'search' ) {
	            // Replace the Search Template Content Accordingly
	            if ( $rVal != "" ) {
		            $ReplStr['[SEARCH-RESULT]'] = NoNull($rVal);
	            }
	            $rVal = readResource( RES_DIR . "/$ResourceFile", $ReplStr);
            }

            // Save Post Count Setting
            $MaxPages = round($RecordTotal / $PostNum) + 1;
            if ( $MaxPages <= 0 ) { $MaxPages = 1; }
            saveSetting( 'core_archives', $this->content->getReadableURI(), $MaxPages );

            // Write the Data to the Cache
            $this->content->saveCacheHTML( $rVal );

        } else {
        	// HTML Was Returned, So Show It
        	if ( $data ) { $rVal = $data; }
        }

        // Return the Page Data
        return $rVal;
    }
    
    /**
     *	Function Prepares The Content for Custom Filtering (If Necessary)
     */
    private function _prepCustoms( $Content ) {
        $ReplStr = array( '[HOMEURL]'     				=> $this->settings['HomeURL'],
                          '[DIV-CLASS]'   				=> "",

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

    /**
     *	Function Returns a Listing of Tags Sorted in Alphabetical Order
     */
    private function _getTagListings( $TagCount = 9999 ) {
    	$data = $this->content->getTagsList();
    	$Filter = array();
        $rVal = "";
        $max = 0;
        $i = 1;

        if ( is_array($data) ) {
            foreach( $data as $Tag=>$Posts ) {
            	if ( $i <= $TagCount ) {
            		array_push($Filter, $Tag);
            	}
            	if ( nullInt($Posts) > $max ) { $max = nullInt($Posts); }
                $i++;
            }

            $i = 0;
            natcasesort($Filter);
            foreach ( $Filter as $Tag ) {
            	$FontSize = intval(50 * (intval($data[$Tag]) / $max));
	            $URL = $this->settings['HomeURL'] . "/tags/" . urlencode($Tag) . "/";
	            $Title = nullInt( $data[$Tag] ) . " Posts";
	            
	            $rVal .= "<li><a href=\"$URL\" class=\"tag-link\" title=\"$Title\" style=\"font-size: " . ($FontSize + 10) . "pt;\">$Tag</a></li>";
            }

        } else {
	        $rVal = $data;
        }

        // Return the Tags Listing
        return $rVal;
    }

    /**
     *	Function Constructs the Page Navigation (Prev/Next) Links accordingly
     */
    private function _getPageNavigation() {
    	$Template = "<div class=\"pageNav\">" .
    				"<div class=\"prev\">[PREV-LINK]</div>" .
    				"<div class=\"next\">[NEXT-LINK]</div>" .
    				"</div>";
    	$Prev = "&laquo; Older";
    	$Next = "Newer &raquo;";
	    $rVal = "";
	    
	    // If this is Not a Single Post, Return a Blank
	    $RecordCount = 0;
	    if ( array_key_exists('RecordCount', $this->settings) ) {
	    	$RecordCount = $this->settings['RecordCount'];
	    }
	    if ( $RecordCount != 1 ) { return $rVal; }
	    
	    $data = $this->content->getPagePagination();
	    if ( is_array($data) ) {
	    	$rVal = $Template;
	    	$PrevHREF = $NextHREF = "";
	    	if ( array_key_exists('Prev', $data) ) {
		    	$PrevURL = str_replace("[HOMEURL]", $this->settings['HomeURL'], $data['Prev']['PostURL']);
		    	$PrevHREF = "<a href=\"$PrevURL\" title=\"" . $data['Prev']['Title'] . "\">$Prev</a>";
	    	}

	    	if ( array_key_exists('Next', $data) ) {
		    	$NextURL = str_replace("[HOMEURL]", $this->settings['HomeURL'], $data['Next']['PostURL']);
		    	$NextHREF = "<a href=\"$NextURL\" title=\"" . $data['Next']['Title'] . "\">$Next</a>";
	    	}

	    	// Replace the Strings Accordingly
	    	$rVal = str_replace("[PREV-LINK]", $PrevHREF, $rVal);
	    	$rVal = str_replace("[NEXT-LINK]", $NextHREF, $rVal);
	    }

	    // Return the Page Navigation
	    return $rVal;
    }
    
    private function _getMonthListings( $GetAll = false ) {
	    $data = $this->content->getMonthlyArchives();
	    $rVal = "";

	    if ( is_array($data) ) {
	    	foreach( $data as $Key=>$Row ) {
	    		$Posts = nullInt($Row['Posts']);
	    		$Title = $this->messages['lblMonth' . $Row['Month']];
	    		$Year = $Row['Year'] . $this->messages['lblYearSuffix'];
	    		$URL = $this->settings['HomeURL'] . '/' . $Row['Year'] . '/' . $Row['Month'] . '/';
	    		
		    	$rVal .= "<li><a href=\"$URL\" title=\"$Title $Year\">$Title $Year</a>&nbsp;($Posts)</li>";
	    	}
	    } else {
		    $rVal = $data;
	    }
	    
	    // Return the List of Months
	    return $rVal;
    }

    private function _getSiteLinks() {
	    $data = $this->content->getSiteLinks();
	    $rVal = "";
	    
	    if ( is_array($data) ) {
		    foreach( $data as $Site=>$Link ) {
			    $rVal .= "<li><a href=\"$Link\" title=\"$Site\">$Site</a></li>";
		    }
	    }
	    
	    // Wrap the Links appropriately
	    if ( $rVal != "" ) {
		    $rVal = "<h5>" . $this->messages['lblElsewhere'] . "</h5>" .
		    		"<ul class=\"elsewhere\">" .
		    		$rVal .
		    		"</ul>";
	    }

	    // Return the Site Links
	    return $rVal;
    }

}
?>
