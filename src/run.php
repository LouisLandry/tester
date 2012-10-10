#!/usr/bin/env php
<?php
/**
 * @package    Joomla.Tester
 *
 * @copyright  Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

define('JPATH_BASE', dirname(__DIR__));

// Bootstrap the Joomla Platform.
require_once JPATH_BASE . '/lib/joomla.phar';

// Register the application classes with the loader.
JLoader::registerPrefix('PT', __DIR__ . '/classes');


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
	$application->loadDatabase()->execute();
}
catch (Exception $e)
{
	// An exception has been caught, just echo the message.
	fwrite(STDERR, $e->getMessage() . "\n");
	exit($e->getCode());
}
