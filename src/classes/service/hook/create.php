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
		$state = $this->_buildModelState();

		// Get the repository model.
		$repository = new PTRepository($state);

		// Get the event type from the request.
		$eventType = $this->input->get->getCmd('event_type');

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

		// Log the event notification.
		$this->_logNotification($eventType, $data);

		// Handle the events.
		switch ($eventType)
		{
			case 'pull_request':
				$this->handlePullRequest($data, $repository);
				break;

			case 'issue_comment':
			case 'commit_comment':
				$this->handleComment($data, $repository);
				break;
		}
	}

	/**
	 * Handle any sort of issue or commit comment event.
	 *
	 * @param   array  $data
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function handleComment(array $data, PTRepository $repository)
	{
		// We are only interested in new comments.
		if ($data['action'] != 'created')
		{
			return;
		}

		// We are also not interested in comments not on pull requests.
		if (!isset($data['issue']['pull_request']))
		{
			return;
		}

		// We are only interested in comments by the author of the pull request.
		if ($data['comment']['user']['login'] != $data['issue']['user']['login'])
		{
			return;
		}

		// Validate if this is a comment to reopen the request.
		if (stristr($data['comment']['body'], 'reopen') !== false || stristr($data['comment']['body'], 're-open') !== false)
		{
			JLog::add(
				sprintf(
					'New comment found on pull request `%d` by `%s`.',
					(int) $data['issue']['number'],
					$data['comment']['user']['login']
				),
				JLog::INFO,
				'github-comments'
			);

			try
			{
				$repository->openRequest($data['issue']['number']);
			}
			catch (Exception $e)
			{
				JLog::add(
					sprintf(
						'`%s` exception encountered with message `%s` and code `%d`.',
						get_class($e),
						$e->getMessage(),
						(int) $e->getCode()
					),
					JLog::ERROR,
					'error'
				);
			}
		}
	}

	/**
	 * Handle a pull request event.
	 *
	 * @param   array  $data
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function handlePullRequest(array $data, PTRepository $repository)
	{
		// Only handle actions where we want to trigger a build.
		if (in_array($data['action'], array('opened', 'synchronize', 'reopened')))
		{
			JLog::add(
				sprintf(
					'Pull request change for request `%d`.  Triggering a test build.',
					(int) $data['pull_request']['number']
				),
				JLog::INFO,
				'github-pull-requests'
			);

			try
			{
				$repository->syncRequest((int) $data['pull_request']['number']);
				$repository->testRequest((int) $data['pull_request']['number']);
			}
			catch (Exception $e)
			{
				JLog::add(
					sprintf(
						'`%s` exception encountered with message `%s` and code `%d`.',
						get_class($e),
						$e->getMessage(),
						(int) $e->getCode()
					),
					JLog::ERROR,
					'error'
				);
			}
		}
	}

	/**
	 * Build and return a model state object.
	 *
	 * @return  JRegistry
	 *
	 * @since   1.0
	 */
	private function _buildModelState()
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

		// Add the PHPCS testing configuration values.
		$state->set('phpcs.standard', $this->app->get('phpcs.standard'));
		$state->set('phpcs.paths', $this->app->get('phpcs.paths'));

		// Add the Jenkins configuration values.
		$state->set('jenkins.url', $this->app->get('jenkins.url'));
		$state->set('jenkins.job', $this->app->get('jenkins.job'));
		$state->set('jenkins.token', $this->app->get('jenkins.token'));

		return $state;
	}

	/**
	 * Log the event.
	 *
	 * @param   string  $eventType  The event type for which to log.
	 * @param   array   $data       The event data to log.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function _logNotification($eventType, array $data)
	{
		// Log that the event was captured.
		JLog::add(
			sprintf(
				'GitHub has sent an `%s` event with action `%s` id `%d`.',
				$eventType,
				$data['action'],
				(int) $data['number']
			),
			JLog::INFO,
			'github'
		);
	}
}
