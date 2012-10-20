<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Service
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Jenkins Notification End Point.
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
class PTServiceJenkinsNotificationCreate extends JControllerBase
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
		// Grab data from the end point.
		$data = $this->input->getArray(
			array(
				'name' => 'string',
				'url' => 'string',
				'build' => array(
					'number' => 'integer',
					'phase' => 'word',
					'status' => 'word',
					'url' => 'string',
					'fullUrl' => 'string',
					'parameters' => 'array'
				)
			)
		);

		// Log the build status notification.
		$this->_logNotification($data['build']);

		// If we have a completed build save the test.
		if ($data['build']['phase'] == 'COMPLETED')
		{
			try
			{
				$test = new PTRequestTest(JFactory::$database);
				$test->processJenkinsBuild($data['build']['number']);
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
	 * Log the notification.
	 *
	 * @param   array  $build  The build section of the notification.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function _logNotification(array $build)
	{
		if ($build['phase'] == 'STARTED')
		{
			JLog::add(
				sprintf(
					'A test for pull request %d has begun with build number %d.',
					$build['parameters']['pull_id'],
					(int) $build['number']
				),
				JLog::INFO,
				'jenkins'
			);
		}
		elseif ($build['phase'] == 'COMPLETED')
		{
			$translate = array(
				'FAILURE' => 'unsuccessfully',
				'SUCCESS' => 'successfully',
				'ERROR' => 'unsuccessfully'
			);

			JLog::add(
				sprintf(
					'A test for pull request %d has completed %s with build number %d.',
					$build['parameters']['pull_id'],
					$translate[$build['status']],
					(int) $build['number']
				),
				JLog::INFO,
				'jenkins'
			);
		}
	}
}
