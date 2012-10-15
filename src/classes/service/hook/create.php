<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Service
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * GitHub Hook End Point.
 *
 * ## Response
 *
 * ### 200 OK
 *
 * #### Payload
 *
 * ```json
 * {}
 * ```
 *
 * @package     Joomla.Tester
 * @subpackage  Service
 * @since       1.0
 */
class PTServiceHookCreate extends JControllerBase
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
		$state->set('github.api', $this->app->get('github.api.url'));
		$state->set('github.username', $this->app->get('github.api.username'));
		$state->set('github.password', $this->app->get('github.api.password'));
		$state->set('github.host', $this->app->get('github.host'));
		$state->set('github.user', $this->app->get('github.user'));
		$state->set('github.repo', $this->app->get('github.repo'));

		// Build the repository path.
		$repoPath = $this->app->get('repo_path', sys_get_temp_dir());
		$state->set('repo', $repoPath . '/' . $this->app->get('github.user') . '/' . $this->app->get('github.repo'));

		// Get the repository model.
		$repository = new PTRepository($state);

		// Grab data from the hook.
		$data = $this->input->getArray(
			array(
				'number' => 'int',
				'comment' => 'array',
				'repository' => 'array',
				'sender' => 'array',
				'action' => 'string',
				'issue' => 'array',
				'pusher' => 'array',
				'forced' => 'raw',
				'head_commit' => 'array',
				'after' => 'string',
				'deleted' => 'raw',
				'ref' => 'string',
				'commits' => 'array',
				'base_ref' => 'string',
				'before' => 'string',
				'compare' => 'string',
				'created' => 'raw',
				'pull_request' => 'array'
			)
		);

		// Log the event.
		file_put_contents(sys_get_temp_dir() . '/servicelog-' . time() . '.txt', print_r($this->input, 1));

		// For some reason we didn't get an action.
		if (!isset($data['action']))
		{
			return;
		}

		// Check to see if this is a new pull request
		if (isset($data['pull_request']) && !empty($data['pull_request']))
		{
			file_put_contents(sys_get_temp_dir() . '/newpull-' . time() . '.txt', print_r($data['pull_request'], 1));
		}

		// Check to see if we have a comment...
		if (isset($data['comment']) && !empty($data['comment']))
		{
			// Validate if this is a reopen request.
			if ($data['action'] == 'created' && stristr($data['comment']['body'], '!reopen') !== false && isset($data['issue']['pull_request']))
			{
				file_put_contents(sys_get_temp_dir() . '/reopen-' . time() . '.txt', $data['issue']['number']);
				//$repository->openRequest($data['issue']['number']);
			}
		}

		$this->app->setBody('{}');
	}
}
