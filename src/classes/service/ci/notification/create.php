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
class PTServiceCiNotificationCreate extends JControllerBase
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

		// Here we need to go get the build logs and get the data into the database.

		$this->app->setBody('{}');
	}
}
