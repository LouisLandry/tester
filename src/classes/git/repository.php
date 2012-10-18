<?php
/**
 * @package     Joomla.Tester
 * @subpackage  Git
 *
 * @copyright   Copyright (C) 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Joomla Git Repository Class
 *
 * @package     Joomla.Tester
 * @subpackage  Git
 * @since       1.0
 */
class PTGitRepository
{
	/**
	 * @var    string  The filesystem path for the repository root.
	 * @since  1.0
	 */
	private $_root;

	/**
	 * Object Constructor.
	 *
	 * @param   string  $root  The filesystem path for the repository root.
	 *
	 * @since   1.0
	 */
	public function __construct($root)
	{
		$this->_root = $root;
	}

	/**
	 * Get the SHA1 hash for the current HEAD commit.
	 *
	 * @return  string
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getHeadSHA()
	{
		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git rev-parse HEAD', $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('Failed to get the hash for the HEAD commit with code %d and message %s.', $return, implode("\n", $out)));
		}

		return trim(implode($out));
	}

	/**
	 * Check if the repository exists.  If we don't have a hidden git folder for the repository it doesn't exist.
	 *
	 * @return  boolean  True if the repository exists.
	 *
	 * @since   1.0
	 */
	public function exists()
	{
		return file_exists($this->_root . '/.git');
	}

	/**
	 * Clone a repository from a given remote.
	 *
	 * @param   string  $remote  The URI from which to clone the repository.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 * @throws  RuntimeException
	 */
	public function create($remote = 'http://github.com/joomla/joomla-platform.git')
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// We add the users repo to our remote list if it isn't already there
		if (!$this->exists())
		{
			// Execute the command.
			exec('git clone -q ' . escapeshellarg($remote) . ' ' . escapeshellarg($this->_root), $out, $return);
		}
		else
		{
			throw new InvalidArgumentException('Repository already exists at ' . $this->_root . '.');
		}

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('The clone failed from remote %s with code %d and message %s.', $remote, $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Fetch updates from a repository remote.
	 *
	 * @param   string  $remote  The remote name from which to fetch changes.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 * @throws  RuntimeException
	 */
	public function fetch($remote = 'origin')
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// Ensure that either the remote exists or is a valid URL.
		if (!filter_var($remote, FILTER_VALIDATE_URL) && !in_array($remote, $this->_getRemotes()))
		{
			throw new InvalidArgumentException('No valid remote ' . $remote . ' exists.');
		}

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git fetch -q ' . escapeshellarg($remote), $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('The fetch failed from remote %s with code %d and message %s.', $remote, $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Merge a branch by name.
	 *
	 * @param   string  $branch  The name of the branch to merge.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function merge($branch = 'origin/master')
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git merge ' . escapeshellarg($branch), $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('Unable to merge branch %s with code %d and message %s.', $branch, $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Add a remote to the repository.
	 *
	 * @param   string  $name  The name of the remote to add.
	 * @param   string  $url   The URI of the remote to add.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 * @throws  RuntimeException
	 */
	public function remoteAdd($name = 'joomla', $url = 'git@github.com:joomla/joomla-platform.git')
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// Ensure that the remote doesn't already exist.
		if (in_array($name, $this->_getRemotes()))
		{
			throw new InvalidArgumentException('The remote ' . $name . ' already exists.');
		}

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git remote add ' . escapeshellarg($name) . ' ' . escapeshellarg($url), $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(
				sprintf('The remote %s could not be added from %s with code %d and message %s.', $name, $url, $return, implode("\n", $out))
			);
		}

		return $this;
	}

	/**
	 * Check if a remote exists for the repository by name.
	 *
	 * @param   string  $name  The remote name to check.
	 *
	 * @return  boolean  True if the remote exists.
	 *
	 * @since   1.0
	 */
	public function remoteExists($name = 'joomla')
	{
		return in_array($name, $this->_getRemotes());
	}

	/**
	 * Set the remote URL for the repository by name.
	 *
	 * @param   string  $name  The name of the remote to change.
	 * @param   string  $url   The URI of the remote to set.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 * @throws  RuntimeException
	 */
	public function remoteSetUrl($name = 'joomla', $url = 'git@github.com:joomla/joomla-platform.git')
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// Ensure that the remote already exists.
		if (!in_array($name, $this->_getRemotes()))
		{
			throw new InvalidArgumentException('The remote ' . $name . ' doesn\'t exist.  Try adding it.');
		}

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git remote set-url ' . escapeshellarg($name) . ' ' . escapeshellarg($url), $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(
				sprintf('Could not set the url %s for remote %s. Error code %d and message %s.', $url, $name, $return, implode("\n", $out))
			);
		}

		return $this;
	}

	/**
	 * Remove a remote from the repository by name.
	 *
	 * @param   string  $name  The remote name to remove.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function remoteRemove($name = 'joomla')
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// If the remote doesn't already exist we have nothing to do.
		if (!in_array($name, $this->_getRemotes()))
		{
			return $this;
		}

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git remote rm ' . escapeshellarg($name), $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('The remote %s could not be removed with code %d and message %s.', $name, $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Check out a branch by name.
	 *
	 * @param   string  $name  The branch name to checkout.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function branchCheckout($name = 'staging')
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git checkout -q ' . escapeshellarg($name), $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('Branch %s could not be checked out with code %d and message %s.', $name, $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Create a branch on the repository.
	 *
	 * @param   string  $name          The name for the new branch to create.
	 * @param   string  $parent        The name of the branch from which we are creating.
	 * @param   string  $parentRemote  The name of the remote from which we are creating [optional for a local branch].
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  InvalidArgumentException
	 * @throws  RuntimeException
	 */
	public function branchCreate($name = 'staging', $parent = 'master', $parentRemote = null)
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// Ensure that the branch doesn't already exist.
		if (in_array($name, $this->_getBranches()))
		{
			throw new InvalidArgumentException('The branch ' . $name . ' already exists.');
		}

		// If we have a parent remote then fetch latest updates and set up the parent.
		if (!empty($parentRemote))
		{
			$this->fetch($parentRemote);

			$parent = $parentRemote . '/' . $parent;
		}

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git checkout -b ' . escapeshellarg($name) . ' ' . escapeshellarg($parent), $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('Branch %s could not be created with code %d and message %s.', $name, $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Check if a local branch exists for the repository by name.
	 *
	 * @param   string  $name  The branch name to check.
	 *
	 * @return  boolean  True if the remote exists.
	 *
	 * @since   1.0
	 */
	public function branchExists($name = 'joomla')
	{
		return in_array($name, $this->_getBranches());
	}

	/**
	 * Remove a branch from the repository.
	 *
	 * @param   string  $name  The branch name to remove.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function branchRemove($name = 'staging')
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// If the branch doesn't already exist we have nothing to do.
		if (!in_array($name, $this->_getBranches()))
		{
			return $this;
		}

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git branch -D ' . escapeshellarg($name), $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('Branch %s could not be removed with code %d and message %s.', $name, $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Clean the repository of untracked files and folders.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function clean()
	{
		// Initialize variables.
		$out = array();
		$return = null;

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git clean -fd', $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('Failure cleaning the repository with code %d and message %s.', $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Reset the current repository branch.
	 *
	 * @param   boolean  $hard  True to perform a hard reset.
	 *
	 * @return  PackagerGitRepository  This repository object for chaining.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function reset($hard = true)
	{
		// Initialize variables.
		$out = array();
		$return = null;

		$flag = $hard ? ' --hard' : '';

		// Execute the command.
		$wd = getcwd();
		chdir($this->_root);
		exec('git reset' . $flag, $out, $return);
		chdir($wd);

		// Validate the response.
		if ($return !== 0)
		{
			throw new RuntimeException(sprintf('Failure resetting the repository with code %d and message %s.', $return, implode("\n", $out)));
		}

		return $this;
	}

	/**
	 * Get a list of the repository local branch names.
	 *
	 * @return  array
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	private function _getBranches()
	{
		// If we don't have a configuration file for the repository PANIC!
		if (!file_exists($this->_root . '/.git/config'))
		{
			throw new RuntimeException('Not a valid Git repository at ' . $this->_root);
		}

		// Initialize variables.
		$branches = array();

		// Parse the repository configuration file.
		$config = parse_ini_file($this->_root . '/.git/config', true);

		// Go find the remotes from the configuration file.
		foreach ($config as $section => $data)
		{
			if (strpos($section, 'branch ') === 0)
			{
				$branches[] = trim(substr($section, 7));
			}
		}

		return $branches;
	}

	/**
	 * Get a list of the repository remote names.
	 *
	 * @return  array
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	private function _getRemotes()
	{
		// If we don't have a configuration file for the repository PANIC!
		if (!file_exists($this->_root . '/.git/config'))
		{
			throw new RuntimeException('Not a valid Git repository at ' . $this->_root);
		}

		// Initialize variables.
		$remotes = array();

		// Parse the repository configuration file.
		$config = parse_ini_file($this->_root . '/.git/config', true);

		// Go find the remotes from the configuration file.
		foreach ($config as $section => $data)
		{
			if (strpos($section, 'remote ') === 0)
			{
				$remotes[] = trim(substr($section, 7));
			}
		}

		return $remotes;
	}
}
