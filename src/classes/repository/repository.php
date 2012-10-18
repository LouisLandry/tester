<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Repository
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Joomla Pull Request Tester Repository class.
 *
 * @package     Joomla.Tester
 * @subpackage  Repository
 * @since       1.0
 */
class PTRepository extends JModelDatabase
{
	/**
	 * @var    JGithub  The GitHub API connector.
	 * @since  1.0
	 */
	protected $github;

	/**
	 * @var    PTGitRepository  The Git repository object.
	 * @since  1.0
	 */
	protected $repo;

	/**
	 * @var    array  A lookup array for github_id to milestone_id values.
	 * @since  1.0
	 */
	private $_milestoneLookup;

	/**
	 * @var    object  The repository database row object.
	 * @since  1.0
	 */
	private $_repository;

	/**
	 * Instantiate the model.
	 *
	 * @param   JRegistry        $state  The model state.
	 * @param   JDatabaseDriver  $db     The database adpater.
	 *
	 * @since   12.1
	 */
	public function __construct(JRegistry $state = null, JDatabaseDriver $db = null)
	{
		// Execute the parent constructor.
		parent::__construct($state, $db);

		// Setup the GitHub API connector.
		$this->github = new JGithub;
		$this->github->setOption('api.url', $this->state->get('github.api'));
		$this->github->setOption('curl.certpath', JPATH_CONFIGURATION . '/cacert.pem');
		$this->github->setOption('api.username', $this->state->get('github.username'));
		$this->github->setOption('api.password', $this->state->get('github.password'));

		// Instantiate the repository object.
		$this->repo = new PTGitRepository($this->state->get('repo'));
	}

	public function cleanReleases()
	{
		$changedRequests = 0;
		$releases = $this->_fetchReleases();

		$requestsToChange = $this->_fetchRequestsToChangeMilestone($releases);
		foreach ($requestsToChange as $issueId => $milestone)
		{
			$this->github->issues->edit(
				$this->state->get('github.user'),
				$this->state->get('github.repo'),
				$issueId,
				null, null, null, null,
				$milestone
			);

			$changedRequests++;
		}

		return $changedRequests;
	}

	/**
	 * Get a pull request by either GitHub id or local id.
	 *
	 * @param   integer  $githubId  The GitHub pull request id number.
	 * @param   integer  $pullId    The internal primary key for the pull request.
	 *
	 * @return  object
	 *
	 * @since   1.0
	 */
	public function getRequest($githubId, $pullId = null)
	{
		// Build the query to get the pull requests.
		$query = $this->db->getQuery(true);
		$query->select('r.pull_id, r.github_id, r.data');
		$query->from('#__pull_requests AS r');
		$query->leftJoin('#__pull_request_tests AS t ON r.pull_id = t.pull_id');

		if ($pullId)
		{
			$query->where('r.pull_id = ' . (int) $pullId);
		}
		else
		{
			$query->where('r.github_id = ' . (int) $githubId);
		}

		try
		{
			$this->db->setQuery($query, 0, 1);
			$pullRequest = $this->db->loadObject();

			// Decode the expanded pull request data.
			$pullRequest->data = json_decode($pullRequest->data);
		}
		catch (RuntimeException $e)
		{
			JLog::add('Error: ' . $e->getMessage(), JLog::DEBUG);
		}

		return $pullRequest;
	}

	/**
	 * Get a list of Pull Requests based on the model state.
	 *
	 * @return  array
	 *
	 * @since   1.0
	 */
	public function getRequests()
	{
		// Initialize variables.
		$pullRequests = array();

		// Build the query to get the pull requests.
		$query = $this->db->getQuery(true);
		$query->select('r.*');
		$query->from('#__pull_requests AS r');
		$query->leftJoin('#__pull_request_tests AS t ON r.pull_id = t.pull_id');
		$query->leftJoin('#__pull_request_tests AS t2 ON (r.pull_id = t2.pull_id AND t.tested_time < t2.tested_time)');
		$query->where('t2.pull_id IS NULL');

		// Add the test data if required.
		if ($this->state->get('list.tests', 0))
		{
			$query->leftJoin('#__pull_request_test_checkstyle_reports AS ch ON t.test_id = ch.test_id');
			$query->leftJoin('#__pull_request_test_unit_test_reports AS ut ON t.test_id = ut.test_id');

			$query->select('t.tested_time, t.data AS tested_data');
			$query->select('ch.error_count AS style_errors, ch.warning_count AS style_warnings, ch.data AS style_data');
			$query->select('ut.failure_count AS test_failures, ut.error_count AS test_errors, ut.data AS test_data');
		}

		// Set the filtering for the query.
		$query = $this->_setFiltering($query);

		// Set the sorting clause.
		$query = $this->_setSorting($query);

		try
		{
			$this->db->setQuery($query, $this->state->get('list.start', 0), $this->state->get('list.limit', 50));
			$pullRequests = $this->db->loadObjectList();

			// Callback function to decode the expanded pull request data.
			$decodeCallback = function($request)
			{
				$request->data = json_decode($request->data);
				return $request;
			};

			// Decode the serialized pull request data for the entire array.
			$pullRequests = array_map($decodeCallback, $pullRequests);
		}
		catch (RuntimeException $e)
		{
			JLog::add('Error: ' . $e->getMessage(), JLog::DEBUG);
		}

		return $pullRequests;
	}

	/**
	 * Method to reopen a pull request..
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function openRequest($githubId)
	{
		// Make sure that the pull request is not already merged.
		$query = $this->db->getQuery(true);
		$query->select('r.is_merged')
			->from('#__pull_requests AS r')
			->where('r.github_id = ' . (int) $githubId);
		$this->db->setQuery($query, 0, 1);
		$isMerged = (int) $this->db->loadResult();

		if (!$isMerged)
		{
			$this->github->pulls->edit(
				$this->state->get('github.user'),
				$this->state->get('github.repo'),
				$githubId,
				null, null,
				'open'
			);
		}
		else
		{
			throw new InvalidArgumentException(sprintf('Cannot open merged pull request %d.', (int) $githubId));
		}
	}

	/**
	 * Method to synchronize the local testing repository with the github repository.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function resetToStaging()
	{
		// If the respository doesn't exist create it.
		if (!$this->repo->exists())
		{
			$this->repo->create($this->state->get('github.host') . '/' . $this->state->get('github.user') . '/' . $this->state->get('github.repo') . '.git');
		}
		// Otherwise update from the origin staging branch.
		else
		{
			$this->repo->fetch('origin')
				->branchCheckout('master')
				->merge('origin/staging');
		}

		// Clean things up.
		$this->repo->clean();
	}

	/**
	 * Test an array of pull requests.
	 *
	 * @param   object  $request  The requests to test.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function saveRequestTest($jenkinsBuildNumber)
	{
		// Get the base url for all build related resources.
		$buildBaseUrl = $this->state->get('jenkins.url') . '/job/' . $this->state->get('jenkins.job') . '/' . $jenkinsBuildNumber;

		// Get the build information from the Jenkins JSON api.
		$buildData = json_decode(file_get_contents($buildBaseUrl . '/api/json'));

		// Get some critical values from the test reports.
		$githubId   = (int) trim(file_get_contents($buildBaseUrl . '/artifact/build/logs/pull-id.txt'));
		$baseCommit = trim(file_get_contents($buildBaseUrl . '/artifact/build/logs/base-sha1.txt'));
		$headCommit = trim(file_get_contents($buildBaseUrl . '/artifact/build/logs/head-sha1.txt'));
		$workspacePath = trim(file_get_contents($buildBaseUrl . '/artifact/build/logs/base-path.txt'));

		// Attempt to load the existing pull request based on GitHub ID.
		$request = new PTRequest($this->db);
		$request->load(array('github_id' => $githubId));

		// Create the test report object.
		$test = new PTTestReport($this->db);
		$test->pull_id = $request->pull_id;
		$test->tested_time = JFactory::getDate((int) $buildData->timestamp / 1000)->toSql();
		$test->head_revision = $headCommit;
		$test->base_revision = $baseCommit;

		if (!$test->check())
		{
			throw new RuntimeException(
				sprintf('Test for pull request %d did not check out for storage.', $request->github_id)
			);
		}

		try
		{
			$test->store();
		}
		catch (RuntimeException $e)
		{
			throw new RuntimeException(
				sprintf('Test for pull request %d was unable to be stored.  Error message `%s`.', $request->github_id, $e->getMessage())
			);
		}

		// Create the checkstyle report object.
		$checkstyle = new PTTestReportCheckstyle($this->db);
		$checkstyle->pull_id = $test->pull_id;
		$checkstyle->test_id = $test->test_id;

		try
		{
			// Parse the checktyle report.
			$parser = new PTParserCheckstyle(array($workspacePath));
			$checkstyle = $parser->parse($checkstyle, $buildBaseUrl . '/artifact/build/logs/checkstyle.xml');
			$checkstyle = $this->runCheckstyleDiff($checkstyle);
			$checkstyle->store();
		}
		catch (RuntimeException $e)
		{
			$test->data->checkstyle = false;
			JLog::add(
				sprintf('Checkstyle report for pull request %d was unable to be stored.  Error message `%s`.', $request->github_id, $e->getMessage()),
				JLog::DEBUG,
				'error'
			);
		}

		$unit = new PTTestReportUnittest($this->db);
		$unit->pull_id = $test->pull_id;
		$unit->test_id = $test->test_id;

		try
		{
			$parser = new PTParserJunit(array($workspacePath));
			$unit = $parser->parse($unit, $buildBaseUrl . '/artifact/build/logs/junit.xml');
			$unit->store();
		}
		catch (RuntimeException $e)
		{
			$test->data->unit = false;
			JLog::add(
				sprintf('Unit test report for pull request %d was unable to be stored.  Error message `%s`.', $request->github_id, $e->getMessage()),
				JLog::DEBUG,
				'error'
			);
		}

		try
		{
			$parser = new PTParserJunit(array($workspacePath));
			$unit = $parser->parse($unit, $buildBaseUrl . '/artifact/build/logs/junit.legacy.xml');
			$unit->store();
		}
		catch (RuntimeException $e)
		{
			$test->data->unit_legacy = false;
			JLog::add(
				sprintf('Legacy unit test report for pull request %d was unable to be stored.  Error message `%s`.', $request->github_id, $e->getMessage()),
				JLog::DEBUG,
				'error'
			);
		}

		$test->store();

		return $this;
	}

	/**
	 * Method to synchronize the local milestone metadata with the github repository.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function syncMilestones()
	{
		// Synchronize closed milestones first.
		$page = 1;
		$milestones = $this->github->milestones->getList($this->state->get('github.user'), $this->state->get('github.repo'), 'closed', 'due_at', 'desc', $page, 100);

		// Paginate over closed milestones until there aren't any more.
		while (!empty($milestones))
		{
			// Process the milestones.
			$this->_processMilestones($milestones);

			// Get the next page of milestones.
			$page++;
			$milestones = $this->github->milestones->getList($this->state->get('github.user'), $this->state->get('github.repo'), 'closed', 'due_at', 'desc', $page, 100);
		}

		// Synchronize open milestones next.
		$page = 1;
		$milestones = $this->github->milestones->getList($this->state->get('github.user'), $this->state->get('github.repo'), 'open', 'due_at', 'desc', $page, 100);

		// Paginate over open milestones until there aren't any more.
		while (!empty($milestones))
		{
			// Process the milestones.
			$this->_processMilestones($milestones);

			// Get the next page of milestones.
			$page++;
			$milestones = $this->github->milestones->getList($this->state->get('github.user'), $this->state->get('github.repo'), 'open', 'due_at', 'desc', $page, 100);
		}
	}

	/**
	 * Synchronize a pull request with GitHub.
	 *
	 * @param   integer  $githubId  The GitHub pull request id number.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 */
	public function syncRequest($githubId)
	{
		// Get the full pull request object from GitHub.
		$pull = $this->github->pulls->get($this->state->get('github.user'), $this->state->get('github.repo'), $githubId);

		$this->_processRequest($pull);
	}

	/**
	 * Method to synchronize the local pull request metadata with the github repository.
	 *
	 * @param   boolean  $full  True to do a full pass over the pull requests.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function syncRequests($full = false)
	{
		// Get the last updated pull request row.
		$query = $this->db->getQuery(true);
		$query->select('r.updated_time')
			->from('#__pull_requests AS r')
			->order('r.updated_time DESC');
		$this->db->setQuery($query, 0, 1);

		$lastUpdated = $this->db->loadResult();
		if ($lastUpdated)
		{
			$lastUpdated = new JDate($lastUpdated);
		}

		// Synchronize closed pull requests first.
		$page = 1;
		$pulls = $this->github->pulls->getList($this->state->get('github.user'), $this->state->get('github.repo'), 'closed', $page, 100);

		// Paginate over closed pull requests until there aren't any more.
		while (!empty($pulls))
		{
			// Only process pull requests if there is something to update.
			$updated = new JDate($pulls[0]->updated_at);
			if (!is_null($lastUpdated) && $lastUpdated >= $updated && !$full)
			{
				break;
			}

			// Iterate the incoming pull requests and make sure they are all synchronized with our database.
			foreach ($pulls as $pull)
			{
				$this->_processRequest($pull);
			}

			// Get the next page of pull requests.
			$page++;
			$pulls = $this->github->pulls->getList($this->state->get('github.user'), $this->state->get('github.repo'), 'closed', $page, 100);
		}

		// Synchronize open pull requests first.
		$page = 1;
		$pulls = $this->github->pulls->getList($this->state->get('github.user'), $this->state->get('github.repo'), 'open', $page, 100);

		// Paginate over open pull requests until there aren't any more.
		while (!empty($pulls))
		{
			// Only process pull requests if there is something to update.
			$updated = new JDate($pulls[0]->updated_at);
			if (!is_null($lastUpdated) && $lastUpdated >= $updated && !$full)
			{
				break;
			}

			// Iterate the incoming pull requests and make sure they are all synchronized with our database.
			foreach ($pulls as $pull)
			{
				$this->_processRequest($pull);
			}

			// Get the next page of pull requests.
			$page++;
			$pulls = $this->github->pulls->getList($this->state->get('github.user'), $this->state->get('github.repo'), 'open', $page, 100);
		}
	}

	/**
	 * Test the master branch and update the database.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function testMaster()
	{
		$this->resetToStaging();

		$sha = $this->repo->getHeadSHA();

		// Get the repository object.
		$repository = $this->_fetchRepositoryObject();

		// If the current head revision is the same as our saved one then there is nothing to do.
		if ($sha == $repository->head_revision)
		{
			return;
		}

		// Reset some repository values.
		$repository->head_revision = $sha;
		$now = new JDate;
		$repository->updated_time = $now->toSql();

		// Create the checkstyle report object.
		$checkstyle = new PTTestReportCheckstyle($this->db);

		try
		{
			$checkstyle = $this->runCheckstyleReport($checkstyle);
			$repository->style_error_count = $checkstyle->error_count;
			$repository->style_warning_count = $checkstyle->warning_count;
			$repository->data->checkstyle->errors = $checkstyle->data->errors;
			$repository->data->checkstyle->warnings = $checkstyle->data->warnings;
		}
		catch (RuntimeException $e)
		{
			$repository->data->checkstyle = false;
			JLog::add(sprintf('Checkstyle could not be run for the master branch.  Error message `%s`.', $e->getMessage()), JLog::DEBUG);
		}

		// Update the database with our new information.
		$repository->data = json_encode($repository->data);
		$this->db->updateObject('#__repositories', $repository, 'repository_id');
	}

	/**
	 * Test a pull request by either GitHub id or local id.
	 *
	 * @param   integer  $githubId  The GitHub pull request id number.
	 * @param   integer  $pullId    The internal primary key for the pull request.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function testRequest($githubId, $pullId = null)
	{
		$request = $this->getRequest($githubId, $pullId);

		// Enqueue the test with the build server.
		$this->enqueueRequestTest(
			$request->github_id,
			$request->data->head->repo->owner->login,
			$request->data->head->repo->name,
			$request->data->head->ref
		);
	}

	/**
	 * Enqueue a pull request test by either GitHub id or local id.
	 *
	 * @param   integer  $githubPullId  The GitHub pull request id number.
	 * @param   string   $githubUser    The GitHub user whose repository we are testing.
	 * @param   string   $githubRepo    The GitHub repository to test.
	 * @param   string   $githubBranch  The GitHub branch in the repository to test.
	 * @param   string   $cause         The reason for enqueuing test.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function enqueueRequestTest($githubPullId, $githubUser, $githubRepo, $githubBranch, $cause = 'Joomla Tester')
	{
		$http = JHttpFactory::getHttp();

		$uri = new JUri($this->state->get('jenkins.url') . '/job/' . $this->state->get('jenkins.job') . '/buildWithParameters');
		$uri->setVar('token', urlencode($this->state->get('jenkins.token')));
		$uri->setVar('cause', urlencode($cause));
		$uri->setVar('pull_id', (int) $githubPullId);
		$uri->setVar('github_user', urlencode($githubUser));
		$uri->setVar('github_repo', urlencode($githubRepo));
		$uri->setVar('github_branch', urlencode($githubBranch));

		$response = $http->get($uri->toString());
		if ($response->code != 200)
		{
			throw new RuntimeException($response->body, $response->code);
		}
	}

	/**
	 * Parse the message into a report entry.
	 *
	 * @param   string  $message  The unit test message to parse.
	 *
	 * @return  object
	 *
	 * @since   1.0
	 */
	protected function parseUnitTestMessage($message)
	{
		// Initialize variables.
		$report = new stdClass;
		$message = explode("\n", trim($message));

		// Strip off the trailing phpunit line from the stacktrace.
		if (strpos(end($message), 'phpunit') !== false)
		{
			array_pop($message);
		}

		// Extract the stack trace.
		$report->stack = array();
		foreach ($message as $k => $line)
		{
			if (strpos($line, '.../') === 0)
			{
				$report->stack[] = $line;
				unset($message[$k]);
			}
		}

		// The last line of the stack trace is our "file" and "line" for the report.
		$line = end($report->stack);
		$report->file = substr($line, 0, strrpos($line, ':'));
		$report->line = (int) substr($line, strrpos($line, ':') + 1);

		// Cleanup the message.
		$message = array_values($message);
		$message = explode("\n", trim(implode("\n", $message)));

		// Set the test and message body.
		$report->test = array_shift($message);
		$report->message = implode("\n", $message);

		return $report;
	}

	/**
	 * Execute PHP_CodeSniffer over the repository.
	 *
	 * @param   PTTestReportCheckstyle  $report  The report object to populate.
	 *
	 * @return  PTTestReportCheckstyle
	 *
	 * @since   1.0
	 */
	protected function runCheckstyleDiff(PTTestReportCheckstyle $report)
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
	 * Execute PHP_CodeSniffer over the repository.
	 *
	 * @param   PTTestReportCheckstyle  $report  The report object to populate.
	 *
	 * @return  PTTestReportCheckstyle
	 *
	 * @since   1.0
	 */
	protected function runCheckstyleReport(PTTestReportCheckstyle $report)
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// Execute the command.
		$wd = getcwd();
		chdir($this->state->get('repo'));
		$cleanPath = getcwd();
		exec('ant clean phpcs', $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('PHP_Codesniffer failed to execute with code %d and message %s.', $return, implode("\n", $out)));
		}

		// Parse the checktyle report.
		$parser = new PTParserCheckstyle(array($cleanPath, '/private/' . $this->state->get('repo'), $this->state->get('repo')));
		$report = $parser->parse($report, $this->state->get('repo') . '/build/logs/checkstyle.xml');

		return $report;
	}

	/**
	 * Get a list of closed, and the current active release objects.  The objects have a milestone_id, github_id, due_time
	 * and start_time properties.  The release cycle can be inferred from the time span between start and due time values.
	 *
	 * @return  array
	 *
	 * @since   1.0
	 */
	private function _fetchReleases()
	{
		$now = JFactory::getDate();

		// Build the query to get the milestone release dates.
		$query = $this->db->getQuery(true);
		$query->select('m.milestone_id, m.github_id, m.due_time');
		$query->from('#__milestones AS m');
		$query->where('m.due_time <= ' . $query->q($now->format($query->dateFormat())));
		$query->order('m.due_time ASC');

		try
		{
			$this->db->setQuery($query);
			$releases = $this->db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Error: ' . $e->getMessage(), JLog::DEBUG);
		}

		// Set the start times for the releases based on the previous release.
		for ($i=0,$n=count($releases); $i < $n; $i++)
		{
		if (isset($releases[$i-1]))
		{
		$releases[$i]->start_time = $releases[$i-1]->due_time;
		}
		else
		{
		$releases[$i]->start_time = $this->db->getNullDate();
		}
		}

		return $releases;
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
		$query = $this->db->getQuery(true);
		$query->select('*')
			->from('#__repositories')
			->where('repository_id = 1');
		$this->db->setQuery($query, 0, 1);

		$this->_repository = $this->db->loadObject();

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

	/**
	 * Get an array of "github_request_id" => "github_milestone_id" values for pull requests that should have milestone
	 * values changed.  The merged pull requests are inspected based on the timeframe of the given release cycles and
	 * if necessary, they are set to have the milestone number changed so that they correctly indicate which release
	 * they were a part of.  Additionally, non-merged pull requests are removed from any released versions.
	 *
	 * @param   array  $releases  An array of release objects.
	 *
	 * @return  array
	 * @since   1.0
	 */
	private function _fetchRequestsToChangeMilestone(array $releases)
	{
		$requestsToChange = array();

		// Find all issues merged during the release cycle which are not tagged for the release.
		foreach ($releases as $release)
		{
			$query = $this->db->getQuery(true);
			$query->select('r.github_id');
			$query->from('#__pull_requests AS r');
			$query->where('r.is_merged = 1');
			$query->where('(r.milestone_id IS NULL OR r.milestone_id <> ' . (int) $release->milestone_id . ')');
			$query->where('r.merged_time > ' . $query->q($release->start_time));
			$query->where('r.merged_time < ' . $query->q($release->due_time));

			try
			{
				$this->db->setQuery($query);
				$requests = $this->db->loadColumn();

				foreach ($requests as $request)
				{
					$requestsToChange[(int) $request] = (int) $release->github_id;
				}
			}
			catch (RuntimeException $e)
			{
				JLog::add('Error: ' . $e->getMessage(), JLog::DEBUG);
			}
		}

		// Find all issues not-merged during the release cycle which are tagged for the release.
		foreach ($releases as $release)
		{
			$query = $this->db->getQuery(true);
			$query->select('r.github_id');
			$query->from('#__pull_requests AS r');
			$query->where('r.is_merged = 0');
			$query->where('r.milestone_id = ' . (int) $release->milestone_id);
			$query->where('r.merged_time > ' . $query->q($release->start_time));
			$query->where('r.merged_time < ' . $query->q($release->due_time));

			try
			{
				$this->db->setQuery($query);
				$requests = $this->db->loadColumn();

				foreach ($requests as $request)
				{
					$requestsToChange[(int) $request] = 0;
				}
			}
			catch (RuntimeException $e)
			{
				JLog::add('Error: ' . $e->getMessage(), JLog::DEBUG);
			}
		}

		return $requestsToChange;
	}

	/**
	 * Update the milestone information.
	 *
	 * @param   array  $milestones  The list of milestones to update.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function _processMilestones($milestones)
	{
		// Iterate over the incoming pull requests and make sure they are all synchronized with our database.
		foreach ($milestones as $milestone)
		{
			// Attempt to load the existing milestone based on GitHub ID.
			$request = new PTMilestone($this->db);
			$request->load(array('github_id' => (int) $milestone->number));

			// Bind the values to our request.
			$request->github_id = (int) $milestone->number;
			$request->title = $milestone->title;
			$request->state = ($milestone->state == 'open' ? 0 : 1);
			$request->created_time = JFactory::getDate($milestone->created_at, 'GMT')->toSql();
			$request->due_time = ($milestone->due_on ? JFactory::getDate($milestone->due_on, 'GMT')->toSql() : $this->db->getNullDate());
			$request->data = $milestone;

			if (!$request->check())
			{
				JLog::add(sprintf('Milestone %d did not check out for storage.', $milestone->number), JLog::DEBUG);
			}

			try
			{
				$request->store();
			}
			catch (RuntimeException $e)
			{
				JLog::add(sprintf('Milestone %d was unable to be stored.  Error message `%s`.', $milestone->number, $e->getMessage()), JLog::DEBUG);
			}
		}
	}

	/**
	 * Update the pull request information.
	 *
	 * @param   array  $pulls  The list of pull requests to update.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function _processRequest($pull)
	{
		// Attempt to load the existing pull request based on GitHub ID.
		$request = new PTRequest($this->db);
		$request->load(array('github_id' => (int) $pull->number));

		// Get the updated timestamps for comparison.
		$githubUpdated = new JDate($pull->updated_at);
		$localUpdated = new JDate($request->updated_time);

		// If the updated timestamps are not the same then we need to syncronize the request.
		if ($githubUpdated != $localUpdated)
		{
			// Get the full pull request object from GitHub.
			$pull = $this->github->pulls->get($this->state->get('github.user'), $this->state->get('github.repo'), $pull->number);

			// Bind the values to our request.
			$request->github_id = (int) $pull->number;
			$request->title = $pull->title;
			$request->state = ($pull->state == 'open' ? 0 : 1);
			$request->is_mergeable = ($pull->mergeable ? 1 : 0);
			$request->is_merged = ($pull->merged ? 1 : 0);
			$request->user = $pull->user->login;
			$request->avatar_url = $pull->user->avatar_url;
			$request->created_time = JFactory::getDate($pull->created_at, 'GMT')->toSql();
			$request->updated_time = ($pull->updated_at ? JFactory::getDate($pull->updated_at, 'GMT')->toSql() : $this->db->getNullDate());
			$request->closed_time = ($pull->closed_at ? JFactory::getDate($pull->closed_at, 'GMT')->toSql() : $this->db->getNullDate());
			$request->merged_time = ($pull->merged_at ? JFactory::getDate($pull->merged_at, 'GMT')->toSql() : $this->db->getNullDate());
			$request->data = $pull;

			// Get the milestone foreign key if applicable.
			if (isset($pull->milestone) && !empty($pull->milestone->number))
			{
				// Only look it up once.
				if (empty($this->_milestoneLookup[(int) $pull->milestone->number]))
				{
					$query = $this->db->getQuery(true);
					$query->select('m.milestone_id')
						->from('#__milestones AS m')
						->where('m.github_id = ' . (int) $pull->milestone->number);
					$this->db->setQuery($query, 0, 1);
					$milestoneId = $this->db->loadResult();

					// Add the milestone to the lookup array.
					$this->_milestoneLookup[(int) $pull->milestone->number] = ($milestoneId ? $milestoneId : null);
				}

				$request->milestone_id = (int) $this->_milestoneLookup[(int) $pull->milestone->number];
			}

			if (!$request->check())
			{
				throw new InvalidArgumentException(
					sprintf('Pull request %d did not check out for storage.', $pull->number)
				);
			}

			try
			{
				$request->store();
			}
			catch (RuntimeException $e)
			{
				throw new RuntimeException(
					sprintf('Pull request %d was unable to be stored.  Error message `%s`.', $pull->number, $e->getMessage())
				);
			}
		}
	}

	/**
	 * Set the filtering for the query.
	 *
	 * @param   JDatabaseQuery  $query  The query on which to set the filtering.
	 *
	 * @return  JDatabaseQuery
	 *
	 * @since   1.0
	 */
	private function _setFiltering(JDatabaseQuery $query)
	{
		// Set the state filter.
		$state = $this->state->get('list.filter.state');
		if ($state !== null)
		{
			$query->where('r.state = ' . ($state ? 1 : 0));
		}

		// Set the mergeable filter.
		$mergeable = $this->state->get('list.filter.mergeable');
		if ($mergeable !== null)
		{
			$query->where('r.is_mergeable = ' . ($mergeable ? 1 : 0));
		}

		// Set the merged filter.
		$merged = $this->state->get('list.filter.merged');
		if ($merged !== null)
		{
			$query->where('r.is_merged = ' . ($merged ? 1 : 0));
		}

		// Set the user filter.
		$user = $this->state->get('list.filter.user');
		if ($user !== null)
		{
			$query->where('r.user = ' . $query->q($user));
		}

		// Set the pending tests filter if required.
		if ($this->state->get('list.filter.pending_tests', 0))
		{
			$query->where('((t.tested_time IS NULL) OR (r.updated_time > t.tested_time))');
		}

		return $query;
	}

	/**
	 * Set the sorting clause for the query.
	 *
	 * @param   JDatabaseQuery  $query  The query on which to set the sorting clause.
	 *
	 * @return  JDatabaseQuery
	 *
	 * @since   1.0
	 */
	private function _setSorting(JDatabaseQuery $query)
	{
		// Get the sorting direction.
		$direction = $this->state->get('list.sort.direction', 'down');
		$direction = ($direction == 'down') ? 'DESC' : 'ASC';

		switch ($this->state->get('list.sort.mode'))
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
}
