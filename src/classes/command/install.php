<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Controller
 *
 * @copyright   Copyright (C) 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Joomla Pull Request Tester Install Controller
 *
 * @package     Joomla.Tester
 * @subpackage  Controller
 * @since       1.0
 */
class PTCommandInstall extends JControllerBase
{
	/**
	 * Method to execute the controller.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function execute()
	{
		// Get the application database object.
		$db = JFactory::getDBO();

		// Get the installation database schema split into individual queries.
		switch ($db->name)
		{
			case 'sqlite':
				$queries = JDatabaseDriver::splitSql(trim(file_get_contents(JPATH_CONFIGURATION . '/db/schema/sqlite/pulltester.sql')));
				break;

			case 'mysql':
			case 'mysqli':
				$queries = JDatabaseDriver::splitSql(trim(file_get_contents(JPATH_CONFIGURATION . '/db/schema/mysql/pulltester.sql')));
				break;

			default:
				throw new RuntimeException(sprintf('Database engine %s is not supported.', $db->name));
				break;
		}

		$this->app->out(sprintf('. Installing database tables using the configured %s database.', $db->name));

		// Execute the installation schema queries.
		foreach ($queries as $query)
		{
			$db->setQuery(trim($query))->execute();
		}

		$this->app->out(sprintf('. Installation successful.', $db->name));
	}
}
