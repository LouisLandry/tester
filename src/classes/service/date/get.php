<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Service
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Get the current server date and time.
 *
 * ## Response
 *
 * ### 200 OK
 *
 * #### Payload
 *
 * ```json
 * "1970-01-01 00:00:00"
 * ```
 *
 * @package     Joomla.Tester
 * @subpackage  Service
 * @since       1.0
 */
class PTServiceDateGet extends JControllerBase
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
		$date = JFactory::getDate();

		$this->app->setBody(json_encode($date->format('Y-m-d H:i:s', false)));
	}
}
