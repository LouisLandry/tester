<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Controller
 *
 * @copyright   Copyright (C) 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Controller to update the application metadata and repository with the most recent information
 * from GitHub.
 *
 * @package     Joomla.Tester
 * @subpackage  Controller
 * @since       1.0
 */
class PTCommandUpdate extends JControllerBase
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
		$state = new JRegistry;

		// Add the GitHub configuration values.
		$state->set('github.api', $this->app->get('github.api'));
		$state->set('github.host', $this->app->get('github.host'));
		$state->set('github.user', $this->app->get('github.user'));
		$state->set('github.repo', $this->app->get('github.repo'));

		$repoPath = $this->app->get('repo_path', sys_get_temp_dir());

		// Build the repository path.
		$state->set('repo', $repoPath . '/' . $this->app->get('github.user') . '/' . $this->app->get('github.repo'));

		// Add the PHPCS testing configuration values.
		$state->set('phpcs.standard', $this->app->get('phpcs.standard'));
		$state->set('phpcs.paths', $this->app->get('phpcs.paths'));

		// Get the repository model.
		$model = new PTRepository($state);

		// Sync the local database with the GitHub metadata.
		$model->syncMilestones();
		$model->syncRequests($this->input->getBool('f', false));

		// Test the master repository.
		$model->testMaster();
	}
}
