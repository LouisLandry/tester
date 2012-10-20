<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Request
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Pull Request Test class for the Joomla Tester.
 *
 * @property-read  integer  $test_id
 * @property-read  integer  $pull_id
 * @property-read  integer  $state
 * @property-read  integer  $revision
 * @property-read  integer  $build_number
 * @property-read  JDate    $tested_time
 * @property-read  string   $head_revision
 * @property-read  string   $base_revision
 * @property-read  object   $data
 *
 * @package     Joomla.Tester
 * @subpackage  Request
 * @since       1.0
 */
class PTRequestTest extends JTable
{
	const PENDING = 0;
	const SUCCESS = 1;
	const FAILURE = 2;
	const ERROR   = 3;

	/**
	 * @var    object  The repository database row object.
	 * @since  1.0
	 */
	private $_repository;

	/**
	 * Constructor
	 *
	 * @param   JDatabaseDriver  $db  Database driver object.
	 *
	 * @since   1.0
	 */
	public function __construct($db)
	{
		parent::__construct('#__pull_request_tests', 'test_id', $db);

		$this->data = new stdClass;
	}

	/**
	 * Overloaded check function
	 *
	 * @return  boolean  True if the object is ok to store.
	 *
	 * @see     JTable::check
	 * @since   1.0
	 */
	public function check()
	{
		if (trim($this->pull_id) == '')
		{
			return false;
		}

		return true;
	}

	/**
	 * Overloaded bind function
	 *
	 * @param   mixed  $src     Named array
	 * @param   mixed  $ignore  An optional array or space separated list of properties
	 *                          to ignore while binding.
	 *
	 * @return  mixed  Null if operation was satisfactory, otherwise returns an error
	 *
	 * @see     JTable::bind
	 * @since   1.0
	 */
	public function bind($src, $ignore = '')
	{
		if (is_object($src) && isset($src->data) && is_scalar($src->data))
		{
			$src->data = json_decode($src->data);
		}
		elseif (is_array($src) && isset($src['data']) && is_scalar($src['data']))
		{
			$src['data'] = json_decode($src['data']);
		}

		return parent::bind($src, $ignore);
	}

	/**
	 * Method to create and execute a SELECT WHERE query.
	 *
	 * @param   array  $options  Array of options
	 *
	 * @return  string  The database query result
	 *
	 * @since   1.0
	 */
	public function find($options = array())
	{
		// Get the JDatabaseQuery object
		$query = $this->_db->getQuery(true);

		foreach ($options as $col => $val)
		{
			$query->where($col . ' = ' . $this->_db->quote($val));
		}

		$query->select($this->_db->quoteName($this->_tbl_key));
		$query->from($this->_db->quoteName($this->_tbl));
		$this->_db->setQuery($query);

		return $this->_db->loadColumn(0);
	}

	/**
	 * Method to load a row from the database by primary key and bind the fields
	 * to the JTable instance properties.
	 *
	 * @param   mixed    $keys   An optional primary key value to load the row by, or an array of fields to match.  If not
	 *                           set the instance property value is used.
	 * @param   boolean  $reset  True to reset the default values before loading the new row.
	 *
	 * @return  boolean  True if successful. False if row not found.
	 *
	 * @see     JTable::load
	 * @since   1.0
	 */
	public function load($keys = null, $reset = true)
	{
		$success = parent::load($keys, $reset);

		// Keep the data property unserialized.
		if ($success && !empty($this->data) && is_string($this->data))
		{
			$this->data = json_decode($this->data);
		}

		return $success;
	}

	/**
	 * Overrides JTable::store to check unique fields.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @see     JTable::store
	 * @since   1.0
	 */
	public function store($updateNulls = false)
	{
		// Serialize the data property for storage.
		if (isset($this->data) && !is_scalar($this->data))
		{
			$this->data = json_encode($this->data);
		}

		// If we are inserting a test get the next revision to be used.
		if (empty($this->test_id))
		{
			$this->revision = $this->fetchNextRevision();
		}

		$success = parent::store($updateNulls);

		// Keep the data property during standard usage.
		if (!empty($this->data))
		{
			$this->data = json_decode($this->data);
		}

		return $success;
	}

	/**
	 * Process and store the data from a Jenkins build.
	 *
	 * @param   integer  $jenkinsBuildNumber  The Jenkins build number to parse and store.
	 *
	 * @return  PTRequestTest
	 *
	 * @since   1.0
	 */
	public function processJenkinsBuild($jenkinsBuildNumber)
	{
		$app = JFactory::getApplication();
		$http = new JHttp;

		// Get the base url for all build related resources.
		$buildBaseUrl = $app->get('jenkins.url') . '/job/' . $app->get('jenkins.job') . '/' . $jenkinsBuildNumber;

		// Get the base path of the build.
		$response = $http->get($buildBaseUrl . '/artifact/build/logs/base-path.txt');
		if ($response->code != 200)
		{
			throw new RuntimeException(sprintf('Cannot find the base path for build %d.', $jenkinsBuildNumber));
		}
		$workspacePath = trim($response->body);

		// Get the build information from the Jenkins JSON api.
		$response = $http->get($buildBaseUrl . '/api/json');
		if ($response->code != 200)
		{
			throw new RuntimeException(sprintf('The build %d does not exist at `%s`.', $jenkinsBuildNumber, $buildBaseUrl));
		}
		$buildData = json_decode($response->body);

		// Update some test values.
		$this->tested_time = JFactory::getDate((int) $buildData->timestamp / 1000)->toSql();
		$this->build_number = (int) $jenkinsBuildNumber;
		$this->data->merge = true;

		// Get the Pull Request ID from the build artifacts.
		$response = $http->get($buildBaseUrl . '/artifact/build/logs/pull-id.txt');
		if ($response->code != 200)
		{
			throw new RuntimeException(sprintf('The build %d does not contain a valid pull request ID.', $jenkinsBuildNumber));
		}
		$githubId = (int) trim($response->body);

		// Attempt to load the existing pull request based on GitHub ID.
		$request = new PTRequest($this->_db);
		$request->load(array('github_id' => $githubId));
		$this->pull_id = $request->pull_id;

		// Get the base SHA1 from the build artifacts.
		$response = $http->get($buildBaseUrl . '/artifact/build/logs/base-sha1.txt');
		if ($response->code != 200)
		{
			throw new RuntimeException(sprintf('The build %d did not successfully get a base repository for testing.', $jenkinsBuildNumber));
		}
		$this->base_revision = trim($response->body);

		// Get the head SHA1 from the build artifacts.
		$response = $http->get($buildBaseUrl . '/artifact/build/logs/head-sha1.txt');
		if ($response->code != 200)
		{
			$this->state = self::ERROR;
			$this->data->merge = false;
		}
		else
		{
			$this->head_revision = trim($response->body);
		}

		if (!$this->check())
		{
			throw new RuntimeException(
				sprintf('Test for pull request %d did not check out for storage.', $request->github_id)
			);
		}

		try
		{
			$this->store();
		}
		catch (RuntimeException $e)
		{
			throw new RuntimeException(
				sprintf('Test for pull request %d was unable to be stored.  Error message `%s`.', $request->github_id, $e->getMessage())
			);
		}

		// If we didn't get a clean merge, there is no sense in going any further.
		if (!$this->data->merge)
		{
			return $this;
		}

		// Create the checkstyle report object.
		$checkstyle = new PTRequestTestCheckstyle($this->_db);
		$checkstyle->pull_id = $this->pull_id;
		$checkstyle->test_id = $this->test_id;

		try
		{
			// Parse the checktyle report.
			$parser = new PTParserCheckstyle(array($workspacePath));
			$checkstyle = $parser->parse($checkstyle, $buildBaseUrl . '/artifact/build/logs/checkstyle.xml');
			$checkstyle = $this->runCheckstyleDiff($checkstyle);
			$checkstyle->store();
			$this->data->checkstyle = true;
		}
		catch (RuntimeException $e)
		{
			$this->data->checkstyle = false;
			JLog::add(
				sprintf(
					'Checkstyle report for pull request %d was unable to be stored.  Error message `%s`.',
					$request->github_id,
					$e->getMessage()
				),
				JLog::DEBUG,
				'error'
			);
		}

		// Set the state based on the checkstyle report.
		if (!$this->data->checkstyle)
		{
			$this->state = self::ERROR;
		}
		elseif ($checkstyle->error_count || $checkstyle->warning_count)
		{
			$this->state = self::FAILURE;
		}

		$unit = new PTRequestTestUnittest($this->_db);
		$unit->pull_id = $this->pull_id;
		$unit->test_id = $this->test_id;

		try
		{
			$parser = new PTParserJunit(array($workspacePath));
			$unit = $parser->parse($unit, $buildBaseUrl . '/artifact/build/logs/junit.xml');
			$unit->store();
			$this->data->unit = true;
		}
		catch (RuntimeException $e)
		{
			$this->data->unit = false;
			JLog::add(
				sprintf(
					'Unit test report for pull request %d was unable to be stored.  Error message `%s`.',
					$request->github_id,
					$e->getMessage()
				),
				JLog::DEBUG,
				'error'
			);
		}

		// Set the state based on the checkstyle report.
		if (!$this->data->unit)
		{
			$this->state = self::ERROR;
		}
		elseif ($unit->failure_count || $unit->error_count)
		{
			$this->state = self::FAILURE;
		}

		try
		{
			$parser = new PTParserJunit(array($workspacePath));
			$unit = $parser->parse($unit, $buildBaseUrl . '/artifact/build/logs/junit.legacy.xml');
			$unit->store();
			$this->data->unit_legacy = true;
		}
		catch (RuntimeException $e)
		{
			$this->data->unit_legacy = false;
			JLog::add(
				sprintf(
					'Legacy unit test report for pull request %d was unable to be stored.  Error message `%s`.',
					$request->github_id,
					$e->getMessage()
				),
				JLog::DEBUG,
				'error'
			);
		}

		// Set the state based on the checkstyle report.
		if (!$this->data->unit_legacy)
		{
			$this->state = self::ERROR;
		}
		elseif ($unit->failure_count || $unit->error_count)
		{
			$this->state = self::FAILURE;
		}

		if ($this->state != self::ERROR && $this->state != self::FAILURE)
		{
			$this->state = self::SUCCESS;
		}

		$this->store();

		return $this;
	}

	/**
	 * Get the next test revision for the pull request.
	 *
	 * @return  integer
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 */
	protected function fetchNextRevision()
	{
		if (empty($this->pull_id))
		{
			throw new InvalidArgumentException('Missing Pull Request ID.');
		}

		// Build the query to retrieve current test revision.
		$query = $this->_db->getQuery(true);
		$query->select('MAX(revision)');
		$query->from($this->_tbl);
		$query->where('pull_id = ' . (int) $this->pull_id);
		$this->_db->setQuery($query);

		return (1 + (int) $this->_db->loadResult());
	}

	/**
	 * Execute PHP_CodeSniffer over the repository.
	 *
	 * @param   PTRequestTestCheckstyle  $report  The report object to populate.
	 *
	 * @return  PTRequestTestCheckstyle
	 *
	 * @since   1.0
	 */
	protected function runCheckstyleDiff(PTRequestTestCheckstyle $report)
	{
		$repository = $this->_fetchRepositoryObject();

		$localErrors = (array) $repository->data->checkstyle->errors;
		$localWarnings = (array) $repository->data->checkstyle->warnings;

		if (isset($report->data->errors))
		{
			$pullErrors = $report->data->errors;
			foreach ($pullErrors as $k => $v)
			{
				foreach ($localErrors as $error)
				{
					if ($v->file == $error->file && $v->line == $error->line)
					{
						unset($pullErrors[$k]);
						break;
					}
				}
			}
			$report->data->new_errors = array_values($pullErrors);
			$report->error_count = count($report->data->new_errors);
		}

		if (isset($report->data->warnings))
		{
			$pullWarnings = $report->data->warnings;
			foreach ($pullWarnings as $k => $v)
			{
				foreach ($localWarnings as $warning)
				{
					if ($v->file == $warning->file && $v->line == $warning->line)
					{
						unset($pullWarnings[$k]);
						break;
					}
				}
			}
			$report->data->new_warnings = array_values($pullWarnings);
			$report->warning_count = count($report->data->new_warnings);
		}

		return $report;
	}

	/**
	 * Get the repository object from the database.
	 *
	 * @return  object
	 *
	 * @since   1.0
	 */
	private function _fetchRepositoryObject()
	{
		if (!empty($this->_repository))
		{
			return $this->_repository;
		}

		// Get the repository object from the database.
		$query = $this->_db->getQuery(true);
		$query->select('*')
			->from('#__repositories')
			->where('repository_id = 1');
		$this->_db->setQuery($query, 0, 1);

		$this->_repository = $this->_db->loadObject();

		// Ensure we have a solid footing for our data object.
		$this->_repository->data = json_decode($this->_repository->data);
		if (is_scalar($this->_repository->data))
		{
			$this->_repository->data = new stdClass;
		}
		elseif (is_array($this->_repository->data))
		{
			$this->_repository->data = (object) $this->_repository->data;
		}

		if (!isset($this->_repository->data->checkstyle))
		{
			$this->_repository->data->checkstyle = new stdClass;
		}

		return $this->_repository;
	}
}
