<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Command
 *
 * @copyright   Copyright (C) 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Controller to display help for the application.
 *
 * @package     Joomla.Tester
 * @subpackage  Command
 * @since       1.0
 */
class PTCommandHelp extends JControllerBase
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
		$this->app->out('Joomla Pull Request Tester 1.0 by Open Source Matters, Inc.');
		$this->app->out();
		$this->app->out('Usage:    joomla-tester <command> [options]');
		$this->app->out();
		$this->app->out('  -h | --help   Prints this usage information.');
		$this->app->out();
		$this->app->out('Examples: joomla-tester install');
		$this->app->out('          joomla-tester test');
		$this->app->out('          joomla-tester update');
		$this->app->out();
	}
}
