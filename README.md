# µRL Shortener

µRL Shortener is a lightweight URL shortener (one file only, the database is a simple file) with really simple statistics support.

Links generated are 6-chars links (chars allowed are [a-z A-Z 0-9], except if personalized). URLs are callable as follow: http://your.shortener/?linkID (or http://your.shortener/linkID if URL rewriting is enabled).

Please note: *this is a work in progress*.

License: [CeCILL v2](http://www.cecill.info/licences/Licence_CeCILL_V2-fr.htm).

## Features

- Lightweight (one file of 18 KiB, and the database is compressed and weighs about 6 KiB per hundred links).
- Easy to install (place one file in a directory, allow the script to write inside, that's all).
- Fast (you will never see http://your.shortener/link when redirecting — except if you have a slow connexion of course).
- Easy to use (two fields to fill to submit a link, one if you don't want a specific small link).
- With light stats (number of clics).
- With memory (if you use the same IP address, you can see the links you posted).
- Compatible with PHP 5.2+.
- Work in any browser (IE 7 included! and probably IE 6, too).
- API included (if you want to create short links automatically, to publish blog posts on Twitter, as example). Doc in the main page of your shortener, [or here](http://l.carrade.eu).


## Installation

Put the `index.php` file in a directory on your webserver. That's all.

If you want to enable URL rewriting, use the `.htaccess` file provided, or equivalent rewrite rules for another web server than Apache.

## Update

The only thing you need to save before updating is the salt (or simply the config section at the top of the file).
So, to update:

1. copy the config section somewhere;
2. replace the `index.php` file with the new one;
3. replace the default config with the saved config.


## Configuration

Open the `index.php` file and follow the instructions written at the top of the file.

```php
<?php
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
```
