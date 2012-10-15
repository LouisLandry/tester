<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Command
 *
 * @copyright   Copyright (C) 2011 Open Source Matters, Inc. All rights reserved.
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

		// Set the filtering values.
		$state->set('list.filter.pending_tests', 1);
		$state->set('list.filter.mergeable', 1);
		$state->set('list.filter.state', 0);

		// Get the repository model.
		$repository = new PTRepository($state);

		// Get the pull requests to test.
		$pullRequests = array_slice($this->app->input->args, 1);
		if ($this->app->input->getBool('a', false) || empty($pullRequests))
		{
			$pullRequests = $repository->getRequests();
		}
		else
		{
			foreach ($pullRequests as $k => $v)
			{
				$pullRequests[$k] = $repository->getRequest((int) $v);
			}
		}

		// Enqueue the pull requests.
		foreach ($pullRequests as $request)
		{
			$repository->testRequest($request->github_id);
		}
	}
}
