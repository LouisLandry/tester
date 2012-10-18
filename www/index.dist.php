<?php
/**
 * Distribution Web entry file for the Joomla Pull Request Tester application.
 *
 * @package    Joomla.Tester
 *
 * @copyright  Copyright (C) 2012 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

// Get some paths from the environment or standard locations.
$tester = getenv('PT_HOME') ?: '/usr/local/joomla/tester';
$joomla = getenv('JOOMLA_HOME') ?: '/usr/local/joomla';

// Wrap the execution in a try statement to catch any exceptions thrown anywhere in the script.
try
{
	// Look for the Joomla Platform in the production installed location.
	if (file_exists($joomla . '/lib/joomla.phar'))
	{
		require_once $joomla . '/lib/joomla.phar';
	}
	// Development location.
	elseif (file_exists(dirname(__DIR__) . '/lib/joomla.phar'))
	{
		require_once dirname(__DIR__) . '/lib/joomla.phar';
	}
	// Panic.
	else
	{
		throw new RuntimeException('Unable to detect Joomla Platform path.', 500);
	}

	// Look for the application classes in the production installed location.
	if (file_exists($tester . '/tester/web.phar'))
	{
		require_once $tester . '/tester/web.phar';
	}
	// Development location.
	elseif (file_exists(dirname(realpath(__DIR__)) . '/src/run.php'))
	{
		JLoader::registerPrefix('PT', dirname(realpath(__DIR__)) . '/src/classes');
		define('JPATH_SITE', dirname(realpath(__DIR__)) . '/src');
	}
	// Panic.
	else
	{
		throw new RuntimeException('Unable to detect Joomla Pull Tester path.', 500);
	}

	// Define the application base path as the current directory.
	define('JPATH_BASE', __DIR__);

	// Instantiate the application.
	$application = JApplicationWeb::getInstance('PTApplicationWeb');
	$application->input = new JInputJSON();

	// Store the application.
	JFactory::$application = $application;

	// Execute the application.
	$application->loadLoggers()->loadDatabase()->execute();
}
catch (Exception $e)
{
	// Set the server response code.
	header('Status: 500', true, 500);

	// An exception has been caught, echo the message and exit.
	echo json_encode(array('message' => $e->getMessage(), 'code' => $e->getCode()));
	exit;
}
