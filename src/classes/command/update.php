<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Command
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Controller to update the application metadata and repository with the most recent information
 * from GitHub.
 *
 * @package     Joomla.Tester
 * @subpackage  Command
 * @since       1.0
 */
class PTCommandUpdate extends PTCommand
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

		// Get the repository model.
		$model = new PTRepository($state);

		// Sync the local database with the GitHub metadata.
		$model->syncMilestones();
		$model->syncRequests($this->input->getBool('f', false));

		// Test the master repository.
		$model->testMaster();
	}
}
