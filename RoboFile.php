<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * Download robo.phar from http://robo.li/robo.phar and type in the root of the repo: $ php robo.phar
 * Or do: $ composer update, and afterwards you will be able to execute robo like $ php vendor/bin/robo
 *
 * @copyright  Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @see        http://robo.li/
 *
 * @since      __DEPLOY_VERSION__
 */

require_once __DIR__ . '/tests/codeception/vendor/autoload.php';

if (!defined('JPATH_TESTING_BASE'))
{
	// Base path for tests
	define('JPATH_TESTING_BASE', __DIR__ . '/tests/codeception');
}

if (!defined('JPATH_BASE'))
{
	// Base path for Jorobo tasks
	define('JPATH_BASE', __DIR__);
}

use Joomla\Testing\Robo\RoboFile\RoboFileBase;

/**
 * Base Robo File for extension testing
 *
 * @package     Weblinks
 * @subpackage  Testing
 *
 * @since       __DEPLOY_VERSION__
 */
final class RoboFile extends RoboFileBase
{
	/**
	 * @var array | null
	 * @since  __DEPLOY_VERSION__
	 */
	private $suiteConfig;

	/**
	 * Check the code style of the project against a passed sniffers using PHP_CodeSniffer_CLI
	 *
	 * @param   string  $sniffersPath  Path to the sniffers. If not provided Joomla Coding Standards will be used.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function checkCodestyle($sniffersPath = null)
	{
		if (is_null($sniffersPath))
		{
			$sniffersPath = __DIR__ . '/.tmp/coding-standards';
		}

		$this->taskCodeChecks()
			->setBaseRepositoryPath(__DIR__)
			->setCodeStyleStandardsRepo('photodude/coding-standards')
			->setCodeStyleStandardsBranch('phpcs-2')
			->setCodeStyleStandardsFolder($sniffersPath)
			->setCodeStyleCheckFolders(
				array(
					'tests/codeception/joomla-cms3'
				)
			)
			->checkCodeStyle()
			->run();
	}

	/**
	 * Build the Joomla CMS
	 *
	 * @since   __DEPLOY_VERSION__
	 *
	 * @return  bool  This is always true
	 */
	public function build()
	{
		return true;
	}

	/**
	 * Creates a testing Joomla site for running the tests (use it before run:test)
	 *
	 * @param   bool    $useHtaccess             (1/0) Rename and enable embedded Joomla .htaccess file
	 * @param   string  $appendCertificatesPath  Path to add extra certificates to the Joomla pem file
	 *
	 * @since   __DEPLOY_VERSION__
	 *
	 * @return  void
	 */
	public function createTestingSite($useHtaccess = false, $appendCertificatesPath = '')
	{
		// Clean old testing site
		if (is_dir($this->configuration->getCmsPath()))
		{
			try
			{
				$this->taskDeleteDir($this->configuration->getCmsPath())->run();
			}
			catch (Exception $e)
			{
				// Sorry, we tried :(
				$this->say('Sorry, you will have to delete ' . $this->configuration->getCmsPath() . ' manually.');

				exit(1);
			}
		}

		$this->build();

		$exclude = array('tests', 'tests-phpunit', '.run', '.github', '.git');

		$this->copyJoomla($this->configuration->getCmsPath(), $exclude);

		// Optionally change owner to fix permissions issues
		if (!empty($this->configuration->getCmsPathOwner()))
		{
			$this->_exec('chown -R ' . $this->configuration->getCmsPathOwner() . ' ' . $this->configuration->getCmsPath());
		}

		// Optionally uses Joomla default htaccess file. Used by TravisCI
		if ($useHtaccess == true)
		{
			$this->say("Renaming htaccess.txt to .htaccess");
			$this->_copy('./htaccess.txt', $this->configuration->getCmsPath() . '/.htaccess');
			$this->_exec(
				'sed -e "s,# RewriteBase /,RewriteBase /tests/codeception/joomla-cms3/,g" -in-place tests/codeception/joomla-cms3/.htaccess'
			);
		}
	}

	/**
	 * Copy the joomla installation excluding folders
	 *
	 * @param   string $dst     Target folder
	 * @param   array  $exclude Exclude list of folders
	 *
	 * @throws  Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 *
	 * @return  void
	 */
	protected function copyJoomla($dst, $exclude = array())
	{
		$dir = @opendir(".");

		if (false === $dir)
		{
			throw new Exception($this, "Cannot open source directory");
		}

		if (!is_dir($dst))
		{
			mkdir($dst, 0755, true);
		}

		while (false !== ($file = readdir($dir)))
		{
			if (in_array($file, $exclude))
			{
				continue;
			}

			if (($file !== '.') && ($file !== '..'))
			{
				$srcFile  = "." . '/' . $file;
				$destFile = $dst . '/' . $file;

				if (is_dir($srcFile))
				{
					$this->_copyDir($srcFile, $destFile);
				}
				else
				{
					copy($srcFile, $destFile);
				}
			}
		}

		closedir($dir);
	}

	/**
	 * Function for actual execution of the test suites of this extension
	 *
	 * @param   array  $opts  Array of configuration options:
	 *                        - 'env': set a specific environment to get configuration from
	 *                        - 'debug': executes codeception tasks with extended debug
	 *
	 * @return void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function runTestSuites(
		$opts = array(
			'env' => 'desktop',
			'debug' => false
		)
	)
	{
		$this->runCodeceptionSuite(
			'acceptance',
			'install',
			$opts['debug'],
			$opts['env']
		);
		$this->runCodeceptionSuite(
			'acceptance',
			'content.feature',
			$opts['debug'],
			$opts['env']
		);
		$this->runCodeceptionSuite(
			'acceptance',
			'users.feature',
			$opts['debug'],
			$opts['env']
		);
		$this->runCodeceptionSuite(
			'acceptance',
			'users_frontend.feature',
			$opts['debug'],
			$opts['env']
		);
		$this->runCodeceptionSuite(
			'acceptance',
			'banner.feature',
			$opts['debug'],
			$opts['env']
		);
		$this->runCodeceptionSuite(
			'acceptance',
			'extensions.feature',
			$opts['debug'],
			$opts['env']
		);
		$this->runCodeceptionSuite(
			'acceptance',
			'category.feature',
			$opts['debug'],
			$opts['env']
		);
		$this->runCodeceptionSuite(
			'acceptance',
			'administrator',
			$opts['debug'],
			$opts['env']
		);
		$this->runCodeceptionSuite(
			'acceptance',
			'frontend',
			$opts['debug'],
			$opts['env']
		);
	}

	/**
	 * Executes the extension packager for this extension
	 *
	 * @param   array  $params  Additional parameters
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function prepareTestingPackage($params = array('dev' => false))
	{
	}

	/**
	 * Detect the correct driver for selenium
	 *
	 * @return  string the webdriver string to use with selenium
	 *
	 * @since version
	 */
	public function getWebdriver()
	{
		$suiteConfig        = $this->getSuiteConfig();
		$codeceptMainConfig = \Codeception\Configuration::config();
		$browser            = $suiteConfig['modules']['config']['JoomlaBrowser']['browser'];

		if ($browser == 'chrome')
		{
			$driver['type'] = 'webdriver.chrome.driver';
		}
		elseif ($browser == 'firefox')
		{
			$driver['type'] = 'webdriver.gecko.driver';
		}
		elseif ($browser == 'MicrosoftEdge')
		{
			$driver['type'] = 'webdriver.edge.driver';

			// Check if we are using Windows Insider builds
			if ($suiteConfig['modules']['config']['AcceptanceHelper']['MicrosoftEdgeInsiders'])
			{
				$browser = 'MicrosoftEdgeInsiders';
			}
		}
		elseif ($browser == 'internet explorer')
		{
			$driver['type'] = 'webdriver.ie.driver';
		}

		// Check if we have a path for this browser and OS in the codeception settings
		if (isset($codeceptMainConfig['webdrivers'][$browser][$this->getOs()]))
		{
			$driverPath = $codeceptMainConfig['webdrivers'][$browser][$this->getOs()];
		}
		else
		{
			$this->yell('No driver for your browser. Check your browser in acceptance.suite.yml and the webDrivers in codeception.yml');

			// We can't do anything without a driver, exit
			exit(1);
		}

		$driver['path'] = $driverPath;

		return '-D' . implode('=', $driver);
	}

	/**
	 * Return the os name
	 *
	 * @return string
	 *
	 * @since version
	 */
	private function getOs()
	{
		$os = php_uname('s');

		if (strpos(strtolower($os), 'windows') !== false)
		{
			$os = 'windows';
		}
		// Who have thought that Mac is actually Darwin???
		elseif (strpos(strtolower($os), 'darwin') !== false)
		{
			$os = 'mac';
		}
		else
		{
			$os = 'linux';
		}

		return $os;
	}

	/**
	 * Get the suite configuration
	 *
	 * @param   string  $suite  Suite
	 *
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function getSuiteConfig($suite = 'acceptance')
	{
		if (!$this->suiteConfig)
		{
			$this->suiteConfig = Symfony\Component\Yaml\Yaml::parse(file_get_contents("tests/codeception/{$suite}.suite.yml"));
		}

		return $this->suiteConfig;
	}

	/**
	 * Create the database
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function createDatabase()
	{
		$suiteConfig = $this->getSuiteConfig();

		$host   = $suiteConfig['modules']['config']['JoomlaBrowser']['database host'];
		$user   = $suiteConfig['modules']['config']['JoomlaBrowser']['database user'];
		$pass   = $suiteConfig['modules']['config']['JoomlaBrowser']['database password'];
		$dbName = $suiteConfig['modules']['config']['JoomlaBrowser']['database name'];

		// Create connection
		$connection = new mysqli($host, $user, $pass);

		// Check connection
		if ($connection->connect_error)
		{
			$this->yell("Connection failed: " . $connection->connect_error);
		}

		// Create database
		$sql = "CREATE DATABASE IF NOT EXISTS {$dbName}";
		if ($connection->query($sql) === true)
		{
			$this->say("Database {$dbName} created successfully");
		}
		else
		{
			$this->yell("Error creating database: " . $connection->error);
		}
	}
}
