<?php
/**
 * @package    Joomla.Tester
 *
 * @copyright  Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

/*
 * Ensure that required path constants are defined.  These can be overriden within the phpunit.xml file
 * if you chose to create a custom version of that file.
 */
if (!defined('JPATH_TESTS'))
{
	define('JPATH_TESTS', realpath(__DIR__));
}
if (!defined('JPATH_BASE'))
{
	define('JPATH_BASE', realpath(__DIR__));
}

// Get some paths from the environment or standard locations.
$tester = getenv('PT_HOME') ?: '/usr/local/joomla/tester';
$joomla = getenv('JOOMLA_HOME') ?: '/usr/local/joomla';

// Wrap the execution in a try statement to catch any exceptions thrown anywhere in the script.
try
{
	// Look for the Joomla Platform and testing classes in the production installed location.
	if (file_exists($joomla . '/lib/joomla-test.phar'))
	{
		require_once $joomla . '/lib/joomla-test.phar';
	}
	// Development location.
	elseif (file_exists(dirname(__DIR__) . '/lib/joomla-test.phar'))
	{
		require_once dirname(__DIR__) . '/lib/joomla-test.phar';
	}
	// Panic.
	else
	{
		throw new RuntimeException('Unable to detect Joomla Platform path.', 500);
	}
}
catch (Exception $e)
{
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}

// Register the application classes with the loader.
JLoader::registerPrefix('PT', __DIR__ . '/../src/classes');
