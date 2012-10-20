<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Command
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Controller to reparse jenkins jobs.
 *
 * @package     Joomla.Tester
 * @subpackage  Command
 * @since       1.0
 */
class PTCommandReparse extends PTCommand
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
		// Create the model state object.
		$state = $this->fetchState();

		for ($i = 28; $i <= 64; $i++)
		{
			$test = new PTRequestTest(JFactory::$database);
			$test->processJenkinsBuild($i);
			$this->app->out(sprintf('Reparsed Jenkins build %d.', $i));
		}
	}
}
