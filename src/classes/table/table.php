<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Table
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Table Iterator class for the Joomla Tester.
 *
 * @package     Joomla.Tester
 * @subpackage  Table
 * @since       1.0
 */
abstract class PTTable implements Iterator
{
	/**
	 * @var    JDatabaseDriver
	 * @since  1.0
	 */
	protected $db;

	/**
	 * @var    array  The results as an array of objects.
	 * @since  1.0
	 */
	protected $list = array();

	/**
	 * @var    JRegistry  The options to use when building the query for retrieving results.
	 * @since  1.0
	 */
	protected $options;

	/**
	 * @var    boolean  True if the query has been executed and the results have been loaded.
	 * @since  1.0
	 */
	private $_loaded = false;

	/**
	 * @var    integer  The current position in the array of result objects.
	 * @since  1.0
	 */
	private $_position = 0;

	/**
	 * Constructor
	 *
	 * @param   JDatabaseDriver  $db  Database driver object.
	 *
	 * @since   1.0
	 */
	public function __construct(JDatabaseDriver $db)
	{
		$this->options = new JRegistry;
		$this->db = $db;
	}

	/**
	 * Return the current element.
	 *
	 * @return  mixed
	 *
	 * @since   1.0
	 */
	public function current()
	{
		$request = $this->getRowInstance();
		$request->bind($this->list[$this->_position]);

		return $request;
	}

	/**
	 * Return the key of the current element.
	 *
	 * @return  scalar
	 *
	 * @since   1.0
	 */
	public function key()
	{
		return $this->_position;
	}

	/**
	 * Move forward to next element.  This method is called after each foreach loop.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function next()
	{
		++$this->_position;
	}

	/**
	 * Reset the Iterator to a clean state with no options and no data.
	 *
	 * @return  PTTable
	 *
	 * @since   1.0
	 */
	public function reset()
	{
		$this->options = new JRegistry;
		$this->list = array();
		$this->_loaded = true;
		$this->rewind();

		return $this;
	}

	/**
	 * Rewind the Iterator to the first element.  This is the first method called when starting a foreach
	 * loop. It will not be executed after foreach loops.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function rewind()
	{
		$this->_position = 0;
	}

	/**
	 * Checks if current position is valid.  This method is called after Iterator::rewind() and Iterator::next()
	 * to check if the current position is valid.
	 *
	 * @return  boolean  True if there is a record at the current position.
	 *
	 * @since   1.0
	 */
	public function valid()
	{
		// If we don't have the correct data to iterate go get it.
		if ($this->_loaded)
		{
			$this->_load();
		}

		return isset($this->list[$this->_position]);
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
	abstract protected function buildQuery(JDatabaseQuery $query);

	/**
	 * Get a new row instance for the table.
	 *
	 * @return  JTable
	 *
	 * @since   1.0
	 */
	abstract protected function getRowInstance();

	/**
	 * Load the objects into the iterator list based on the options.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function _load()
	{
		// Build the query based on state.
		$query = $this->db->getQuery(true);
		$query = $this->buildQuery($query);

		// Load the list of objects from the database.
		$this->db->setQuery($query);
		$this->list = $this->db->loadObjectList();

		// Set the loaded flag since we got our data.
		$this->_loaded = true;
	}
}
