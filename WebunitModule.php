<?php

class WebunitModule extends CWebModule
{
	/**
	 * @var string the password that can be used to access WebunitModule.
	 * If this property is set false, then WebunitModule can be accessed without password
	 * (DO NOT DO THIS UNLESS YOU KNOW THE CONSEQUENCE!!!)
	 */
	public $password;

	/**
	 * @var array the IP filters that specify which IP addresses are allowed to access WebunitModule.
	 * Each array element represents a single filter. A filter can be either an IP address
	 * or an address with wildcard (e.g. 192.168.0.*) to represent a network segment.
	 * If you want to allow all IPs to access gii, you may set this property to be false
	 * (DO NOT DO THIS UNLESS YOU KNOW THE CONSEQUENCE!!!)
	 * The default value is array('127.0.0.1', '::1'), which means GiiModule can only be accessed
	 * on the localhost.
	 */
	public $ipFilters = array('127.0.0.1', '::1');

	/**
	 * @var string path where unit tests are located as Yii path alias
	 */
	public $pathUnitTests = 'application.tests.unit';

	/**
	 * @var string path where functional tests are located as Yii path alias
	 */
	public $pathWebTests  = 'application.tests.functional';

	/**
	 * @var bool If true use extension built-in PHPUnit otherwise use default
	 */
	public $useBuiltInPhpUnit = true;

	/**
	 * @var bool True if PHP errors must be handled by webunit
	 */
	public $registerShutdownHandler = true;

	/**
	 * If you want to override application components config when this extension is loaded
	 * @example
	 * 'componentsConfig' => [
	 *     'db' => [
	 *         'connectionString' => 'mysql:host=localhost;dbname=my_site_tests',
	 *         'username' => 'user',
	 *         'password' => 'password',
	 *     ]
	 * ]
	 * @var array
	 */
	public $componentsConfig = array();

	/**
	 * @var array mapping from controller ID to controller configurations.
	 * Please refer to {@link CWebApplication::controllerMap} for more details.
	 */
	public $controllerMap = array(
		'default' => 'ext.webunit.controllers.WuController',
	);

	private $_assetsUrl;

	public function init()
	{
		parent::init();

		$this->setImport(array(
			'webunit.components.*',
			'webunit.models.*',
			'application.tests.*',
			'system.test.*',
		));

		$this->overrideComponents();

		if ($this->useBuiltInPhpUnit) {
			require_once dirname(__FILE__) . '/vendor/autoload.php';
		}

		if ($this->registerShutdownHandler) {
			$this->registerShutdown();
		}
	}

	/**
	 * @return string the base URL that contains all published asset files of webunit.
	 */
	public function getAssetsUrl()
	{
		if ($this->_assetsUrl === NULL)
			$this->_assetsUrl = Yii::app()->getAssetManager()->publish(
				dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets'
			);
		return $this->_assetsUrl;
	}

	/**
	 * @param string $value the base URL that contains all published asset files of webunit.
	 */
	public function setAssetsUrl($value)
	{
		$this->_assetsUrl = $value;
	}

	/**
	 * Performs access check to webunit.
	 * This method will check to see if user IP and password are correct if they attempt
	 * to access actions other than "default/login" and "default/error".
	 * @param CController $controller the controller to be accessed.
	 * @param CAction $action the action to be accessed.
	 * @return boolean whether the action should be executed.
	 * @throws CHttpException
	 */
	public function beforeControllerAction($controller, $action)
	{
		if (parent::beforeControllerAction($controller, $action)) {
			$route = $controller->id . '/' . $action->id;
			if (!$this->allowIp(Yii::app()->request->userHostAddress) && $route !== 'default/error')
				throw new CHttpException(403, "You are not allowed to access this page.");

			$publicPages = array(
				'default/login',
				'default/error',
			);
			if ($this->password !== false && Yii::app()->user->isGuest && !in_array($route, $publicPages))
				Yii::app()->user->loginRequired();
			else
				return true;
		}
		return false;
	}

	/**
	 * Checks to see if the user IP is allowed by {@link ipFilters}.
	 * @param string $ip the user IP
	 * @return boolean whether the user IP is allowed by {@link ipFilters}.
	 */
	protected function allowIp($ip)
	{
		if (empty($this->ipFilters))
			return true;
		foreach ($this->ipFilters as $filter) {
			if ($filter === '*' || $filter === $ip || (($pos = strpos($filter, '*')) !== false && !strncmp($ip, $filter, $pos)))
				return true;
		}
		return false;
	}

	private function registerShutdown()
	{
		register_shutdown_function(function() {
			$e = error_get_last();
			$errorsToHandle = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING;

			if(!is_null($e) && ($e['type'] & $errorsToHandle)) {
				$msg = 'Fatal error: '.$e['message'];
				yii::app()->errorHandler->errorAction = null;
				yii::app()->handleError($e['type'], $msg, $e['file'], $e['line']);
			}
			exit(1);
		});
	}

	private function overrideComponents()
	{
		$componentsConfig = CMap::mergeArray(array(
			'user' => array(
				'stateKeyPrefix' => 'webunit',
				'loginUrl' => Yii::app()->createUrl($this->getId() . '/default/login')
			),
			'errorHandler'  => array(
				'class'       => 'CErrorHandler',
				'errorAction' => $this->getId() . '/default/error',
			),
			'fixture' => array(
				'class' => 'system.test.CDbFixtureManager',
			)
		), $this->componentsConfig);

		$loadedComponents = Yii::app()->getComponents();
		foreach ($componentsConfig as $component => $config) {
			if (isset($config['class']) && isset($loadedComponents[$component])) {
				$componentsConfig[$component] = Yii::createComponent($config);
			}
		}

		Yii::app()->setComponents($componentsConfig, true);
	}
}