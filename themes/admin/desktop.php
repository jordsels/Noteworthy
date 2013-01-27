<?php

/**
 * @author Jason F. Irwin
 * @copyright 2012
 * 
 * Class contains the rules and methods called for the Admin Theme
 */
require_once( LIB_DIR . '/content.php' );

class miTheme extends theme_main {
    var $settings;
    var $messages;
    var $content;
    var $perf;
    var $user;

    function __construct( $settings ) {
        $GLOBALS['Perf']['app_s'] = getMicroTime();
        $this->settings = $settings;

        // Set the Resource Prefix
        $this->settings['resource_prefix'] = 'desktop';
        $this->messages = getLangDefaults( $this->settings['DispLang'] );

        // Prep the Content
        $this->content = new Content( $settings, dirname(__FILE__) );

        // Prep the User Data
		require_once( LIB_DIR . '/user.php' );
        $this->user = new User( $this->settings['token'] );

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

        // Are We Trying to Log In?
        if ( $this->settings['dataset'] == 'login' ) {
	        $this->_performLogin();
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
                          '[PAGE_TITLE]'  => $this->_getPageTitle( NoNull($this->settings['PgRoot']) ),
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

        $lblSecond = ( $App == 1 ) ? "Second" : "Seconds";
        $lblCalls  = ( $Api == 1 ) ? "Call"   : "Calls";
        $lblQuery  = ( $SQL == 1 ) ? "Query"  : "Queries";

        $ReplStr = array( '[GenTime]'    => "<!-- Page generated in roughly: $App $lblSecond, $Api API $lblCalls, $SQL SQL $lblQuery -->",
                         );

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
     * 
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
     * Function Returns a Boolean Response whether the PgRoot Requested
     *       is Valid or Not 
     * 
     * Note: This needs to be made a bit more automatic, as it's high
     *       maintenance in the long-term.
     */
    private function _isValidPage() {
        $rVal = true;

        $validPg = array('login', 'landing', 'dashboard', 'search', 'about', '');

        // Determine if the Page Requested is in the Array
        if ( in_array(NoNull($this->settings['PgSub1']), $validPg) ) {
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
    	$SiteID = NoNull( $this->settings['dispSiteID'], $this->settings['SiteID'] );
    	$PostCount = (!is_numeric( $this->content->getReadableURI() )) ? 5 : 9;
    	$ENDataToken = "ENData";
    	if ( nullInt($SiteID) > 0 ) { $ENDataToken .= "_$SiteID"; }
        $ReplStr = array( '[HOMEURL]'	  => $this->settings['HomeURL'],
        				  '[API_URL]'	  => $this->settings['api_url'],
                      	  '[SITEURL]'	  => $this->settings['URL'],
                          '[COPYRIGHT]'   => date('Y') . " - " . NoNull($this->messages['company_name'], NoNull($this->settings['site_name'])),
                          '[CopyYear]'	  => date('Y'),
                          '[SITENAME]'	  => $this->settings['site_name'],
                          '[SITEDESCR]'   => $this->settings['site_descr'],
                          '[APPINFO]'     => APP_NAME . " | " . APP_VER,
                          '[APP_VER]'     => APP_VER,
                          '[GENERATOR]'   => GENERATOR,
                          '[TOKEN]'		  => NoNull($this->settings['token']),
                          '[EN_TOKEN]'	  => readSetting($ENDataToken, 'DevToken'),
                          '[EN_SANDBOX]'  => readSetting($ENDataToken, 'UseSandbox'),
                          '[ACCESSKEY]'	  => NoNull($this->settings['api_key']),
                          '[LANG_CD]'     => strtoupper($this->messages['lang_cd']),
                          '[ERROR_MSG]'   => $this->_getPageError(),
                          '[CONF_DIR]'    => $this->settings['HomeURL'] . "/conf",
                          '[CSS_DIR]'     => CSS_DIR,
                          '[IMG_DIR]'     => IMG_DIR,
                          '[JS_DIR]'      => JS_DIR,
                          '[DISP_SITEID]' => $SiteID,

                          /* User Data */
                          '[USERBLOCK]'	  => $this->_getUserBlock(),
                          '[USERNAME]'	  => readSetting('core', 'username'),

                          /* Body Content */
                          '[NAVIGATION]'  => $this->_getNavigationMenu(),
                          '[PAGE_TITLE]'  => $this->_getPageTitle(),
                          '[EXTEND_HDR]'  => $this->_getExtendedHeaderInfo(),                          
                         );

        // Read In the Language Strings
        foreach( $this->messages as $key=>$val ) {
            if ( !array_key_exists( $key, $ReplStr ) ) {
                $ReplStr[ "[$key]" ] = $val;
            }
        }
        
        // Read In the Settings Data
        foreach( $this->settings as $key=>$val ) {
	        if ( !array_key_exists( $key, $ReplStr ) ) {
		        $ReplStr[ "[$key]" ] = $val;
	        }
        }

        // Add any Extra Data (Replacing Anything That Might Already Exist)
        $Extras = $this->_getExtraContent();
        foreach( $Extras as $key=>$val ) {
        	/*
            if ( !array_key_exists( $key, $ReplStr ) ) {
                $ReplStr[ $key ] = $val;
            }
            */
            $ReplStr[ $key ] = $val;
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
     *	Function Constructs the User Information Block and Returns formatted HTML
     */
    private function _getUserBlock() {
    	$rVal = "";

    	if ( $this->settings['isLoggedIn'] == 'Y' ) {
	    	$Gravatar = getGravatarURL( $this->user->EmailAddr() );
	    	$DispName = $this->user->DisplayName();
	    	$HomeURL  = $this->settings['HomeURL'];
	    	$AboutLnk = $HomeURL . "/" . $this->settings['PgRoot'] . '/dashboard/';
	    	$LogoutLnk = "";
	    	// $LogoutLnk = "<img src=\"" . IMG_DIR . "/icons/lock_large_locked.png\" alt=\"" . $this->messages['lblLogout'] . "\"> <a href=\"$HomeURL\">" . $this->messages['lblLogout'] . "</a>";
		    $rVal = "<div id=\"logout\">" .
		    		"<img class=\"grav_default\" src=\"$Gravatar\" alt=\"" . $this->messages['lblWelcome'] . "\">" . $this->messages['lblWelcome'] . " <a class=\"welcome-link\" href=\"$AboutLnk\">$DispName</a>" .
		    		$LogoutLnk .
		    		"</div>";	    	
    	}

	    // Return the User Block
	    return $rVal;
    }

    /**
     * Function Returns Either a Formatted Error Message or an Empty String.
     */
    private function _getPageError() {
	    $rVal = '';
	    
	    if ( NoNull($this->settings['ErrorMsg']) != '' ) {
		    $rVal = '<div class="sys-message sys-error"><p>' . NoNull($this->settings['ErrorMsg']) . '</p></div>';
	    }
	    
	    // Return the Error Message
	    return $rVal;
    }

    /**
     * Function Returns the Appropriate Page Title for a Section
     */
    private function _getPageTitle() {
        $rVal = NoNull($this->messages['site_name']);
        $rSuffix = $this->messages['ttl_' . strtolower(NoNull($this->settings['PgRoot'])) ];

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
        
        switch ( NoNull($this->settings['PgSub1']) ) {
            case 'dashboard':
            case '':
            	if ( YNBool($this->settings['isLoggedIn']) ) {
	            	$rVal = '<link rel="stylesheet" href="' . CSS_DIR . '/prettyPhoto.css" type="text/css" /><!-- lightbox stylesheet -->\r\n' .
	            			'<link rel="stylesheet" href="' . JS_DIR  . '/markitup/skins/simple/style.css" type="text/css" /><!-- WYSWYG editor -->\r\n' .
	            			'<link rel="stylesheet" href="' . JS_DIR  . '/markitup/sets/default/style.css" type="text/css" /><!-- WYSWYG editor -->\r\n' .
	            			'<link rel="stylesheet" href="' . CSS_DIR . '/jquery-ui.custom.css" type="text/css" /><!-- jQuery UI stylesheet -->\r\n' .
	            			'<link rel="stylesheet" href="' . CSS_DIR . '/font-awesome.css" />\r\n' .
	            			'<link rel="stylesheet" href="' . CSS_DIR . '/font-awesome.less" />';
            	}
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
    	$SiteID = NoNull( $this->settings['dispSiteID'], $this->settings['SiteID'] );
    	$ENDataToken = "ENData";
    	if ( nullInt($SiteID) > 0 ) { $ENDataToken .= "_$SiteID"; }
        $rVal = array( '[ARCHIVE-LIST]' => '',
                       '[SOCIAL-LINK]'  => '',
                       '[RESULTS]'      => '',
                       '[SYSTEM-MSGS]'	=> '',
                       '[ADMINURL]'		=> $this->settings['HomeURL'] . '/' . $this->settings['PgRoot'],
                       '[NBOOKCOUNT]'	=> $this->_getSelectedNotebookCount( $SiteID ),
                      );

        switch ( $this->settings['PgSub1'] ) {
            case 'sites':
            	$SiteID = nullInt( $this->settings['dispSiteID'], $this->settings['SiteID'] );
            	$SiteInfo = getSiteDetails( $SiteID );

            	// Website Settings
            	$rVal['[HOMEURL]'] = $SiteInfo['HomeURL'];
            	$rVal['[SiteName]'] = $SiteInfo['SiteName'];
            	$rVal['[SiteDescr]'] = $SiteInfo['SiteDescr'];
            	$rVal['[SiteSEOTags]'] = $SiteInfo['SiteSEOTags'];
            	$rVal['[SiteDefault]'] = ($SiteInfo['isDefault'] == 'Y') ? 'checked="checked"' : '';

            	$doComments = YNBool( $SiteInfo['doComments'] );
            	$rVal['[raNoCommentChk]'] = ( !$doComments ) ? 'checked="checked"' : '';
            	$rVal['[raGoCommentChk]'] = (  $doComments ) ? 'checked="checked"' : '';
            	$rVal['[dVis]'] = ( $doComments ) ? 'block' : 'none';
            	$rVal['[ThemeList]'] = $this->_buildThemeList();
            	
            	// Social Media Links
            	$SocItems = array('SocName', 'SocLink', 'SocShow');
            	for ( $i = 1; $i<= 5; $i++ ) {
            		$KeySuffix = str_pad((int) $i, 2, "0", STR_PAD_LEFT);
	            	foreach ( $SocItems as $Item ) {
	            		$KeyName = $Item . $KeySuffix;
		            	$rVal[ "[$KeyName]" ] = $SiteInfo[ $KeyName ];
	            	}
	            	if ( $SiteInfo[ "SocShow$KeySuffix" ] == "Y" ) {
		            	$rVal[ "[SocChk$KeySuffix]" ] = "checked=\"checked\"";
	            	} else {
		            	$rVal[ "[SocChk$KeySuffix]" ] = "";
	            	}
            	}

            	// Cron Settings
            	$doCron = YNBool( $SiteInfo['doWebCron'] );
            	$rVal['[raNoCronChk]'] = ( !$doCron ) ? 'checked="checked"' : '';
            	$rVal['[raDoCronChk]'] = (  $doCron ) ? 'checked="checked"' : '';

            	// Twitter Settings
            	$doTwitter = YNBool( $SiteInfo['doTwitter'] );
            	$rVal['[raNoTweetChk]'] = ( !$doTwitter ) ? 'checked="checked"' : '';
            	$rVal['[raDoTweetChk]'] = (  $doTwitter ) ? 'checked="checked"' : '';
            	$rVal['[tVis]'] = ( $doTwitter ) ? 'block' : 'none';
            	$rVal['[TwitName]'] = NoNull($SiteInfo['twitName']);

            	// Evernote Settings
            	$UseSandbox = NoNull($SiteInfo, readSetting( $ENDataToken, 'UseSandbox' ));
            	if ( $UseSandbox != 'N' ) { $UseSandbox = 'Y'; }

            	// Set the Various Values for Sandbox Usage
                $rVal['[raSandboxChk]'] = ($UseSandbox == 'Y') ? 'checked="checked"' : '';
                $rVal['[raProductionChk]'] = ($UseSandbox == 'N') ? 'checked="checked"' : '';
                $rVal['[note-sandboxStyle]'] = ($UseSandbox == 'N') ? 'style="display: none;"' : '';
                $rVal['[note-productionStyle]'] = ($UseSandbox == 'Y') ? 'style="display: none;"' : '';
                $rVal['[iVis]'] = 'style="display: none;';
                

                if ( $rVal['[NBOOKCOUNT]'] > 0 ) {
	                $rVal['[iVis]'] = "";
                }
                break;

            case 'settings':
            	// MySQL Settings
            	$rVal['[DT_SQL]'] = ( DB_TYPE == 1 ) ? " selected" : "";
            	$rVal['[DT_NWS]'] = ( DB_TYPE == 2 ) ? " selected" : "";
                $rVal['[DO_SQL]'] = ( DB_TYPE == 1 ) ? "" : ' style="display: none;"';
                $rVal['[DBSERV]'] = ( DB_TYPE == 1 ) ? NoNull(DB_SERV) : "";
                $rVal['[DBNAME]'] = ( DB_TYPE == 1 ) ? NoNull(DB_MAIN) : "";
                $rVal['[DBUSER]'] = ( DB_TYPE == 1 ) ? NoNull(DB_USER) : "";
                $rVal['[DBPASS]'] = ( DB_TYPE == 1 ) ? NoNull(DB_PASS) : "";
                $rVal['[DBPASSTYPE]'] = ( $rVal['[DBPASS]'] != "" ) ? 'password' : 'text';

                // Debug Settings
                $rVal['[DEBUG0]'] = ( DEBUG_ENABLED == 0 ) ? " selected" : "";
                $rVal['[DEBUG1]'] = ( DEBUG_ENABLED == 1 ) ? " selected" : "";

                // Email Settings
                $EmailEnabled = YNBool(readSetting('core', 'EmailOn'));
                $SecureSSL = YNBool(readSetting('core', 'EmailSSL'));
            	$rVal['[EMAIL_N]']		= ( !$EmailEnabled ) ? " selected" : "";
            	$rVal['[EMAIL_Y]']		= (  $EmailEnabled ) ? " selected" : "";
            	$rVal['[DO_EMAIL]'] = ( $EmailEnabled ) ? "" : ' style="display: none;"';
            	$rVal['[SSL_N]'] = ( !$SecureSSL ) ? " selected" : "";
            	$rVal['[SSL_Y]'] = (  $SecureSSL ) ? " selected" : "";
            	$rVal['[EMAIL_STUB]']	= $this->_readBaseDomainURL( $this->settings['HomeURL'] );
            	$rVal['[MAILSERV]']		= readSetting( 'core', 'EmailServ' );
            	$rVal['[MAILPORT]']		= readSetting( 'core', 'EmailPort' );
            	$rVal['[MAILUSER]']		= readSetting( 'core', 'EmailUser' );
            	$rVal['[MAILPASS]']		= readSetting( 'core', 'EmailPass' );
            	$rVal['[MAILPASSTYPE]']	= ( $rVal['[MAILPASS]'] != "" ) ? 'password' : 'text';
            	$rVal['[MAILSENDTO]']	= readSetting( 'core', 'EmailSendTo' );
            	$rVal['[MAILREPLY]']	= readSetting( 'core', 'EmailReplyTo' );
                break;

            case 'dashboard':
            case '':
            	$rVal['[SYSTEM-MSGS]'] = $this->_getSystemMessages();
            	break;

            default:
            	// Do Nothing
        }

        // Return the Extra Content Data
        return $rVal;
    }

    /**
     *	Function Reads the Base Domain, excluding any subdomain information that might exist.
     */
	function _readBaseDomainURL( $url ) {
		$rVal = false;
	
		$pieces = parse_url( $url );
		$domain = isset( $pieces['host'] ) ? $pieces['host'] : '';
		if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
			$rVal = $regs['domain'];
		}
	
		// Return the Domain Information
		return $rVal;
	}

    /**
     *	Function Constructs a List of Themes (Excluding the Admin, of course)
     */
    private function _buildThemeList() {
	    $rVal = "";
	    
	    $data = getThemeList();
	    foreach ( $data as $Key=>$Name ) {
	    	$isCurrent = ( $this->settings['Location'] == $Key ) ? " selected" : "";
		    $rVal .= "<option value=\"$Key\"$isCurrent>$Name</option>";
	    }

	    // Return the HTML Structure
	    return $rVal;
    }

    /**
     * Function Returns the Appropriate .html Content File Required for a
     *      given PgSub1 Value.
     */
    private function _getReqFileName() {
        $rVal = '';

        if ( YNBool($this->settings['isLoggedIn']) ) {
	        $FileName = '/content-' . strtolower(NoNull($this->settings['PgSub1'])) . '.html';
        } else {
	        $FileName = '/content-login.html';
        }

        // Load the Appropriate File
        if ( file_exists( RES_DIR . $FileName ) ) {
            $rVal = $FileName;
        } else {
            $rVal = '/content-landing.html';
        }

        // Return the Required FileName
        return $rVal;        
    }

    /**
     * Function returns an array containing a list of all the sites currently configured as well
     *		as the option to make a new site.
     */    
    private function _getSitesList() {
	    $rVal = array();
	    $MaxID = 0;

	    // Collect the List of Sites
	    $Sites = getSitesList();
	    foreach ( $Sites as $Site ) {
		    $dtl = getSiteDetails( $Site );

		    // Add the Appropriate Information
		    if ( $dtl['HomeURL'] != "" ) {
			    $rVal[$dtl['SiteID']] = $dtl['SiteName'];
			    if ( nullInt($dtl['SiteID']) > $MaxID ) { $MaxID = nullInt($dtl['SiteID']); }
		    }
	    }

	    // Append the Option to Create a New Site
	    $rVal[ ($MaxID + 1) ] = 'Create New Site';

	    // Return the Array of Sites
	    return $rVal;
    }

    /**
     * Function returns the Administration Panel. Should the file be older than the Cache limit,
     *		or a fresh one is requested, the menu will be rebuilt and saved accordingly.
     */
    private function _getNavigationMenu( $forceUpdate = false) {
        $rVal = "";

        if ( YNBool($this->settings['isLoggedIn']) ) {
            $pages = array('dashboard'	=> array('icon' 	=> "icon-home",
            									 'current'	=> "N",
            									 'class'	=> "current_menu_item",
            									 'label'	=> $this->messages['lblDashboard'] ),
            			   'sites'		=> array('icon' 	=> "icon-pencil",
            									 'current'	=> "N",
            									 'class'	=> "current_menu_item",
            									 'label'	=> $this->messages['lblSites'],
            									 'subs'		=> $this->_getSitesList(),
            									 			   ),
            			   'settings'	=> array('icon' 	=> "icon-cogs",
            									 'current'	=> "N",
            									 'class'	=> "current_menu_item",
            									 'label'	=> $this->messages['lblSettings'] ),
            			/*
            			   'about'		=> array('icon' 	=> "icon-user",
            									 'current'	=> "N",
            									 'label'	=> $this->messages['lblAbout'] ),
            			 */
            			   );

            foreach ( $pages as $url=>$dtl ) {
            	$FullURL = $this->settings['HomeURL'] . '/' . $this->settings['PgRoot'] . "/$url/";
            	$SubList = '';
            	$isCurrent = '';
            	if ( array_key_exists('subs', $dtl) ) {
            		$SubList = "<ul>";
	            	foreach ( $dtl['subs'] as $subUrl=>$subDtl ) {
		            	$SubList .= '<li><a href="' . $FullURL . '?dispSiteID=' . $subUrl . '"><span class="nav-icon icon-pencil"></span> ' . $subDtl . '</a></li>';
	            	}
	            	$SubList .= "</ul>";
            	}
            	$isCurrent = '';
            	if ( $this->settings['PgSub1'] == $url ) {
	            	$isCurrent = ' class="' . $dtl['class'] . '"';
            	}
            	$rVal .= '<li' . $isCurrent . '><a href="' . $FullURL . '"><span class="nav-icon ' . $dtl['icon'] . '"></span> ' . $dtl['label'] . '</a>' . $SubList . '</li>';
	        }
        }

        if ( $rVal != "" ) {
	        $rVal = tabSpace( 4) . "<div id=\"nav-container\">\r\n" .
	        		tabSpace( 6) . "<div class=\"container_16 sticky\" id=\"navigation\">\r\n" .
	        		tabSpace( 8) . "<ul class=\"nav-list\" id=\"main-nav\">\r\n" .
	        		tabSpace(10) . $rVal .
	        		tabSpace( 8) . "<span id=\"cron-info\" style=\"display: none;\"></span>" .
	        		tabSpace( 8) . "</ul>\r\n" .
	        		tabSpace( 6) . "</div>\r\n" .
	        		tabSpace( 4) . "</div>";
        }

        // Return the Administration Navigation Menu
        return $rVal;
    }

    /**
     *	Function Performs the Login Functions
     */
    private function _performLogin() {
    	$redirURL = "";
    	$rVal = false;

    	// Ensure We Have a Token
    	if ( $this->settings['token'] != "" ) {
			$data = $this->user->authAccount( $this->settings['email_addr'], $this->settings['PgRoot'], $this->settings['token'] );
			if ( $data['redir'] != "" ) { $redirURL = $this->settings['HomeURL'] . '/' . $data['redir']; }
			$rVal = YNBool( $data['isGood'] );
    	}

    	// If we have a Redirect URL, Use it
	    if ( $redirURL != "" ) {
		    redirectTo( $redirURL );
	    }

	    // Return the Boolean Response
	    return $rVal;
    }
    
    private function _getSelectedNotebookCount( $SiteID ) {
    	$CacheToken = "ENData_$SiteID";
	    $UseSandbox = NoNull(readSetting( $CacheToken, 'UseSandbox' ), 'Y');
	    $isProd = ( $UseSandbox == 'Y' ) ? '_sb' : '_prod';
	    $TokenFile = $CacheToken . "_notebooks$isProd";
	    $rVal = 0;

	    $data = readSetting( $TokenFile, '*');
	    foreach ( $data as $NotebookGUID ) {
		    if ( $NotebookGUID != "" ) { $rVal++; }
	    }

	    // Return the Number
	    return $rVal;
    }

    /**
     *	Function Writes any System Messages that Might Exist to the Dashboard
     */
    private function _getSystemMessages() {
    	$data = $this->_checkSystemMessages();
		$rVal = "";

		// Construct the Messages
		foreach( $data as $Key=>$Msg ) {
			foreach( $Msg as $Icon=>$Show ) {
				$rVal .= "<div class=\"sys-message $Icon\"><p>$Show</p></div>";				
			}
		}

		// Return the HTML-Formatted Information
		return $rVal;
    }

    /**
     *	Function Checks the State of the Installation and Returns the Appropriate Messages
     */
    private function _checkSystemMessages() {
	    $rVal = array();

	    // Check the Database Version (If Necessary)
	    if ( DB_TYPE == 1 ) {
		    $sqlStr = "SELECT `intVal` FROM `SysParm` WHERE `isDeleted` = 'N' and `Code` = 'DB_VERSION';";
		    $rslt = doSQLQuery( $sqlStr );
		    if ( is_array($rslt) ) {
			    if ( nullInt($rslt[0]['intVal']) < DB_VER ) {
			    	// Update the Database
			    	require_once( LIB_DIR . '/settings.php' );
			    	$conf = array( 'DispLang'	=> $this->settings['DispLang'],
			    				   'dataset'	=> 'updatedb',
			    				  );
			    	$sets = new Settings( $conf );
			    	$data = $sets->update();
			    	
			    	if ( $data['isGood'] ) {
				    	$rVal[] = array( 'sys-info' => "Successfully Updated Database to Version " . DB_VER );
			    	} else {
			    		foreach ( $data['error'] as $Error ) {
				    		$rVal[] = array( 'sys-warning' => $Error );
			    		}
			    	}

				    // Release the Class
				    unset( $sets );
			    }

		    } else {
			    $rVal[] = array( 'sys-error' => "Unable to Check Database Version!" );
		    }		    
	    }

		// If there is no data, add a single record	    
	    if ( count($rVal) == 0 ) {
		    $rVal[] = array('sys-success' => "Everything looks good! No errors to report.");
	    }

	    // Return the Array of Messages
	    return $rVal;
    }

}
?>