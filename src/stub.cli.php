#!/usr/bin/env php
<?php
/**
 * @package    Joomla.Tester
 *
 * @copyright  Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

// Setup the Pharsanity!
Phar::interceptFileFuncs();

// Bootstrap the Joomla Platform.
require_once 'phar://' . __FILE__ . '/lib/import.php';

// Register the application classes with the loader.
JLoader::registerPrefix('PT', 'phar://' . __FILE__ . '/classes');

define('JPATH_BASE', __DIR__);

// Wrap the execution in a try statement to catch any exceptions thrown anywhere in the script.
try
{
	// Set error handler to echo.
	JLog::addLogger(array('logger' => 'echo'), JLog::ALL);

	// Instantiate the application.
	$application = JApplicationCli::getInstance('PTApplicationCli');

	// Store the application.
	JFactory::$application = $application;

	// Execute the application.
	$application->execute();
}
catch (Exception $e)
{
	// An exception has been caught, just echo the message.
	fwrite(STDERR, $e->getMessage() . "\n");
	exit($e->getCode());
}
__HALT_COMPILER();?>