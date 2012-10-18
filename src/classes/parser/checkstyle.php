<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Parser
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Checkstyle XML report parser for the Joomla Pull Request Tester Application.
 *
 * @package     Joomla.Tester
 * @subpackage  Parser
 * @since       1.0
 */
class PTParserCheckstyle extends PTParser
{
	/**
	 * Parse a Checkstyle XML report into a value object.
	 *
	 * @param   PTRequestTestCheckstyle  $report  The report on which to bind parsed data.
	 * @param   string                  $file    The filesystem path of the checkstyle report to parse.
	 *
	 * @return  PTRequestTestCheckstyle
	 *
	 * @see     PTParser::parse()
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function parse($report, $file)
	{
		$reader = new XMLReader;
		$reader->open($file);
		while ($reader->read())
		{
			if ($reader->name == 'file')
			{
				$fName = $this->cleanPaths($reader->getAttribute('name'));
			}

			if ($reader->name == 'error')
			{
				if ($reader->getAttribute('severity') == 'warning')
				{
					$e = new stdClass;
					$e->file = $fName;
					$e->line = (int) $reader->getAttribute('line');
					$e->message = $this->cleanPaths($reader->getAttribute('message'));

					$report->data->warnings[] = $e;
				}

				if ($reader->getAttribute('severity') == 'error')
				{
					$e = new stdClass;
					$e->file = $fName;
					$e->line = (int) $reader->getAttribute('line');
					$e->message = $this->cleanPaths($reader->getAttribute('message'));

					$report->data->errors[] = $e;
				}
			}
		}

		$reader->close();

		// Set the aggregate counts.
		$report->error_count = count($report->data->errors);
		$report->warning_count = count($report->data->warnings);

		return $report;
	}
}
