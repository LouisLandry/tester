<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Milestone
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Milestone collection class for the Joomla Tester.
 *
 * @package     Joomla.Tester
 * @subpackage  Milestone
 * @since       1.0
 */
class PTMilestoneCollection extends PTTable
{
	/**
	 * Set the flag to return the number of requests for each milestone or not.
	 *
	 * @param   boolean  $enabled  True to return the request count for reach milestone.
	 *
	 * @return  PTMilestoneCollection
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 */
	public function includeRequestCount($enabled = true)
	{
		$this->_loaded = false;

		$this->options->set('include.request_count', (bool) $enabled);

		return $this;
	}

	/**
	 * Set list filters by name and value.
	 *
	 * @param   string  $name   The name of the filter.
	 * @param   mixed   $value  The value of the filter.
	 *
	 * @return  PTMilestoneCollection
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
			case 'past_due':
				$this->options->set('filter.' . $name, (bool) $value);
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
	 * @return  PTMilestoneCollection
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 */
	public function sort($mode, $direction)
	{
		$this->_loaded = false;

		// Get the sorting direction.
		$this->options->set('sort.direction', ($direction == 'down' ? 'DESC' : 'ASC'));

		switch ($mode)
		{
			case 'title':
			case 'created':
			case 'due':
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
		$query->select('m.*');
		$query->from('#__milestones AS m');
		$query->leftJoin('#__pull_requests AS r ON m.milestone_id = r.milestone_id');
		$query->group('m.milestone_id');

		// Add the status data if required.
		if ($this->options->get('include.request_count', false))
		{
			$query->select('COUNT(r.*) AS request_count');
		}

		// Set the state filter.
		$state = $this->options->get('filter.state');
		if ($state !== null)
		{
			$query->where('r.state = ' . ($state ? 1 : 0));
		}

		// Set the past due filter if required.
		if ($this->options->get('filter.past_due', false))
		{
			$query->where('due_time < ' . $query->q(JFactory::getDate()->toSql(false, $this->db)));
		}

		$direction = $this->options->get('sort.direction', 'ASC');
		switch ($this->options->get('sort.mode'))
		{
			case 'title':
				$query->order('m.title ' . $direction);
				break;
			case 'created':
				$query->order('m.created_time ' . $direction);
				break;
			default:
			case 'due':
				$query->order('m.due_time ' . $direction);
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
		$object = new PTMilestone($this->db);

		return $object;
	}
}
