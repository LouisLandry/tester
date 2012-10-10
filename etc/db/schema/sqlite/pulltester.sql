-- Joomla Pull Request Tester Schema
-- ---------------------------------

-- Base pull request table for tracking the open pull requests.

CREATE TABLE "pt_pull_requests" (
  pull_id INTEGER PRIMARY KEY AUTOINCREMENT,
  github_id INTEGER NOT NULL,
  title TEXT NOT NULL,
  state INTEGER NOT NULL,
  is_mergeable INTEGER NOT NULL,
  is_merged INTEGER NOT NULL,
  user TEXT NOT NULL,
  avatar_url TEXT NOT NULL,
  created_time TEXT NOT NULL,
  updated_time TEXT NOT NULL,
  closed_time TEXT NOT NULL,
  merged_time TEXT NOT NULL,
  data TEXT NOT NULL
);

CREATE TABLE "pt_pull_request_tests" (
  test_id INTEGER PRIMARY KEY AUTOINCREMENT,
  pull_id INTEGER REFERENCES pt_pull_requests(pull_id) ON UPDATE CASCADE ON DELETE CASCADE,
  revision INTEGER NOT NULL,
  tested_time TEXT NOT NULL,
  head_revision TEXT NOT NULL,
  base_revision TEXT NOT NULL,
  data TEXT NOT NULL
);

CREATE TABLE "pt_pull_request_test_checkstyle_reports" (
  report_id INTEGER PRIMARY KEY AUTOINCREMENT,
  pull_id INTEGER REFERENCES pt_pull_requests(pull_id) ON UPDATE CASCADE ON DELETE CASCADE,
  test_id INTEGER REFERENCES pt_pull_request_tests(test_id) ON UPDATE CASCADE ON DELETE CASCADE,
  error_count INTEGER NOT NULL,
  warning_count INTEGER NOT NULL,
  data TEXT NOT NULL
);

CREATE TABLE "pt_pull_request_test_unit_test_reports" (
  report_id INTEGER PRIMARY KEY AUTOINCREMENT,
  pull_id INTEGER REFERENCES pt_pull_requests(pull_id) ON UPDATE CASCADE ON DELETE CASCADE,
  test_id INTEGER REFERENCES pt_pull_request_tests(test_id) ON UPDATE CASCADE ON DELETE CASCADE,
  test_count INTEGER NOT NULL,
  assertion_count INTEGER NOT NULL,
  failure_count INTEGER NOT NULL,
  error_count INTEGER NOT NULL,
  elapsed_time` FLOAT NOT NULL,
  data TEXT NOT NULL
);

CREATE TABLE "pt_repositories" (
  repository_id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT NOT NULL,
  http_url TEXT NOT NULL,
  head_revision TEXT NOT NULL,
  created_time TEXT NOT NULL,
  updated_time TEXT NOT NULL,
  style_error_count INTEGER NOT NULL,
  style_warning_count INTEGER NOT NULL,
  test_failure_count INTEGER NOT NULL,
  test_error_count INTEGER NOT NULL,
  data TEXT NOT NULL
);

INSERT INTO "pt_repositories" ("repository_id", "title", "description", "http_url", "created_time", "updated_time", "style_error_count", "style_warning_count", "test_failure_count", "test_error_count", "data")
VALUES
	(1, 'Joomla Platform', 'The Joomla Platform is a platform for writing Web and command line applications in PHP.', 'https://github.com/joomla/joomla-platform', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, 0, 0, 0, '');
