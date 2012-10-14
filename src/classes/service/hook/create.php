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
		file_put_contents('/tmp/servicelog-' . time() . '.txt', print_r($this->input, 1));

		// For some reason we didn't get an action.
		if (!isset($data['action']))
		{
			parent::execute();
			return;
		}

		// Check to see if this is a new pull request
		if (isset($data['pull_request']) && !empty($data['pull_request']))
		{
			// do something, check the action is opened or something...
		}

		// Check to see if we have a comment...
		if (isset($data['comment']) && !empty($data['comment']))
		{
			// Validate if this is a reopen request.
			if ($data['action'] == 'created' && stristr($data['comment']['body'], '!reopen') !== false && isset($data['issue']['pull_request']))
			{
				// Set the options.
				$options = new JRegistry;
				$options->set('api.username', $this->app->get('github.api.username'));
				$options->set('api.password', $this->app->get('github.api.password'));
				$options->set('api.url', $this->app->get('github.api.url'));
				$ghHttp = new JGithubHttp(new JRegistry, new JHttpTransportStream(new JRegistry));
				$gh = new JGithub($options, $ghHttp);
				$gh->pulls->edit($this->app->get('github.user'), $this->app->get('github.repo'), $data['issue']['number'], null, null, 'open');
			}
		}

		$this->app->setBody('{}');
	}
}
