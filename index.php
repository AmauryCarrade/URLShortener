<?php
	/**
	 * µRL shortener
	 * @author Amaury Carrade
	 * @version 0.1 bêta
	 *
	 * @license http://www.opensource.org/licenses/zlib-license.php
	 *
	 * µRL Shortener is a lightweight URL shortener (one file only, the database is a simple file)
	 * with really simple statistics support.
	 *
	 * Links generated are 6-chars links (chars allowed are [a-z A-Z 0-9@_-]) (except if personalized).
	 *
	 * URLs are callable as follow: http://your.shortener/?linkID (or http://your.shortener/linkID
	 * if URL rewriting is enabled).
	 */

	## Configuration

	$config = array();

	// The title displayed in the home page of the shortener.
	$config['title'] = 'µRL Shortener';


	// IMPORTANT: Set to true if URL rewriting is enabled.
	// This can be changed at any moment without problems.
	// Unrewrited links always works; but obviously, rewrited links works only with URL rewriting
	// enabled in your webserver configuration, regardless to this configuration point.
	$config['rewriteEnabled'] = false;
	

	// True if the number of links must be displayed.
	$config['countLinks'] = true;
	

	// True if a list of links must be available.
	$config['listLinks'] = true;
	

	// True if some light statistics must be displayed (only number of access).
	// Statistics are displayed in the list of the links (if any), and at the URL
	// http://linkToShortener.com/<linkId>+ .
	$config['stats'] = true;
	
	// True if an user can delete his own links.
	// The deletion is allowed only if the user has created the link and if he is the only one who had created it.
	$config['allowDeletion'] = true;
	

	// The file where data is stored.
	// This file must be readable and writable.
	$config['dataFile'] = 'data/data.php';


	// Put here a random value. Example: ask your cat to walk on the keyboard.
	define('SALT', 'Change me!');
	// Do not change this salt after the first use, because change it will remove all associations
	// between links and authors.

	## -----------End of configuration--------------------------------------------------------------

	## Don't change anything below, except if you understand what you do!
	
	
	# Constants for page identification
	
	define('PAGE_HOME', 'home');
	define('PAGE_LIST', 'list');
	define('PAGE_STATS', 'stats');
	define('PAGE_DELETE', 'delete');
	
	# Constants for messages types
	
	define('MESSAGE_SUCCESS', 'success');
	define('MESSAGE_FAIL', 'fail');


	/**
	 * About the links storage format
	 *
	 * The links are stored in an array serialized, compressed and encoded in Base64.
	 *
	 * The format of the array is the following:
	 * 	linkId => Array(
	 * 		'url'	=> 'The real link (the script will redirect the user here)',
	 *		'views'	=>  The number of views,
	 *		'ip'	=> array('A hashed+salted version of the IP address of the user(s) who submitted the link.')
	 *	)
	 */



	ini_set('session.use_cookies', 1);       // Use cookies to store session.
	ini_set('session.use_only_cookies', 1);  // Force cookies for session (phpsessionID forbidden in URL)
	ini_set('session.use_trans_sid', false); // Prevent php to use sessionID in URL if cookies are disabled.
	session_name('lus');
	if (session_id() == '') session_start(); // Start session if needed (some server auto-start sessions).

	// Init (should happend only on the first launch)
	if(!is_dir(dirname($config['dataFile']))) {
		if(!mkdir(dirname($config['dataFile']), 777)) {
			die('<strong>Fatal error</strong>: can\'t create the data directory "' . dirname($config['dataFile']) . '".');
		}
	}

	// Check if data directory is readable and writable
	if(!(is_dir(dirname($config['dataFile'])) && is_readable($config['dataFile']) && is_writable($config['dataFile']))) {
		die('<strong>Fatal error</strong>: the data file located at "' . $config['dataFile'] . '" is not readable and writable.');
	}


	// From Shaarli (sebsauvage.net)
	// In case stupid admin has left magic_quotes enabled in php.ini:
	if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
	{
		function stripslashes_deep($value) {
			$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
			return $value;
		}
		$_POST = array_map('stripslashes_deep', $_POST);
		$_GET = array_map('stripslashes_deep', $_GET);
		$_COOKIE = array_map('stripslashes_deep', $_COOKIE);
	}


	// Returns the saved data (here, an array).
	// In the file, data is serialized,compressed and encoded in base64.
	function loadData() {
		global $config;
		
		$dataFile = $config['dataFile'];
		if(!is_file($dataFile)) {
			return array();
		}
		else {
			return unserialize(gzinflate(base64_decode(include($dataFile))));
		}
	}

	// Saves data in file.
	// Return success.
	function saveData($data) {
		global $config;

		$dataFile = $config['dataFile'];
		$rawData = '<?php return \'' . base64_encode(gzdeflate(serialize($data))) . '\';';

		return file_put_contents($dataFile, $rawData);
	}


	// From Shaarli (sebsauvage.net)
	// Only one change: we want only the following characters: a-z A-Z 0-9.
	/* Returns the small hash of a string
	   eg. generateHash('20111006_131924') --> yZH23w
	   Small hashes:
		- are unique (well, as unique as crc32, at last)
		- are always 6 characters long.
		- only use the following characters: a-z A-Z 0-9
		- are NOT cryptographically secure (they CAN be forged)
		In Shaarli, they are used as a tinyurl-like link to individual entries.
		Here, for the generated short URLs.
	*/
	function generateHash($text) {
		$letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$t = rtrim(base64_encode(hash('crc32',$text,true)),'=');
		$t = str_replace('+', $letters[mt_rand(0, 61)], $t); // Get rid of characters which need encoding in URLs.
		$t = str_replace('/', $letters[mt_rand(0, 61)], $t);
		$t = str_replace('=', $letters[mt_rand(0, 61)], $t);
		return $t;
	}

	function hashIP($ip) {
		return sha1(hash('sha256', SALT . $ip . SALT) . SALT);
	}


	// Returns true if $link is not already used and is allowed.
	function isLinkFree($links, $link) {
		$forbidden = array('url', 'do');
		if(in_array($link, $forbidden)) {
			return false;
		}
		return !array_key_exists($link, $links);
	}

	// Generates a redirection URL from the key
	function generateURL($key) {
		global $config;
		$url  = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
		$url .= str_replace('?', NULL, $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
		$url .= !$config['rewriteEnabled'] ? '?' : NULL;
		$url .= $key;
		
		// We need to remove the query string, if any.
		if($config['rewriteEnabled']) {
			$url = str_replace($_SERVER['QUERY_STRING'], NULL, $url);
		}
		else {
			$url = str_replace('?' . $_SERVER['QUERY_STRING'], NULL, $url);
		}

		return $url;
	}


	/**
	 * Search an URL into the links already saved.
	 *
	 * @param $currentLinks array 	The array containing the current links (see above).
	 * @param $url 			string 	The URL of the long link.
	 *
	 * @return string The hash if the URL exists, false else.
	 */
	function searchUrlInLinks($currentLinks, $url) {
		foreach($currentLinks AS $shortLink => $link) {
			if($link['url'] == $url) {
				return $shortLink;
			}
		}
		return false;
	}

	/**
	 * Get all links, or all links from $ip if $ip is not null.
	 * @param $links array  The list of all links (see the format above).
	 * @param $ip    string The IP.
	 * @return array A list of links (see the format above).
	 */
	function getLinks($links, $ip = NULL) {
		if($ip == NULL) {
			return $links;
		}

		$filteredLinks = array();
		$ip = hashIP($ip);
		foreach($links as $id => $link) {
			if(in_array($ip, $link['ip'])) {
				$filteredLinks[$id] = $link;
			}
		}
		return $filteredLinks;
	}



	/**
	 * Creates a new short link.
	 * @param $currentLinks array 	The array containing the current links (see above).
	 * @param $url 			string 	The URL of the long link.
	 * @param $link 		string 	The preferred short URL. If this short URL is not available, an other short
	 * 								URL will be generated. Same if this arg is set to NULL.
	 *
	 * @return string The key of the new URL, or false if an error happened (ex: if the URL is not valid).
	 */
	function addLink($currentLinks, $url, $link = NULL) {
		if($url != NULL) { // Very permissive control on URLs
			// Protection against unwanted additionnals headers
			$url = str_replace("\n", NULL, $url);
			$url = str_replace("\r", NULL, $url);

			$url  = rawurldecode($url) ; // Avoid the remplacement of "+" by a space. "+" is needed by some URLs (like ZeroBin URLs).
			$link = urldecode($link);


			// The anchor must be URL-encoded


			// What link?
			$shortURL = NULL;
			if($link != NULL) {
				/*
				 * Summary:
				 * If a link is requested:
				 *  - it is free? Take it.
				 *  - it isn't? If a link to this URL already exists, we take this one; else we generate a random one.
				 * If no link is requested, a random link is generated, except if a link already exists.
				 */
				 
				// Cleaning link, because a "#" is interpreted as an anchor by the browser, and the URL can't be loaded if this happens.
				$link = str_replace('#', NULL, $link);

				if(!isLinkFree($currentLinks, $link)) { // If the requested link is taken, we generate an other link.
					$search = searchUrlInLinks($currentLinks, $url);
					if($search !== false) {
						$shortURL = $search;
					}
					else {
						do {
							$shortURL = generateHash(mt_rand());
						} while(!isLinkFree($currentLinks, $shortURL));
					}
				}
				else {
					$shortURL = $link; // The link requested is free, all is OK.
				}
			}
			else {
				$search = searchUrlInLinks($currentLinks, $url);
				if($search !== false) {
					$shortURL = $search;
				}
				else {
					do {
						$shortURL = generateHash(mt_rand());
					} while(!isLinkFree($currentLinks, $shortURL));
				}
			}

			// Saving
			// Nota: a link may have multiple authors.
			if(!isset($currentLinks[$shortURL])) {
				$currentLinks[$shortURL] = array(
					'url' => $url,
					'views' => 0,
					'ip' => array(hashIP($_SERVER['REMOTE_ADDR']))
				);
			}
			else {
				if(!in_array(hashIP($_SERVER['REMOTE_ADDR']), $currentLinks[$shortURL]['ip'])) {
					$currentLinks[$shortURL]['ip'][] = hashIP($_SERVER['REMOTE_ADDR']);
				}
			}
			saveData($currentLinks);

			return $shortURL;
		}
		else {
			return false;
		}
	}



	$data = loadData();


	## Action
	
	/* * * * * *   API   * * * * * */

	// We want to add a new URL through the 'API'.
	// call http://shortener/?url=<URLHere> (generate a random link), or
	//      http://shortener/?url=<URLHere>&link=<linkHere> (link is "<linkHere>", 
	//              except if "<linkHere>" is already taken, then a random link is 
	//              generated).
	//
	// Displays the complete short URL in plain text as a response.
	if(isset($_GET['url'])) {
		header('Content-type: text/plain; charset=UTF-8');
		$url = $_GET['url'];
		$link = isset($_GET['link']) ? $_GET['link'] : NULL;

		$shortURL = addLink($data, $url, $link);
		if($shortURL === false) {
			die('Fatal error: URL is not valid (probably).');
		}
		else {
			echo generateURL($shortURL);
		}
	}
	
	/* * * * * *   User   * * * * * */
	
	else {
	
		// Routing
		
		$query = trim($_SERVER['QUERY_STRING']);
		
		$page = PAGE_HOME;

		if(isset($_GET['do'])) {
			if($_GET['do'] == 'links') {
				$page = PAGE_LIST;
			}
			else if($_GET['do'] == 'delete') {
				$page = PAGE_DELETE;
			}
		}

		else if(!empty($query)) {
			// Lookup for link
			if(!isLinkFree($data, $query)) { // The link exists.
				header($_SERVER['SERVER_PROTOCOL'] . ' 301 Moved Permanently');
				header('Location: ' . $data[$query]['url']);
				$data[$query]['views']++;
				saveData($data);
				echo '<!DOCTYPE html><html><head><title>' . $config['title'] . ' – 301 Moved Permanently</title></head>';
				echo '<body><p>Redirecting you to <a href="' . $data[$query]['url'] . '">' . $data[$query]['url'] . '</a>...</p></body></html>';
				exit;
			}
			else {
				// Maybe stats?
				$pureLink = str_replace('+', '', $query);
				if(!isLinkFree($data, $pureLink) && $config['stats']) {
					$page = PAGE_STATS;
				}
				else {
					header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
					echo '<!DOCTYPE html><html><head><title>' . $config['title'] . ' – 404 Not Found</title></head>';
					echo '<body><h1>Link not found</h1><p>Sorry, the link could not be found :-( .</p></body></html>';
					exit;
				}
			}
		}
		
		if($page != PAGE_STATS && 
		   ($page != PAGE_LIST || $page == PAGE_LIST && !$config['listLinks']) && 
		   ($page != PAGE_DELETE || $page == PAGE_DELETE && !$config['allowDeletion']) ) {
		   
		   // If the user attemps to access an unautorized page, we simply show the home page.
		   
		   $page = PAGE_HOME;
		   
		   }

		// From this point, an UI is required.
		
		$message = NULL;
		$messageType = NULL;
		
		$statsShortURL = str_replace('+', '', $query);

		// Form protection
		if(isset($_SESSION['form_protection'])) {
			if($_POST['form_protection'] != $_SESSION['form_protection']) {
				$_POST = array();
			}
		}
		$_SESSION['form_protection'] = hash('sha256', mt_rand());

		$shortURL = NULL;
		if(isset($_POST['url'])) {
			$url = $_POST['url'];
			$link = isset($_POST['link']) ? $_POST['link'] : NULL;

			$shortURL = addLink($data, $url, $link);
			if($shortURL === false) {
				$message = 'An error occurred. The link is probably invalid.';
				$messageType = MESSAGE_FAIL;
			}
			else {
				$message  = 'Your link has been saved. The link is: <br />';
				$message .= '<a href="' . generateURL($shortURL) . '+" class="notifLink">' . generateURL($shortURL) . '</a>';
				$messageType = MESSAGE_SUCCESS;
				
				$_POST['url'] = NULL; $_POST['link'] = NULL;
			}
		}
		
		
		if($page == PAGE_DELETE) {
			
			$link = $_GET['link'];
			
			// First: is the user allowed to delete the link?
			
			if(isLinkFree($data, $link)) {
				// Probably an error. The link does not exists.
				// We redirect the user to the home page.
				
				$message = 'This link does not exists.';
				$messageType = MESSAGE_FAIL;
				
				$page = PAGE_LIST;
				
			}
			
			else {
				
				$linkData = $data[$link];
				
				if(!in_array(hashIP($_SERVER['REMOTE_ADDR']), $linkData['ip'])) {
					$message = 'You\'re not allowed to delete this link: you\'re not the creator!';
					$messageType = MESSAGE_FAIL;
					
					$page = PAGE_LIST;
				}
				else {
					
					if(isset($_GET['ok'])) {
						// We first remove the IP of the user
						unset($linkData['ip'][ array_search(hashIP($_SERVER['REMOTE_ADDR']), $linkData['ip']) ]);
						
						if($linkData['ip'] == array()) {
							// The user was the only author of the link.
							// The link can be safely deleted.
							
							unset($data[$link]);
							
							$message = 'The link “' . $link . '” was successfully deleted.';
							$messageType = MESSAGE_SUCCESS;
							
							$page = PAGE_LIST;
						}
						
						else {
							// The user was not the only creator of the link.
							
							$data[$link] = $linkData;
							
							$message = 'The link “' . $link . '” was not removed; it has only been disassociated from your IP as it has also been saved by others.';
							$messageType = MESSAGE_SUCCESS;
							
							$page = PAGE_LIST;
						}
						
						saveData($data);
					}
				}
			}
		}
		

		$filteredLinks = NULL;
		$ip = NULL;
		if($page == PAGE_LIST) {
			$ip = isset($_GET['all']) ? NULL : $_SERVER['REMOTE_ADDR'];
			$filteredLinks = getLinks($data, $ip);

			// If there is no personal links, we shows all the links.
			if($ip != NULL && $filteredLinks == array()) {
				header($_SERVER['SERVER_PROTOCOL'] . ' 301 Moved Permanently');
				header('Location: ?do=links&all');
				exit;
			}
		}

		header('Content-type: text/html; charset=UTF-8');
		?>

		<!DOCTYPE html>
		<html>
			<head>
				<title><?php echo $config['title']; ?></title>
				<style>
					body {
						margin: 0;
						padding: 0;
						padding-bottom: 42px;
						font-family: UbuntuLight, Verdana, Arial, sans-serif;
						text-align: center;
					}
					header {
						background-color: lightgrey;
						border-bottom: solid 1px grey;
						margin: 0;
						padding: 10px;
						padding-bottom: 4px;
					}
					header p {
						font-size: 0.9em;
						margin: 0;
					}
					h4 {
						margin-top: 80px;
					}
					form {
						width: 60%;
						margin-left: auto;
						margin-right: auto;
						margin-top: 30px;
						margin-bottom: 42px;
					}
					input[type='text'], input[type='url'], input[type='submit'] {
						width: 100%;
						font-size: 1.2em;
						margin-bottom: 3px;
						text-align: center;
					}
					input[type='submit'] {
						width: 60%;
						font-size: 1em;
					}

					.linkAssoc {
						margin-top: 30px;
						font-size: 1.1em;
						margin-bottom: 50px;
					}

					a {
						text-decoration: none !important;
						color: #666;
					}
					h1 a {
						color: #000 !important;
					}
					.notifLink {
						font-size: 1.2em;
					}
					.small {
						font-size: small;
					}
					.deleteLink {
						font-size: small;
						float: right;
						margin-top: 2px;
					}
					
					.linkPreview {
						border: solid black 1px;
						border-radius: 5px;
						
						width: 50%;
						margin-right: auto;
						margin-left: auto;
						
						font-size: small;
					}

					/* Thanks to Bootstrap. */
					.alert {
						padding: 8px 35px 8px 14px;
						margin-bottom: 18px;
						text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
						background-color: #fcf8e3;
						border: 1px solid #fbeed5;
						-webkit-border-bottom-left-radius: 4px;
						-moz-border-bottom-left-radius: 4px;
						border-bottom-left-radius: 4px;
						-webkit-border-bottom-right-radius: 4px;
						-moz-border-bottom-right-radius: 4px;
						border-bottom-right-radius: 4px;
						color: #c09853;
					}
					.alert-success {
						background-color: #dff0d8;
						border-color: #d6e9c6;
						color: #468847;
					}
					.alert-error {
						background-color: #f2dede;
						border-color: #eed3d7;
						color: #b94a48;
					}

					code {
						color: #888;
					}
					.arg {
						color: #111 !important;
					}

					p.nav {
						margin-top: -25px;
					}

					table {
						width: 80%;
						margin: auto;
						text-align: left !important;
					}
					table td {
						border-top: 1px solid #ccc;
					}
					table td.table-cell-link {
						width: 80%;
					}
				</style>
			</head>
			<body>
				<header>
					<h1><a href="./"><?php echo $config['title']; ?></a></h1>
					<?php if($config['countLinks']): ?>
						<p>
							<?php if($config['listLinks']): ?><a href="./?do=links"><?php endif; ?>
							<?php echo count($data); ?> links
							<?php if($config['listLinks']): ?></a><?php endif; ?>
						</p>
					<?php elseif($config['listLinks']): ?>
						<p>
							<a href="./?do=links">All links</a>
						</p>
					<?php endif; ?>
				</header>
				
				
				<?php /* Message */ ?>
				
				<?php if($message != NULL): ?>
					<?php if($messageType == MESSAGE_SUCCESS): ?>
						<div class="alert alert-success">
							<?php echo $message; ?>
						</div>
					<?php else: ?>
						<div class="alert alert-error">
							<?php echo $message; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
				
				
				<?php if($page == PAGE_HOME): ?>
					<form method="post">
						<h3>Want to generate a link?</h3>
						<input type="hidden" name="form_protection" value="<?php echo $_SESSION['form_protection']; ?>" />
						<input type="url" name="url" placeholder="Enter the long URL here..." value="<?php isset($_POST['url']) ? $_POST['url'] : NULL; ?>" required /><br />
						<input type="text" name="link" placeholder="Enter a prefered short URL here (optionnal)" value="<?php isset($_POST['link']) ? $_POST['link'] : NULL; ?>" /><br />
						<input type="submit" value="Generate the link" />
					</form>

					<h3>Do you want some stats?</h3>
					<p>
						Just add a “+” after the short URL.<br />
						<small>Example: <?php echo generateURL('abcdef'); ?> → <?php echo generateURL('abcdef'); ?>+</small>
					</p>

					<h4>Maybe an API?</h4>
					<p>
						<small>
							To generate a link, call <code><?php echo str_replace('?', NULL, generateURL(NULL)); ?>?url=<span class="arg">&lt;yourURL&gt;</span></code>.<br />
							With a preferred short link, use <code><?php echo str_replace('?', NULL, generateURL(NULL)); ?>?url=<span class="arg">&lt;yourURL&gt;</span>&amp;link=<span class="arg">&lt;thePreferredShortURL&gt;</code>.<br />
							These pages display the complete short URL in plain text as a response.
						</small>
					</p>
				
				
				<?php elseif($page == PAGE_STATS): ?>
				
				
					<p class="linkAssoc">
						<?php echo generateURL($statsShortURL); ?><br />
						↓<br />
						<a href="<?php echo generateURL($statsShortURL); ?>"><?php echo $data[$statsShortURL]['url']; ?></a>
					</p>
					<p>
						<?php echo number_format($data[$statsShortURL]['views'], 0, ',', '&#8239;'); ?> clic<?php if($data[$statsShortURL]['views'] != 1) { echo 's'; } ?> on this link.
					</p>
				
				
				<?php elseif($page == PAGE_LIST): ?>
				
				
					<h2><?php if($ip != NULL): ?>Your links<?php else: ?>All links<?php endif; ?></h2>
					<p class="nav">
						<a href="?do=links">Your links</a> – <a href="?do=links&amp;all">All links</a>
					</p>

					<table>
						<thead>
							<tr>
								<th class="table-cell-link">Link</th>
								<th class="table-cell-views">Views</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($filteredLinks as $linkID => $link): ?>
							<tr>
								<td class="table-cell-link">
									<a href="<?php echo generateURL($linkID); ?>"><?php echo generateURL('<span class="arg">' . $linkID . '</span>'); ?></a><br />
									&nbsp;&nbsp;&nbsp;→ <span class="small"><a href="<?php echo generateURL($linkID); ?>"><?php echo $link['url']; ?></a></span>
								</td>
								<td class="table-cell-views">
									<a href="<?php echo generateURL($linkID); ?>+"><span class="arg"><?php echo number_format($link['views'], 0, ',', '&#8239;'); ?></span>&nbsp;clic<?php if($link['views'] != 1) { echo 's'; } ?></a>
									<?php if($ip != NULL && $config['allowDeletion']): // The delete link is only shown to the author(s) ?>
										<div class="deleteLink"><a href="?do=delete&amp;link=<?php echo $linkID; ?>">× Delete</a></div>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
						
				<?php elseif($page == PAGE_DELETE): ?>
				
				
					<h2>You are about to delete a link.</h2>
					
					<p>
						Do you really want to delete this link?
					</p>
					<div class="linkPreview">
						<p class="linkAssoc">
							<?php echo generateURL($link); ?><br />
							↓<br />
							<a href="<?php echo generateURL($link); ?>"><?php echo $data[$link]['url']; ?></a>
						</p>
					</div>
					<p class="linksConfirm">
						<a href="?do=delete&amp;link=<?php echo $_GET['link']; ?>&amp;ok">Yes, I am</a>&nbsp;&nbsp;&nbsp;—&nbsp;&nbsp;&nbsp;<a href="?do=links">No, I changed my mind!</a>
					</p>
				
				
				<?php else: // Should never happens ?>
				
					<p>
						What? A page, here? Probably an error.
					</p>
				
				<?php endif; ?>
			</body>
		</html>
		<?php
	}