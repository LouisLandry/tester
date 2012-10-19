<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Request
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Pull Request collection class for the Joomla Tester.
 *
 * @package     Joomla.Tester
 * @subpackage  Request
 * @since       1.0
 */
class PTRequestCollection extends PTTable
{
	/**
	 * Set the flag to return the test status for each request or not.
	 *
	 * @param   boolean  $enabled  True to return the test status information for each request.
	 *
	 * @return  PTRequestCollection
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 */
	public function includeStatus($enabled = true)
	{
		$this->_loaded = false;

		$this->options->set('include.status', (bool) $enabled);

		return $this;
	}

	/**
	 * Set list filters by name and value.
	 *
	 * @param   string  $name   The name of the filter.
	 * @param   mixed   $value  The value of the filter.
	 *
	 * @return  PTRequestCollection
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 */
	public function filter($name, $value)
	{
		$this->_loaded = false;

		switch ($name)
		{
			case 'state':
			case 'mergeable':
			case 'merged':
			case 'pending_tests':
				$this->options->set('filter.' . $name, (bool) $value);
				break;
			case 'user':
				$this->options->set('filter.' . $name, (string) $value);
				break;
			default:
				throw new InvalidArgumentException(sprintf('`%s` is not a valid filter.', $name));
				break;
		}

		return $this;
	}

	/**
	 * Set list sorting mode and direction.
	 *
	 * @param   string  $mode       The sort mode.
	 * @param   string  $direction  The sort direction. [ascending, descending]
	 *
	 * @return  PTRequestCollection
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 */
	public function sort($mode, $direction)
	{
		$this->_loaded = false;

		// Get the sorting direction.
		$this->options->set('sort.direction', ($direction == 'descending' ? 'DESC' : 'ASC'));

		switch ($mode)
		{
			case 'author':
			case 'closed':
			case 'created':
			case 'mergeability':
			case 'updated':
				$this->options->set('sort.mode', $mode);
				break;
			default:
				throw new InvalidArgumentException(sprintf('`%s` is not a valid sort mode.', $mode));
				break;
		}

		return $this;
	}

	/**
	 * Build the query to select objects for the iterator based on the options object.
	 *
	 * @param   JDatabaseQuery  $query  The query to build.
	 *
	 * @return  JDatabaseQuery
	 *
	 * @since   1.0
	 */
	protected function buildQuery(JDatabaseQuery $query)
	{
		$query->select('r.*');
		$query->from('#__pull_requests AS r');
		$query->leftJoin('#__pull_request_tests AS t ON r.pull_id = t.pull_id');
		$query->leftJoin('#__pull_request_tests AS t2 ON (r.pull_id = t2.pull_id AND t.tested_time < t2.tested_time)');
		$query->where('t2.pull_id IS NULL');

		// Add the status data if required.
		if ($this->options->get('include.status', false))
		{
			$query->select('t.test_time, t.data AS test_data, t.state AS test_state, t.revision AS test_revision');
		}

		// Set the state filter.
		$state = $this->options->get('filter.state');
		if ($state !== null)
		{
			$query->where('r.state = ' . ($state ? 1 : 0));
		}

		// Set the mergeable filter.
		$mergeable = $this->options->get('filter.mergeable');
		if ($mergeable !== null)
		{
			$query->where('r.is_mergeable = ' . ($mergeable ? 1 : 0));
		}

		// Set the merged filter.
		$merged = $this->options->get('filter.merged');
		if ($merged !== null)
		{
			$query->where('r.is_merged = ' . ($merged ? 1 : 0));
		}

		// Set the user filter.
		$user = $this->options->get('filter.user');
		if ($user !== null)
		{
			$query->where('r.user = ' . $query->q($user));
		}

		// Set the pending tests filter if required.
		if ($this->options->get('filter.pending_tests', false))
		{
			$query->where('((t.tested_time IS NULL) OR (r.updated_time > t.tested_time))');
		}

		$direction = $this->options->get('sort.direction', 'ASC');
		switch ($this->options->get('sort.mode'))
		{
			case 'author':
				$query->order('r.user ' . $direction);
				break;
			case 'closed':
				$query->order('r.closed_time ' . $direction);
				break;
			case 'created':
				$query->order('r.created_time ' . $direction);
				break;
			case 'mergeability':
				$query->order('r.is_mergeable ' . $direction);
				break;
			default:
			case 'updated':
				$query->order('r.updated_time ' . $direction);
				break;
		}

		return $query;
	}

	/**
	 * Get a new row instance for the table.
	 *
	 * @return  JTable
	 *
	 * @since   1.0
	 */
	protected function getRowInstance()
	{
		$object = new PTRequest($this->db);

		return $object;
	}
}
