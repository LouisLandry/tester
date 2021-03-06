<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Application
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Joomla Pull Request Tester Web Application Class
 *
 * @package     Joomla.Tester
 * @subpackage  Application
 * @since       1.0
 */
class PTApplicationWeb extends JApplicationWeb
{
	/**
	 * @var    JDatabaseDriver  A database object for the application to use.
	 * @since  1.0
	 */
	protected $db;

	/**
	 * Method to create a database driver for the application.
	 *
	 * @return  PTApplicationWeb  This object for method chaining.
	 *
	 * @since   1.0
	 */
	public function loadDatabase()
	{
		// Handle the db_name separately due to a default etc path option.
		$dbName = $this->get('db_name');
		if (strpos($dbName, '@etc') === 0)
		{
			$dbName = JPATH_CONFIGURATION . substr($dbName, 4);
		}

		$this->db = JDatabaseDriver::getInstance(
			array(
				'driver' => $this->get('db_driver'),
				'host' => $this->get('db_host'),
				'user' => $this->get('db_user'),
				'password' => $this->get('db_pass'),
				'database' => $dbName,
				'prefix' => $this->get('db_prefix')
			)
		);

		// Select the database.
		$this->db->select($dbName);

		// Set the debug flag.
		$this->db->setDebug($this->get('debug'));

		// Set the database to our static cache.
		JFactory::$database = $this->db;

		return $this;
	}

	/**
	 * Method to create loggers for the application.
	 *
	 * @return  PTApplicationWeb  This object for method chaining.
	 *
	 * @since   1.0
	 */
	public function loadLoggers()
	{
		// Get the loggers from the configuration.
		$loggers = $this->get('loggers');
		foreach ($loggers as $logger)
		{
			JLog::addLogger(
				(array) $logger->options,
				constant(sprintf('%s::%s', 'JLog', $logger->priorities)),
				$logger->categories,
				$logger->exclude
			);
		}

		return $this;
	}

	/**
	 * Execute the application.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function doExecute()
	{
		// Get the route maps from configuration file.
		$maps = json_decode(file_get_contents(JPATH_CONFIGURATION . '/routes.json'), true);

		$router = new JApplicationWebRouterRest($this);
		$router->setControllerPrefix('PTService')
			->setDefaultController('date')
			->addMaps($maps)
			->execute($this->get('uri.route'));
	}

	/**
	 * Method to get the application configuration data to be loaded.
	 *
	 * @return  object  An object to be loaded into the application configuration.
	 *
	 * @since   1.0
	 */
	protected function fetchConfigurationData()
	{
		// Instantiate variables.
		$config = array();

		// Find the configuration file path.
		if (!defined('JPATH_CONFIGURATION'))
		{
			// First look at the environment variable.
			$path = getenv('PULLTESTER_CONFIG');
			if ($path)
			{
				define('JPATH_CONFIGURATION', realpath($path));
			}
			// Next look in a standard path.
			elseif (is_dir('/usr/local/joomla/etc/tester'))
			{
				define('JPATH_CONFIGURATION', '/usr/local/joomla/etc/tester');
			}
			// Fall back to the development path.
			elseif (is_file(dirname(dirname(dirname(__DIR__))) . '/etc/config.dist.json'))
			{
				define('JPATH_CONFIGURATION', dirname(dirname(dirname(__DIR__))) . '/etc');
			}
			// Panic.
			else
			{
				throw new RuntimeException('Unable to detect the configuration file path.');
			}
		}

		// Set the configuration file path for the application.
		if (file_exists(JPATH_CONFIGURATION . '/config.json'))
		{
			$file = JPATH_CONFIGURATION . '/config.json';
		}
		else
		{
			$file = JPATH_CONFIGURATION . '/config.dist.json';
		}

		// Verify the configuration exists and is readable.
		if (!is_readable($file))
		{
			throw new RuntimeException('Configuration file does not exist or is unreadable.');
		}

		// Load the configuration file into an object.
		$config = json_decode(file_get_contents($file));
		if ($config === null)
		{
			throw new RuntimeException(sprintf('Unable to parse the configuration file %s.', $file));
		}

		return $config;
	}
}
