<?php

/**
 * Test: Kdyby\Autowired\AutowireProperties.
 *
 * @testCase KdybyTests\Autowired\AutowirePropertiesTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Autowired
 */

namespace KdybyTests\Autowired;

use Kdyby;
use Nette;
use Nette\DI;
use Nette\PhpGenerator\PhpLiteral;
use Tester;
use Tester\Assert;
use KdybyTests\Autowired\UseExpansion\ImportedService as AliasedService;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class AutowirePropertiesTest extends ContainerTestCase
{

	/**
	 * @var Nette\DI\Container
	 */
	private $container;



	protected function setUp()
	{
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('sampleFactory')
			->setFactory('KdybyTests\Autowired\SampleService', [new PhpLiteral('$name'), new PhpLiteral('$secondName')])
			->setParameters(['name', 'secondName' => NULL])
			->setImplement('KdybyTests\Autowired\ISampleServiceFactory')
			->setAutowired(TRUE);

		$builder->addDefinition('sample')
			->setClass('KdybyTests\Autowired\SampleService', ['shared']);

		$builder->addDefinition('importedService')
			->setClass('KdybyTests\Autowired\UseExpansion\ImportedService');

		$builder->addDefinition('cacheStorage')
			->setClass('Nette\Caching\Storages\MemoryStorage');

		$this->container = $this->compileContainer($builder);
	}



	public function testFunctional()
	{
		$presenter = new DummyPresenter();
		Assert::null($presenter->service);
		Assert::null($presenter->factoryResult);
		Assert::null($presenter->secondFactoryResult);

		$this->container->callMethod([$presenter, 'injectProperties']);

		Assert::true($presenter->service instanceof SampleService);
		Assert::same(['shared'], $presenter->service->args);

		Assert::true($presenter->factoryResult instanceof SampleService);
		Assert::same(['string argument', NULL], $presenter->factoryResult->args);

		Assert::true($presenter->secondFactoryResult instanceof SampleService);
		Assert::same(['string argument', 'and another'], $presenter->secondFactoryResult->args);
	}



	public function testMissingServiceException_var()
	{
		$container = $this->container;

		Assert::exception(function () use ($container) {
			$presenter = new WithMissingServicePresenter_ap();
			Assert::null($presenter->service);
			$container->callMethod([$presenter, 'injectProperties']);
		}, 'Kdyby\Autowired\MissingClassException', 'Class "SampleMissingService12345" was not found, please check the typehint on KdybyTests\Autowired\WithMissingServicePresenter_ap::$service in annotation @var.');
	}



	public function testMissingServiceException_factory()
	{
		$container = $this->container;

		Assert::exception(function () use ($container) {
			$presenter = new WithMissingServiceFactoryPresenter_ap();
			Assert::null($presenter->secondFactoryResult);
			$container->callMethod([$presenter, 'injectProperties']);
		}, 'Kdyby\Autowired\MissingClassException', 'Class "SampleMissingService12345" was not found, please check the typehint on KdybyTests\Autowired\WithMissingServiceFactoryPresenter_ap::$secondFactoryResult in annotation @autowire.');
	}



	public function testTraitUserIsDescendantOfPresenterComponent()
	{
		$container = $this->container;

		Assert::exception(function () use ($container) {
			$component = new NonPresenterComponent_ap();
			$container->callMethod([$component, 'injectProperties']);
		}, 'Kdyby\Autowired\MemberAccessException', 'Trait Kdyby\Autowired\AutowireProperties can be used only in descendants of PresenterComponent.');
	}



	public function testPrivateAutowiredPropertyException()
	{
		$container = $this->container;

		Assert::exception(function () use ($container) {
			$component = new PrivateAutowiredPropertyPresenter();
			$container->callMethod([$component, 'injectProperties']);
		}, 'Kdyby\Autowired\MemberAccessException', 'Autowired properties must be protected or public. Please fix visibility of KdybyTests\Autowired\PrivateAutowiredPropertyPresenter::$service or remove the @autowire annotation.');
	}



	public function testWrongCasePropertyAnnotationException()
	{
		$container = $this->container;

		Assert::exception(function () use ($container) {
			$component = new WrongCasePropertyAnnotationPresenter();
			$container->callMethod([$component, 'injectProperties']);
		}, 'Kdyby\Autowired\UnexpectedValueException', 'Annotation @Autowire on KdybyTests\Autowired\WrongCasePropertyAnnotationPresenter::$service should be fixed to lowercase @autowire.');
	}



	public function testTypoPropertyAnnotationException()
	{
		$container = $this->container;

		Assert::exception(function () use ($container) {
			$component = new TypoPropertyAnnotationPresenter();
			$container->callMethod([$component, 'injectProperties']);
		}, 'Kdyby\Autowired\UnexpectedValueException', 'Annotation @autowired on KdybyTests\Autowired\TypoPropertyAnnotationPresenter::$service should be fixed to lowercase @autowire.');
	}

	public function testUseExpansion()
	{
		$presenter = new PropertyWithUsePresenter();

		$this->container->callMethod([$presenter, 'injectProperties']);
		Assert::true($presenter->service instanceof AliasedService);
	}

	public function testTraitUsage()
	{
		$presenter = new WithTraitPresenter();

		$this->container->callMethod([$presenter, 'injectProperties']);
		Assert::true($presenter->service instanceof SampleService);
	}

}



class DummyPresenter extends Nette\Application\UI\Presenter
{

	use Kdyby\Autowired\AutowireProperties;

	/**
	 * @var SampleService
	 * @autowire
	 */
	public $service;

	/**
	 * @var SampleService
	 * @autowire("string argument", factory=\KdybyTests\Autowired\ISampleServiceFactory)
	 */
	public $factoryResult;

	/**
	 * @var SampleService
	 * @autowire("string argument", "and another", factory=\KdybyTests\Autowired\ISampleServiceFactory)
	 */
	public $secondFactoryResult;

}


class WithMissingServicePresenter_ap extends Nette\Application\UI\Presenter
{

	use Kdyby\Autowired\AutowireProperties;

	/**
	 * @var \SampleMissingService12345
	 * @autowire
	 */
	public $service;

}



class WithMissingServiceFactoryPresenter_ap extends Nette\Application\UI\Presenter
{

	use Kdyby\Autowired\AutowireProperties;

	/**
	 * @var SampleService
	 * @autowire("string argument", "and another", factory=\SampleMissingService12345)
	 */
	public $secondFactoryResult;

}


class NonPresenterComponent_ap
{
	use Kdyby\Autowired\AutowireProperties;
}



class PrivateAutowiredPropertyPresenter extends Nette\Application\UI\Presenter
{

	use Kdyby\Autowired\AutowireProperties;

	/**
	 * @var SampleService
	 * @autowire
	 */
	private $service;

}



class WrongCasePropertyAnnotationPresenter extends Nette\Application\UI\Presenter
{

	use Kdyby\Autowired\AutowireProperties;

	/**
	 * @var SampleService
	 * @Autowire
	 */
	public $service;

}



class TypoPropertyAnnotationPresenter extends Nette\Application\UI\Presenter
{

	use Kdyby\Autowired\AutowireProperties;

	/**
	 * @var SampleService
	 * @autowired
	 */
	public $service;

}



class PropertyWithUsePresenter extends Nette\Application\UI\Presenter
{

	use Kdyby\Autowired\AutowireProperties;

	/**
	 * @var AliasedService
	 * @autowire
	 */
	public $service;

}



class WithTraitPresenter extends Nette\Application\UI\Presenter
{

	use Kdyby\Autowired\AutowireProperties;
	use \KdybyTests\Autowired\UseExpansion\TraitSample;

}



class SampleService
{
	public $args;

	public function __construct($name, $secondName = NULL)
	{
		$this->args = func_get_args();
	}
}



interface ISampleServiceFactory
{
	/** @return SampleService */
	function create($name, $secondName = NULL);
}


run(new AutowirePropertiesTest());

namespace KdybyTests\Autowired\UseExpansion;

use KdybyTests\Autowired\SampleService as AliasedService;

class ImportedService
{

}

trait TraitSample
{

	/**
	 * @var AliasedService
	 * @autowire
	 */
	public $service;

}
