<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Command
 *
 * @copyright   Copyright (C) 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Abstract Command Controller
 *
 * @package     Joomla.Tester
 * @subpackage  Command
 * @since       1.0
 */
abstract class PTCommand extends JControllerBase
{
	/**
	 * Method to execute the controller.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	abstract public function execute();

	/**
	 * Get a pre-populated model state object.
	 *
	 * @return  JRegistry
	 *
	 * @since   1.0
	 */
	protected function fetchState()
	{
		// Create the model state object.
		$state = new JRegistry;

		// Add the GitHub configuration values.
		$state->set('github.api', $this->app->get('github.api.url'));
		$state->set('github.username', $this->app->get('github.api.username'));
		$state->set('github.password', $this->app->get('github.api.password'));
		$state->set('github.host', $this->app->get('github.host'));
		$state->set('github.user', $this->app->get('github.user'));
		$state->set('github.repo', $this->app->get('github.repo'));

		$repoPath = $this->app->get('repo_path', sys_get_temp_dir());

		// Build the repository path.
		$state->set('repo', $repoPath . '/' . $this->app->get('github.user') . '/' . $this->app->get('github.repo'));

		// Add the PHPCS testing configuration values.
		$state->set('phpcs.standard', $this->app->get('phpcs.standard'));
		$state->set('phpcs.paths', $this->app->get('phpcs.paths'));

		// Add the Jenkins configuration values.
		$state->set('jenkins.url', $this->app->get('jenkins.url'));
		$state->set('jenkins.job', $this->app->get('jenkins.job'));
		$state->set('jenkins.token', $this->app->get('jenkins.token'));

		return $state;
	}
}
