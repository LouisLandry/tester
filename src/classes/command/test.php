<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Command
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Controller to test a pull request or all untested mergeable pull requests.
 *
 * @package     Joomla.Tester
 * @subpackage  Command
 * @since       1.0
 */
class PTCommandTest extends PTCommand
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
		$state = $this->fetchState();

		// Get the repository model.
		$repository = new PTRepository($state);

		// Get the pull requests to test.
		$pullRequests = array_slice($this->app->input->args, 1);
		if ($this->app->input->getBool('a', false) || empty($pullRequests))
		{
			$pullRequests = $repository->requests
								->filter('pending_tests', true)
								->filter('mergeable', true)
								->filter('state', false);
		}
		else
		{
			foreach ($pullRequests as $k => $v)
			{
				// Attempt to load the pull request based on GitHub ID.
				$request = new PTRequest($this->db);
				$request->load(array('github_id' => $v));

				$pullRequests[$k] = $request;
			}
		}

		// Enqueue the pull requests.
		foreach ($pullRequests as $request)
		{
			$repository->testRequest($request->github_id);
		}
	}
}
