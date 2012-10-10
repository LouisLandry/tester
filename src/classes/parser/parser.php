<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Parser
 *
 * @copyright   Copyright (C) 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Abstract output report parser for the Joomla Pull Request Tester Application.
 *
 * @package     Joomla.Tester
 * @subpackage  Parser
 * @since       1.0
 */
abstract class PTParser
{
	/**
	 * @var    array  The array of base paths to clean from output.
	 * @since  1.0
	 */
	protected $paths;

	/**
	 * Instantiate the parser.
	 *
	 * @param   array  $paths  The array of base paths to clean from output.
	 *
	 * @since   1.0
	 */
	public function __construct(array $paths = array())
	{
		$this->paths = $paths;
	}

	/**
	 * Parse a report file.
	 *
	 * @param   object  $report  The report on which to bind parsed data.
	 * @param   string  $file    The absolute path for the file to parse.
	 *
	 * @return  mixed
	 *
	 * @since   1.0
	 */
	abstract public function parse($report, $file);

	/**
	 * Method to clean base paths from a string.
	 *
	 * @param   string  $dirty  The string to clean.
	 *
	 * @return  string  The clean string.
	 *
	 * @since   1.0
	 */
	protected function cleanPaths($dirty)
	{
		return str_replace($this->paths, '...', $dirty);
	}
}
