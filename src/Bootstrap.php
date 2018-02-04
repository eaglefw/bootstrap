<?php

/**
 * MIT License
 * Copyright (c) 2018 EagleFw
 * http://www.eaglefw.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Eagle;

use Phalcon\Events\Manager as EventsManager;
use Phalcon\Loader;
use Phalcon\Config;
use Phalcon\Exception;
use Phalcon\Di;
use Phalcon\Config\Adapter\Json;
use Phalcon\Config\Adapter\Php;
use Phalcon\Config\Adapter\Yaml;
use Phalcon\Di\FactoryDefault;
use Eagle\Bootstrap\Exception as BootstrapException;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Text;
use Phalcon\Security;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Events\Event;
use Phalcon\Session\Adapter\Files as SessionAdapter;
use Phalcon\Cache\Frontend\Data as FrontendData;
use Phalcon\Cache\Backend\File as BackendFile;

/**
 * Class Bootstrap
 * @package Eagle
 */

class Bootstrap {

	/**
	 * @var string - App directory path
	 */

	protected $appDirectory;

	/**
	 * @var Config - Application configuration
	 */

	protected $configuration;

	/**
	 * @var Di - Application dependency injector
	 */

	protected $dependencyContainer;

	/**
	 * @var boolean
	 */

	protected $pluginsDisabled = false;


	/**
	 * Bootstrap constructor.
	 */

	public function __construct($di = FactoryDefault::class) {

		$dependencyContainer = new $di();

		$this->dependencyContainer = $dependencyContainer;

	}

	/**
	 * Adding configuration to application
	 *
	 * @throws Exception
	 */

	public function addConfiguration($file, $adapter = self::CONFIG_TYPE_PHP) {

		if(!class_exists($adapter))
			throw new Config\Exception('Configuration adapter ' . $adapter . ' doesn\'t exist.');

		if(!file_exists($file))
			throw new Config\Exception('Configuration file ' . $file . ' doesn\'t exist.');

		$configuration = new $adapter($file);

		if(!isset($this->configuration))
			$this->configuration = $configuration;

		else
			$this->configuration->merge($configuration);

	}

	/**
	 * Get configuration
	 *
	 * @throws Exception
	 * @return Config
	 */

	protected function getConfiguration() {

		if(!isset($this->_config))
			throw new Config\Exception('No configuration available.');

		return $this->_config;
	}

	const CONFIG_TYPE_YAML = Yaml::class;

	const CONFIG_TYPE_PHP = Php::class;

	const CONFIG_TYPE_JSON = Json::class;

	/**
	 * Create autoloader
	 *
	 * @return Loader
	 */

	public function createAutoloader() {

		$autoloader = new Loader();

		/*
		foreach($this->getRegisterModules() as $module) {

			$autoloader->registerNamespaces([
				$module . '\Controllers' => $this->_rootPath . '/app/modules/' . $module . 'Module/controllers',
				$module . '\Library' => $this->_rootPath . '/app/modules/' . $module . 'Module/library',
			], true);
		}
		*/

		return $autoloader;
	}

	/**
	 * Get registered modules
	 *
	 * @throws Exception
	 * @return array
	 */

	protected function getRegisterModules() {

		$application = $this->configuration->get('application');

		if(is_null($application))
			throw new Exception('Application configuration is not provided.');

		$modules = $application->get('modules');

		if(is_null($modules))
			throw new Exception('Modules are not registered in application configuration.');

		return $modules;
	}

	/**
	 * Set app directory
	 *
	 * @param $path
	 *
	 * @throws Exception
	 */

	public function setAppDirectory($path) {

		if(!is_dir($path))
			throw new BootstrapException($path . ' is not a directory.');

		$this->appDirectory = $path;

		if(!is_writable($path . '/temp'))
			throw new BootstrapException('Application doesn\'t  have permissions to write temp directory.');

	}

	/**
	 * Get dependency container
	 *
	 * @return Di
	 */

	public function createDependencyContainer() {

		/**
		 * Volt Compiler
		 */

		$this->dependencyContainer->set('voltCompiler', function($view, $di) {

			$volt = new Volt($view, $di);

			$volt->setOptions([
				'compiledPath'      => APP_PATH . '/cache/volt/',
				'compiledExtension' => '.php'
			]);

			return $volt;

		});

		/**
		 * View class
		 */

		$this->dependencyContainer->set('view', function() {

			$view = new View();

			$view->setLayout('main');

			$view->registerEngines(
				[
					'.volt' => 'voltCompiler',
				]
			);

			return $view;

		}, true);

		/**
		 * Dispatcher
		 */

		$this->dependencyContainer->set('dispatcher', function() {

			$eventsManager = new EventsManager();

			$eventsManager->attach('dispatch', function($event, Dispatcher $dispatcher) {

				$actionName = Text::camelize($dispatcher->getActionName());

				$dispatcher->setActionName(lcfirst($actionName));

				$controller = Text::camelize($dispatcher->getControllerName());

				$dispatcher->setControllerName(lcfirst($controller));

			});

			$eventsManager->attach('dispatch:beforeException', function (Event $event, Dispatcher $dispatcher, Exception $exception) {

				switch ($exception->getCode()) {

					case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
					case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:


						$dispatcher->forward([
							'controller' => 'error',
							'action' => 'notFoundException'
						]);

						return false;

					default:

						if(Debug::isDebugMode()) {

							$dispatcher->forward(
								array(
									'controller' => 'error',
									'action' => 'uncaughtException',
								)
							);

							return false;
						}

				}

				return true;

			});

			$dispatcher = new Dispatcher();

			$dispatcher->setEventsManager($eventsManager);

			return $dispatcher;
		});

		/**
		 * Session adapter
		 */

		$this->dependencyContainer->set('session', function() {

			$session = new SessionAdapter();

			$session->setOptions([
				'uniqueId' => $this->configuration->get('application')->get('prefix')
			]);

			$session->start();

			return $session;

		}, true);


		// Set the models cache service

		$this->dependencyContainer->set('modelsCache', function () {

			$frontCache = new FrontendData(
				[
					'lifetime' => 86400,
				]
			);

			$cache = new BackendFile(
				$frontCache,
				[
					'cacheDir' => $this->appDirectory . '/cache/db/'
				]
			);

			return $cache;
		});

		/**
		 * Set up the flash session service
		 */

		$this->dependencyContainer->set('cookies', function() {

			$cookies = new \Phalcon\Http\Response\Cookies();

			$cookies->useEncryption(false);

			return $cookies;

		}, true);

		$this->dependencyContainer->set('security', function() {

			$security = new Security();

			$security->setWorkFactor(12);

			return $security;

		}, true);


		return $this->dependencyContainer;
	}

	public function disablePlugins() {

		$this->pluginsDisabled = true;
	}

	public function enablePlugins() {

		$this->pluginsDisabled = false;
	}

}