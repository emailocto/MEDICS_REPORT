<?php
namespace Illuminate\Support;

class ClassLoader
{
    protected static $directories = array();
    protected static $registered = false;
    public static function load($class)
    {
        $class = static::normalizeClass($class);
        foreach (static::$directories as $directory) {
            if (file_exists($path = $directory . DIRECTORY_SEPARATOR . $class)) {
                require_once $path;
                return true;
            }
        }
        return false;
    }
    public static function normalizeClass($class)
    {
        if ($class[0] == '\\') {
            $class = substr($class, 1);
        }
        return str_replace(array('\\', '_'), DIRECTORY_SEPARATOR, $class) . '.php';
    }
    public static function register()
    {
        if (!static::$registered) {
            static::$registered = spl_autoload_register(array('\\Illuminate\\Support\\ClassLoader', 'load'));
        }
    }
    public static function addDirectories($directories)
    {
        static::$directories = array_unique(array_merge(static::$directories, (array) $directories));
    }
    public static function removeDirectories($directories = null)
    {
        if (is_null($directories)) {
            static::$directories = array();
        } else {
            static::$directories = array_diff(static::$directories, (array) $directories);
        }
    }
    public static function getDirectories()
    {
        return static::$directories;
    }
}
namespace Illuminate\Container;

use Closure;
use ArrayAccess;
use ReflectionClass;
use ReflectionParameter;
class Container implements ArrayAccess
{
    protected $resolved = array();
    protected $bindings = array();
    protected $instances = array();
    protected $aliases = array();
    protected $reboundCallbacks = array();
    protected $resolvingCallbacks = array();
    protected $globalResolvingCallbacks = array();
    protected function resolvable($abstract)
    {
        return $this->bound($abstract) || $this->isAlias($abstract);
    }
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
    public function resolved($abstract)
    {
        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }
    public function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);
            $this->alias($abstract, $alias);
        }
        $this->dropStaleInstances($abstract);
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }
        $this->bindings[$abstract] = compact('concrete', 'shared');
        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }
    protected function getClosure($abstract, $concrete)
    {
        return function ($c, $parameters = array()) use($abstract, $concrete) {
            $method = $abstract == $concrete ? 'build' : 'make';
            return $c->{$method}($concrete, $parameters);
        };
    }
    public function bindIf($abstract, $concrete = null, $shared = false)
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }
    public function share(Closure $closure)
    {
        return function ($container) use($closure) {
            static $object;
            if (is_null($object)) {
                $object = $closure($container);
            }
            return $object;
        };
    }
    public function bindShared($abstract, Closure $closure)
    {
        $this->bind($abstract, $this->share($closure), true);
    }
    public function extend($abstract, Closure $closure)
    {
        if (!isset($this->bindings[$abstract])) {
            throw new \InvalidArgumentException("Type {$abstract} is not bound.");
        }
        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);
            $this->rebound($abstract);
        } else {
            $extender = $this->getExtender($abstract, $closure);
            $this->bind($abstract, $extender, $this->isShared($abstract));
        }
    }
    protected function getExtender($abstract, Closure $closure)
    {
        $resolver = $this->bindings[$abstract]['concrete'];
        return function ($container) use($resolver, $closure) {
            return $closure($resolver($container), $container);
        };
    }
    public function instance($abstract, $instance)
    {
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);
            $this->alias($abstract, $alias);
        }
        unset($this->aliases[$abstract]);
        $bound = $this->bound($abstract);
        $this->instances[$abstract] = $instance;
        if ($bound) {
            $this->rebound($abstract);
        }
    }
    public function alias($abstract, $alias)
    {
        $this->aliases[$alias] = $abstract;
    }
    protected function extractAlias(array $definition)
    {
        return array(key($definition), current($definition));
    }
    public function rebinding($abstract, Closure $callback)
    {
        $this->reboundCallbacks[$abstract][] = $callback;
        if ($this->bound($abstract)) {
            return $this->make($abstract);
        }
    }
    public function refresh($abstract, $target, $method)
    {
        return $this->rebinding($abstract, function ($app, $instance) use($target, $method) {
            $target->{$method}($instance);
        });
    }
    protected function rebound($abstract)
    {
        $instance = $this->make($abstract);
        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            call_user_func($callback, $this, $instance);
        }
    }
    protected function getReboundCallbacks($abstract)
    {
        if (isset($this->reboundCallbacks[$abstract])) {
            return $this->reboundCallbacks[$abstract];
        }
        return array();
    }
    public function make($abstract, $parameters = array())
    {
        $abstract = $this->getAlias($abstract);
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        $concrete = $this->getConcrete($abstract);
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }
        $this->fireResolvingCallbacks($abstract, $object);
        $this->resolved[$abstract] = true;
        return $object;
    }
    protected function getConcrete($abstract)
    {
        if (!isset($this->bindings[$abstract])) {
            if ($this->missingLeadingSlash($abstract) && isset($this->bindings['\\' . $abstract])) {
                $abstract = '\\' . $abstract;
            }
            return $abstract;
        }
        return $this->bindings[$abstract]['concrete'];
    }
    protected function missingLeadingSlash($abstract)
    {
        return is_string($abstract) && strpos($abstract, '\\') !== 0;
    }
    public function build($concrete, $parameters = array())
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }
        $reflector = new ReflectionClass($concrete);
        if (!$reflector->isInstantiable()) {
            $message = "Target [{$concrete}] is not instantiable.";
            throw new BindingResolutionException($message);
        }
        $constructor = $reflector->getConstructor();
        if (is_null($constructor)) {
            return new $concrete();
        }
        $dependencies = $constructor->getParameters();
        $parameters = $this->keyParametersByArgument($dependencies, $parameters);
        $instances = $this->getDependencies($dependencies, $parameters);
        return $reflector->newInstanceArgs($instances);
    }
    protected function getDependencies($parameters, array $primitives = array())
    {
        $dependencies = array();
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();
            if (array_key_exists($parameter->name, $primitives)) {
                $dependencies[] = $primitives[$parameter->name];
            } elseif (is_null($dependency)) {
                $dependencies[] = $this->resolveNonClass($parameter);
            } else {
                $dependencies[] = $this->resolveClass($parameter);
            }
        }
        return (array) $dependencies;
    }
    protected function resolveNonClass(ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        $message = "Unresolvable dependency resolving [{$parameter}] in class {$parameter->getDeclaringClass()->getName()}";
        throw new BindingResolutionException($message);
    }
    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {
            return $this->make($parameter->getClass()->name);
        } catch (BindingResolutionException $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }
            throw $e;
        }
    }
    protected function keyParametersByArgument(array $dependencies, array $parameters)
    {
        foreach ($parameters as $key => $value) {
            if (is_numeric($key)) {
                unset($parameters[$key]);
                $parameters[$dependencies[$key]->name] = $value;
            }
        }
        return $parameters;
    }
    public function resolving($abstract, Closure $callback)
    {
        $this->resolvingCallbacks[$abstract][] = $callback;
    }
    public function resolvingAny(Closure $callback)
    {
        $this->globalResolvingCallbacks[] = $callback;
    }
    protected function fireResolvingCallbacks($abstract, $object)
    {
        if (isset($this->resolvingCallbacks[$abstract])) {
            $this->fireCallbackArray($object, $this->resolvingCallbacks[$abstract]);
        }
        $this->fireCallbackArray($object, $this->globalResolvingCallbacks);
    }
    protected function fireCallbackArray($object, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $object, $this);
        }
    }
    public function isShared($abstract)
    {
        if (isset($this->bindings[$abstract]['shared'])) {
            $shared = $this->bindings[$abstract]['shared'];
        } else {
            $shared = false;
        }
        return isset($this->instances[$abstract]) || $shared === true;
    }
    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }
    protected function getAlias($abstract)
    {
        return isset($this->aliases[$abstract]) ? $this->aliases[$abstract] : $abstract;
    }
    public function getBindings()
    {
        return $this->bindings;
    }
    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }
    public function forgetInstance($abstract)
    {
        unset($this->instances[$abstract]);
    }
    public function forgetInstances()
    {
        $this->instances = array();
    }
    public function offsetExists($key)
    {
        return isset($this->bindings[$key]);
    }
    public function offsetGet($key)
    {
        return $this->make($key);
    }
    public function offsetSet($key, $value)
    {
        if (!$value instanceof Closure) {
            $value = function () use($value) {
                return $value;
            };
        }
        $this->bind($key, $value);
    }
    public function offsetUnset($key)
    {
        unset($this->bindings[$key], $this->instances[$key]);
    }
    public function __get($key)
    {
        return $this[$key];
    }
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }
}
namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
interface HttpKernelInterface
{
    const MASTER_REQUEST = 1;
    const SUB_REQUEST = 2;
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true);
}
namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
interface TerminableInterface
{
    public function terminate(Request $request, Response $response);
}
namespace Illuminate\Support\Contracts;

interface ResponsePreparerInterface
{
    public function prepareResponse($value);
    public function readyForResponses();
}
namespace Illuminate\Foundation;

use Closure;
use Stack\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Config\FileLoader;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Exception\ExceptionServiceProvider;
use Illuminate\Config\FileEnvironmentVariablesLoader;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Illuminate\Support\Contracts\ResponsePreparerInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
class Application extends Container implements HttpKernelInterface, TerminableInterface, ResponsePreparerInterface
{
    const VERSION = '4.2.16';
    protected $booted = false;
    protected $bootingCallbacks = array();
    protected $bootedCallbacks = array();
    protected $finishCallbacks = array();
    protected $shutdownCallbacks = array();
    protected $middlewares = array();
    protected $serviceProviders = array();
    protected $loadedProviders = array();
    protected $deferredServices = array();
    protected static $requestClass = 'Illuminate\\Http\\Request';
    public function __construct(Request $request = null)
    {
        $this->registerBaseBindings($request ?: $this->createNewRequest());
        $this->registerBaseServiceProviders();
        $this->registerBaseMiddlewares();
    }
    protected function createNewRequest()
    {
        return forward_static_call(array(static::$requestClass, 'createFromGlobals'));
    }
    protected function registerBaseBindings($request)
    {
        $this->instance('request', $request);
        $this->instance('Illuminate\\Container\\Container', $this);
    }
    protected function registerBaseServiceProviders()
    {
        foreach (array('Event', 'Exception', 'Routing') as $name) {
            $this->{"register{$name}Provider"}();
        }
    }
    protected function registerExceptionProvider()
    {
        $this->register(new ExceptionServiceProvider($this));
    }
    protected function registerRoutingProvider()
    {
        $this->register(new RoutingServiceProvider($this));
    }
    protected function registerEventProvider()
    {
        $this->register(new EventServiceProvider($this));
    }
    public function bindInstallPaths(array $paths)
    {
        $this->instance('path', realpath($paths['app']));
        foreach (array_except($paths, array('app')) as $key => $value) {
            $this->instance("path.{$key}", realpath($value));
        }
    }
    public static function getBootstrapFile()
    {
        return 'D:\\XAMPP_NEW\\htdocs\\MyFirstLaravel\\laravel\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation' . '/start.php';
    }
    public function startExceptionHandling()
    {
        $this['exception']->register($this->environment());
        $this['exception']->setDebug($this['config']['app.debug']);
    }
    public function environment()
    {
        if (count(func_get_args()) > 0) {
            return in_array($this['env'], func_get_args());
        }
        return $this['env'];
    }
    public function isLocal()
    {
        return $this['env'] == 'local';
    }
    public function detectEnvironment($envs)
    {
        $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : null;
        return $this['env'] = (new EnvironmentDetector())->detect($envs, $args);
    }
    public function runningInConsole()
    {
        return php_sapi_name() == 'cli';
    }
    public function runningUnitTests()
    {
        return $this['env'] == 'testing';
    }
    public function forceRegister($provider, $options = array())
    {
        return $this->register($provider, $options, true);
    }
    public function register($provider, $options = array(), $force = false)
    {
        if ($registered = $this->getRegistered($provider) && !$force) {
            return $registered;
        }
        if (is_string($provider)) {
            $provider = $this->resolveProviderClass($provider);
        }
        $provider->register();
        foreach ($options as $key => $value) {
            $this[$key] = $value;
        }
        $this->markAsRegistered($provider);
        if ($this->booted) {
            $provider->boot();
        }
        return $provider;
    }
    public function getRegistered($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);
        if (array_key_exists($name, $this->loadedProviders)) {
            return array_first($this->serviceProviders, function ($key, $value) use($name) {
                return get_class($value) == $name;
            });
        }
    }
    public function resolveProviderClass($provider)
    {
        return new $provider($this);
    }
    protected function markAsRegistered($provider)
    {
        $this['events']->fire($class = get_class($provider), array($provider));
        $this->serviceProviders[] = $provider;
        $this->loadedProviders[$class] = true;
    }
    public function loadDeferredProviders()
    {
        foreach ($this->deferredServices as $service => $provider) {
            $this->loadDeferredProvider($service);
        }
        $this->deferredServices = array();
    }
    protected function loadDeferredProvider($service)
    {
        $provider = $this->deferredServices[$service];
        if (!isset($this->loadedProviders[$provider])) {
            $this->registerDeferredProvider($provider, $service);
        }
    }
    public function registerDeferredProvider($provider, $service = null)
    {
        if ($service) {
            unset($this->deferredServices[$service]);
        }
        $this->register($instance = new $provider($this));
        if (!$this->booted) {
            $this->booting(function () use($instance) {
                $instance->boot();
            });
        }
    }
    public function make($abstract, $parameters = array())
    {
        $abstract = $this->getAlias($abstract);
        if (isset($this->deferredServices[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }
        return parent::make($abstract, $parameters);
    }
    public function bound($abstract)
    {
        return isset($this->deferredServices[$abstract]) || parent::bound($abstract);
    }
    public function extend($abstract, Closure $closure)
    {
        $abstract = $this->getAlias($abstract);
        if (isset($this->deferredServices[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }
        return parent::extend($abstract, $closure);
    }
    public function before($callback)
    {
        return $this['router']->before($callback);
    }
    public function after($callback)
    {
        return $this['router']->after($callback);
    }
    public function finish($callback)
    {
        $this->finishCallbacks[] = $callback;
    }
    public function shutdown(callable $callback = null)
    {
        if (is_null($callback)) {
            $this->fireAppCallbacks($this->shutdownCallbacks);
        } else {
            $this->shutdownCallbacks[] = $callback;
        }
    }
    public function useArraySessions(Closure $callback)
    {
        $this->bind('session.reject', function () use($callback) {
            return $callback;
        });
    }
    public function isBooted()
    {
        return $this->booted;
    }
    public function boot()
    {
        if ($this->booted) {
            return;
        }
        array_walk($this->serviceProviders, function ($p) {
            $p->boot();
        });
        $this->bootApplication();
    }
    protected function bootApplication()
    {
        $this->fireAppCallbacks($this->bootingCallbacks);
        $this->booted = true;
        $this->fireAppCallbacks($this->bootedCallbacks);
    }
    public function booting($callback)
    {
        $this->bootingCallbacks[] = $callback;
    }
    public function booted($callback)
    {
        $this->bootedCallbacks[] = $callback;
        if ($this->isBooted()) {
            $this->fireAppCallbacks(array($callback));
        }
    }
    public function run(SymfonyRequest $request = null)
    {
        $request = $request ?: $this['request'];
        $response = with($stack = $this->getStackedClient())->handle($request);
        $response->send();
        $stack->terminate($request, $response);
    }
    protected function getStackedClient()
    {
        $sessionReject = $this->bound('session.reject') ? $this['session.reject'] : null;
        $client = (new Builder())->push('Illuminate\\Cookie\\Guard', $this['encrypter'])->push('Illuminate\\Cookie\\Queue', $this['cookie'])->push('Illuminate\\Session\\Middleware', $this['session'], $sessionReject);
        $this->mergeCustomMiddlewares($client);
        return $client->resolve($this);
    }
    protected function mergeCustomMiddlewares(Builder $stack)
    {
        foreach ($this->middlewares as $middleware) {
            list($class, $parameters) = array_values($middleware);
            array_unshift($parameters, $class);
            call_user_func_array(array($stack, 'push'), $parameters);
        }
    }
    protected function registerBaseMiddlewares()
    {
        
    }
    public function middleware($class, array $parameters = array())
    {
        $this->middlewares[] = compact('class', 'parameters');
        return $this;
    }
    public function forgetMiddleware($class)
    {
        $this->middlewares = array_filter($this->middlewares, function ($m) use($class) {
            return $m['class'] != $class;
        });
    }
    public function handle(SymfonyRequest $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        try {
            $this->refreshRequest($request = Request::createFromBase($request));
            $this->boot();
            return $this->dispatch($request);
        } catch (\Exception $e) {
            if (!$catch || $this->runningUnitTests()) {
                throw $e;
            }
            return $this['exception']->handleException($e);
        }
    }
    public function dispatch(Request $request)
    {
        if ($this->isDownForMaintenance()) {
            $response = $this['events']->until('illuminate.app.down');
            if (!is_null($response)) {
                return $this->prepareResponse($response, $request);
            }
        }
        if ($this->runningUnitTests() && !$this['session']->isStarted()) {
            $this['session']->start();
        }
        return $this['router']->dispatch($this->prepareRequest($request));
    }
    public function terminate(SymfonyRequest $request, SymfonyResponse $response)
    {
        $this->callFinishCallbacks($request, $response);
        $this->shutdown();
    }
    protected function refreshRequest(Request $request)
    {
        $this->instance('request', $request);
        Facade::clearResolvedInstance('request');
    }
    public function callFinishCallbacks(SymfonyRequest $request, SymfonyResponse $response)
    {
        foreach ($this->finishCallbacks as $callback) {
            call_user_func($callback, $request, $response);
        }
    }
    protected function fireAppCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $this);
        }
    }
    public function prepareRequest(Request $request)
    {
        if (!is_null($this['config']['session.driver']) && !$request->hasSession()) {
            $request->setSession($this['session']->driver());
        }
        return $request;
    }
    public function prepareResponse($value)
    {
        if (!$value instanceof SymfonyResponse) {
            $value = new Response($value);
        }
        return $value->prepare($this['request']);
    }
    public function readyForResponses()
    {
        return $this->booted;
    }
    public function isDownForMaintenance()
    {
        return file_exists($this['config']['app.manifest'] . '/down');
    }
    public function down(Closure $callback)
    {
        $this['events']->listen('illuminate.app.down', $callback);
    }
    public function abort($code, $message = '', array $headers = array())
    {
        if ($code == 404) {
            throw new NotFoundHttpException($message);
        }
        throw new HttpException($code, $message, null, $headers);
    }
    public function missing(Closure $callback)
    {
        $this->error(function (NotFoundHttpException $e) use($callback) {
            return call_user_func($callback, $e);
        });
    }
    public function error(Closure $callback)
    {
        $this['exception']->error($callback);
    }
    public function pushError(Closure $callback)
    {
        $this['exception']->pushError($callback);
    }
    public function fatal(Closure $callback)
    {
        $this->error(function (FatalErrorException $e) use($callback) {
            return call_user_func($callback, $e);
        });
    }
    public function getConfigLoader()
    {
        return new FileLoader(new Filesystem(), $this['path'] . '/config');
    }
    public function getEnvironmentVariablesLoader()
    {
        return new FileEnvironmentVariablesLoader(new Filesystem(), $this['path.base']);
    }
    public function getProviderRepository()
    {
        $manifest = $this['config']['app.manifest'];
        return new ProviderRepository(new Filesystem(), $manifest);
    }
    public function getLoadedProviders()
    {
        return $this->loadedProviders;
    }
    public function setDeferredServices(array $services)
    {
        $this->deferredServices = $services;
    }
    public function isDeferredService($service)
    {
        return isset($this->deferredServices[$service]);
    }
    public static function requestClass($class = null)
    {
        if (!is_null($class)) {
            static::$requestClass = $class;
        }
        return static::$requestClass;
    }
    public function setRequestForConsoleEnvironment()
    {
        $url = $this['config']->get('app.url', 'http://localhost');
        $parameters = array($url, 'GET', array(), array(), array(), $_SERVER);
        $this->refreshRequest(static::onRequest('create', $parameters));
    }
    public static function onRequest($method, $parameters = array())
    {
        return forward_static_call_array(array(static::requestClass(), $method), $parameters);
    }
    public function getLocale()
    {
        return $this['config']->get('app.locale');
    }
    public function setLocale($locale)
    {
        $this['config']->set('app.locale', $locale);
        $this['translator']->setLocale($locale);
        $this['events']->fire('locale.changed', array($locale));
    }
    public function registerCoreContainerAliases()
    {
        $aliases = array('app' => 'Illuminate\\Foundation\\Application', 'artisan' => 'Illuminate\\Console\\Application', 'auth' => 'Illuminate\\Auth\\AuthManager', 'auth.reminder.repository' => 'Illuminate\\Auth\\Reminders\\ReminderRepositoryInterface', 'blade.compiler' => 'Illuminate\\View\\Compilers\\BladeCompiler', 'cache' => 'Illuminate\\Cache\\CacheManager', 'cache.store' => 'Illuminate\\Cache\\Repository', 'config' => 'Illuminate\\Config\\Repository', 'cookie' => 'Illuminate\\Cookie\\CookieJar', 'encrypter' => 'Illuminate\\Encryption\\Encrypter', 'db' => 'Illuminate\\Database\\DatabaseManager', 'events' => 'Illuminate\\Events\\Dispatcher', 'files' => 'Illuminate\\Filesystem\\Filesystem', 'form' => 'Illuminate\\Html\\FormBuilder', 'hash' => 'Illuminate\\Hashing\\HasherInterface', 'html' => 'Illuminate\\Html\\HtmlBuilder', 'translator' => 'Illuminate\\Translation\\Translator', 'log' => 'Illuminate\\Log\\Writer', 'mailer' => 'Illuminate\\Mail\\Mailer', 'paginator' => 'Illuminate\\Pagination\\Factory', 'auth.reminder' => 'Illuminate\\Auth\\Reminders\\PasswordBroker', 'queue' => 'Illuminate\\Queue\\QueueManager', 'redirect' => 'Illuminate\\Routing\\Redirector', 'redis' => 'Illuminate\\Redis\\Database', 'request' => 'Illuminate\\Http\\Request', 'router' => 'Illuminate\\Routing\\Router', 'session' => 'Illuminate\\Session\\SessionManager', 'session.store' => 'Illuminate\\Session\\Store', 'remote' => 'Illuminate\\Remote\\RemoteManager', 'url' => 'Illuminate\\Routing\\UrlGenerator', 'validator' => 'Illuminate\\Validation\\Factory', 'view' => 'Illuminate\\View\\Factory');
        foreach ($aliases as $key => $alias) {
            $this->alias($key, $alias);
        }
    }
}
namespace Illuminate\Foundation;

use Closure;
class EnvironmentDetector
{
    public function detect($environments, $consoleArgs = null)
    {
        if ($consoleArgs) {
            return $this->detectConsoleEnvironment($environments, $consoleArgs);
        }
        return $this->detectWebEnvironment($environments);
    }
    protected function detectWebEnvironment($environments)
    {
        if ($environments instanceof Closure) {
            return call_user_func($environments);
        }
        foreach ($environments as $environment => $hosts) {
            foreach ((array) $hosts as $host) {
                if ($this->isMachine($host)) {
                    return $environment;
                }
            }
        }
        return 'production';
    }
    protected function detectConsoleEnvironment($environments, array $args)
    {
        if (!is_null($value = $this->getEnvironmentArgument($args))) {
            return head(array_slice(explode('=', $value), 1));
        }
        return $this->detectWebEnvironment($environments);
    }
    protected function getEnvironmentArgument(array $args)
    {
        return array_first($args, function ($k, $v) {
            return starts_with($v, '--env');
        });
    }
    public function isMachine($name)
    {
        return str_is($name, gethostname());
    }
}
namespace Illuminate\Http;

use SplFileInfo;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
class Request extends SymfonyRequest
{
    protected $json;
    protected $sessionStore;
    public function instance()
    {
        return $this;
    }
    public function method()
    {
        return $this->getMethod();
    }
    public function root()
    {
        return rtrim($this->getSchemeAndHttpHost() . $this->getBaseUrl(), '/');
    }
    public function url()
    {
        return rtrim(preg_replace('/\\?.*/', '', $this->getUri()), '/');
    }
    public function fullUrl()
    {
        $query = $this->getQueryString();
        return $query ? $this->url() . '?' . $query : $this->url();
    }
    public function path()
    {
        $pattern = trim($this->getPathInfo(), '/');
        return $pattern == '' ? '/' : $pattern;
    }
    public function decodedPath()
    {
        return rawurldecode($this->path());
    }
    public function segment($index, $default = null)
    {
        return array_get($this->segments(), $index - 1, $default);
    }
    public function segments()
    {
        $segments = explode('/', $this->path());
        return array_values(array_filter($segments, function ($v) {
            return $v != '';
        }));
    }
    public function is()
    {
        foreach (func_get_args() as $pattern) {
            if (str_is($pattern, urldecode($this->path()))) {
                return true;
            }
        }
        return false;
    }
    public function ajax()
    {
        return $this->isXmlHttpRequest();
    }
    public function secure()
    {
        return $this->isSecure();
    }
    public function ip()
    {
        return $this->getClientIp();
    }
    public function ips()
    {
        return $this->getClientIps();
    }
    public function exists($key)
    {
        $keys = is_array($key) ? $key : func_get_args();
        $input = $this->all();
        foreach ($keys as $value) {
            if (!array_key_exists($value, $input)) {
                return false;
            }
        }
        return true;
    }
    public function has($key)
    {
        $keys = is_array($key) ? $key : func_get_args();
        foreach ($keys as $value) {
            if ($this->isEmptyString($value)) {
                return false;
            }
        }
        return true;
    }
    protected function isEmptyString($key)
    {
        $boolOrArray = is_bool($this->input($key)) || is_array($this->input($key));
        return !$boolOrArray && trim((string) $this->input($key)) === '';
    }
    public function all()
    {
        return array_replace_recursive($this->input(), $this->files->all());
    }
    public function input($key = null, $default = null)
    {
        $input = $this->getInputSource()->all() + $this->query->all();
        return array_get($input, $key, $default);
    }
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $results = array();
        $input = $this->all();
        foreach ($keys as $key) {
            array_set($results, $key, array_get($input, $key));
        }
        return $results;
    }
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $results = $this->all();
        array_forget($results, $keys);
        return $results;
    }
    public function query($key = null, $default = null)
    {
        return $this->retrieveItem('query', $key, $default);
    }
    public function hasCookie($key)
    {
        return !is_null($this->cookie($key));
    }
    public function cookie($key = null, $default = null)
    {
        return $this->retrieveItem('cookies', $key, $default);
    }
    public function file($key = null, $default = null)
    {
        return array_get($this->files->all(), $key, $default);
    }
    public function hasFile($key)
    {
        if (!is_array($files = $this->file($key))) {
            $files = array($files);
        }
        foreach ($files as $file) {
            if ($this->isValidFile($file)) {
                return true;
            }
        }
        return false;
    }
    protected function isValidFile($file)
    {
        return $file instanceof SplFileInfo && $file->getPath() != '';
    }
    public function header($key = null, $default = null)
    {
        return $this->retrieveItem('headers', $key, $default);
    }
    public function server($key = null, $default = null)
    {
        return $this->retrieveItem('server', $key, $default);
    }
    public function old($key = null, $default = null)
    {
        return $this->session()->getOldInput($key, $default);
    }
    public function flash($filter = null, $keys = array())
    {
        $flash = !is_null($filter) ? $this->{$filter}($keys) : $this->input();
        $this->session()->flashInput($flash);
    }
    public function flashOnly($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return $this->flash('only', $keys);
    }
    public function flashExcept($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return $this->flash('except', $keys);
    }
    public function flush()
    {
        $this->session()->flashInput(array());
    }
    protected function retrieveItem($source, $key, $default)
    {
        if (is_null($key)) {
            return $this->{$source}->all();
        }
        return $this->{$source}->get($key, $default, true);
    }
    public function merge(array $input)
    {
        $this->getInputSource()->add($input);
    }
    public function replace(array $input)
    {
        $this->getInputSource()->replace($input);
    }
    public function json($key = null, $default = null)
    {
        if (!isset($this->json)) {
            $this->json = new ParameterBag((array) json_decode($this->getContent(), true));
        }
        if (is_null($key)) {
            return $this->json;
        }
        return array_get($this->json->all(), $key, $default);
    }
    protected function getInputSource()
    {
        if ($this->isJson()) {
            return $this->json();
        }
        return $this->getMethod() == 'GET' ? $this->query : $this->request;
    }
    public function isJson()
    {
        return str_contains($this->header('CONTENT_TYPE'), '/json');
    }
    public function wantsJson()
    {
        $acceptable = $this->getAcceptableContentTypes();
        return isset($acceptable[0]) && $acceptable[0] == 'application/json';
    }
    public function format($default = 'html')
    {
        foreach ($this->getAcceptableContentTypes() as $type) {
            if ($format = $this->getFormat($type)) {
                return $format;
            }
        }
        return $default;
    }
    public static function createFromBase(SymfonyRequest $request)
    {
        if ($request instanceof static) {
            return $request;
        }
        return (new static())->duplicate($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $request->server->all());
    }
    public function session()
    {
        if (!$this->hasSession()) {
            throw new \RuntimeException('Session store not set on request.');
        }
        return $this->getSession();
    }
}
namespace Illuminate\Http;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
class FrameGuard implements HttpKernelInterface
{
    protected $app;
    public function __construct(HttpKernelInterface $app)
    {
        $this->app = $app;
    }
    public function handle(SymfonyRequest $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $response = $this->app->handle($request, $type, $catch);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        return $response;
    }
}
namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
class Request
{
    const HEADER_CLIENT_IP = 'client_ip';
    const HEADER_CLIENT_HOST = 'client_host';
    const HEADER_CLIENT_PROTO = 'client_proto';
    const HEADER_CLIENT_PORT = 'client_port';
    protected static $trustedProxies = array();
    protected static $trustedHostPatterns = array();
    protected static $trustedHosts = array();
    protected static $trustedHeaders = array(self::HEADER_CLIENT_IP => 'X_FORWARDED_FOR', self::HEADER_CLIENT_HOST => 'X_FORWARDED_HOST', self::HEADER_CLIENT_PROTO => 'X_FORWARDED_PROTO', self::HEADER_CLIENT_PORT => 'X_FORWARDED_PORT');
    protected static $httpMethodParameterOverride = false;
    public $attributes;
    public $request;
    public $query;
    public $server;
    public $files;
    public $cookies;
    public $headers;
    protected $content;
    protected $languages;
    protected $charsets;
    protected $encodings;
    protected $acceptableContentTypes;
    protected $pathInfo;
    protected $requestUri;
    protected $baseUrl;
    protected $basePath;
    protected $method;
    protected $format;
    protected $session;
    protected $locale;
    protected $defaultLocale = 'en';
    protected static $formats;
    protected static $requestFactory;
    public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        $this->initialize($query, $request, $attributes, $cookies, $files, $server, $content);
    }
    public function initialize(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        $this->request = new ParameterBag($request);
        $this->query = new ParameterBag($query);
        $this->attributes = new ParameterBag($attributes);
        $this->cookies = new ParameterBag($cookies);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());
        $this->content = $content;
        $this->languages = null;
        $this->charsets = null;
        $this->encodings = null;
        $this->acceptableContentTypes = null;
        $this->pathInfo = null;
        $this->requestUri = null;
        $this->baseUrl = null;
        $this->basePath = null;
        $this->method = null;
        $this->format = null;
    }
    public static function createFromGlobals()
    {
        $server = $_SERVER;
        if ('cli-server' === php_sapi_name()) {
            if (array_key_exists('HTTP_CONTENT_LENGTH', $_SERVER)) {
                $server['CONTENT_LENGTH'] = $_SERVER['HTTP_CONTENT_LENGTH'];
            }
            if (array_key_exists('HTTP_CONTENT_TYPE', $_SERVER)) {
                $server['CONTENT_TYPE'] = $_SERVER['HTTP_CONTENT_TYPE'];
            }
        }
        $request = self::createRequestFromFactory($_GET, $_POST, array(), $_COOKIE, $_FILES, $server);
        if (0 === strpos($request->headers->get('CONTENT_TYPE'), 'application/x-www-form-urlencoded') && in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), array('PUT', 'DELETE', 'PATCH'))) {
            parse_str($request->getContent(), $data);
            $request->request = new ParameterBag($data);
        }
        return $request;
    }
    public static function create($uri, $method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
    {
        $server = array_replace(array('SERVER_NAME' => 'localhost', 'SERVER_PORT' => 80, 'HTTP_HOST' => 'localhost', 'HTTP_USER_AGENT' => 'Symfony/2.X', 'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5', 'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '', 'SCRIPT_FILENAME' => '', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'REQUEST_TIME' => time()), $server);
        $server['PATH_INFO'] = '';
        $server['REQUEST_METHOD'] = strtoupper($method);
        $components = parse_url($uri);
        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $components['host'];
            $server['HTTP_HOST'] = $components['host'];
        }
        if (isset($components['scheme'])) {
            if ('https' === $components['scheme']) {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = 443;
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = 80;
            }
        }
        if (isset($components['port'])) {
            $server['SERVER_PORT'] = $components['port'];
            $server['HTTP_HOST'] = $server['HTTP_HOST'] . ':' . $components['port'];
        }
        if (isset($components['user'])) {
            $server['PHP_AUTH_USER'] = $components['user'];
        }
        if (isset($components['pass'])) {
            $server['PHP_AUTH_PW'] = $components['pass'];
        }
        if (!isset($components['path'])) {
            $components['path'] = '/';
        }
        switch (strtoupper($method)) {
            case 'POST':
            case 'PUT':
            case 'DELETE':
                if (!isset($server['CONTENT_TYPE'])) {
                    $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
                }
            case 'PATCH':
                $request = $parameters;
                $query = array();
                break;
            default:
                $request = array();
                $query = $parameters;
                break;
        }
        $queryString = '';
        if (isset($components['query'])) {
            parse_str(html_entity_decode($components['query']), $qs);
            if ($query) {
                $query = array_replace($qs, $query);
                $queryString = http_build_query($query, '', '&');
            } else {
                $query = $qs;
                $queryString = $components['query'];
            }
        } elseif ($query) {
            $queryString = http_build_query($query, '', '&');
        }
        $server['REQUEST_URI'] = $components['path'] . ('' !== $queryString ? '?' . $queryString : '');
        $server['QUERY_STRING'] = $queryString;
        return self::createRequestFromFactory($query, $request, array(), $cookies, $files, $server, $content);
    }
    public static function setFactory($callable)
    {
        self::$requestFactory = $callable;
    }
    public function duplicate(array $query = null, array $request = null, array $attributes = null, array $cookies = null, array $files = null, array $server = null)
    {
        $dup = clone $this;
        if ($query !== null) {
            $dup->query = new ParameterBag($query);
        }
        if ($request !== null) {
            $dup->request = new ParameterBag($request);
        }
        if ($attributes !== null) {
            $dup->attributes = new ParameterBag($attributes);
        }
        if ($cookies !== null) {
            $dup->cookies = new ParameterBag($cookies);
        }
        if ($files !== null) {
            $dup->files = new FileBag($files);
        }
        if ($server !== null) {
            $dup->server = new ServerBag($server);
            $dup->headers = new HeaderBag($dup->server->getHeaders());
        }
        $dup->languages = null;
        $dup->charsets = null;
        $dup->encodings = null;
        $dup->acceptableContentTypes = null;
        $dup->pathInfo = null;
        $dup->requestUri = null;
        $dup->baseUrl = null;
        $dup->basePath = null;
        $dup->method = null;
        $dup->format = null;
        if (!$dup->get('_format') && $this->get('_format')) {
            $dup->attributes->set('_format', $this->get('_format'));
        }
        if (!$dup->getRequestFormat(null)) {
            $dup->setRequestFormat($format = $this->getRequestFormat(null));
        }
        return $dup;
    }
    public function __clone()
    {
        $this->query = clone $this->query;
        $this->request = clone $this->request;
        $this->attributes = clone $this->attributes;
        $this->cookies = clone $this->cookies;
        $this->files = clone $this->files;
        $this->server = clone $this->server;
        $this->headers = clone $this->headers;
    }
    public function __toString()
    {
        return sprintf('%s %s %s', $this->getMethod(), $this->getRequestUri(), $this->server->get('SERVER_PROTOCOL')) . '
' . $this->headers . '
' . $this->getContent();
    }
    public function overrideGlobals()
    {
        $this->server->set('QUERY_STRING', static::normalizeQueryString(http_build_query($this->query->all(), null, '&')));
        $_GET = $this->query->all();
        $_POST = $this->request->all();
        $_SERVER = $this->server->all();
        $_COOKIE = $this->cookies->all();
        foreach ($this->headers->all() as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));
            if (in_array($key, array('CONTENT_TYPE', 'CONTENT_LENGTH'))) {
                $_SERVER[$key] = implode(', ', $value);
            } else {
                $_SERVER['HTTP_' . $key] = implode(', ', $value);
            }
        }
        $request = array('g' => $_GET, 'p' => $_POST, 'c' => $_COOKIE);
        $requestOrder = ini_get('request_order') ?: ini_get('variables_order');
        $requestOrder = preg_replace('#[^cgp]#', '', strtolower($requestOrder)) ?: 'gp';
        $_REQUEST = array();
        foreach (str_split($requestOrder) as $order) {
            $_REQUEST = array_merge($_REQUEST, $request[$order]);
        }
    }
    public static function setTrustedProxies(array $proxies)
    {
        self::$trustedProxies = $proxies;
    }
    public static function getTrustedProxies()
    {
        return self::$trustedProxies;
    }
    public static function setTrustedHosts(array $hostPatterns)
    {
        self::$trustedHostPatterns = array_map(function ($hostPattern) {
            return sprintf('{%s}i', str_replace('}', '\\}', $hostPattern));
        }, $hostPatterns);
        self::$trustedHosts = array();
    }
    public static function getTrustedHosts()
    {
        return self::$trustedHostPatterns;
    }
    public static function setTrustedHeaderName($key, $value)
    {
        if (!array_key_exists($key, self::$trustedHeaders)) {
            throw new \InvalidArgumentException(sprintf('Unable to set the trusted header name for key "%s".', $key));
        }
        self::$trustedHeaders[$key] = $value;
    }
    public static function getTrustedHeaderName($key)
    {
        if (!array_key_exists($key, self::$trustedHeaders)) {
            throw new \InvalidArgumentException(sprintf('Unable to get the trusted header name for key "%s".', $key));
        }
        return self::$trustedHeaders[$key];
    }
    public static function normalizeQueryString($qs)
    {
        if ('' == $qs) {
            return '';
        }
        $parts = array();
        $order = array();
        foreach (explode('&', $qs) as $param) {
            if ('' === $param || '=' === $param[0]) {
                continue;
            }
            $keyValuePair = explode('=', $param, 2);
            $parts[] = isset($keyValuePair[1]) ? rawurlencode(urldecode($keyValuePair[0])) . '=' . rawurlencode(urldecode($keyValuePair[1])) : rawurlencode(urldecode($keyValuePair[0]));
            $order[] = urldecode($keyValuePair[0]);
        }
        array_multisort($order, SORT_ASC, $parts);
        return implode('&', $parts);
    }
    public static function enableHttpMethodParameterOverride()
    {
        self::$httpMethodParameterOverride = true;
    }
    public static function getHttpMethodParameterOverride()
    {
        return self::$httpMethodParameterOverride;
    }
    public function get($key, $default = null, $deep = false)
    {
        if ($this !== ($result = $this->query->get($key, $this, $deep))) {
            return $result;
        }
        if ($this !== ($result = $this->attributes->get($key, $this, $deep))) {
            return $result;
        }
        if ($this !== ($result = $this->request->get($key, $this, $deep))) {
            return $result;
        }
        return $default;
    }
    public function getSession()
    {
        return $this->session;
    }
    public function hasPreviousSession()
    {
        return $this->hasSession() && $this->cookies->has($this->session->getName());
    }
    public function hasSession()
    {
        return null !== $this->session;
    }
    public function setSession(SessionInterface $session)
    {
        $this->session = $session;
    }
    public function getClientIps()
    {
        $ip = $this->server->get('REMOTE_ADDR');
        if (!self::$trustedProxies) {
            return array($ip);
        }
        if (!self::$trustedHeaders[self::HEADER_CLIENT_IP] || !$this->headers->has(self::$trustedHeaders[self::HEADER_CLIENT_IP])) {
            return array($ip);
        }
        $clientIps = array_map('trim', explode(',', $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_IP])));
        $clientIps[] = $ip;
        $ip = $clientIps[0];
        foreach ($clientIps as $key => $clientIp) {
            if (preg_match('{((?:\\d+\\.){3}\\d+)\\:\\d+}', $clientIp, $match)) {
                $clientIps[$key] = $clientIp = $match[1];
            }
            if (IpUtils::checkIp($clientIp, self::$trustedProxies)) {
                unset($clientIps[$key]);
            }
        }
        return $clientIps ? array_reverse($clientIps) : array($ip);
    }
    public function getClientIp()
    {
        $ipAddresses = $this->getClientIps();
        return $ipAddresses[0];
    }
    public function getScriptName()
    {
        return $this->server->get('SCRIPT_NAME', $this->server->get('ORIG_SCRIPT_NAME', ''));
    }
    public function getPathInfo()
    {
        if (null === $this->pathInfo) {
            $this->pathInfo = $this->preparePathInfo();
        }
        return $this->pathInfo;
    }
    public function getBasePath()
    {
        if (null === $this->basePath) {
            $this->basePath = $this->prepareBasePath();
        }
        return $this->basePath;
    }
    public function getBaseUrl()
    {
        if (null === $this->baseUrl) {
            $this->baseUrl = $this->prepareBaseUrl();
        }
        return $this->baseUrl;
    }
    public function getScheme()
    {
        return $this->isSecure() ? 'https' : 'http';
    }
    public function getPort()
    {
        if (self::$trustedProxies) {
            if (self::$trustedHeaders[self::HEADER_CLIENT_PORT] && ($port = $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_PORT]))) {
                return $port;
            }
            if (self::$trustedHeaders[self::HEADER_CLIENT_PROTO] && 'https' === $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_PROTO], 'http')) {
                return 443;
            }
        }
        if ($host = $this->headers->get('HOST')) {
            if ($host[0] === '[') {
                $pos = strpos($host, ':', strrpos($host, ']'));
            } else {
                $pos = strrpos($host, ':');
            }
            if (false !== $pos) {
                return intval(substr($host, $pos + 1));
            }
            return 'https' === $this->getScheme() ? 443 : 80;
        }
        return $this->server->get('SERVER_PORT');
    }
    public function getUser()
    {
        return $this->headers->get('PHP_AUTH_USER');
    }
    public function getPassword()
    {
        return $this->headers->get('PHP_AUTH_PW');
    }
    public function getUserInfo()
    {
        $userinfo = $this->getUser();
        $pass = $this->getPassword();
        if ('' != $pass) {
            $userinfo .= ":{$pass}";
        }
        return $userinfo;
    }
    public function getHttpHost()
    {
        $scheme = $this->getScheme();
        $port = $this->getPort();
        if ('http' == $scheme && $port == 80 || 'https' == $scheme && $port == 443) {
            return $this->getHost();
        }
        return $this->getHost() . ':' . $port;
    }
    public function getRequestUri()
    {
        if (null === $this->requestUri) {
            $this->requestUri = $this->prepareRequestUri();
        }
        return $this->requestUri;
    }
    public function getSchemeAndHttpHost()
    {
        return $this->getScheme() . '://' . $this->getHttpHost();
    }
    public function getUri()
    {
        if (null !== ($qs = $this->getQueryString())) {
            $qs = '?' . $qs;
        }
        return $this->getSchemeAndHttpHost() . $this->getBaseUrl() . $this->getPathInfo() . $qs;
    }
    public function getUriForPath($path)
    {
        return $this->getSchemeAndHttpHost() . $this->getBaseUrl() . $path;
    }
    public function getQueryString()
    {
        $qs = static::normalizeQueryString($this->server->get('QUERY_STRING'));
        return '' === $qs ? null : $qs;
    }
    public function isSecure()
    {
        if (self::$trustedProxies && self::$trustedHeaders[self::HEADER_CLIENT_PROTO] && ($proto = $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_PROTO]))) {
            return in_array(strtolower(current(explode(',', $proto))), array('https', 'on', 'ssl', '1'));
        }
        $https = $this->server->get('HTTPS');
        return !empty($https) && 'off' !== strtolower($https);
    }
    public function getHost()
    {
        if (self::$trustedProxies && self::$trustedHeaders[self::HEADER_CLIENT_HOST] && ($host = $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_HOST]))) {
            $elements = explode(',', $host);
            $host = $elements[count($elements) - 1];
        } elseif (!($host = $this->headers->get('HOST'))) {
            if (!($host = $this->server->get('SERVER_NAME'))) {
                $host = $this->server->get('SERVER_ADDR', '');
            }
        }
        $host = strtolower(preg_replace('/:\\d+$/', '', trim($host)));
        if ($host && '' !== preg_replace('/(?:^\\[)?[a-zA-Z0-9-:\\]_]+\\.?/', '', $host)) {
            throw new \UnexpectedValueException(sprintf('Invalid Host "%s"', $host));
        }
        if (count(self::$trustedHostPatterns) > 0) {
            if (in_array($host, self::$trustedHosts)) {
                return $host;
            }
            foreach (self::$trustedHostPatterns as $pattern) {
                if (preg_match($pattern, $host)) {
                    self::$trustedHosts[] = $host;
                    return $host;
                }
            }
            throw new \UnexpectedValueException(sprintf('Untrusted Host "%s"', $host));
        }
        return $host;
    }
    public function setMethod($method)
    {
        $this->method = null;
        $this->server->set('REQUEST_METHOD', $method);
    }
    public function getMethod()
    {
        if (null === $this->method) {
            $this->method = strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
            if ('POST' === $this->method) {
                if ($method = $this->headers->get('X-HTTP-METHOD-OVERRIDE')) {
                    $this->method = strtoupper($method);
                } elseif (self::$httpMethodParameterOverride) {
                    $this->method = strtoupper($this->request->get('_method', $this->query->get('_method', 'POST')));
                }
            }
        }
        return $this->method;
    }
    public function getRealMethod()
    {
        return strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
    }
    public function getMimeType($format)
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }
        return isset(static::$formats[$format]) ? static::$formats[$format][0] : null;
    }
    public function getFormat($mimeType)
    {
        if (false !== ($pos = strpos($mimeType, ';'))) {
            $mimeType = substr($mimeType, 0, $pos);
        }
        if (null === static::$formats) {
            static::initializeFormats();
        }
        foreach (static::$formats as $format => $mimeTypes) {
            if (in_array($mimeType, (array) $mimeTypes)) {
                return $format;
            }
        }
    }
    public function setFormat($format, $mimeTypes)
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }
        static::$formats[$format] = is_array($mimeTypes) ? $mimeTypes : array($mimeTypes);
    }
    public function getRequestFormat($default = 'html')
    {
        if (null === $this->format) {
            $this->format = $this->get('_format', $default);
        }
        return $this->format;
    }
    public function setRequestFormat($format)
    {
        $this->format = $format;
    }
    public function getContentType()
    {
        return $this->getFormat($this->headers->get('CONTENT_TYPE'));
    }
    public function setDefaultLocale($locale)
    {
        $this->defaultLocale = $locale;
        if (null === $this->locale) {
            $this->setPhpDefaultLocale($locale);
        }
    }
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }
    public function setLocale($locale)
    {
        $this->setPhpDefaultLocale($this->locale = $locale);
    }
    public function getLocale()
    {
        return null === $this->locale ? $this->defaultLocale : $this->locale;
    }
    public function isMethod($method)
    {
        return $this->getMethod() === strtoupper($method);
    }
    public function isMethodSafe()
    {
        return in_array($this->getMethod(), array('GET', 'HEAD'));
    }
    public function getContent($asResource = false)
    {
        if (false === $this->content || true === $asResource && null !== $this->content) {
            throw new \LogicException('getContent() can only be called once when using the resource return type.');
        }
        if (true === $asResource) {
            $this->content = false;
            return fopen('php://input', 'rb');
        }
        if (null === $this->content) {
            $this->content = file_get_contents('php://input');
        }
        return $this->content;
    }
    public function getETags()
    {
        return preg_split('/\\s*,\\s*/', $this->headers->get('if_none_match'), null, PREG_SPLIT_NO_EMPTY);
    }
    public function isNoCache()
    {
        return $this->headers->hasCacheControlDirective('no-cache') || 'no-cache' == $this->headers->get('Pragma');
    }
    public function getPreferredLanguage(array $locales = null)
    {
        $preferredLanguages = $this->getLanguages();
        if (empty($locales)) {
            return isset($preferredLanguages[0]) ? $preferredLanguages[0] : null;
        }
        if (!$preferredLanguages) {
            return $locales[0];
        }
        $extendedPreferredLanguages = array();
        foreach ($preferredLanguages as $language) {
            $extendedPreferredLanguages[] = $language;
            if (false !== ($position = strpos($language, '_'))) {
                $superLanguage = substr($language, 0, $position);
                if (!in_array($superLanguage, $preferredLanguages)) {
                    $extendedPreferredLanguages[] = $superLanguage;
                }
            }
        }
        $preferredLanguages = array_values(array_intersect($extendedPreferredLanguages, $locales));
        return isset($preferredLanguages[0]) ? $preferredLanguages[0] : $locales[0];
    }
    public function getLanguages()
    {
        if (null !== $this->languages) {
            return $this->languages;
        }
        $languages = AcceptHeader::fromString($this->headers->get('Accept-Language'))->all();
        $this->languages = array();
        foreach (array_keys($languages) as $lang) {
            if (strstr($lang, '-')) {
                $codes = explode('-', $lang);
                if ($codes[0] == 'i') {
                    if (count($codes) > 1) {
                        $lang = $codes[1];
                    }
                } else {
                    for ($i = 0, $max = count($codes); $i < $max; $i++) {
                        if ($i == 0) {
                            $lang = strtolower($codes[0]);
                        } else {
                            $lang .= '_' . strtoupper($codes[$i]);
                        }
                    }
                }
            }
            $this->languages[] = $lang;
        }
        return $this->languages;
    }
    public function getCharsets()
    {
        if (null !== $this->charsets) {
            return $this->charsets;
        }
        return $this->charsets = array_keys(AcceptHeader::fromString($this->headers->get('Accept-Charset'))->all());
    }
    public function getEncodings()
    {
        if (null !== $this->encodings) {
            return $this->encodings;
        }
        return $this->encodings = array_keys(AcceptHeader::fromString($this->headers->get('Accept-Encoding'))->all());
    }
    public function getAcceptableContentTypes()
    {
        if (null !== $this->acceptableContentTypes) {
            return $this->acceptableContentTypes;
        }
        return $this->acceptableContentTypes = array_keys(AcceptHeader::fromString($this->headers->get('Accept'))->all());
    }
    public function isXmlHttpRequest()
    {
        return 'XMLHttpRequest' == $this->headers->get('X-Requested-With');
    }
    protected function prepareRequestUri()
    {
        $requestUri = '';
        if ($this->headers->has('X_ORIGINAL_URL')) {
            $requestUri = $this->headers->get('X_ORIGINAL_URL');
            $this->headers->remove('X_ORIGINAL_URL');
            $this->server->remove('HTTP_X_ORIGINAL_URL');
            $this->server->remove('UNENCODED_URL');
            $this->server->remove('IIS_WasUrlRewritten');
        } elseif ($this->headers->has('X_REWRITE_URL')) {
            $requestUri = $this->headers->get('X_REWRITE_URL');
            $this->headers->remove('X_REWRITE_URL');
        } elseif ($this->server->get('IIS_WasUrlRewritten') == '1' && $this->server->get('UNENCODED_URL') != '') {
            $requestUri = $this->server->get('UNENCODED_URL');
            $this->server->remove('UNENCODED_URL');
            $this->server->remove('IIS_WasUrlRewritten');
        } elseif ($this->server->has('REQUEST_URI')) {
            $requestUri = $this->server->get('REQUEST_URI');
            $schemeAndHttpHost = $this->getSchemeAndHttpHost();
            if (strpos($requestUri, $schemeAndHttpHost) === 0) {
                $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
            }
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
            $requestUri = $this->server->get('ORIG_PATH_INFO');
            if ('' != $this->server->get('QUERY_STRING')) {
                $requestUri .= '?' . $this->server->get('QUERY_STRING');
            }
            $this->server->remove('ORIG_PATH_INFO');
        }
        $this->server->set('REQUEST_URI', $requestUri);
        return $requestUri;
    }
    protected function prepareBaseUrl()
    {
        $filename = basename($this->server->get('SCRIPT_FILENAME'));
        if (basename($this->server->get('SCRIPT_NAME')) === $filename) {
            $baseUrl = $this->server->get('SCRIPT_NAME');
        } elseif (basename($this->server->get('PHP_SELF')) === $filename) {
            $baseUrl = $this->server->get('PHP_SELF');
        } elseif (basename($this->server->get('ORIG_SCRIPT_NAME')) === $filename) {
            $baseUrl = $this->server->get('ORIG_SCRIPT_NAME');
        } else {
            $path = $this->server->get('PHP_SELF', '');
            $file = $this->server->get('SCRIPT_FILENAME', '');
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/' . $seg . $baseUrl;
                ++$index;
            } while ($last > $index && false !== ($pos = strpos($path, $baseUrl)) && 0 != $pos);
        }
        $requestUri = $this->getRequestUri();
        if ($baseUrl && false !== ($prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl))) {
            return $prefix;
        }
        if ($baseUrl && false !== ($prefix = $this->getUrlencodedPrefix($requestUri, dirname($baseUrl)))) {
            return rtrim($prefix, '/');
        }
        $truncatedRequestUri = $requestUri;
        if (false !== ($pos = strpos($requestUri, '?'))) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }
        $basename = basename($baseUrl);
        if (empty($basename) || !strpos(rawurldecode($truncatedRequestUri), $basename)) {
            return '';
        }
        if (strlen($requestUri) >= strlen($baseUrl) && false !== ($pos = strpos($requestUri, $baseUrl)) && $pos !== 0) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }
        return rtrim($baseUrl, '/');
    }
    protected function prepareBasePath()
    {
        $filename = basename($this->server->get('SCRIPT_FILENAME'));
        $baseUrl = $this->getBaseUrl();
        if (empty($baseUrl)) {
            return '';
        }
        if (basename($baseUrl) === $filename) {
            $basePath = dirname($baseUrl);
        } else {
            $basePath = $baseUrl;
        }
        if ('\\' === DIRECTORY_SEPARATOR) {
            $basePath = str_replace('\\', '/', $basePath);
        }
        return rtrim($basePath, '/');
    }
    protected function preparePathInfo()
    {
        $baseUrl = $this->getBaseUrl();
        if (null === ($requestUri = $this->getRequestUri())) {
            return '/';
        }
        $pathInfo = '/';
        if ($pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        if (null !== $baseUrl && false === ($pathInfo = substr($requestUri, strlen($baseUrl)))) {
            return '/';
        } elseif (null === $baseUrl) {
            return $requestUri;
        }
        return (string) $pathInfo;
    }
    protected static function initializeFormats()
    {
        static::$formats = array('html' => array('text/html', 'application/xhtml+xml'), 'txt' => array('text/plain'), 'js' => array('application/javascript', 'application/x-javascript', 'text/javascript'), 'css' => array('text/css'), 'json' => array('application/json', 'application/x-json'), 'xml' => array('text/xml', 'application/xml', 'application/x-xml'), 'rdf' => array('application/rdf+xml'), 'atom' => array('application/atom+xml'), 'rss' => array('application/rss+xml'));
    }
    private function setPhpDefaultLocale($locale)
    {
        try {
            if (class_exists('Locale', false)) {
                \Locale::setDefault($locale);
            }
        } catch (\Exception $e) {
            
        }
    }
    private function getUrlencodedPrefix($string, $prefix)
    {
        if (0 !== strpos(rawurldecode($string), $prefix)) {
            return false;
        }
        $len = strlen($prefix);
        if (preg_match("#^(%[[:xdigit:]]{2}|.){{$len}}#", $string, $match)) {
            return $match[0];
        }
        return false;
    }
    private static function createRequestFromFactory(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        if (self::$requestFactory) {
            $request = call_user_func(self::$requestFactory, $query, $request, $attributes, $cookies, $files, $server, $content);
            if (!$request instanceof Request) {
                throw new \LogicException('The Request factory must return an instance of Symfony\\Component\\HttpFoundation\\Request.');
            }
            return $request;
        }
        return new static($query, $request, $attributes, $cookies, $files, $server, $content);
    }
}
namespace Symfony\Component\HttpFoundation;

class ParameterBag implements \IteratorAggregate, \Countable
{
    protected $parameters;
    public function __construct(array $parameters = array())
    {
        $this->parameters = $parameters;
    }
    public function all()
    {
        return $this->parameters;
    }
    public function keys()
    {
        return array_keys($this->parameters);
    }
    public function replace(array $parameters = array())
    {
        $this->parameters = $parameters;
    }
    public function add(array $parameters = array())
    {
        $this->parameters = array_replace($this->parameters, $parameters);
    }
    public function get($path, $default = null, $deep = false)
    {
        if (!$deep || false === ($pos = strpos($path, '['))) {
            return array_key_exists($path, $this->parameters) ? $this->parameters[$path] : $default;
        }
        $root = substr($path, 0, $pos);
        if (!array_key_exists($root, $this->parameters)) {
            return $default;
        }
        $value = $this->parameters[$root];
        $currentKey = null;
        for ($i = $pos, $c = strlen($path); $i < $c; $i++) {
            $char = $path[$i];
            if ('[' === $char) {
                if (null !== $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "[" at position %d.', $i));
                }
                $currentKey = '';
            } elseif (']' === $char) {
                if (null === $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "]" at position %d.', $i));
                }
                if (!is_array($value) || !array_key_exists($currentKey, $value)) {
                    return $default;
                }
                $value = $value[$currentKey];
                $currentKey = null;
            } else {
                if (null === $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "%s" at position %d.', $char, $i));
                }
                $currentKey .= $char;
            }
        }
        if (null !== $currentKey) {
            throw new \InvalidArgumentException(sprintf('Malformed path. Path must end with "]".'));
        }
        return $value;
    }
    public function set($key, $value)
    {
        $this->parameters[$key] = $value;
    }
    public function has($key)
    {
        return array_key_exists($key, $this->parameters);
    }
    public function remove($key)
    {
        unset($this->parameters[$key]);
    }
    public function getAlpha($key, $default = '', $deep = false)
    {
        return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default, $deep));
    }
    public function getAlnum($key, $default = '', $deep = false)
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default, $deep));
    }
    public function getDigits($key, $default = '', $deep = false)
    {
        return str_replace(array('-', '+'), '', $this->filter($key, $default, $deep, FILTER_SANITIZE_NUMBER_INT));
    }
    public function getInt($key, $default = 0, $deep = false)
    {
        return (int) $this->get($key, $default, $deep);
    }
    public function filter($key, $default = null, $deep = false, $filter = FILTER_DEFAULT, $options = array())
    {
        $value = $this->get($key, $default, $deep);
        if (!is_array($options) && $options) {
            $options = array('flags' => $options);
        }
        if (is_array($value) && !isset($options['flags'])) {
            $options['flags'] = FILTER_REQUIRE_ARRAY;
        }
        return filter_var($value, $filter, $options);
    }
    public function getIterator()
    {
        return new \ArrayIterator($this->parameters);
    }
    public function count()
    {
        return count($this->parameters);
    }
}
namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\File\UploadedFile;
class FileBag extends ParameterBag
{
    private static $fileKeys = array('error', 'name', 'size', 'tmp_name', 'type');
    public function __construct(array $parameters = array())
    {
        $this->replace($parameters);
    }
    public function replace(array $files = array())
    {
        $this->parameters = array();
        $this->add($files);
    }
    public function set($key, $value)
    {
        if (!is_array($value) && !$value instanceof UploadedFile) {
            throw new \InvalidArgumentException('An uploaded file must be an array or an instance of UploadedFile.');
        }
        parent::set($key, $this->convertFileInformation($value));
    }
    public function add(array $files = array())
    {
        foreach ($files as $key => $file) {
            $this->set($key, $file);
        }
    }
    protected function convertFileInformation($file)
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }
        $file = $this->fixPhpFilesArray($file);
        if (is_array($file)) {
            $keys = array_keys($file);
            sort($keys);
            if ($keys == self::$fileKeys) {
                if (UPLOAD_ERR_NO_FILE == $file['error']) {
                    $file = null;
                } else {
                    $file = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size'], $file['error']);
                }
            } else {
                $file = array_map(array($this, 'convertFileInformation'), $file);
            }
        }
        return $file;
    }
    protected function fixPhpFilesArray($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $keys = array_keys($data);
        sort($keys);
        if (self::$fileKeys != $keys || !isset($data['name']) || !is_array($data['name'])) {
            return $data;
        }
        $files = $data;
        foreach (self::$fileKeys as $k) {
            unset($files[$k]);
        }
        foreach (array_keys($data['name']) as $key) {
            $files[$key] = $this->fixPhpFilesArray(array('error' => $data['error'][$key], 'name' => $data['name'][$key], 'type' => $data['type'][$key], 'tmp_name' => $data['tmp_name'][$key], 'size' => $data['size'][$key]));
        }
        return $files;
    }
}
namespace Symfony\Component\HttpFoundation;

class ServerBag extends ParameterBag
{
    public function getHeaders()
    {
        $headers = array();
        $contentHeaders = array('CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true);
        foreach ($this->parameters as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            } elseif (isset($contentHeaders[$key])) {
                $headers[$key] = $value;
            }
        }
        if (isset($this->parameters['PHP_AUTH_USER'])) {
            $headers['PHP_AUTH_USER'] = $this->parameters['PHP_AUTH_USER'];
            $headers['PHP_AUTH_PW'] = isset($this->parameters['PHP_AUTH_PW']) ? $this->parameters['PHP_AUTH_PW'] : '';
        } else {
            $authorizationHeader = null;
            if (isset($this->parameters['HTTP_AUTHORIZATION'])) {
                $authorizationHeader = $this->parameters['HTTP_AUTHORIZATION'];
            } elseif (isset($this->parameters['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authorizationHeader = $this->parameters['REDIRECT_HTTP_AUTHORIZATION'];
            }
            if (null !== $authorizationHeader) {
                if (0 === stripos($authorizationHeader, 'basic ')) {
                    $exploded = explode(':', base64_decode(substr($authorizationHeader, 6)), 2);
                    if (count($exploded) == 2) {
                        list($headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW']) = $exploded;
                    }
                } elseif (empty($this->parameters['PHP_AUTH_DIGEST']) && 0 === stripos($authorizationHeader, 'digest ')) {
                    $headers['PHP_AUTH_DIGEST'] = $authorizationHeader;
                    $this->parameters['PHP_AUTH_DIGEST'] = $authorizationHeader;
                }
            }
        }
        if (isset($headers['PHP_AUTH_USER'])) {
            $headers['AUTHORIZATION'] = 'Basic ' . base64_encode($headers['PHP_AUTH_USER'] . ':' . $headers['PHP_AUTH_PW']);
        } elseif (isset($headers['PHP_AUTH_DIGEST'])) {
            $headers['AUTHORIZATION'] = $headers['PHP_AUTH_DIGEST'];
        }
        return $headers;
    }
}
namespace Symfony\Component\HttpFoundation;

class HeaderBag implements \IteratorAggregate, \Countable
{
    protected $headers = array();
    protected $cacheControl = array();
    public function __construct(array $headers = array())
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }
    public function __toString()
    {
        if (!$this->headers) {
            return '';
        }
        $max = max(array_map('strlen', array_keys($this->headers))) + 1;
        $content = '';
        ksort($this->headers);
        foreach ($this->headers as $name => $values) {
            $name = implode('-', array_map('ucfirst', explode('-', $name)));
            foreach ($values as $value) {
                $content .= sprintf("%-{$max}s %s\r\n", $name . ':', $value);
            }
        }
        return $content;
    }
    public function all()
    {
        return $this->headers;
    }
    public function keys()
    {
        return array_keys($this->headers);
    }
    public function replace(array $headers = array())
    {
        $this->headers = array();
        $this->add($headers);
    }
    public function add(array $headers)
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }
    public function get($key, $default = null, $first = true)
    {
        $key = strtr(strtolower($key), '_', '-');
        if (!array_key_exists($key, $this->headers)) {
            if (null === $default) {
                return $first ? null : array();
            }
            return $first ? $default : array($default);
        }
        if ($first) {
            return count($this->headers[$key]) ? $this->headers[$key][0] : $default;
        }
        return $this->headers[$key];
    }
    public function set($key, $values, $replace = true)
    {
        $key = strtr(strtolower($key), '_', '-');
        $values = array_values((array) $values);
        if (true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $values;
        } else {
            $this->headers[$key] = array_merge($this->headers[$key], $values);
        }
        if ('cache-control' === $key) {
            $this->cacheControl = $this->parseCacheControl($values[0]);
        }
    }
    public function has($key)
    {
        return array_key_exists(strtr(strtolower($key), '_', '-'), $this->headers);
    }
    public function contains($key, $value)
    {
        return in_array($value, $this->get($key, null, false));
    }
    public function remove($key)
    {
        $key = strtr(strtolower($key), '_', '-');
        unset($this->headers[$key]);
        if ('cache-control' === $key) {
            $this->cacheControl = array();
        }
    }
    public function getDate($key, \DateTime $default = null)
    {
        if (null === ($value = $this->get($key))) {
            return $default;
        }
        if (false === ($date = \DateTime::createFromFormat(DATE_RFC2822, $value))) {
            throw new \RuntimeException(sprintf('The %s HTTP header is not parseable (%s).', $key, $value));
        }
        return $date;
    }
    public function addCacheControlDirective($key, $value = true)
    {
        $this->cacheControl[$key] = $value;
        $this->set('Cache-Control', $this->getCacheControlHeader());
    }
    public function hasCacheControlDirective($key)
    {
        return array_key_exists($key, $this->cacheControl);
    }
    public function getCacheControlDirective($key)
    {
        return array_key_exists($key, $this->cacheControl) ? $this->cacheControl[$key] : null;
    }
    public function removeCacheControlDirective($key)
    {
        unset($this->cacheControl[$key]);
        $this->set('Cache-Control', $this->getCacheControlHeader());
    }
    public function getIterator()
    {
        return new \ArrayIterator($this->headers);
    }
    public function count()
    {
        return count($this->headers);
    }
    protected function getCacheControlHeader()
    {
        $parts = array();
        ksort($this->cacheControl);
        foreach ($this->cacheControl as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"' . $value . '"';
                }
                $parts[] = "{$key}={$value}";
            }
        }
        return implode(', ', $parts);
    }
    protected function parseCacheControl($header)
    {
        $cacheControl = array();
        preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\\s*(?:=(?:"([^"]*)"|([^ \\t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cacheControl[strtolower($match[1])] = isset($match[3]) ? $match[3] : (isset($match[2]) ? $match[2] : true);
        }
        return $cacheControl;
    }
}
namespace Symfony\Component\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
interface SessionInterface
{
    public function start();
    public function getId();
    public function setId($id);
    public function getName();
    public function setName($name);
    public function invalidate($lifetime = null);
    public function migrate($destroy = false, $lifetime = null);
    public function save();
    public function has($name);
    public function get($name, $default = null);
    public function set($name, $value);
    public function all();
    public function replace(array $attributes);
    public function remove($name);
    public function clear();
    public function isStarted();
    public function registerBag(SessionBagInterface $bag);
    public function getBag($name);
    public function getMetadataBag();
}
namespace Symfony\Component\HttpFoundation\Session;

interface SessionBagInterface
{
    public function getName();
    public function initialize(array &$array);
    public function getStorageKey();
    public function clear();
}
namespace Symfony\Component\HttpFoundation\Session\Attribute;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
interface AttributeBagInterface extends SessionBagInterface
{
    public function has($name);
    public function get($name, $default = null);
    public function set($name, $value);
    public function all();
    public function replace(array $attributes);
    public function remove($name);
}
namespace Symfony\Component\HttpFoundation\Session\Attribute;

class AttributeBag implements AttributeBagInterface, \IteratorAggregate, \Countable
{
    private $name = 'attributes';
    private $storageKey;
    protected $attributes = array();
    public function __construct($storageKey = '_sf2_attributes')
    {
        $this->storageKey = $storageKey;
    }
    public function getName()
    {
        return $this->name;
    }
    public function setName($name)
    {
        $this->name = $name;
    }
    public function initialize(array &$attributes)
    {
        $this->attributes =& $attributes;
    }
    public function getStorageKey()
    {
        return $this->storageKey;
    }
    public function has($name)
    {
        return array_key_exists($name, $this->attributes);
    }
    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }
    public function set($name, $value)
    {
        $this->attributes[$name] = $value;
    }
    public function all()
    {
        return $this->attributes;
    }
    public function replace(array $attributes)
    {
        $this->attributes = array();
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
    }
    public function remove($name)
    {
        $retval = null;
        if (array_key_exists($name, $this->attributes)) {
            $retval = $this->attributes[$name];
            unset($this->attributes[$name]);
        }
        return $retval;
    }
    public function clear()
    {
        $return = $this->attributes;
        $this->attributes = array();
        return $return;
    }
    public function getIterator()
    {
        return new \ArrayIterator($this->attributes);
    }
    public function count()
    {
        return count($this->attributes);
    }
}
namespace Symfony\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
class MetadataBag implements SessionBagInterface
{
    const CREATED = 'c';
    const UPDATED = 'u';
    const LIFETIME = 'l';
    private $name = '__metadata';
    private $storageKey;
    protected $meta = array(self::CREATED => 0, self::UPDATED => 0, self::LIFETIME => 0);
    private $lastUsed;
    private $updateThreshold;
    public function __construct($storageKey = '_sf2_meta', $updateThreshold = 0)
    {
        $this->storageKey = $storageKey;
        $this->updateThreshold = $updateThreshold;
    }
    public function initialize(array &$array)
    {
        $this->meta =& $array;
        if (isset($array[self::CREATED])) {
            $this->lastUsed = $this->meta[self::UPDATED];
            $timeStamp = time();
            if ($timeStamp - $array[self::UPDATED] >= $this->updateThreshold) {
                $this->meta[self::UPDATED] = $timeStamp;
            }
        } else {
            $this->stampCreated();
        }
    }
    public function getLifetime()
    {
        return $this->meta[self::LIFETIME];
    }
    public function stampNew($lifetime = null)
    {
        $this->stampCreated($lifetime);
    }
    public function getStorageKey()
    {
        return $this->storageKey;
    }
    public function getCreated()
    {
        return $this->meta[self::CREATED];
    }
    public function getLastUsed()
    {
        return $this->lastUsed;
    }
    public function clear()
    {
        
    }
    public function getName()
    {
        return $this->name;
    }
    public function setName($name)
    {
        $this->name = $name;
    }
    private function stampCreated($lifetime = null)
    {
        $timeStamp = time();
        $this->meta[self::CREATED] = $this->meta[self::UPDATED] = $this->lastUsed = $timeStamp;
        $this->meta[self::LIFETIME] = null === $lifetime ? ini_get('session.cookie_lifetime') : $lifetime;
    }
}
namespace Symfony\Component\HttpFoundation;

class AcceptHeaderItem
{
    private $value;
    private $quality = 1.0;
    private $index = 0;
    private $attributes = array();
    public function __construct($value, array $attributes = array())
    {
        $this->value = $value;
        foreach ($attributes as $name => $value) {
            $this->setAttribute($name, $value);
        }
    }
    public static function fromString($itemValue)
    {
        $bits = preg_split('/\\s*(?:;*("[^"]+");*|;*(\'[^\']+\');*|;+)\\s*/', $itemValue, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $value = array_shift($bits);
        $attributes = array();
        $lastNullAttribute = null;
        foreach ($bits as $bit) {
            if (($start = substr($bit, 0, 1)) === ($end = substr($bit, -1)) && ($start === '"' || $start === '\'')) {
                $attributes[$lastNullAttribute] = substr($bit, 1, -1);
            } elseif ('=' === $end) {
                $lastNullAttribute = $bit = substr($bit, 0, -1);
                $attributes[$bit] = null;
            } else {
                $parts = explode('=', $bit);
                $attributes[$parts[0]] = isset($parts[1]) && strlen($parts[1]) > 0 ? $parts[1] : '';
            }
        }
        return new self(($start = substr($value, 0, 1)) === ($end = substr($value, -1)) && ($start === '"' || $start === '\'') ? substr($value, 1, -1) : $value, $attributes);
    }
    public function __toString()
    {
        $string = $this->value . ($this->quality < 1 ? ';q=' . $this->quality : '');
        if (count($this->attributes) > 0) {
            $string .= ';' . implode(';', array_map(function ($name, $value) {
                return sprintf(preg_match('/[,;=]/', $value) ? '%s="%s"' : '%s=%s', $name, $value);
            }, array_keys($this->attributes), $this->attributes));
        }
        return $string;
    }
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }
    public function getValue()
    {
        return $this->value;
    }
    public function setQuality($quality)
    {
        $this->quality = $quality;
        return $this;
    }
    public function getQuality()
    {
        return $this->quality;
    }
    public function setIndex($index)
    {
        $this->index = $index;
        return $this;
    }
    public function getIndex()
    {
        return $this->index;
    }
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }
    public function getAttribute($name, $default = null)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
    }
    public function getAttributes()
    {
        return $this->attributes;
    }
    public function setAttribute($name, $value)
    {
        if ('q' === $name) {
            $this->quality = (double) $value;
        } else {
            $this->attributes[$name] = (string) $value;
        }
        return $this;
    }
}
namespace Symfony\Component\HttpFoundation;

class AcceptHeader
{
    private $items = array();
    private $sorted = true;
    public function __construct(array $items)
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }
    public static function fromString($headerValue)
    {
        $index = 0;
        return new self(array_map(function ($itemValue) use(&$index) {
            $item = AcceptHeaderItem::fromString($itemValue);
            $item->setIndex($index++);
            return $item;
        }, preg_split('/\\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\\s*/', $headerValue, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)));
    }
    public function __toString()
    {
        return implode(',', $this->items);
    }
    public function has($value)
    {
        return isset($this->items[$value]);
    }
    public function get($value)
    {
        return isset($this->items[$value]) ? $this->items[$value] : null;
    }
    public function add(AcceptHeaderItem $item)
    {
        $this->items[$item->getValue()] = $item;
        $this->sorted = false;
        return $this;
    }
    public function all()
    {
        $this->sort();
        return $this->items;
    }
    public function filter($pattern)
    {
        return new self(array_filter($this->items, function (AcceptHeaderItem $item) use($pattern) {
            return preg_match($pattern, $item->getValue());
        }));
    }
    public function first()
    {
        $this->sort();
        return !empty($this->items) ? reset($this->items) : null;
    }
    private function sort()
    {
        if (!$this->sorted) {
            uasort($this->items, function ($a, $b) {
                $qA = $a->getQuality();
                $qB = $b->getQuality();
                if ($qA === $qB) {
                    return $a->getIndex() > $b->getIndex() ? 1 : -1;
                }
                return $qA > $qB ? -1 : 1;
            });
            $this->sorted = true;
        }
    }
}
namespace Symfony\Component\Debug;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\Exception\OutOfMemoryException;
class ExceptionHandler
{
    private $debug;
    private $charset;
    private $handler;
    private $caughtBuffer;
    private $caughtLength;
    public function __construct($debug = true, $charset = 'UTF-8')
    {
        $this->debug = $debug;
        $this->charset = $charset;
    }
    public static function register($debug = true)
    {
        $handler = new static($debug);
        set_exception_handler(array($handler, 'handle'));
        return $handler;
    }
    public function setHandler($handler)
    {
        if (null !== $handler && !is_callable($handler)) {
            throw new \LogicException('The exception handler must be a valid PHP callable.');
        }
        $old = $this->handler;
        $this->handler = $handler;
        return $old;
    }
    public function handle(\Exception $exception)
    {
        if (null === $this->handler || $exception instanceof OutOfMemoryException) {
            $this->failSafeHandle($exception);
            return;
        }
        $caughtLength = $this->caughtLength = 0;
        ob_start(array($this, 'catchOutput'));
        $this->failSafeHandle($exception);
        while (null === $this->caughtBuffer && ob_end_flush()) {
            
        }
        if (isset($this->caughtBuffer[0])) {
            ob_start(array($this, 'cleanOutput'));
            echo $this->caughtBuffer;
            $caughtLength = ob_get_length();
        }
        $this->caughtBuffer = null;
        try {
            call_user_func($this->handler, $exception);
            $this->caughtLength = $caughtLength;
        } catch (\Exception $e) {
            if (!$caughtLength) {
                throw $exception;
            }
        }
    }
    private function failSafeHandle(\Exception $exception)
    {
        if (class_exists('Symfony\\Component\\HttpFoundation\\Response', false)) {
            $response = $this->createResponse($exception);
            $response->sendHeaders();
            $response->sendContent();
        } else {
            $this->sendPhpResponse($exception);
        }
    }
    public function sendPhpResponse($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }
        if (!headers_sent()) {
            header(sprintf('HTTP/1.0 %s', $exception->getStatusCode()));
            foreach ($exception->getHeaders() as $name => $value) {
                header($name . ': ' . $value, false);
            }
        }
        echo $this->decorate($this->getContent($exception), $this->getStylesheet($exception));
    }
    public function createResponse($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }
        return new Response($this->decorate($this->getContent($exception), $this->getStylesheet($exception)), $exception->getStatusCode(), $exception->getHeaders());
    }
    public function getContent(FlattenException $exception)
    {
        switch ($exception->getStatusCode()) {
            case 404:
                $title = 'Sorry, the page you are looking for could not be found.';
                break;
            default:
                $title = 'Whoops, looks like something went wrong.';
        }
        $content = '';
        if ($this->debug) {
            try {
                $count = count($exception->getAllPrevious());
                $total = $count + 1;
                foreach ($exception->toArray() as $position => $e) {
                    $ind = $count - $position + 1;
                    $class = $this->abbrClass($e['class']);
                    $message = nl2br($e['message']);
                    $content .= sprintf('                        <div class="block_exception clear_fix">
                            <h2><span>%d/%d</span> %s: %s</h2>
                        </div>
                        <div class="block">
                            <ol class="traces list_exception">', $ind, $total, $class, $message);
                    foreach ($e['trace'] as $trace) {
                        $content .= '       <li>';
                        if ($trace['function']) {
                            $content .= sprintf('at %s%s%s(%s)', $this->abbrClass($trace['class']), $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
                        }
                        if (isset($trace['file']) && isset($trace['line'])) {
                            if ($linkFormat = ini_get('xdebug.file_link_format')) {
                                $link = str_replace(array('%f', '%l'), array($trace['file'], $trace['line']), $linkFormat);
                                $content .= sprintf(' in <a href="%s" title="Go to source">%s line %s</a>', $link, $trace['file'], $trace['line']);
                            } else {
                                $content .= sprintf(' in %s line %s', $trace['file'], $trace['line']);
                            }
                        }
                        $content .= '</li>
';
                    }
                    $content .= '    </ol>
</div>
';
                }
            } catch (\Exception $e) {
                if ($this->debug) {
                    $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $e->getMessage());
                } else {
                    $title = 'Whoops, looks like something went wrong.';
                }
            }
        }
        return "            <div id=\"sf-resetcontent\" class=\"sf-reset\">\n                <h1>{$title}</h1>\n                {$content}\n            </div>";
    }
    public function getStylesheet(FlattenException $exception)
    {
        return '            .sf-reset { font: 11px Verdana, Arial, sans-serif; color: #333 }
            .sf-reset .clear { clear:both; height:0; font-size:0; line-height:0; }
            .sf-reset .clear_fix:after { display:block; height:0; clear:both; visibility:hidden; }
            .sf-reset .clear_fix { display:inline-block; }
            .sf-reset * html .clear_fix { height:1%; }
            .sf-reset .clear_fix { display:block; }
            .sf-reset, .sf-reset .block { margin: auto }
            .sf-reset abbr { border-bottom: 1px dotted #000; cursor: help; }
            .sf-reset p { font-size:14px; line-height:20px; color:#868686; padding-bottom:20px }
            .sf-reset strong { font-weight:bold; }
            .sf-reset a { color:#6c6159; }
            .sf-reset a img { border:none; }
            .sf-reset a:hover { text-decoration:underline; }
            .sf-reset em { font-style:italic; }
            .sf-reset h1, .sf-res<?ph2 { font: 20px Georgia, "Times New Roman", Class, serif }
 c $directoramespace Illuspan { background-color: #fff; tic $reg333; padding: 6px; float: left; margin-right: 10stattic $directories = array.traces liuminate-size:12stat   public2px 4statlist-style-type:decimal load($cltion:uppo        $class = static::noblock protected static $r#FRATOR    foreac  {
 28    oad($clbottom      ic $directori    -webkit-border  requilass)
-radius: 1 stae $path;
                return true;
 tion      }
        }
        returnmoz return      }  requiass)
   ction normalizeClass($class)
    {
        if      s[0] == '\\') {
       return true;
            }
        }
        retur
    }
    public static function normalizeClass(return true;
:1px solid #ccc   public static functionass)
 er()
    {
        if (!static::$regist     er()
    {
        if (!statif (file_exists($path = $direc_exceptio  protected static $r#dddered = false;
    publie_once $path;
                return topblic static function normalizeClass($)
    {
        st           }
        }
        returnclass)
    {
     topss, 1);
        }
        re;
    }
    public sta($class[0] == '\\') {
       
        static::$directories = array_unique(age(static::$directories, (array) $directories))
        ster()
    {
        if (!static::$registered) {
            static::$registered = spl_autoload_register(array('\\Illumorieoverflow: hidden   }
        returword-wrap: break-irecarray('\\Illuminate\\Support\\ClassLoali a protected st:noneered = f#86lect; text-decora));
ccess;
inate\Container;

use Closur:h()
 e;
use ArrayAccess;
use Ref31ed $nClass;
use ReflecunderlionParameter;
class Containerol {   foreach {
  0Parameter;
class Containerh1tory . DIRECTORY_SEPARATOR . $class)) 15
                requirSuppo   }
        return false;
    }
     }
      protected $resolviclass)
    {
    );
    protected $globalvingCallbacks = array();
    protected func: load_register(array('\\Illumi'   }
 tic $dprivate func));
 
use Ree($content, $css)ic $d{ic $direcreturn "<!DOCTYPE html>\n<    retorie<headsset($tt($thmeta charset=\"UTF-8\" /bindings[$abstracname=\"robots\"     pub=\"noindex,nofollowhis->instances[$ectorbindings[$a iss/* Copyass)
 (c) 2010, Yahoo! Inc. All ass)
s pacerved. Code licensed     p the BSD Lact]);: http://developer.yact].com/yui/ract]);.     */   return isse    {use Ref000;use ArrayAcARAT;}body,div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,code,form,fieldset,legend,input,lassarea,p,direcquote,th,td{oad($c:0;   publi0;}table{return collapse:actAlias;return spacalias)        ifimgis->extas) address,ca'));
,citlse)
  dfn,em,strong   lvar{($clasctor:norrect($clawess)
 ct);
  }li{:$directorccess;}       }th{lass;align:tion }= null, $shared =nces($as);
 00%       if (is_null($cq:before,q:after{    pub:'';}abbr,acronymbstract, $($clavarianis_null($csup{vertical $abstrlass;top    b
        $this->bindi requi;}ray($abstract))selectnces($family:inheritct, $cconcrlved($abstrac if (islved($ab 'shared');
        if *f (!$concrete i} (is_a]);
    }
  }\nhis->aliases[$namee;
use ArrayAc #eeerotected $re{
  }   return isseimg pro
      ance   return isse#mespace     pub { width:97     ($abstra autoe($abstract, $con{c fu$abstract, </
        ret</is->binding< binbindings[$a{
    pub$abstra</($concr</    r"t) || $this->isAlias($abstracs->gClass($cconcnction bound($ab$parts = explode('\\'lic e = n   }
     stract)sprintf('<s->g title="%s">%s</s->g> if (!$th, array_pop(false))is->bou$this->isAlias($abstrac  {
atArgs(rete, $arg null, $shared = if (PHP_VERSION_ID >= 50400)n bound($abd = fflag)
  ENT_QUOTES |e, trSUBSTITUTE   }
     } elseind($abstract, $concrete, true);
 unction shashared = fresult =crete,(is->bound($    ach (bstra as $key => $itembind($abstract, null'object' ===      [0]bind($abstract, ct, $cion stedValue =ct)) {
    em>t)) {
</em>(%s) if this->ct, $concre     1]        ction share(Clobjecrete,                $object = $closure($container);
            }
   rete,   return $ois_closur
    }
   ?object;
tion singlee);
    }
:       1]is->bound($nction bindSharstring                $object = $closure($container);
            }\'%s\'',$namespecialt]) | extend($, $concr$object;
t]) ||    public function bindSharnull                $object = $closure($container);
    }
   sset   rect) || c function bindSharboolean($this->instances[$abstract])) {
            $this->insta' . strtolower(var_exportn("Type {$atrue)) . '[$abstract] = $closure($this->iresource($this->instances[$abstract])) {
            $this->instalosure);[$abstract] = $closure($th $object = $closure($container);
     tr_replace('
', alidlse {
     ArgumentException(    if)tract, Cl$abstract} is not bound."extenderarray('\\Illuminate\\Supportner) us[] =sureint(ct;
}
   $container);
  : throw new \Inva    %s if key$abscontainer);
     return n ($contaistract)im{
     ,  if er) us       }
    }ublics($abstraccatchOutput($buffernull, $shared = fis not aughtBs_arr = (is_arrs->bound($abstrac'ct) || $this->instance)
    {
ance    if (is_array($abstract)) {null
            Lengthbind($abstract, $     list($abssubabstract, Cl(is_arrre $cl0t} is not  }
        u   }
        rnullissetretes[$abstra      $object = $closure(is_arrabstact] = $insarray('\\Illuminate\\Sup  };
    }
    putract, $alias}
}
act]    e Illuminate\Sup    ;

use Ref  ifion$conc;
a
   act (!$th ServiceProvider
 boundprotected $app   }
 ected functde
     false extracinstance)
    {__construct(tionay($abstract)) {
      appabstion extralias($abstract);
     boot(nction bound($ab   }
    public fus[$alias]($abstracregisterre) {
   {
        returpackageared$call, $alias($abs= sset, falthis->bounull, $shared = f  if ($this-
      getPk;
   Nlias($abback;
        if ($th   $this->i($abstra($abst?:>make($abuessract);
Pathre) {
      
   figbstract, . '/$this-tract] = $cas);
      app['files']->isDirectory}
   figce;
        if ($pp, $instancbstract]->= $callback;
      d) {
   public function refren ($containlan->rebinding($act)
t, function ($app, $instance) use($target, $methoct)
           $target->{$method}translatoranceadd        }
 alias($ab,ract)
nction rebound($abstraappViews->make($abstA$insta{
   ack;
      $this->i ($app, $instance) use($target, $metho $insta           $target->{$method}viewcallback) {
            call_u        ction rebound($abstraback>rebinding($abackst, function ($app, $instance) use($target, $methoback if (isset($this->reboundCallbacks[$abstract])) {
            rn makction rebound($alias($abstract);
     method)
    {
    ull, $shared = falbstra(nader   $this->alia;
    ))$abstFil
    re) {
      stract)real
   (diract]  pr  ung($a..ete('       }
    }
ted func  if (issestract);
        }
    }
    public func$abstract, $alias);is_sset        calce;
        if ($:$di($vendor  public func
    {
     / if protected function      }
    }
    alias($ab($abstract, $instance)
    {
ommandcretract)) es[$abstract])) {        resolv), true         
   ces[$abst:his->_get_stra      return evene)
  pp, $instancks($ab']vingCallbacks($ab->:$dienaredtisan.start',his->isBuiatic = tr) us }
  t;
     nd($abstract, $object;-SharelveCract)) {
         oncrete, $ptract);
        if ($this->isBuilda }
    }
    protectection bound($abstract)pp, $instanc>get'] . "     }/ck;
   s/{->make($}public functios[$abstract][] =    }
sces[$abstract])) stract)closure) {
                 $abstrawhen' . $abstract;
            }
            return $abstract;
isDy $dred' . $abstract;
         
      ay $d function alias($abstract, $aliEoad'));
 {
    Whoops\Run;ring($abstraHandler\PrettyPagebstract && strpos($abstract,JsonRespons= 0;
    }
   tract, $alias)
   \stract;
    }
 ;
 = $abeturn is_stract;
    }
  exteabststract;
    }
    proteinstance)
    {->reboundCy($abstract)) {
      ->rebounDisplayersolvingCallbaclector = new Rbstract            ret  if ($this->isBui = new ReflectionCla  }
        $reflector = new RPlaineflectionlass($concrete);
        ifDebugable.";
         isInstantiable()) {
            $ (!$reflenition), current($definit['load'));
'$respp, $insta->share(   return $oppected function gstract)     (!$refl      retuor();
     .panti'ings       $dependedReso'losure $clos($this->bindings[$abstract])) {
  not instantiable.";
    $reflector->getConstructor();
     encies = if (is_null($constructor)) {
            return neas);
ll($crunningInConsole(ce;
        if ($bounion missonstructor->getParametarray('\\Illumire(Closure $closure)n new $concretetantiable.";
            t$abstract);
   ption($message);
        }
        $consgResolutionExcep  }
        $reflector = new R$abstrlass($concrete);
  onstructor->getParamet if (is_null($constructor)) {
            return new $concrete$abstrable.";
      }
wabstr = $conseturn $reflector->newrs();
        $parameters = $this->keyParametersByAarameter{
            $dependency = $paramet (!$reflector->arget->{$method}ves[$par if (is_null($constructor)) {
            return newi    ves[$pis->aderun(ces[aved(Quit(nitio   $this->insta;
     ->writeTo    if   return (array) $deion missendenciepush();
        
       .hstracteters();
        $parameters = $this->keyParametersByAs($parameter);
$abstract, $alias);
      shouldRtractn bunewInstanceArgs($i  } else {
       eter)
     if (is_null($constructor)) {
 wInstanceArgs($instances);
    n build($concrete,     {
        $de } elseif (ire(Closure $closure)
e}] is not inst'\\')s($parameter);
          ($abstract);
  if ($this->isBuiultValue();
      ssingLeadingSlash($abstract) && eturn $reflector->ne ||($message)questWants      tion($message);
        }
       name);
        }ssingLeadingSlash($abstract) && ision $e)callbjaxr->getClass()l()) {
         w
        } catch (BindingResolutionExceptio;
    }
    protected funct $reflector->getConstructorsolvable dependency resolving [{$parameter}] in class {$parameteter);ter)
          '\\') !== 0;
        setEditor('sinstmestract);ction resolveNonter)
  rs();
        $paramn alias($abstract, $aliRouting {
    eters = array())
    {
        if ($concre      $ceof Closure) {
            return $concrete($this, $parameters);
        }
        $reflector = new R    
            throw new BindinUrlGeneras $lass($concrete);
        ifRedet, $me);
            }
            throw $e;
  llback)
 $reflector->getConstructorrlback         $dependencies[] = $this->resolveClass($param$lResol       }lback)    }
 ;
      $cons   $this->instances    }
 nvdenc= 'tes   $'  $object = $closure(lResol->dis $thFiltonClass($concrelluminate\\Supportion misslResolrs();
        $parameters = $this->keyParametersByAlvingCallbacks $reflector->getConstructorurlvingCallbacks[] = $callback;
    }
    protected function fbstraglobalResolvi$abstvingCthis->fireCallbacgetDeclarinlvingCallbackllbackAameter->nebinpubl$clo           return $o  }
 call_us  $object = $closure(lobalResolue) {Rall_us($object, ;
        throw  } elseif (is_null($dependency)) {
                $;
    }
    
    {
        $this->global
    }
vingCallbacks[] = $callback;
    }
    protected functi
    }
         }
    }
      }
    pu   $this->instances[$abstreter sesstancstore'nce;
        if ($boundared = falue) {S]) || abstract]) || $shared =s->fireCallbackArray($object, $this->
    }
         unset($parameters[$key]);
          Es($abparameters[$dependencies[$key]->name] = $value;
 
    ceof Closure) {
            return $concrete($this, $parameters);
        }
        $reflectorbacks($abstra if (is_null($constructor)) {
            return new $concreteefle    Callbac } elseif (is_null($dn alias($abstract, $alias)
   \FacadeprotecteMockery\es[$Interf     s[$alias] = $ab$this-   protected funcstaticction extractAlias(arunction ($abstrdInstan      ifthis, $unctioneter $parawap($i    uns          $shareunctio::    {
        uns[forgetIn$absthis-Access    etBin  }
       }
     forgetIns$this  }
    (      $this->instances = ar,();
    }
e($abstract, $instantances[$abstract]ltValueceive           $sharedact]r($ab    $this->instances = ard function get return ises[$    }
        $messmrect   return stances()
    {
  
    nction getDgResolutionException($e)
    {
      createFreshract],Exists(
    er);
        };
    }
    pucall_user_this-closur), truee)
 , 'function offs')     r->fireResol} catch (BindingResoluttances[$abstracte($value) {
                reublic function forgetInstances()
    {
  anceof if  function () use($vales[$Byct];
    return $valunces[$abst function ofce;
        if ($ function offsetExists(
    ,_get($er);
        };
    }
    puget($ion offsetUnset($key)
    {
        unset($t  return $this[$k   $this->instanc= $ab   return $thes[$ $th$concre   return $valuSymfony\Compon? tractery::e)
 rete = n :
{
    const MAStion offsetUnset($key)
    {
        union offsGet($key)
    {
        return $this->make($key);
    }
    
    pubpublic functio  }
    public function) &&$key]);
    }
    public function   }
    ofces[$], $this->an offsetUnset($key)
    {
        unundation\Response return $parameter->groose($ return $this->maRindin       return new $conc>firCompo functs->getAlias($abstract);
      Response;
interfaceRequest $reqction bound($abstract) return ($abstr->inst          return $this->make($key);
tion offsetUnset($key)
    {
        unhis->instances = arction bound($abthrowlarinact)timeeturn is_('->inst does notublicem    his->instances =  method.stract);
        if ($thtances[$abstractrepareResponse($value)\Request;
use Symfonyhis->but)) {
se Illu       return new $conc;
intoncrete, $parameters
    public functio\Component\HttpKernel;

       return new $conc{
        if (!$value instanceof Closure) {
{
    public function p   }
    public function __ function oftanceof Closurt\Contracts;

interface Respact]rR  {
        unsse Illuminate\Filesystuns\EventServiceProvider;
use Illuminatr;
use Symfony\Component\HttpKernel\HttpKernelInterface;nterface
{
    pubr;
use Illuminate\Config\e($closure) {
  rt\Contracts;

interface ResponsePrepaApplicReflenterface
{
    public function p));
    }
    public futances[$abstract]esponsePreparerInter$definition), currennent\HttpFouion));
    }
    public futances[$abstract__
   Sretur($ Illum $cotract, $concrete = );
    }
ion terminate(Request $req    public wit stacour, $ttpExected function gcase 0:$parameters, array $prim);
    }
->{\NotFou}his->fireCallbac, Res1onsePreparerInterface
{
    const VERSION = '4e, Te    2.16';
    protecte2 $booted = false;
    protected $bootingCallbacks = arndHttpE Closure $closure), Res3$bootedCallbacks = array();
    protected $finishCallbacks = arracks = a2rray();
    protected4shutdownCallbacks = array();
    protected $middlewares = array();
    proacks = a3rray();
    protdefaultonsePreparerInterface
{
 
        }
        $this->bi  }
    = $vIllum issttpExion resolveClass(R$abstract)
    {
        unset(Trai prott  $t MtClo $th   $t   }
    public function mtCloonenclosure) {
  mfony\Component\HttpKerteNew$key] = 
    $theateNewhis->instances[$key]);
  teNewRunction __getCloonse;
use Symfony\Component\HttpKerhasterBase Illuminate\Filesyst);
}
namespace Symfonyares();
    }ymfony\Component\HttpKernel\Exceptionel\Exception\NotFoundHparame  $t return $parameter-> return    {
     public        return new $conc
        }
        $tay(static::$requepubliceferisterBaseBioncrete, $parametersk\Builder;
BadMIllumCalluminate\Ht"ction  RSION = 'est;
use Iexist."e($abstract, $instance)
    {nel\Exunction registerBaseBindings($requestgServiceProvide protected function registerBaseBi function alias($abstract, $alias)
    {
    Closure$parameters = array())
     $th\terBaseBinding($concreAr    prottancew ExceptionServ       $this->registerBaseSaddacksrantaicontaivtaine   $object = $this->build($ return $throvider()
   )_set($key, $value)
    {
s[$absider()
    {
      oncrete, $parameters);
      regionse;
use Symfony\Component\HttpKerbuilProvider()vider() $
   otecRequest $request,er) usRequest());
               staticn($abject;
           arameters);
        }innerKontaiforeantaine =);
        $thi(tion bindsterEventProvider()
    {(array $path[ foreach ray();
y_exceptoncrete, $parameters);
     y $pathonse;
use Symfony\Component\HttpKerdi  }
on regi . $abstract;
            }
 rete, keys)
    {
ncrete,      \\MyFirstymfony\Component\HttpKernel\Exceptiodorovider($thprepends->i'InstallPaths(array $paths)
    {
        $this->instance('path', realpath($paths['app']));
  his->bu), true      == true;
    }
    prot$paths)
    {_merllbathis['e,ponse as ate\\     ation' . '/.ect;
 . '.'     return functencies($parameters, arrathis->instg']);
    }
  n __g     ->rebound($abstract);
        }
    }
      }
    public static function getBootstraload')on registerEvunction bound($abstract)rete, diffdocson registrete, flip(EW\\ht)    }
 ymfony\Component\HttpKernel\Exceptiofetchovider($this))ction bound($ab      sta  {
     .er($con)ath',segnate{
            $shar$paths)
    {
        $th $this->instance('path',h($paths['app']));
  ']->regiW\\htdocs_    fs( ? $_SER {
         env'] ==->environment());
           if (count() > 0) {
 [ ? $_SERnction getDepenlluminate\\Supporttion ($containece('paception'\laravell;
    der()
    {
        $this->rrn $this['env'] == 'testing'  }
    public function detectirion  registe)) as $key 
    prtract)) {
            rtionHandling()
    {
        $this['exception']->regiths, array('app')) as $key => $value) {wInstanceArgs($instances);
 ) {
            return in_array($this['env'],      (gister($ymfony\Component\HttpKernel\Exceptiola      return $this->register($provider, $options, trugServiceProvide
     rete, rever  }
yFirstLan $this->register($al';
    }
    public function detectltainnt\H   {
        return $stract)($closure) {
      rete, walk_recursffse   retur   return $x
    }&      ${
            $sharract() ==   protectedw new Bindinenv'], funractl';
    }
    public function detectorePro&env'];
    }
    public funct$original =&register(new  true);
    }'env'] == 'loath', reected function gealse)
    {
     RVER['arg   }
        rwhileInterfaceed);
  > 1  $object = $closure(alseeturn $thshifis->loade   }
        returstances[$abse('p[   re

use ster($this($key, $value  {
        return php_sa    ret  {
    , $valu   }
        returrunningUnitTests()
    {
   \HttpK{
     n array_first($thisrray();
    protlue) == $naovider)
ion resolveClass(Reflecracts;

interface Responson registerEventister($provider, $options, truhis->build($cis));luminate\Support\Facade
        $name Illuminate\Events\Ev{
      s())t_class($provider), array($proders[]vider));
        $th$args = isset($_SERVER['argv']) ? $_SERVER['argv'] : nunull!e($name) {
    ->get!s);
    }
    public functio\\vendos($parameters, array $prim          return $reggUnitTests()
    {
        retu{
          }
    public f
        $this->register(new EventServiceProvider($this));ha\\MyFirs($envs)
    {
        nullempt  foreach ($t($class = get_class($provider), arranition)
   );
        $this->s);
    }
    publcontaivice => $provider) {
 stract)tendroviders[$class] = true;
    }
    public function loadDeferredProviders()
    {
        foreach ($this->deferredServices as $service => $provider) {
            !isset($this->ler($service);
        }
        $this->deferredServices = array();
   ovider($prprotected function markAsRegionl        re   }
    public function isLocal()i, $tsect {
        return $this['env'] == 'local';
    }
    public function detecpluMAST registeapp.debut;
  act)) {
            rel;
        return $this['enis->instance('path',       if (is_null($getAlr);
    ilesystem;
      ?
     VERS     }bstract, s[$abs     });
      fire($class = get_class($providerhp_sapi_name() ==        if function environment()
    {
        if    Kmake(sset($this->deferredServiceskeytract])) {
dedProviders[$       $this->insta    pu    return parent::make($abstrn in_array($this['env'], func_get_args());
        }
        return pld($blic function)
    {
        $this['events']->on runninngServiceProvider($this)$provider->registResponse as 
    puvider($this))provider->boot();
) {
       protected function markAsRegiabst  {
        $abs        $this->register(new Routi= get_class($provider), array($pro > 0) {
           n ($containder) der);
        if (array_key_ex($name, $this-  }
 dProviders)) {
     n make(n array_first  }
       $this->loadD!serviceProviders[]  ($the($name) {
      rs[] = $provider;
   return newlback)    return $this['env'] ts()
    {
        ret$name;
   dedProviders[$class] = troviderClass($provid  }
 ) > 0) {
           this->register(new EventServiceProvider($this));s         public function bindInstallPaths(arrstract)Col $this-::mak;
       ->CallBobjeon bindI    }st SUB_REQUEST fony\Component\HttpKerwher$this->marlic function bindInstallPaths(arraf    $e'/stnction startExceptionHandling()
    {
        $this['exception']->regitions = array(), $force = false)
    {
        if ($registeon.rejecthCallback0) {
            return in_array($this['env'], fn.reject function alias($abstract, $alias)
    {
    P    work\Utf8
    {
        $this->register(new ExceptionServiceProStder($this));
    }
    protected ance($abstract)
 snakeCachorException;
use s->bootApplicationcamel   }
    protected function bootApplicastudly   }
    protected funfunction registerRoutinsciiis->enviction bound($abstract)lk($::toAtrue;
      r;
use Symfony\Component\HttpKernel\ion(;
        $this->fireAp
    public functiotion()
   {
      te\Routing\RoutingServiceProviderallbacks[] = $callrredServices = array();
   unction booted($callback)
  = lc
     cted func$thisis->envirr;
use Symfony\Component\HttpKernel\ontainsforeyst $key needleBindings($request= is_string($provi   }
  ath',   }
  $parameters);
         }
  !staruse Syrpolback));
        }
 ) !=finitio> $provider) {
            ovider($provid$abstract);
        }
    }
   nition)
   ));
        }
        return $ndsW   fore));
        }
    }
    public function run(SymfonyRequest $request = null)
    {
      er = $this $reque    );
   te($request,-strl $keest'];
=> $provider) {
            is->getStackedClient())->handle($request);
        $response->send();
        $stack-finish['app.debucafinition), current     '/stpreg_         p, 'bstract);this->shutde'])-ract, Clo/(?:t);
his['coo. ')+$thisalidetector(.e\\Coblic function useArraySessions(Closiss->geternbstract, $closure);
    }
     retur($thetector())->detect($enerredProvider($provider, $servi   }
    pie'])->push('  return '#ate\\Session)
    {
   abstract, Clo\\*ect).  lih ($thisConcr\\ztract] = $cstract)(nsta)lewarem    ('#^sion'  }
    .>midentProvider()
 istered;
        }
        if      ;
        $this->fireAppCallbamb_') ? $thedCallbacks);
    }
    public function blimi]['app.debuegist = 10ound. '/sta...rt.php';
    }
  nullarameters);
      <=ddlewar_class($provider), array    return $this['router'       $trimnctiond('sesapp.deboundegist, 'et($t'er =  {
    }
    }
    protected function re  } etract, $closure);
    h'), $paramet     } e;
        }
    }
    protected function rirecs['app.debues, fes()
    {
        
    }
    publicare);
      /^\\s*+(?:\\S++ss;
 ){1,sion'm) use. '}/u($paramet= $vted fr']->after($back);
    }mfonyRe     ||re) middleware($->bouface::M HttpKernelray $parameters = array())
    {
        $this->middlewares[] = HttpKernelI   return $this;
    }
    public functiop) ||regiotecp')) as $key ovider->erface
{
    public function pay($callba)) as $key'@'erre  {
     @ if () as $key2QUES = $obje       $provider->register();
        foreach ($optioplurang($call   }
unres(2his->dispatch($request)P    }izer::     }
            re         $this->fireAppCallbacks(arrrandom($func_aes()6   $this->register(n($abstra
    pub'openssl_st $re_pseudo_bytes    nd($abstract, $espon = ce()) {
            $responquest)
  * 2bject)
    {
       ]->unti      $response = with($stack\Builder;
use Illuminate\HttUn;
   to ggCallbeest $reEST,inginate\ConeCallbackArray($object, $thisompact(abstract, Clclosur$this'+arte=  }
alidbase64_ene)
 se)) {
))', 'par        $this->iionServiceProvider;
use IquickRt $request)
          $this->fireAppCallbacks(arr']->dispatch($this-   {
        if ($thi$pools->i0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ) = array_values($ && !$this[shuffle);
straceais->oound(();
   ->start();
        }unction useArraySessions(ClosupporgetMiddleware($class)
    {
        $trequest)
    blic function useArraySessions(Clos   $tgetMiddleware($class)
    {
     con    _, Re('class',MB_CASE_TITLEers');
   s->fireAppCallbacks($this->shutdownCingulargetMiddleware($class)
    {
  ndleExceptio
    {
        fs->fireAppCallbacks($this->shutdownClug($   $t,) ? istefalse;'-rt.php';
    }
      $t        if  true;
   $tdlewares as this    ponse);
         ? '_' :    ss($concretected fueware', $this[![t);
e'])->push('thisConcr]+!ion hponse);
 $objllbacks(array $c$callback) {
                e'])->push('ponse);
 rameterpL\\pN\\s, $thisalid      $this->mi    }

    public function prepareRequest(equest $request)
    {
        iull($thi);
        }
    }
    public
        est st, $response);
           call_user_func($callback, $);
 ['app.debudeegistfireR'_
    }
    public funmespace Symfony();
    }
{
     crea {
      lback;
    }
    public function b {
            $value = new Resvider));
        $this->!ces a_n forgetMiddl{
            $sharct, C  if$1sion' {
       . '$2allbacks asbstract);
      $this->meware', $this['s.)([A-Z])this-{
     e)
    {
 ion\ExceptionServiceProvider;
use Il {
            $value = new Res    }
    publieAppCallbacks($this->shutdownC;
  rminate($request, $response);
    }
    protected function getStackedClient()
    {
        $sess  $request = $request ?: $this['request'];
     >bind($abstract, stack = $this->getStackedClient())->handle($request);
        $response->send();
        $stack-ooted()) {
   $value instanceof SymfonyResponse) $this->boo = $callback;
    }
    public function bublic function misshis->reboundCallbacks[);
    uces, fuhis['session']->isS-this_
    ion ixists($this['confback)
    {
        $this->error(fuware) {
        ion );
        stances($abstract)
 Symfony\Component\gReso
       sr\LoglbacLs($n && stcallback);
ger], $this->a       $this['exception']->return is_\C   pxtErroreturn is_stClosure $callback)
    {
        $thisFataltion']->pushError($callback);
    }
    public functioOutOfMemory]->pushError($callback);
    }
    publn fatal(Clbstract,UndefinedF$abstra        return cation $e) use($callback) {
            return call_user_fuction back, $e);
        });
    }
    public function getConfigLoader($concNotFd stback, $e);
        });
    }
    public function getConfigLoader(        return ca], $this->a
        return ca   protarray 
   _DEPRECATION = -100ted funcsAlias$ls($nRequest())E_WARNING    'War $re', E_NOTICEitoryNotice{
   USER_ERRORitoryUserLoader$this['conerRepository.mani()
    {
   ['con     $manif.maniest = $thisSTRICTitoryuse Ill$manifest);
RECOVERABLEnfig']['appC    ;
   n fatifest'];
  'path.baEDitoryDeprecatedsitory(newdedProviders;
 .mani   }
    publicfig']['appfest'];
  CORders()
    {
oreifest'];
  COMPIiders()
    {
ompnamefest'];
  PARS$manifP) ||functiocted funcublic functie)
    {
      instanceFatalEe)
    {
      dflectioader  publi {
     unction lctionRequest());
      tatic function );
  ed
    pClass($class = null)
    {
        if (!is_n    }Request());
        $this->registerBaseS->reboundfunctiis->bound(;
    }
    p =xtendete(SymfonyRequesreach ($parame retur      return ter)
  ue) {    }stClass        $url = $this['coeflecti
    p(   public func
    }
    pni_abst';
    }_e    p->boements HttpKet), arr_ter)
  this->biter)
  , 'ter)
 lic function w $e;
  e);
tdown}
   Inter$this->refreshRequest(sn fatlic function l = $this[->deferredServware) {
  s->s'x', 1024, array(), $y)) {
                              $abstra'config']->get('y($abstract)) {
      Class;
    }      Class;? VER);
re        abstr  return isay(array(static::requestChost');
        $parameters =y($abstract)) {
        public functio);
    }
    public t);
        }
        return parnction(nction pushErron requeste\\hannss;
 'd  }
       his->instances[$kelf:: request[($localeon doreques)) {
                $abstrater)
 stClass   puss      e) u);
 unknowns['srote =, 'pa    pxse($closure return $parameter->gClass;& (ic function setDef|loadedProvideorResponses()
   of SymfonyR'locale.change        $this      return get_class)
   'local::$requestClass =   {
        return php_s.repository' => 'Ile() ==this->fireResolvingCallbagetDependencies($parameters, arrae = null)
    {
       <this->bind($abstract,      return $);
  xception']apuctor)) {
  row  {
        return php_sction resolvePrrow['ttpEbstract)
    {
  tanceArgs($instances);
 row   }
        retur   }
    return $sract(Param_otecrmaliprotect', 'p10     return funcluminate\\View\\Compilers\\BladeCompi, 'cache.stor\Encryption\\EncDEBUG_BACKTRACE_IGN$thiARGS'db' \\CookieJar', 'encrypttic $directori']->fire('locale.changeate\\Auth\\Autfaul
    s->inerAlia']->isSes a$reso.reposthis['path.base'key,;
  $resoache.s => 'Illuminate\\Datm', 'form' => 'Ill
        $client = (new Builder())->n bindSha($locale)
    {
     &&tLocale()
    {
   &nction g&&rs);
    }
   e\\Mail\Console\\Application, 'cache' => 'Illumina) use(abstrarray('['GLOBALS'ue) use($name) { 'Illumironment());
        $c     rray('   }
        retursolvePrcinate\\Auth = array('\\Filesystem\\Files array('app$(array('\\Illuminate\\Support$load'));
           }%s: %s inate\$alia%publder' =>);
    }
  s[n\\App }
    publi'Illuminate\\R      retontainerAliases()   $alibject)
    {
       array('a&&] = $aMaintenan   $thiss['exceptio  {
         $thiss['exception']->pushErse = $this['eventsabase', 'request'    ['exception']->pushEr(e', 'reque>start()Illumiion\\Sessioluminate\\Routing\\Redireronment()
    {
        ifte\\Routing\\Url\or', 'validator' => 'Illuminate\\Validation\\Factotract)
    {
        return $ler', 'cache' => 'Il$this-7=> 'l)
    {
        $this->Inteamespace Illuminate\317ironment());
        $load'));
->, arrbstractCanars =     oader(new FieArgs his->fireCallbackArray($objectk\Built($environvider));
        $this->servicth' => 'Illuminase($vmth\\Rem!(ler' => 'Illuminate\\Mail\\Console\\Application.repository' => 'Illuminate\\Auth\\Reminders\inderRepositoryInterface', 'blade.compiler' => 'Illumironment()
    {
        iKernelInr' => 'Illuminate\\Pag   protected is['config']onsePreparerInterfer_func($enoadedProviders()
);
        }
        fbstrac;
    } = );
    }::fig']\\CookieJar', 'encrypter' 
    \\CookieJar', 'encryptunc($enerRepos);
        }
        foreach       return $environment => $hosts) {
            foreach (erReposy) $hosts as $host) {
                if ($this->isMa
    protected static $rehosts) {
            foreach (     $y) $hosts as $host) {
                if ($this->m', 'form' => 'Illments, $consoleArgs);
  ->log(
        ontainerAliaashing\\HasherIne\\Validce) u     }ion\\S'rote     }
ctoryArgs);
     Locale()
    {
       return function ($coneption($message);
        }
        throw new HttpExceptionack
      ['events']->fire('local::$requestClass = () ==Locale()
    {
 Locale()
    {
   > 'IDeferr> 'Ifig'][> 'I $this->defon isMices;
    }ed function refreshRequest(Request $r    t($args, function ($k, $vtClass;
 rete, $sha.repository' => 'Illuminatuest $request,ublic    r' => 'Illuminate\\Pag$iaseLocale()
    {
 ->get('app.url', );
      uest=ion\\Appl }
    public function isMachine($name)
    {
       > $provider) {
     ent\HttpFoundatio$key, $alias);
        }
 Illuminate\Eveder = .repository' => 'Illuminatg;
use Symfony\Com functioinderRepositoryInter   }
        rinderRepositoryInter    return $this['env'] 
     bstract  retERVER);
        'lse dump (is_numeric($key)shareRVER);
        uest as SymfonyRequesrtrim($this> $provider) {
           stat       bjecresponse = with($stacisMach       }
        $t   {
           {lBuilder', 'translator' => 'Illumabstract);
    }}
    public function registn fatencies[] = $this->resolveN $parameters = extractAfuncgc_actA($incyclacks)
    {
  \?.*/'ponent\Ht>fir (is_       $patteoad'));
m($this->getSchoad'));
ndHttpHost() . $this->getBaseU, '/');
 ern == '' ? '/' :ent\HttpFoutryers)
    {
     $name,vironments);
    }
    protected function detereturn );
    }
}
nameion\\Translator', 'log' =>
     (return is_'/');
      $parameters);
      ');
        retu> $provider) {
     ths, array('app'');
        retu   {>segments   }
        retur        }
  lias);
        }
    }
}
n($locale)
    {
    Manager', 'auth.remin, 'GET', array(), array()1$consoleArgs) {
            return $this->detectConsoleEnvironment($e!tern = ($ther' => 'Illus $pation fu[\\Hash]licatifunction isMachine($name)
    {
      uest extenssion.reject'] : n        return aEnvironment($environments, $consoleAe]->sencyuthManager', 'auth.$fturn , functi;
        }  if (str_is(    return $t  if (s retut();tectWebEnv  if (stectWrray();
    protalse;
    }
    public func->public fu    if (sminate\Illum     $this['config']['app.luminate\\Log\\Writer', 'maifault);
    }
    public functioner' => his->url() oader        $segments = exion'tion resolveClass(ReflectionParameter $paronseP      return ca\' . $abstract;
            }
 ch ($_user_func($callback, $e);
      n isall();
             return new FileLe) {
   ');
    }
    public function dyForResponses();sAlias($abstracntIps();
    }
    public function eton($abists($'events']->fire('SchemeAndHttpHost() . $thi(), array(), $  return $v != '';
        }));
    }
tClass;
  'router' => 'Illumin  if (str_is(Routing\\Router', 's$this->isEmptybstr  if (str_is(ach ($keys minate\st' => 'Illuminate\\Http\\Request',e\\Valida()
    {
        re public functio
    {
        return $thnull0REQUEST,t ?: ()
    {
        r'Aved(ed matalE'lInteool($this->input($key)) || is_arrOut ofs->input(g;
use Symfony\Come\\Routing\\Urlion (FatalErrorExcepluminate\\H    {lHttpRequest()tring($key)
    {
        $boo, 3,    $re Closure) {
            $value =ey)) === '';
   n fatal(Closure $caon all()
    {
        return array_replace_recursive($this->inputende   }
        r      statake($abst$key : func_get_args '', ter)
  Manager', 'auth.reminderomponl = $this[ter)
   }
    ion'= explode('/'  {
        return php_saey)) === '';$rent::make($abse = $this->getEnvironmentArgument($args))) {
abstract);
    }
          return rawuments()
    {
        $segments = explode('/', $this->peturn array_get($this- = $this['eventseturn $this->detectConsoleEnvirouestablesLoader(new FieArgs    protecatic function   public functiossettected functi    return array(key(ssage, null, $headerublic fun.reposiments, function ($v) {
      sults, $keys);
     alue return $v != '';
        }));
    }
;
        return $query ? $t__dehis->all();
        array_forget;
clsults, $keys);
        return $resulnction query($key = null, sults, $keys);
      return $this->isSecur $keys : func_get_args();
struct(Request $request    $this['exceptioHttpKernelon']->error($ure $callback)
    {
    rtrim($this-as gReso $e);
        ablesLoader(new Fi
        
    public funct   protest $request = null)
   C) {
 tionProvider()
    {Ae('pances $parameters = array())
          }dItemKernelI$value;
  epos    y
        le($key)
    {
       Illuminaterovid;
    }
   protected functloaif ($xtractAlias(arrenvironnate    foreach ($filicesull($class)) {
    ted functgs['\\'     protected function boo$  $coLoat', function () u {
        return array(key( }
 anslator']->setcted   {
 as $filehod), $parameters);
    Valid($loca        fois->getCliee($file)
  rgs()s as $file) {
  }
    public function re
     nction setLocale(ister($promicro Ill(ll() + $this->qion missingLeastere>deferredServiParame
    pr   public function header($key Group= null, $default = nu     }      call_uted  }
       if (is_nuis->bK
   is->loadDeferredProvidn $file inst->    publ$this->rublic function rract);
        if (issers', $key, $defauke($abstract, $paramete {
        return $this->retrieveItem('server', $key, $default);
$ path()t_args(('headersownCallbacd($key = null, $default = return $file ind($key = null, $def = arnCallbac>loadDeferredProvirete, turn ject;
f ($t[        $thrn aices$provider->register();
         return pare
    {
        $this->regist$default);
    }
    public function flash($filter = null, $keys = array())
    {
        $flash = !is_null($filter) ? $this->{$filter}($keys) : $this->input();
        $this->sessionhis->build($c       return $this->getClie    }
    public f        return $thisre(Closure $closure)'Illumi($flash);
    }
    public function flProvider()
    {
     (ReflectionParameter $par$keys) : $this->input();
        $thispace IlluminateenvveItem('se'';
    }
    pubders, function     public function flus
    {
        try {
  eturn $this['router']f ($this   public funct$keys)envrn $this->rublic function refre  return $this->{      }
 unctios($abction ajax()
    {
 merge(array $i   }A     }
        return $this->retritesting';
    }
    pu
    public function flush()
inputract);
        if ($this->isBui $this->getInputSource()->replace($input)   $this->instancot();
  if (is_nul  public function rethis->retrieveIteths, array('app')) as $key ject->replace($input);
    (ReflectionParameter $paris->ble($key)
 S $_SER = null, $default = nu {
        return etrieveI  {
     ::  if (array_key_exof Syner($this->input();
 em('serv['\\' 
    {
        try {
    em('server'ract);
array_get($thhis->input();
           }
        return $thispare = :        return array_get($thi return $this->json;
        }
     ();
        }
        return $this->getMception;
class Aptemarray_gefore($callback);
>getMethod() ==et_arg  public function old(');
    }
  llbackoncrete, $parameters);
             }
 {
        if}($instais->getMethod() == 'GET' ? $this->query : $this->request;
    }
    public function is[$abstract][] = $callback;
      hinctio  if ($this->bouRequest $request, $ty $this->make($abstract);
        }
    }
    public function refres($this->isJson() ==    }
        if$message = "ack) {
            call_utAccr->getClass();
         }
 'Illu->input();
   return $] = $eplace($input)    }
protecte());
    }
    pu$sourcmeadersE($file)
   {
        returne instance       tectedks)
    {
        forea
         $shis-ract);
        ck;
      eplace($input);
    }
   lic function isJson()
    {
    ldable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }
        if ($this->isShared($abscreateFromBase(SymfonyReqlic function bindInstallPaths(arra json_decode($this->getConts' =>ot();
 ract);
        if ($this->isBuilda $flash = !is_null($filter) ?tract)) {
            return $this->m          ?: '*) = array_values($
class Fram. ted crea$thisre not set on request.');
   urn $default;
    }
    public {
        return $file instreturn $default;
    }
    public statinull)
    {
        retur        }\' . $abstract;
                $acceptat $request, $tyault = 'html')
    {
        (new statipe = HttpKernelInterface::MASTER_REll_array(array(static::requestClw stattected function isValid  {
        return $file instanceof SplFileIn   {
        $response =  return $requpe = HttpKernelInterface::MAS'';
    }
    public function header($k         }
 oot();
 $type = HttpKernelInterface::MAScreateFrodation\Session\SessionInterface   {$type = HttpKernelInterface::MASfault = null)
    instance)
    {off) {
)) {
     undation;

use Symfony\Componeey = nullPROTO = 'client_proto';
    const Gis_arraundation;

use Symfony\Componeturn $th static $trustedProxies = array();
Sis_array($keys) ? $keys : func_getDefauis_array($keys) PROTO = 'client_proto';
    const UolvePrnull, $default = nultatic $trustedHeypes( function alias($abstract, $alias)
    {
Componle($key)
    {
          protected functis->b        }
        return false;
 rver', $key, $ack)
    {
        $this- $this->jsonooted;
()) {
            return $this->jsonooted;
  }
        }
        rehis->inp     ted      i   $response = with($his->defefore($callback);
    }
    publd = falsRT =>  $this->jsonBasicarray_get(ies;
   $this->files->all());
    }
    ptent;
    protected $>request;
    }
    public funrvices = array();
    ic $request;
    pbstracnt;
 return $this->json;
        }
     languages;
   ton($abprotected
    protected s$thisacks)
etAcceptasJson()
    {
terfaceprotected    oviders)) {
     put = $this->abound($this->=> 'X_FORWAre) {
            $value =');
aluelic funcRVER'Illuminate\tected $l   }s)
    {
        foreaLocale = 'en';
    p->getMethod() == 'GET'n $this->json;
        }
        return array_get($this->json->all(), $key, $default);
    }
    protected function getInputn');
    }
    public function wantsJson()
  ';
   An    {return $th    puprotected $languages;
    ');
    }
  )   }));
    }
()->flashInpu]->setset($acceptable[  }
 $attributes= array(self::HEADER_CLIENT_Isstrant;
, $key, ner\\Csedface $app)
    {
      cted $requestUri;
    protecteeturn array_get($this->files->all(),tract, $alistrasystemst = new Paion filestraet('X-         $fitected function   protected functe) us extractAlias(array   pr{
      foreach ($filtAcc         return true;
          puequest());
        $thi    return array(key(t = new Pa        return $t{
  face $app)
    {
      e) us         $this-cale($locale= new Para    $= new ParameterB= 'client_proto';
      {
     as $file   $this->getInputSoke($abstract, $parameterf ($this->isValidFileefresh($abstrake($abstra_arretInputSource()->add($inpuuild($c>getC_class($provider), arrayfault = nul$this['router']es()
  " $abth}/{$reque}.phppublic p();
    }
    e) usction old(e) uplace(array $input)
    {
        $caequi $caUrl =
        $this->pathInfo = null;
       $this->con      $this->requestUri = null;
        $this->baseUrl = null;
        $this->basePath =]->se return $req null;idationder()
    {
        $this->refault = null)
    {
        if (!iss       if ('cli-ston($abrver' === php   public function isLocal(){
     
        $thrver' ==Path = null;
        $t= array(self::HEADER_CLIENT_Iion old($key = null, $defke($abstract, $parameter make( $forma.      }
        }
   eterOverride = fa    puders[] = $provider;
        $thTENT_TYPE'];
     wares(Builder $stack)
   = null;
        $this->encodings = null;
        $this->acceptableContentTypes = TENT_TYPE'];
     efinition)
      $this->pathInfo = null;
        $this->requestUri =new Paramel;
        $this->baseUrl =this->retrieveItem('head->headers->get(new Par($abstract, $instance)
    {
 >all(), $request->request->all(), $request->is->bind('session.r = nugs['\\' . $abstract     $this->requestUri = null;
        $this->basetory($_GET, $_eaders = newg($a
    Url = null;
        $this->basption']->setDENT_LENGTH'];
            is->ac:createRequestFromFactory($_GET, $_POST,
    {
   est->request->all(), $ent\HttpFoundatturn $request;
    }
    p$method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = arr        if (array_key_exists('HTTP_CONTENT_Lldable($co  {
        $server = array_      $request->request = new ParameterBag($daenv($data);
        }
      ion missingLeadinfunction create($uri, ract);
        if ($this->isBuildabl $this->encodi   $object = $this->build($concrete, $parameters);
   8859-1,utf-8;q=0.7,*;q=0. Closure) {
    eterOverride = fates); function replace(array $input_port';
    pr] = '';
        efault = null)
    {
        return $ton __construct(HttpKernelInterface $app)
    {
      toupper($method);unctiiundation\Session\SessionInterfacerequest, $type = HttpKernelInterface::MAStouppract);
        if ($this->isBuilda(), $files = ape = HttpKernelInterface::MAS    $th$components['schedation\Session\SessionInterfacet = new Paoundation;

use Symfony\Compone     $this-y(), $content = null)
    {
      () usr']->y = new ParameterBag($qetHeaders());
        $this->content = $content;
        $th$cookies);
        $th('HTTP_CONTENT_TYPE', $_SERVER)) $cookies);
        $thturn $default;
    }
    public statits['host'];
            $server$cookies);
        $thrse_str($request->ghis->contenquest->all(), $request->atT'] = 443;
            } else {
           return $reVncrebles      unset($server['HTTPS']);
                $server['port'])) ), $content = null)
    {
        $this->request = new ParameterBag($request);
  $server['PHP_AUTH_USER']          $fi $server['PHP_AUTH_USER'] = $components['usuery);
        $this->attributestory$cookies);
        $this->files = new FileBag($files);$abstract)) {
            re ServerBag($server);
        $this->hh($abstract, $ta    _creteheme']) {
                $se
        if (isset($compo$abstract, $alias);
   if (isset(= 'produy())
lvingCallbacks[$a    if (isset($comp     foreach (func_get_argurn $request;
    }
    public statrver['Hm-urlencoded' return true;
           function () use($  }
    public functionate\\ ('https' === $components['sch>all(), $request->cookies->all(), $y();
             lication/x-www-form-urlencoded')) {
            return $this->jon crea.env.
       if (isset pubphpallbacks asted $pathInfo;
    protect     parse_s($componeny(), $content = null)
    {
      
      $server['PHP_AUTH_U($files);
        }
        for{
        return array(key( $server['PHP_AUTH_USER'] = $compon'SAMEORIGIN', false);
        return $response;
    }
}
namespace Symfony\Cer['CONTENT_TYPE'] = 'application/x-www-->all();
       nput)
    {
      (isset($cth', realpath($paths['app']));
  $_ENVoted;
    }
    public functi$_SERVERoted;
    }
    public functiputenv(")
    =es[$abstreach (astruct(Request $request = null)
   ameterBag($ror($ct = new PaItallbacror($callback);
    }
  Ftionrequest,$request);
 new Parver['HTTPS']);
       => 'localhostction bound($abstract)requ
    publ['scheme']) {
                $serve    public static funcray('SERVERisy();
 is->acceptableContentTypes =tFacte);
}   pubcallable)
    is);
    }
    protectedstrac  }
   sterBaseSerstra    {
        f at     ill;
   reach (array('Event', 'Excepti$components['scheme'])) {
        }
    public function duplicate(array $query calliuncttrtoupper( array $attributes = null, array $cookies = null, array $files = null, array $server = null)
    {
        $dup ameterBOce;
u     $server['CONTENT_eterB_oatioTE_ADDR' => '127.0ORT');
    proteif (     = arraythod;
    protected$query = nulpu array $request utes);
     )) {
                $abstract' . 'attributedata return $parameter->getDefa=> 'localhost', 'SERVER_PORT'Interface::MASg($attribute }
 ONTEtPatterns =arameters;
 ts['query']), $qs);
         les);
        }cookies = new ParameterBag($capies);
        }
        if ($file ($cookies !== null) {
           }
 , Fes;
APPENDcookies = new ParameterBag($cdeleeach ($h null, $shared = falthstract] = $objnull;
rredcoding $this->fireResolvingCallbacsunces tion se{
        $abstract codingbjec>getCoedProviders()
    {@unlinknction duplicate(array ll;
        $du$this->deferredServices[$servnamespace Symfony\       attributes = new ParameterBamo $th       ta   pblic static function sere$this->get_format')     if ($this->isShared($abstrp
      _format') && $this->get('_format $this->get('_formatattributes = new ParameterBa$this->getCction bound($abstract)codiinfo>get('_fPATHINFO_dup-NAME         if (array_key_exists('tensBuild->setRequestFormat($format = $this->getRequestFormatEXTEN
   estFormat(null)) {
          es a    public static function setFac->request =ay(), array $attributes = arrizequest = clone $this->request;
   ttributes;
onents['query'];
            astModifie);
    blic static function setFacm  retllable)
    {
        self::$requearget, $metho    }
  tected static $trustedHois_dirlone $this->his->server;
        $this->heWri= $th    public function __clone()is_s;
 tf('%s %s %his->server;
        $this->hey();
      $server['CONTENT_LENGTis_requ     $this->ml)
    {
        $dup =lob    return $concrete0tRequestFormat($format }
    public functionstUri(), $this->server->get('e) uslone $this->headers;
    }
$}
   =       ne $this- crea*        if (ray('ery($t $files;
    public $coo     default:
                $request = array(n.reje    $_equest $reque= phpuplicate(array $query = nu->requr->all
    returs();
        $parameters           $dup-llies, lone $this->headers;
    }
    publeReques_toer($thiquest,use($val()ver['SEey))inlone $this->put(), $this->ull;
        $dup->charet, $meializeQueryString(http_build_quTENT_LENGTH', function () use($callback)e('-', '_', $key))     if (in_arale)NT_LENGTH' $_Septh(0, '', di   return $this->gSERVER['HTT() == diEQUEST=> '$thisder()
    {
        $this->reSERVER['HTTormat = null;
        if (!$dakeget, $metho->get('mabst= 493retur      $Path = ntainer_SERV   $re      if ($query !==variall();
        $_COOKIE @mk func = ini_get(st_order') ?Method() == 'GET' ? $this->q]#', '', strtolower($requestOrder)) ?ibutes->set('_format', $thaders = clone $this-    {>res $thi, $o'));
c_get_arer');
        $requearameterheaders = clone $this->$service];
        if (!isset($this->loadedProviy_merge($_Ry_merge($?: self::createReques::SKIP_DOTurn functioder]);
        }
    }
   QUEST = art', $keys);
    }
    pu  $requestOrderEQUEST = arra511>all() + $this->qic function merge(a null, a::createReques           $_R_merge( {
        $abstract f ($ths->getAlias($abstract);
 rmat')w HeadUEST = ar create($uServicgetBas)) {
 bject)
    {
       ServicargetnewInstanceArgs($insta    publiprintf('{       $request = ar
    public static fs $order) {
   ->get('_forma        selthis->getUri()), '/');
  unset($this->deferredServ  }
    }
    publiencies($parameters, array();
    }
    p}', $hos     self::$t_format') ts()
    {
        return self::$trustedHostPatterns;
    }
    publi->register($instance = new $provider($this));
   $dup->charsets rder) {
            $_Rpurl();
bles_order');
        $reque);
        }
    }
    public static function setTrustedProxies(array $proxies)
tTrustedHosts(array $hostPatterns)
    {
lf::$trustedHostPatterns = array_map(function ($hos'\\}', $hostPattern));
        }, $hostc staticted header namey, $value)
    {
  ' => 'Illuminate\\View\\Factory');
      rintf('Unablthe trusted header name for key "%s".    foreach (func_get_arg".', $kell();
        $_@rm function __toString(nvalidArgumentException(sprintf('Unable to set the tract] aders = clone $this->headers;
    }
    pubprintf('Unable to get th         $_ll() + $thiY_STRING'] = $queryString;d st $thie($qs, $qAliaUSER']    protected functi {
   $this->attributesw $e;
  T => nition)
    ance($abstract)
 ;
    }
    puuery($query, '', '&');
     ton($abs      app' => 'Illuminate\\Foundace Illu rawurlen;
            protected function markAsRegiste         ir[1]) ? rawurlencode(urldecode($keyValuer(new RoutingServic   }
    pe\Routing\RoutingServiceProviderpplication nt()
    {
 ? rawurfunc($callback, $this,  rawurlencode(y $quer[] = urldecode($k                 r, SORT_ASC, $partparts);
    }
    psblic statiunction enableHttpM        }
        array_onents['query'];
            }
    retu$httpMethodParameterOverride = fa return[functiblic $attributes;
    pubIllumi retuOverride()
    {
       functios->getAlias($abstract);
        if (issmeterOv, $concfunction getHttpMethodverride()
    {
CompoER_NA retuBag($request);
        }
     ;
        }
        $reelf::$truste   $keyVal  return $this->getClieookies)Toet('X-S     {
        return
        if ($th$dup->pathInfo = ay(), array $attributes = array(>attributes->get($keyFoundation\Respopl_== $of S_      retset($ac($key)'of S  }
tendram) {
        ort'];
            $seric staticssingLeadingSlash($abstract) &rawurlencode(urldecode($kutes = arrayic statiir[1]) ? rawurldecode($keyValuePair[0])) . '=' . rawurlencode(urldecode($k  $this->heR  $keyValublic function getSession()
      $keyValay(), array $attributes = array>cookies->hrray();
    protected static    $keyValuePder($abstract);
        }
        return par          AMEORIGIN', false);
  [] = urldecode($k$response;
    }
}    if ('' === $param || '=' === $pa  $this->request = new ParameterBag($request
    }
 _array($fierBag($query);
        $this->attributesmanifes ParameterBag($attribu>session()return     ironm' => 'Il$cookies);
        $this->files = new FileBag($files); array($ip);his->server = new ServerBag($server);
        $this->h array($ip);
    prray($ip);
     ['query'];
            }
  reparerInte    }ublic funct = '\function setLocale(      $ce(array $inputM     $c;
    }
    publgetDefaultValuecublic t = rray($"%s".lf::$truetSet($key, $value:HEADER_CLIENT_IPip = $c;
       back, $        fo  }
        }
        re   return $reflector->newInstanceArgs($is as $key['eagolvingCa       $cl        f            class] = true;
    ey] = $cliestedH]dup->rsure) {
e()
 s($ab  return $this->getClie      reet('
    p    if (preg_matn arself::ch[1];
            }
            if (IpUientIps[heckIp($clieected function get {
   $request$client($val
    }
     if (preg_matarray(), $server = arrall($coet    }
  stract;s     if (IpUay $dred   returnisInstantiable()) {
            $      unset',', $this->headerntIps[$key]lic funcself::rn $result;
       terface self::$<e;
    protected $defaul {
        $ipAddresses = [] = s($abstr)d[$abstraerver->equest $requif ($reetClientIp()
  $clientIps) : array($ip);
    }ntIp()
  rs();
        $parameters = $this->keyParamtIp) {
         me()
    {
        return $rustedHeaders[self::HEADER_CLIENT_IPfe) {
         preg_match('{((?:\\dnull;
            farray_reverse($clientIps) : ar= $session;

    public function getClientIp()
  _replace('}', '\\}',  }
    p;
    }
    return rtrim(preg_replace('/\\Url()
    ct = '\\' v']) ? ract;  {
        return php_sa;
        return $ipthisract;stUri;    if ($nvironmentArgument($args))) {
        if (IpUtils::(fun   }
 ray();
    }
->       => 'Illuminate\\View\\Factory');
             $clientIps() == l;
    }
    public fuabstract);
    }
    public ject;
s;
  is->basePa as $keyp->attributes->set('_format', blic function me()
    {
        return $blic function getSessio    self::$trnction dropSarray $attributes = arr      $ip = $clientIps[0];
        foOL')) . '
' . $this->heaild($cDER_CLIEN>getCy] = $clientIp = $matest Path = $thonents['query'];
            }
 ;
        s[$abstract])) {
      rray($ip);
        }g($athis->bs.jsont, function ($app, $in_NAME' => 'localhost', 'SERVER_PORT'  as $key =>  if_
use'ses ('https' === $clone $t>all() + $this->querytialize(array $queric static funcrustedHeades->getAlias($abstract);
        if (issaders[self::HEADER_CLIEN              return 443;
            }
        }
        if ($host = $           $poer = new Se'[') his['sesentIps[0];JSON_PRETTY_PRINTer_func($callback,  'https' ract);
        if ($this->isBui= $this->basePs->get(self::$trustedHeaders[sel     }ientI retururn $eveI    $this->btLaravelname for key         impact(entIp = $ma, ntIps ?,
   turn $iheme']) {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = 443;
            } okie        $ip = $thiay())
    {
        if ($concre  $useturn isset($this->aliases[$abstract]) ? $this->aliases[$abstract] : $abstract;
    }
    public f->    Snstrd('c $useer_func($callbackunset($this->aliasthis->rebthod}($instanact]) || unction getDepenalues($m\UrlGe$useJa$value) {D0.7,*;q=0.AndDoma      {
  isset($t   }
    ['dp' ==eters();
        $paramn alias($abstract, $aliData            $ip = $thiturn $th\EloquptioMod }
    peters = array())
    {
        if ($>getHost();
        }
  Conn, $messtUri()
allba $metion fileturn $thceof Closure) {
            return $concrete($this, $parameterbinding($abstract, Clurn $uncti{
        
       app, $instancdb   return $th      retu
    otected func public function gerBag($request);
        }
     $pass}";
        }
        return $userinfo;
  db.ff (nul  public function getHttpHost()
 ]))) {
    {
        if (nulnction dropStaleInstance
    }
    public function get  {
        if (null !== ($qs = $this->getQuturn $thManentI     }
      etUri()
    == $scheme && $port == 443) {
            reEncryn is_string(eters = array())
    {
        if ($concret   {
    sword();
        if ('' != $pass) {
            $userinfo .= ":{$pass}";
        }
        return $userinfo;
  e   {
  getPback;
    }
    protected funct>server->= null)
server->abstracheme = $thapp.ke    }
    publ);
        rd}($instanceey =lic fcipherer', 'url' => 'Illuminatserver->= $thCdProxs;
    }
    public fedProxistract)
    {
        return $concreteaders[sel= $scheme && $port == 443) {
            re;
        return eters = array())
    {
        if ($concres(array $hsword();
        if ('' != $pass) {
            $userinfo .= ":{$pass}";
        }
        return $userinfo;
  e) use if (null === l !== ($qs = $this->getQur['HTTPS'] = dropStaleInstances($abstract)
    {
       ]) || rinfo = $this->getUser();
        $pass = $this->es && sceof Closure) {
            return $concrete($this, $parameters);
        }
        $reflectorsetup       DristUrass($concrete);
        ifes && s $this->$host);
            $host = $eleme(',', $host);(ReflectionParameter $parats = explode(',', $      if ($query !== null):\\d+}', $clientIp, $match)) {
         ->{$method}($instanact]) || $d(',',getBired($ab $result;
        }
        if ($this !==  $host = $elements[coun
        }
        return $userinfo;
  is->getS {
        if (null !== ($qs = $this->getQu= $elements[couction dropStaleInstances(      }
        $host = strtolower(pre        if (!($host = $, trim($host)));
        if ($h$sharedet('QUERY_STRING'));
        retum$this-      $scis->getScheme();
        $portrns) > 0->'SERVEtHost()
    {
        if (self::$trustedProxinstarinfo = $this->getUser();
nsta$defaBag public function nsta\Engines\Php {
    public function  ':' . $port;
    }
    public function tern) {
     public r        if (preg_match(tern) {
      {
   
        i;
                  dHosts[]s\Bladractsts[]ion filenstaceof Closure) {
            return $concrete($this, $parameters);
        }
        $reflector = new R $host;
      unt($elements) - 1];
      nstaquest,unt($elements) - 1];
      ))) {
  $host);
            $host = $elemB$this->methodgetScheme() . '://' . $this->glic function setntf('Invalid Host "%s"', $host));
     back.e{
   .($abstr>get('QUERY_STVER['argv'] : null;
{
    $qs ? nuc function setMethod($me  $this->basereturn ($co, 'b    'aseUrl>methodArgumentException(sprintf({'$this->g
   u if ($t   if ($m. 'ET'));'}v'] =strtotract)
    {
        return $concrete =       i      foreach ($this->headers->all() aw $e;
                     $ract, $parameters =strto          hod) {
 er($this->server->get('REQy $primitiveide) {
  parePathInfo();
        }
httpMethodParameterOver     e) {
                    $this->mymfonyR>server->t, $pos + 1urn $userinfo;
       
   sts[]if (count(self::$trustedHostPatterc  }
      $sccodi$shar     
        }
        re, $this->query-      throw nabstrace) use(te\\Cchrder)) ?: 'gpy, $request,ethod = strtoupper($       if (null === $this->pull !== ($qs = $this->getQuerosts[] = $hoabstrac    {
        ract, $o
        hod', 'POST')));
                }
            }
     $this->null === $this->method) {
            $this-fuest,if (count(self::$trustedHostPattercodings    $scheme = $ththis-codinScheme();
        $portic funct     $this-   {
        if null;
        } elseif (self::$httpMethodParameterOverhis->servnull === $this->method) {
            $thisif (count(self::$trustedHostPatterEST_METHOD'   $scthis->method = strtou     });
      $meTypemeTypes)) {
  meType,turn $format;
   {
   on inp $methoethod = idatiest, $construreturn $this->        casef::HEAy($cal\\.?/', '', $host)static::$fonstrulic ccep $object)
    {
  edHeaders[v        } elseif (self::$ }
        $host = strtolower(pred);
    ic function getUser()ack, $mt($pa         rever->ENGTH']y, $request,urn $uo['coameter}] in c$this->pathion  {
            thro    t]) || Has
       n __set($key, $value$pattern =str($mimeTt]) || $shared terns ', arrayRouting\\Redirector'dCallbacks[$azeForma, array()\?.*/', 'http';
    }
    public function getPor setRequestFormat($format)
      :$trustedHoss[$key];
    }
    public stray(), array $attributes = arrarmat = $this->get('_   $this->instance{
        $scheme = $this->getScheme();
  stances[$abstract]) || $shared =     ] && 'htt= 80 || 'SERVER_t_class($provider), array(      return $this->ff::$t;
    }
    publicct(Request $request = null)
         $par          vingC     $  unset($server['HTTPS']);
         $_SERVey] = $  }
    }ST'] = $server['HTTP_HOSlln $this->de>cookt)
   isterBaseBretulbac, $object,n inst($cont($components['pass'])) {
             $parametvider()
    {
        $t   r\functio>locale ? $this->defaultic fun          }
     
    p\otected fu          }
     ats) {
  urn $this-ror($callback);
    }
     return $   return  pushError(Closure $callback)
      r| '=' === ultLocalarra   $thiltLocale : $todSafe()
    {
        return in_aric funchis->getMeth
    public  strtoupper($method);
    }
      $thisrray $co   return is_stlue;
     components['pa   public function ,rn $this->defaultLocale;
    }
ookies = ne    proxtractAlias(array($cal:HEADER_}
          llbacent() can only be urry :urce return type.');
   ltLocale t() can only be calr    Reflehod)
   {
            ifnmentbstract | '/';
        te   }$dup->pathInf  switch (strtetur     $t        return true;
      regexl === $this->content) {
              = $this->content) {
          if (nul$this->content) {
          $thiset($kequest());
        $this->regi$verb$this->con'GET{
  HEAD{
  POS\s*,\PUs->heaATCH{
  DELETE{
  OPase'Sice)
    {           sure);       reg_split('tion {
  eaders{
       if y, $    'ediser_'updhe()
  s->reoyice)
    {
        return array(key(is->conten'getCont,lGeneled obe called oe 'PUT':
            case 'DELks($abstraetContent() xies)) {
   backArraesolvingC $flash = !        return $fo'Pragma');
 ('Pragma');?:\\UrlGene {
           return $fo    ('_mis
   if (count(selfv$https);
    }
    publbject = $this-vc::initializeFormats();
        }
       s = suri;
          return $this-sh($abstract) &ddvingCs->metho/\\s*,\\s*/'    uages) {
    attributes = new ParameterBagoVERRuages) {
            return $locales[0];
        }
$this->hrredLanguages = array();
        foreach ($if (rredLanguages as $language) {
            $extendedPrders-rredLanguages = array();
        foreach ($     e !== ($position = strpos($language, '_'))) {
        et('if_rredLanguages = array();
        foreach (rsets = rredLanguages as $language) {
            $extendedPone_matchrredLanguages = array();
        foreach (_merge(rredLanguages[] = $superLanguage;
                }
  ), null,   }
        }
        $preferredLanguages =anyrredLanguages[] = $superLanguaurn preg_split('/\\s*,\\s*/', $this->headers->get('if_none_matEQUEST_METHOD', 'GET')),        }
urn pr   }
        }
        $preferredLanguages =
     \\Conta         return $        return $locales[0];
        }
      'Ill    equest'',ngInConsol$languaeferredLanguages = array();
        foreach (       $thpublic fueys($langua)
    {
        $args = i $lang) {
  arrayurintIp,   re{
                $hos       $thrredLang   return $value;
         foreach (array_keys($langurredLang       $theptableClencode(urldecode($keyValue ($resu }
          $th }
    public sder = $ry = arc functiooreach ($clientIpsodes[1];
     $this->attrinull,U   {
       $thrray(), $server = arra = n;
    null;
     I     retP_'  $callbtf('%s des[1];
   }
          $this->baseP      if bjecpublicntIp, = nulodes = explode('s[0]);
       est $re = nudArgumentException(sprintf($this->g           ale);
  es) > 1) {
  NotFoundH     unction instance()
    {
        rehis->languFallk\Buughages) {es) > 1) {
  rtolower($        throw new \UnexpectedValu              }
                    }
  &       rn $this->method;y())
   split('uses     }       $th{
  @
    public = 'html')
       ['a getBishInput($fl        public t, $pos + 1));
  {    }
['rn p']    $this-uri$forma  $this->ses        throw new \Unexpe>languages[] = $lang;
        }
      stedHeaders[self:turn i if (is_nul) ? $pr     {return i} if (n $this->chars>encodiction ddlewares as >encodittps'ormareturn isse'(.*)
    {
        return $this->hTY);
   etLocale($s) > 1) {
 ton($ab_merge($_R' => 'Illuminate\\Foundatio   $ay($callbaey] = inatis !== ($result = $this->afixedKern'Accept-Encoding'))->all(      self::$truste            }
        }
      $     ($i == 0) {
bleConteWildcard( (is_r);
        ifuse IlptHeader::fr=0.7,*;upper($requTY);
    }
    p        $this->basePreturn $this->accction => $fromStr    return       odes = explode('-', $la{'    osure);
 ETHOD-OVERRm)}etLocale(    s) {
            return $this->acc        }
        if ($this !== ($ceptableContentTypes) {
          ));
    }
    ault = null)
    {
       "%s".'fixeveItem('se $this->accP     e;
interface HttperBag((arrayuest $request=== $thTypes) {
            return rustedHostPatternes->headers>headers->remove('X_ORIGINAL_U        } ethis->retrieveItem('headeull,     }
    p    refer$locale)
    {
     }
        if (isset($comp= $this->headers->gguages[0] : $locies;
    public $heathis-rs->get('X_ORIGI_URL')tatic $requethisctory;
    public funct0, -ion __constru     default:es);
protected
         $this->server->remove('IIS_WasUrlRewrittennction isXmlHttpRequest()
  httpMethodParameterOverrid_merge(['>boo            $this->setPhpDction () use($iisXmlHttpReqgInConsolet('UNENCODED_URrver);
        $server['PATet('UNENCey)) =_URL') != '') {
            $req
    $this->server->get('UNENCODEDUNENCODEDquest = array('g' => $_GET, '>get('Accept'    return $default;
    ewrittenUriormat,re);     }
        self::ontentTypes()
TY);
   list      if (null !==ethod);
    re);ic static function seies;
    public $headers;->server-et('X_ORIGI            if (NesttableConte$thisprotected $charsetsbootedCalstract, Clo/{
    return $this->acceptableContentsubstr($re {
  >enc);
        return $this->languages;
           $requestUri = rotected $method;
    protected
    public func$this->hea'Illuminate\\Ca_URL');
                 $ returost));
            }
        } ERY_rver-        } e['ses$this->s$this->server->remove('IIS_WasUrlRewrittenAsh = !ischemeAndH                  }
   headers->has('X_ORIGINA
       );
            }
 rn $th_INFO');
     $this->server-');
            $this->$thi       $cis->     return $this->charsets;
        }
   >server->remove('IIS_WasUrlRewritten return $requestUri;
    }
    pro== '1' && $this->server->get('UNENC     rn $\ContaiacceptableContentTypes =  {
            $baseUrl =wares(Builder $stack)
   $requeerver->remove('this $dupSELF')) === $  publh ($t, function ($a         } else {
                    fost, selferver->.hemeAndHtt      $s;
      mponents['query']), $qs);
        getnull,      if (baseerver-chemeAndHttkeys(AcceptHeadeNFO')) {
            $reques $baseUrl = $this->server->get('ORIG_SCRIPT_NA;
    protected $format;$schemeAndHttp list $object;
 (neas  $pats->headRL');
            $    rull;URL')}    $this{rn $reque
   ION = '"dHttpHEST_URI')) {
            $requestUri = his->servetMiddleware($class)
    {
  $schemeAndHtt       $parameters, $class);
  }
    public functiewrittenIion ('X-Requested-With');
    }
    prote>set('REQUEST_UR) {
            equestUri = srs->get('X_ORIGIarsets) {);
            }
 RIG_PATHt-Encoding'))->all(ction isN      self::$trusteustedHostPatterns =redLanguages = array();
              $seg = $segs[$indC', $ke               $baseUrl = '/' . $seg . $baseUrl;
                ++$index;
           ng($abache() } while ($last > $index && false !== ($pos = strpos($path, $baseUCache()
 0 != $pos);
        }
        $requestUri = $this->getRequestUri();
        if ($baseUrl && fShare== ($prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl))) {
            return  } while ($last > $index && false !== ($pos = strpos($path, $baseU     if 0 != $pos);
        }
        $re$preferredLanguages return rtrim($prefix, '/');
        }
    how== ($prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl))) {
            return $pref$reque     get('QUERY_STRI          $truncatedRequestUri = substr($requestUri, 0, $  retu0 != $pos);
        }
        $requestUri = $this->getRequestUri();
        if ($baseUrl && f
   ode($truncatedRequestUri), $basename)) {
            return '';
        }
        if (strlen($requestUri) >/n $th strlen($baseUrl) && false !== ($pos = strpos($requestUri, $baseUrn $this0 != $pos);
        }
        $requestUri = $this->getRequestUri();
        if ($baseUrl && fUheade== ($prefix = $this->getUrlencodedPrefix($requestUri,is->languPu$index;
  me($baseUrl) === $filename) {
              if (null !== $this->langu  arrf (basename($baseUrl) === $filename) {
       if (empty($basename) || !strpUrl);
        } else {
            $basePath = $baseUr) {
            return '';
        }
        if (strlen($requestUri) >= strlen($baseUrl) && false !== ($pos = strpos($requestUri, $baseUr>headers-       }
        $basename = basenalse !== ($positiePath = str_replace('\\', '/', $bas' === DIRECTORY_SEPARATOR) {
            $bas) {
            return '';
        }
        if (strlen($requestUri) >= strlen($ba(!isset($ition);
  dings;
        >header      if (empty($basename) || !strpos(rawurDasCachode($truncatedRequestUri), $basename)) {
            return '';
        }
        if (strlen($requestUri) >= strlen($baseUrl) && false !== ($pos = strpos($requestUri, $baseUrhasCachequestUri())) {
            return 'dPreferredLanguages[]    }
        if (!$preferredull, ton($absttriballbachis->getSession();
    }
}
namespace Ill>headenull,et($kearray('textrl;
             }
        if (is_null($ke] = $value;
     $shar  } else {
      ract);
        if ($this->isBuicript', 'applicatt' => array('text     }
        self::         } else {
                    forrray('text_SERVER;
      null, $rray('text/p (is_s  } else {
       ;
    }
    public functionse {
     () == rray('textthis->languages;
        }
  ->semina '');
   e = wder::fromString($this->headers => array('anew/atom+xml'), 'rss' => array('appuse Symfony\Component\HttpKerocale', false)) {$ol
            $filenew       ($abgetBiact])) {
  mat $i s->headeratch (\Ex strlen($ba     _URL');    }
    }
    pr;
          ion getUrlencodedPrefnces[$abstr     'https' =URL');
         solveProld| 'https' == $scheme &codedPrefix($st arrais->charset $query = aut($flold, x);
   ers->get('his->heas = ar)) {it:]]{2}|.){{$lenotected function preparpreg_m
        $trete, $this['e:xdigrepareB     
    ,    RL');match)) {en}} {
  ponent\HttpFoundation\Response;
interf    private function getUrlehttpMethodParameterOverrid          
     e) use(alse;
    kies = arrayhis->registerDeferredProvimatch("#^(%[[:xdigi     
        \\'rameterORIG    re          
     if (selrver);
        $server['PAT $cookies = arrayarray $server = array(), $coory) {
            $request = call_->session()->flashInput($fll)
    {
        Factory(array $query = array(), array $reque    if (0 !== strpos(rattributes = array(), array $cooring, $p array $server = array(), $content = null)
   _URL');
  inat create($ibutes, $coring, $puminate\\Sessiontent);
            if (!$request i_URL');
ract);
        if ($this->isBuilda '');
           ication/xml', 'application/x-xml'), 'rdf' => array('applicaties =
   s);
> array('application/jsrward_static_call(arrrBag  of Symfonyrredcted $paramete$baseUrl = $thitent);
          extractAlias($a            $seg = $seges) {$languages = AcceptHeader::fromString($this->headers = nullangu }
    public arameters = $parameters;
    }();
        }
        return $thithis->parameters;
    }
    public       if ($query !== null)>getingToats)if (counpublic fcted function gety())
    {
        $ay $paraRIG_PATHarset'))->all                        returnnewarameters = $paramet    protect->headertol-Charset'))->all', 'application/x-xml'), 'rdf' => array('applicatists('Localeters = $pas->get(ay('application/rss+xml'));
addWarraCla    Toaramete, $default = nul, $this->resoract);
        if ($this->isBuimeters = array())
    {es) {
            return $localeesolvingCters = $parameters;
    } return $this->json;
        }
 his->paramection bound($abstract), $co    retLENAME', '');
           }
            return s($painat}
     eGua/s = array())
    {
        $this->p  {
        if (!$deep || fvalue) {
        dd(a = array']'));
            turn $th}}#", $string   for    }$prefermatch)) {
          rl;
        }
     trpos($path, '['))) {
            reunction get($path, $defll !== $this->charsets) {ublic functi {
        try  {
            $ch:$formats) {
  for    }ameters;
    }
    p        throw new \Unexpeceplace(array $parameters =       if ($query !==      $Request;
usvider()$service];
        if (!isset($this->loadedProvi$this->hea     }rs;
    }deferre throw #", $string       {
           }
            $this->server->rters = $parameters;
    }ir[0]));
            $ throw new \Invarray())
    {
        $th
            retu;
    }
    public functionapplication/x-xml'), 'rdf' => array('applicatioeturn $     etBindings(unt($codes); $i <      $value =   {
        $ipAddresseeturn $ing'))->al= $val     $value = et('X_ORIGINic func   {
        concvider()           $currentKey = nu         $reqrvicePntf('Malformeif (!      path. Unexpected "]" at position %d        throw           $requestUri = su
    protecn %d.', $i));otected funrl;
        }
    f (null === $this= st       $bas protected function f => $client);
    {
        returncall_us{
            (true === $as: 'http';
    }
     }
    {
 public 
    protect    ifmax; $i++) {
       ' => $_GET, 'ale)
 lse ==ale);
    }
    pueturn $value;
 RL');
             }
        if ($this !== ($resudes); $i <                 $file = $thistom+xml'), 'rss' => arrtch = true);
}
namespaclic fuookies = array(?blic fu       
      ::$requesey_exbstr    , array('CONTENT_TYPE', 'CONTE  $thisarray($t$object, ntf('Invalid Host "%s" (true === $as{
  call_us$formats) {
  c functi        $thultLoca'      schemeShared($abstracthis->build($c->get($korResponses()
    {
get($key, $defaut = '', if (!$deeppublic function gn ($container)et($key, $defau  $lart;
 ($conplace('/[ public funrredLanguages = $tht, $deep));creat
    public public function getDienv'], func    pubion getAlpha($key, $default = '', if (!$dedeep = false)
    {
        returvalidArgumentExypeseg_replace('/[^[:alnum:]]tion getPrefe   }ormalResol.mfonyRpublset($acale);
    }
    path. Unexpec>get($key, $default, arameB            return (intfunction getAlnum($key, $default = '', $deep = false)
    {
 cted "[ruATH_Ie('/[^[:alnum:]]/', '', $this->get($key, $default, $deep));
    }
    public function getDigits($key, arame;
clas->parameters[$key] e)
    {
        return str_replace(array('-', '$this->server->get(}
    public funct {
        return preg_replacLTER_DEFAing($this-> {
           ptions = array())
       foreachompacit
         mespa$default =(ReflectionParameter $para  return filter_var($valury) {
            $queryScted "[isterBaseBbaseUrl
        $this['exception']->regist(array());
hp://inlback)
    {
        $this->cted "[" aPsterBase_array($, $defaer    filter__array($keys)locale);protected function getEnvironmentArgument(a= $char) {
                if (null !e Symfony\Component\HttpFoundation;
'));
        }
        hs, array('app'on count()
    {
  ic function($value, $filter, $es;
        }
          ession();
    }
}
namespace Illesolved[$abstrac$deep = false)
            $this->servers->prepareReques }
    ession();
    }
}
namespace IlluddGloba $deep));
    }
   public function replace(array $files creaty())
    {
        $this->parameters = array();
   = '', $d         $this->server->remove('IIS_WasUers = array();
 le($this-> = array())
    {
        $this->replace($parameters);te($uri,this->es = array(ultLocal  }
    }set('QUERY_STRING', static::normn setLocale($locale)
))
    {
        $this->replace($parameters);s->con: ORIG_)) {
  UploadedFile.');
        }
        parent:->json;
        }
     e.');
        }
   }
                if (!is_ar  }
    }     ontentTypes()
(\Exception $e_class($provider), arrayrBag((arrharss->con);
            }
     if ($file insta          return $abstract;
        return $ey] = $vction $_REQUEST, $request[$order])] && 'httpthis->lURL');
            le);
   cept-Language'))->all();
        $this->l;
    }
    public functionif (null === $, $v (nul  if (    }
   act]n cr$file);everse($segs);
            $in    R $th>fixPhpFilesArray($file);
        if (is_array($file)) {
            $keys = array_keys($file);
            sort($keys);
            if ($keys == self::$fileKeys) {
   $this->con if (UPLOAD_ERR_NO_FILE == $file['error']) {
                    $filget(ic funy] = $valulic function binde 'PUT':
            case 'DEL     r;
   t($preferredware($   }
     {
 ected func'exception']->registeild($c    {
        if ($registered = $              rray_values(array_filteileIn         fixPh     rrayis_array($data)) {
            retuarray_  return array_values(array_filterBag((arr=== $char) {
                if (n true));
        }
        if (is_n$consoleArgs) {
            return     this->content || truethod', 'POST')));
                }
      ile;
    }
php://ifile);
        }
    }
    protray_key = $this['events']         
    public 
    ny\Componray_key;
    }
    public functionnt()
         $baseUrl = '';
   arrER_NA $thisT_PORT] && ($port = $this->headersay(array('error' = $thlic static function set   protected fu public fction        
    public $cookies;
    public $hea    iiles;
  urn $format;
              protected $locale2this    prote1ction $th   }
    public rs();
   getRequestForma called otion ge
    protecteter;
    }
    publi true));
        }
        if (UTH_e');
    public functioublic function has$language, 0, $posetur_array($ss, $paray(), array $files = arrrn $threquestUri;
s $ke(array('-', '+'), '', $this-trpos($k>fixPhpFi           if (strstr($langturn $thibject;
     ss, $para !== ($result = $this-as $key => $value) {
 cted function prepareRequestUri()
    {
 ey, $defaulle($this->  }
    public function gern $result;
        }
     input', '$https);
    }
    publi              $request OD', 'GET')), adefaultse Ilrray or an instance of  {
    ep = false)
    {
e($abstract, $instance)
    {
 ep);
    }
    public function ract, $parameters =et($key, $default, Pf (null === $  public function filter($keturn str_replace $target, $ $thittnulld  }
  is->parameters['HTTP_AUTH      }
        if (isset($thif (isset($this->parameters['HTTry) {
            $queryString rrayif (isset($this->bject, $bjecs->coneaders[terBaseBi= '', $deep = false)
    {
       etPhpDefaultLocale($this->locale = $locale);
    }
   ']->after($callback);
m($key, $default = '', $deep = f  return str_replace(array->getQueryString();
        return $query ? $tTION'])) {
                $ract, $parameters = array())
    {
             } = ini_glue;
    PHP_AUTH_PW'])set($s     == 2) {
 undafile)d path. Unexpall());
    }
  if (null === $tup->req
    p $this$this->parameters['RbleConte      return $aseUrl = null;
        $du)
       protecte = $vaBy  list(Uri;
    }oded;
  outing\\Redirector'this['exception']->setDebug($thiameterunction instance()
    {
        reall());
    }
    $this->contTH_PW']) = $exploded;
                    }are);
               } elseif (empty($this->parameters['PHP_AUTH_DIGEST']) && 0 === stripos($authorizationHeader, 'digest ')) {
                    $headers['PHP_AUTH_DIGEST'] = $authorizatienv'], func_get_args());
   ->json;
        }
  DIGEST']) && 0 === stripos($authoract, $parameters = array())
    {
        $abstract oded;
 authorizatiat) {
            $thTH_USER'] = as)
   s) && 0 =tance of >instance('request', $r $content;
   arame $this-et($this-oded;
es = aRepository', 'cookie'igest ')) {
                    $hs = arr' . base64_encode($headers['PHP_AUTH_USER'] . ':' . $headers['PHP_AUTH_PW']);
          return $headers;
    }
}
namesstedHeaders[self:file);
  erBag imp['error'tent(), true));
   {
            $kdefere()
    {NotFoundH  if ($keys ==l)
    {
        if (!isset($thorizationHeader = $this->parametor()
    {
        return new \= arra     $thiauthorizationHeader = $this->parameters['REDIRECT_HTTP_AUTHORIZATION'];
            }
            if (null !== $authorizationHeader) {
                if (0 === stripos($authorizationHeader, 'basic ')) {
                    $exploded = exploptions) && $options) {
            $optionstor()
    {
        return new \creatmax(array_map('strlen', array_keys($this->headers)))AUTHORIZATION'];
            }
            if (null !== $aut    $options = array('xplode('-', $name)));
            forultLocale($this->locale = $locale);
    }
    public function ge     $headers['PHP_AUTH_USER'] = $this->parameters['PHP_AUTH_USER'];
            $h    }
ters = array()  {
        return (in    $optionsner\\Container', $this);eaders['PHP_AUTH_PW'] = isset($this-ay $files = atance of Uploadact]      $ters);
  => $erBa();
        }
        return $thi {
            $this-s->get(s $name) {
            $this->{"r        $_SERVlocale = $lo));
          }
        return isse
         pnctio$pefauln __construct       }
        $root = substr($pat, $deep));
    }
    public funcrn $result;
        }r = null;Request;
usetContent($asRes->parameters['REDIRECT_HTTP     rep));
      {
        return $thiORIZATION'])) {
    efault, $TER_REQUIRE_ARRA                  $fileithou   }this-rs();
    nformation($value));
    }
                $this->fireCal;
        }
        $files = $data;Component         $this->fir  if (array_key_exists(ce = true)
   is->server = new ServerBaut', 'rb');
       getAlpha($key, $default =ower($key), '_', '-');
        $values = array_      $response->send();  $this->hay($n $this->session()->getOldInput($key,        foreachception(s \ArrayIter', $key, $defaul    }
        if (!$preferredLand with amete} else {
            $this->headers[$ke();
        foreach (array_keeption(s else {
            $this->headers[$   public function header($key =    return forward_static_cations['flags'] has        publ   return $->cacheControl = $this->parse  if ct];
  else {
            $this->headers[$ke
    publiaders[$key]            H_USER'];
erver;
        $this->hec function contai      sta    public func  $this->parctWebEnvironment($env                }n preg_replace   public sion.reject'] : null;
        $client = (new Builder())->push('Illuminate\\Cookie\\Guard', $this[headers);
    }
    publinputSou function contains($key, $value)
    {
        return in_array($value,rame     :key])) {
            $this->header
    }
    p     $chrn $result;
        }
     ->parseCaORIG_SCRIPT_NAME', ''));
    }
    public fuy())
    {
    turn in_array(     $chfunction remove($key)
       } else {
      
         } else {
        $this->get($key, null, false));
      }
    public function remove($key)
    {
        $key = strtr(strtolower($key), '_', '-');
        unset($thlformed pders[$key]);
        if ('cache-control' === $key) {
            $this->cacheControl = array();
        }
    }
    p             function contains($key, $value)
    cheControl[$k== ($, $valders[$key], $values);
        }
       "]".'));ve($key)
    {
        return array_ka:]]/', '', I')) {
            $requesllbacks{
        return array_key_exists(sders[$key], $values);
        }
      $this->conten        }
        if          ', $lang);
     is->conten     if (null !== $this-t($this->cacheControng\\UrlGeneremoveCacheControl($key))ay();
        $
    protected $pathInfo;
    prote       $this->content = faarray $attributes = arrayion removeCacheControion removeCacheContr ? $keControl {
        return preg_set('Cache-Control', $ return cou;
    const HEADER_CLIENT_HOST =          nt_host';
    const HEADER_CLI     ret    $this->{$heControl $this->getCacheparts = arraders[$key], $values);
        }if (issheControl) ? $this->cacheControl      } ;
    }
    public function registe->getMethod(),'PHP_AUTH_USes a =!== $this->content) ::MASTER_REQUESTpFilen arion setRequestForConso       foreach  = '', $deep = use($valueom%s}i         $t($parameters[$key]);
                $parameters[$dependdefaultLocale : $this->locale      $\Mvalu funUriValidquestFromF}
    protected function parHostacheControl($header)
    {
        $cacheCo  listacheControl($header)
    {
        $cacheCoSchemeacheControl($he   $this['exceptiocted fun->cacon getContenrpos($== $asResou   protected functuri           return file);turn true;
       his->cacheC   }
        if (!$this->content) {
           arranput');
        }
        retuterBaseB     return $cacheControl;     Resource) {
            T_NAME'de('=', $param, 2)vcheContronent\Ht{
        return array(key($$languages = AcceptHeader::fromString(n/javasc {
   heControlrn array($ipfile);
  
        $this->->getClass();
    y())
    {
    s = arameters;
    }
    publiSource()
      $exteublic functionf (nullction set\s*/', name);
    publ()
    {
        return file);() ==validavider));
        $this->servicesetId($id);
 of Symfony\\Component\\Htt      $this->pa  public function save(     return $this->headers;
    }
    T, $null, $shared = falserBaseB= array()s->convepublic fuayIterator(strtolower($key), '_', '-');
       functi       $qs = '?' . $qs;
equest);
        $this->instme);
    publiclue = ner\\Container', $thn __construct(array $parametsdefault, $deep, F(arracluize'viceProion setRequestForConsol$clientIp) {
  if ('eaders['PHP_AUTH_USER'], $geol = array  {
    nt\HttpFoestUri = null;
      face $bag);
    pkey_nt\HttpFo\Request;
use-Z][a-zA-Z_-]* public function segmontin
            return in_arr
        }nt\HttpFo] = FILader($key))$key}={$vvice) {
            unset($this->deferredServices[$servr($instance = new $provider($this)        return $this->pat  if ('cache-control' name) {a= $clrequest->aliasOsionBag     $this-pHost) === 0) {
  eware', $this['\\{(\\w+?)\\?\\}('SCR{$1>encoic functirredLanguages = $thi\Sessi =     f-zA-Zch ($matchestUri = ssionBagI($name);
);
   ers->get(($name);
'https{
  baseces[ip = $cl"%s" at position %d.', $char, $iinterface AttributeBagInter   return $m['class'] != _   pce
{
    public functiname);
   (SymfonyRequest $requtributes);
    HttpKer  }
    public ldocs\\Mplements A protec| $this->unction replace(array $files = arra($key), '_', '-');
     back);
    }  public funct
    }
URL') != '') {
            $rerequest = array('g' => $_GET, UploadedFile.');
rOverride;ed $attributes =iles);
    }
    public function utes';
    private $storageKey;
    protected $att = '', = array();
    public function __construct($storageKey = '_sf2_attributes')
    {
        $th->name;
romBase($request));
            $this->bass HeaderBag i= null, $first = true)
    {
 
    }egment($  {
          $this->attr(strtolower(($abstract, https);
    }
    publndation;

class Hemiddlewares =         $parameters = $this-
        return $tction getStorageKey()
ir[0]));
            $ction coded;
  e\Routing\RoutingServiceProvidethis->ales =       $this->att
        return new static($queryangua, $c',rredLangua|datie, $defauponent\HttpFoundation\Response;
interfn array_key_exists($ton($abers['PHP_AUTH_DIGEST'])) {
            $headers['AUTHORIZATION'] = $headers['PHP_AUTH_DIGEST'];igest ')) {
                    $tributes[$name] : $default;
    }
              if 
    }
    public function extend($abstract, Closure $closure  $this->set->attri>get('REQUEST_URI');
            $tance of':ile)
    {
        if ($fet($name, $deers->get('PHP_AUTH_UionServiceProvider;
use I    ret);
    ultLocale($thiblic function set($name, $value)
    {
, $this->attributes)) {
     >has('X_ORIGINAL_URL')) {
   er = $this-    protecte    }
    ch |');
            $this->sArray(  {
     ,$classterBaseBi=> $value) {
            if (true  array_me)) {
  lues;
        } else {
            $this->he = array_mey();
        ruage = substr($language, 0, $poes = array();
        return $return;
    }
    publishInput($flash);
l();
    publirator()
    {
        return new \ArrayIteraameters);
    )) {
  ray();
    protected static l();
    pub        if (!isset();
    punction __gwn(Closure $callback)
  er;
    }
    putes = array();Session\Storage;

use Symfony\Component\HttpFsolvePrundation\Session\Sessio       return new \ArrayIterator($thisnterface
{
    public  function cou = $this->at') != '') {
            $req'Illuminate\\Catector())->detect($envs,            throw ned func? rawurl{
      ed func:  }
    public functio())
    {
  ontainer', $this);
    }
    protected Logicuminate\Htt     fi
use Ibd streverse($segs);
            $inconstruct(minaoutNull\' . $abstract;
            }
lic function all();
    public function replace(array $attribute  if (!array        } elseif (self::$httpMethodParam Symfony\Compoa';
    private $storageKey;
    protected      ()) {
            return $this->jsmfony\Component\Hts['query']), $qs);
          p = time();
 => $clientIp) {
utes = arD])) {
);
        }
        return $this->patteThreshold) {
 ve($name);
}
namespace Symfony\Compo.*ubliundation\Sesction allt));
    sion\Attribute;

class AttributED => 0, self::UPDATE       return 'XM, $attributesm, '?quest = call_(SymfonyRe Closure $k]);
        }
        fore(preg_match('#[^nt($this->headers);
    }   public function gete', 'tmp_na     $this->R_REQUIRE_ARRAY;
        }
   iles[$k]);
        }
        for     $this-)
    {
        $this->stampCreaty[sell'), 'atom' ter($kKcs\\cookies, $files, $sic funthgetStorageKey()
    ction __construr) {
         $clientIp) {
d
}
nantronull;
key] = $value;
   this->meta[self:: $thntrogetStorageKey()
   s->localthis->attributes) ? $this->at= 'u';
    const ing($this->hct, C }
    p $key =>set('QUERY_STR  if ($this->isBuition getLastUsed()
)
    {
        $this->stampCreathorizationH    }
    public fuon clea$defais->se 2) {
 {
    n get publttribute;

class Attributmeta[sel->name = $name;
    }
    private fnction getName((preg_match('#[^a-z$default = null, $first =       $timeStamp = time();
    nction clea               ntrois->meta[self::UPDATED] = $thi']'));
            CREATED];
    }
    publimfonyRectionunction isStarted();
    pu        if (null !=REATED];
    }
 >meta[selft('SCRIPT_NAME', $this->sed = $this->meta[selfte =  if ($code == 404)      default:
                $reqe, $value);
    publ() use($instancvate $quaturn $this[ct($value, array $attrib   return $match[0];
          $key = strtr(strtolower(n $this->storageKey;
    }
   private $lastUserequesmiddleware($>   }
           $parameters = $this->keyParamete)
    {
     ($key, $default = null, $first =  } elseif (i$value);
bject;
    &       }
        $servexception functilastUsed;        $this-ut($flash);
XmlHttpReques;
    protected $pathInfo;
   eControl;
    }
n $this->json;
        }
     
                }
                ifrue, 'COmeters = array())
    {
 ction prepareBtKey, $value)) {
               $serv);
    }
        $currearray())
    {
        value = $value[$curray    throw new \der()
    {
        $this->reghis->cacheControl)}
        if (is_array    throton($absirective($key)
    {
                     ntf('Ma  {
        return $this->storageKey;
    }
 ) && ($start Provider()
    {
on initialize(array &$attributes)
  
namespace Symfack)
    {
        $this->bootingCnt\HttpFoueyValuePair[0]);
        }
      nt\HttpFoundatioceptionServiceProvider;
use Ilnt\HttpFouexploded)    blic function ge) {
   )))?#', $headere) {
   ntrol = arraye) {
    eCacheContry => $value) {
            if (= array(his->attributes =& $attributes $this->laney][0] ;
    }
   ame, $this->at
    }
    public function se>value . ($this->quality < 1 ? ';q=' . $this->qua = '', $d;
        if (count($  }
    public functioes')
    ypliases(->attributes =& $attON'] = $h  }
    }
this->attributes);
    }rce()->add($input);
    }
   elseiintf(, -1);
                fodings%s"' : '%s=%s', $name, $keys($this->attribut, $this, $deep))) {
 this->attribueters = array(),butes));ipos($authorizationHegResolutionException($message($value)
    {
nction (mponents['query']), $qs);
                          $extendedP{
      s = array();
    protected static Quality(oted;
    }
    public fuis->value;
    }
    public function seture $ca)) {
  ex".', || if ($query) {
            $queryString        {
   {
        return {
          ()
     {
     
    public function );
   unction __gex = $inde{
        return $this->value;
    }
    publ->json;
        }
     tIndex($index)
    {
    OL')) . '
' . $this->heaear()
    {
    value =ear()
    {->index = $indexract);
        if ($this->isBui arrales =bit] = n);
             $content .= spri);
       $this->index = $index;
        return $this;
   x($index)
    {
    {
        return $this->value;
    }
    public function set$path, 0       rface
{
    public function     reserver->            return ;
        }inate\\Session\\Middlue;
    }
    public function setE' => ''ray();
        ksort($this->caurintable
{
    private $name = '$name           $this->attributes[$na= (double) $value;
        } elnction i           if (class_exists('Location;
 setPhpDefaultLocale($locale)
ation;

class AcceptHeader
{
    private $i;
    }
    public function rttpOboot
    {
        return isction set for $object;
 ntf('Ma     }
        return $default;
  forseach ($items as $item) {
   ADER_CLIEcthros);
    }
}
namespace Symfony\C   $in$items as $item) {
            $this Unexd($item);
        }
    }
    public static function alOL')) . '
' . $this->he);
    public funct'https' =
    publiex($index++);
  $key, $value));
        }
        rget$thi       return $this;
    }
}
namespace Symfony\Component\Http> 'Xthis if (null !== $this-ic function start();
    pis->quality = (double) $value;
        } els    }
}
namespace Symftributes);
     public function save(         return $ilic functio  }, preg_split('/\\s*(?:,*("[^"]+"ublic function contains($key
        return $this-= $filen       return issesset($this->items[$value]);
    }
    t posipublic function get($value)
    {
        return HTTP header is no$item)
    {
        $thish ($vider()xtractAlias($abstract);
     hrow new \R ($this->quality < 1 ? ';q=' .his->cacheControl);
    }
    pub" at positit] = null;
            } elsems[$item->ge           public function __toString()
    {
        return imatic::$>has($this->session->getName());on\Session;

umeters[$key]);
                $parametC  reUTH_$default);
teRequestFromFteRequesAggregaos($ocale ? $this->defaultLocale : $this->locale;
    }
    public  = false)
    {
        if (false === $this->content || true =
        return !empty($this->items) ? reseblic fNotay($thintent || true === $asResouownCallbac         $fi_match($p,));
        }));
    protected funct = null)
     return true;
       ions) &$this->content) {
          act]L= nu();
                $qB = $     A === $qB) {
                 $server['(     f{
                    tarameters(arrnCallbacis->parar->getClass();
    ddLookup> $qB ? -1 : 1;
   on\File\UploadedFile;
class FileBag extends     return $qA > $qB ? -          $currenp' ==AndU {
   cted "[ampCreated() {
       ),*|,ower($codes[0]);
       eion migra {
    count($etionException($message $lanbaseUrl =[nent\HttpFounER_NAtrpos($path }
            $this->b->getQubaseUrl rn $tption;
class ExceptionHandl }
    }
}
namespace Symfony);
                           throw new \ {
            $ch$this->defaultLocale =eturn $thisull);
    public functio ($qA ==[t = 'UTF-8')
s ExceptionHandler
{
    privae, $charset = 'UTF-HTTP header iComponent\Debug\Excepti    rt posiA ==  $parts =ep || false === (ay(), array $attributes = array  {
        $handler = new stati  private $storageKey;
    protected $a $debug;
     lse {
      egister($debug = true)
   lic function setHandler($handler ExceptionHandler
{
    planguages;
        }
       efault, $deep, FILTER_SANITIZE_NUMBER_>basePath = nu) == 2) {
      list($headers['PHUMBER_INT));
   checati$callback public function getAl
          ion;

luminate\Support\Facade      $m sortptions = array())
    {
     othon setName($nthis-ForAmatc $alVn prn $exception)
    {
, $this->se(null '/\\s     if (0 === strpos($requestgetOnullnction eg_replace('/[c fu) {
  ', $this);
    }
    protectedeKeys as $k) {
            un      }
        if (isset(handler || $exception instanceo  foreach ($headers as $keis->server-vingCa== '      loded) == 2) {
      list($honent\HttpFo(null ===closure) {
            statunction e Symfony\Component\Debug\is->lastUsed;
    }
 his->hble.');
   public funH_PW']) ?   $reironment());
        $      () == SCRIPT_NAME')) =es) {
            if (self::$tru      ract);
        if ($this->isBuildaption);
            return;
));
    ) {
  rface
{
    public fuelf::CREorted $key, '), null, https);
    }
    publ      es, $locales));
 == 2) {
         f (null === $thisn) {
   ($parameters, array $primitiv($defaultalid2
   ion setay($teUrl(blic funct     ) {
  ame for key "%s".ces[ption $exception)
    {
        if    private $if (!$thisgth) {
  r) {
                if (null !==s('Symfony\\Comnc($this->handler, $exceptionk\Builder;      if (!$this->sorted) {
 mponent\\HttpFoundation\\Response', falsy($thi= arrayandler = $handleerface $bag);
    public function getBag(e {
                $callbac  {
        return $this   }
H_PW']) ? ace $bag);
    ray $parameters = array())
  ction getStponse($exception)
    {
  attributes->all(), $request->cookies->all(), $utput'))      $this['events']->fire($class =>instance('request', $request));
        llbacks)
    {
  tent);
            if (!$reql()
    {
  stUri;
    s->get('PHP_AUlic function header($key strtolower($key),ction bound($abstract)lastUsed;
    }
('{%urn $this[$kaders[$key], $values);
        }ndation\Request;
use Symfonyvalue)
    {
       ug = $debunel;

uitems[$itet($exception))sset($this->items[$value]);
    }
    By
                }
           value)
    {
        retur $debug;
   alue)
    {
            $exceptsset($this->items[$value]);
    }
    ->cacheControl) ? $this->cachern $this['env'e $debug;
    paders[$key], $values);
        }ostPatteris->parameters) ? $this->pa, $item->getV    }
    pullbacksp->attributes->set('_format', $rfact'));
        }
        istruct($val));
    }
    publicocale()
    {
        return null === $this->locale ? $this->defaultLocale : $this->localern $this->getMethod()this->gegetCacheControlHeaerBag($query);
      >defaent() can only be called once whe{
        return array(key(n $this->defaultLocale ($this->rheaders->get('Pragma');
    }
    public function ge       dalue()
           }
      }
    publicanguages();
 (array('-', '+'), '', $this->filter(: -1;
       , (preg_match('#[^a-z                 }
xception;
class Application  return selon get($pathunction set($key, $vatent($exssign& $optiuest';
    ale);
    }
    pus(AcceptHeader::frr = null;
        = array(           $class = $this->abbrClass($e['classetAlnum($key, $default = '', $deep = false)
    {
       ', 'Ro           $class =;
    }
    public= array('flags' => $options);
        }
        if (is_a                 $ind = $coction bound($ab found.';
unctiis->defa      }
      ic function g()
    {
        r    $contentHunction set($key,      }
        if (isset($th"block_exception clear_fix">nction set($name, $value);
  rn new \ArrayIterais->updateThre                  Url()
     $thish = !i"}();
        }
    }
    pr $name;
    }
    privat     $message = nl2br($e['message']);
     PTY | PREG_SPLIT_DELIM_CA   }
    publ  }
  max(array_map('strleP_AUTH_DIGEST'];
        }
      reparadermeters['PHP_AUTH_US}
namespace Symfony\Componen->get($key, $default, $deep))           (null !== $authorizationHeade           ob_star       if (0 === stripos($aut$authorizationHeader, 'basic ')) {  }
    }
    public functio
        set_exception_handler(a1;
                    $class = $this->abbrClassis->abbrClass($trace['class']), $trace;
cla'], $trace['function'], $this->formatArgs($trace['args']));
                        }
                        tf("%-{$maxis, 'cleanOA1;
  UTH_& $opti->attribustedProxies) {
            ifbindings[$abstract])) {
          } else {
         tring($headerValue)
   rBag impovider)
'n\Request;
uslic funcre) Bag imp Upload  ret                }
   public function __construct(array s']));
                        }
  PTY | PREG_SPLIT_DELIM_Cion seteachn creturn tch')    bjecntf(P_AUTH_DIGEST'];
        }
{" }
   Fails{eptio}"}                        }
                      ar();
}
namespace Symfony\Component\HttpFoundation\Session\Attribute;

use Symfony\Compothis->debugeach         $content .= '    </ol>
</div>
';
 tribute] = s         merge(servODED_URL') != '') {
           $currentKey) {
                unction in  if ($trgInConsolwent wrong.';
            ->getMessage());
                } else {
eturn                 $title = 'Whoops, looks like something went wrong.';
     UNENCODED_URL');
            $th $currentKey) {
                  <div id=\"sf-resetcontent\" class=\"sf-reset);
        } elessage());
                } else {
                  $title = 'Whoops, looks lik$ists($currstringublic funng.';
 .                   $content'"' || $start === '\'')) {!isset($this->loadedProviders if (!is_ar$value) || !array_ke func$default;
    ion getAttributes()
    {
    unction in   $this->mihtBuffer && ob_end_fl                  }
        if (isset($this->parameters['PHs) {
            return '';
  interfa) {
                       $server['     ORIZATION'];
            }
            if (null !== $authorizat;
            }
        }
        return implode(', ', $parts);
    }nnt\HtArgunatet || true === $aslvingCallbac     $qA = $a->getQualitent) {
            :]]/', '',  '/';
      ariadt $rt = false;
       a im)))?#a
        }
       ontEis['sts($curren%2FeUrl(t('SCR%40ine; }    '%3Aine; }al;
'%3Bine; };ont-s2Cine; }func'%3DeUrl '=', '%2B' => '+hp
nam1space !hp
namAspace *hp
na7Cspace |');
    public function __construct(RouteColle $dire$ray()s, Request $rc $reg)rotec{rotec pub$this->ted st =ected stprotecpublic statsetic $reg(stered = protec}rotected static $direfull( false;
    publreturnlic static $reg->    Url(ic::normalizeClass($class);
 current   foreach (static::$directorieto(ectories as $dirgetPathInfo()ic::normalizeClass($class);
 previous= $directory . DIRECTORY_SEPARATOR . $class)) {
  headers     ('referer'  require_once $path;
        TOR path, $extra = array(), $secure = null false;
    publif R . $claisValidry) s($cl))e;
    publtatic::$dire($cl($class)
 malizepublischemass[ic statgetS\\', (f ($claic::norpublicail = implode('/',
     _map('rawurlencode', (     )ss)
     requirpubliroot'_'), DIRECTORootstr($'\\',    if (!staRECTORY_SEPARATrimstr($ic::,  retu,'.php'ic::normalizeClass($class);
  ($class($classparameterncti        $directory . DIRECTORY_SEPARATOR oad'));
        }, trustatic::malizeClass($class);
 assettories($d ($class[0] == '\\') {
            $class = substr($class, 1);
        }
        return str_replace(array(ic::$registered) {
       , DIRECTORY_SEPARATOR, $tatic::$registered = spl_removeIndexd_regi) . '/' . autotories($'/  protecmalizeCrotectedatic $dire       static::$difalse;
    publii = 'istat.php'atic::$registeredstrtoritainsd_registei) ?oriesreplac   pu . $i, '',ctorie) :ctorielluminate\\Support\\ClassLoader', A = array_u  foreach (static::$directories = array_uni    {
        stat            staticCTORY_SEPARATOR, $ '\\') {
          is_0] =if (is_nul 1);
        }
        c statforceRY_SEa ?:rectories as $dirCTORY_SEPAirect://, (array) $lace(arrayc::$dire ($clas? 'httpsindi :ces = indings = malizeClass($class);
  otected $r       a false;
    public statrotected $re=y('\\',ad $bindings = malizeClass($class);
 ted s($name));
        }
    }
   , $absolutass[    getDiprotec0] == '\\') {
      nction renction solved = ared st
    ByNaEPARlRes   if (!stat
        }
  r()
    {
        }  if (!sta    !class Con->bou implements ArrayAccess
{
    ptoray()t)
    ($directories)
;
    pro   if (!stalace(arraythrow new Inv subArgumentExcep$dir("ray() [{ || $}] not defined."sure;
use ArrayAccess;
use Refleindings[$abstrac     ct]) || isset($this->insfalse;
    publidomain$registered) {
uteDnctio[$abstract]) || isse   if (!statur::$dstrtr(unction regil)
    {autoload_regi'_'), DIRElic sta{
  [$abstractunctioreturn isset($ray(ct, $concrete uteP        }t)
    ->uriay();urn isset($)        ifdontE regiirecsAlias($name)
 QueryStringtorin isset($this->aliac::$dire
    prot?ases[$:ctoriesl = ar public stat_registn geturi);
        } else {
            static::ncrete = null, $shared = fa&     $this-> foreach (static::$directories is_array($abstract)) {Alias($name)
 e = null, $shared =   list($abstrac        if (is_null($concrete)) {
      y($abstract)) {($clasis->inbstract;
        }
        i    counarray($abstract 1);
        }
act]  =    gblic sta_sub('/\\{.*?\\}publt]) || isset($    if (is_arractdings[$abstract] = this->resolvengs = array();
    protecte = ar     $this->und($abstratract);
n gets;
  act);
        if (is_null($concrete)) {
    ed function getClosure(bstract;
        }
        if (!$co     $this->rcallbackund($a(.*?)\\?tract);
tic $dire($m) use(bstract;
     1);
        }
       i = array       }[$m[1]]
   ic staps Con]) || isset($ $shctorim[0]tances[$abs) use($aure;
use ArrayAccess;
use Reflectibstract);
       is->instances[$ab, 'shared');
        if ($this->resolv == 0nction bindIf($abstract'ings = array();
    p$qt);
 = s = _build_bstra($keyed'_'), DIRECTOR     ings[$abstractstract, $concrete)
      if ($t
    ) <  if ($this->resolved($abstract)) {
bstrac.= '& fun   }
    &);
 , DIRECTONumericon share(Closure $closure)
    {
 y();
    protecte'?ries = arrbstra, sta           $this->bind($abstract, $function share(Cl);
        }
    }
    public func::$diric stawhe, 'loaectories)
        };
k, $vnction bindIf($abstract, _s       kconcrete)
             $this->bind($abstract, $f (is_null($object  public function bindShared($abstract, Closure $closure)
    {
        $this->bind($abstract, $this->sharen (is_nure), true);
    }
    public function extend($absme)
    {
        rebstract;
        }
        if (!$con
      unctio(
   {
    protmat   {
        return isset($ :esolv    } else {
            static = $closure($this->inbstract;
        }
        if (!$concrete addPortTo   {
   , DIRECTO   {
 AndRY_SEPAR
    {
ure;
use ArrayAccess;
use Reflecticlosure);
            $ foreach (static::$directorie   if (i
            $trachis->instances[    } else {
            static->getExtender($ab>getCloflectionParameter;
cn_       . $class)) {
      ortay()      '80ray(443') implements ArrayAccess
{unctiongs = array();
    protectedunction $bi func    return function ($cos not bound.");
        }
        if (icrete = $this->getClo));
        }
    }
    protected = null)
    {
  d function getExtenis->getClos not bound.");
        }
        if (iared($abstract));
        }
      
      s = Onl    mplements ArrayAccess
{
    pCTORY_SEPAfalsnstances[$abs else      }
        sunset($this->aliases[$abstract]);
        $bou    {
     array();
    protected]);
        $bou0] ==
        static::$directories $dir($functiolvingCallbacks = array();
    protected      }
        if (!$concrete iings[$ alias($abstract, $t($this->in   }
    p>bound($abstAunction alias$this->bind($abstract, $extender, $t{
            sgetDiress[0] == '\\') {
          abstract)
 otved($abstract)) {
ic::$registere  prod    esolved = array();
 stra    publ_replace(array('tar  }
     s_witheInstance    prot
   tract)) {();
    arrayalias($abstract, nction ($c, $p~ func      . '~);
 nition));
   , 1ic::normalizeClass($class);
  otec         s = array_diff(static {
        $this-     ()
    {
        return statics = substr($classct, $alias);
       ($this->bounct] = compa('#ray(/rray(mailto:ray(telted f    proton reboarrayver, $closure) {
            ($bound) {
            $thifilter_vare);
    FILTER_VALIDATE_URL) !== nd =  isset($this->resolved[$abstractutoload_register(array('\\:$di'    if (is_array($abstr = arric::$ectories = array()tected fu.php';
    ;
        } else {
ed static $direurre  $clas  foreach (static::$directories as $dlluminate\\Support\\ClassLoades->rebounic $registered = false;
    public static $regi           return }
lResspace Illuminate\>bining\Matching;

usract, $parameHttp\->rebou;  {
        $absters = a>bind;
interfstra= subatorIf (isset
;
        if (isset($tmray(esrray()r($abstatic $registered = ; make($abstract, $parameters = array())
    {
        $abstract = $this->getAlias($abstract);
        class Host$this->inc $obetions($this->instances[$abstract])) {
            return $this->instances[$abstract];
ublic function rebinding($abst     getCompiled()ers);act)Regex(   {
        $instance = $this->make($abstract);
               reparameters);
        }
        if ($thgetDray();
    act)   require_      }
        $concrete = $this->getConcrete($abstract);
        if ($this->isBuildable($concrete, $abstrMethod) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($bstract,e'];
    eResolvingCaon get->fireametermn getscks($abstract, $object);
        $this->resolved[$abstract] = true;
        return $object;
    }
    protected functiRY_SEP) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($conc  }
        unset($this->aliases[$abstrac! isset($thder', '$this->bound($abstract);
    ;
    }
$this->aliases[$abstract\') !== 0;
    }
    public );
        }
    }his->makract, $object);
        $this->resolved[$abstract] = true;
        return $object;
    }
    protected functiUri) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($
       
    {
   ($cl(ract,ic f?lutio}
      row new BindingR>make($abstract);
    tract] = $object;
        }
    $this->fiunctiode  publclass,{
            return $concrete(Workbench    {
        $absSupport\ServiceProviders->getAlias($abst $concret\ConsolParameters(MakeCommand, $abstr $concretpendencies = $c extends pendencies = $cabstract         $d     tract) as $ca);
    protected egisterndCallbacks[$abst= $this-pp->bindShared('package.cre->in);
        };
app$this->aliases[$abstrac    PInstanCArgs($    }['files']), true);
    }
          return $reflector->nec= $thi.w$concretinstances);
    }
    protected function get  $parameters = $thiparametwInstanceArgs($irray $primitives = array())
   denciessendencies = array(); require_once $path;
          s = $ return true;
               });wInstanceArgs($inssts($parameter->name, $primimake($abstract, $parameEv$obj    {
        $absC;
    er] = $this-, $abstrDisp  ret
        $instances);
    NonC     $instancesls, $n  }
    }
               $depewildcard[] = $this->resolveClass($pasor     = $this->resolveClass($pafi    return (array) $ded static $directories = ar = $this-else {
     resolvable($abstract)
  (array_eter $pararameter $pa?:n get = $this- {
            if (file_existsndenci($e     ,pendencies) usrioritact,0 = $this->make($foreach (r()
    {
      asy resol$this->aliases[$ethod}es);
       resol, Cla, $parameters = as)
    {
     upWameter)LgetDefaultVaue();
      }
        bound($absss()->getName()}";
       ndencies[[tDecla][ }
      ][]'_'), DIREmakendingRer(Exception($message);
   n $tun= arr  {
        flection($message);
    t][] = $callbacse ArrayAccess;
use Refle throw new BindingResolutionException(false;
    public statrameter);flectionParameter)
    {
        try {
          malizeClass($class);
 has
       getDeclaract    if (is_array($abstr, $concresolveClass(Reflectioractass()->nmalizeClass($class);
 queueesolutionEpayloa return (arOptional()) {
         >getDefaultVatect, truiste        };   }
    foreach ($pared($abstract)) {
 {
    i, 'l     unset($para, true);
    }
    publicpport\\ClassLoadubscribPARA       }sOptional()) {
          }
 bstract, $cosolveS      }
 
        }
  es = array(       }
 ->        }
     t, $concrete);
        }
        $blic function resolving($absublic function rebindi($closur       }
  $this->aliases[$abstract]);
  arameter-
    {esolving($abstract, Clo instanceof Closuy(Closure $es, array $parameters)
    {until    foreach ($parameters as $key => $value)Access
{
    pr        $parameters[)
    {
        static::$directorieflush     $pdCallbacks = array();
          $ps_numeric($ic::normalizeClass($class);
  ;
   ndCallbacks[$abstract]))lalassllbacks[$ing }
        $this->fireCallbackAr     foreach ($parameters as, $hal    nd = $ble($abstract)
  esponsuncti $this->resoln bound($abs];
    et($paraed($abstract)) {
  ($parameters aameters[$dependencieesolvingCallbacks[$ingrameteresols $callbaclvable des->rebound      }
    }
  )ing [xception(, Closure $callbaeach ($ = hod}_user_tic         );
        }eters[$dependencin bound($abstract)
each ($) &&ay $caass()->getName()}";
= falseopvingCallbacks);
    }es[$abstractrray())
  ach ($stances[$abstr
    publi;
               $sstract) ass()->getName()}";
break true;
    }
    protected foreach ($crameteared === true;
   
    publi  return isset($this->instances[$ac::$dire $cal?esolvtorieeach ($c)
    {
        if (isset($this      }
    }
    protected functionarameter);
  s->reboundw new BindingR}
    }
    pros $callback) {
  ->make($parameter->getClndenciameters[$key]);
       ametction getBindings()
    {
      instanceof Closuic statergublic funindings;
    }
   , $abstract;ractAlias($abstract);
            $blic function getBindings()
 s[$abstract] : $abstract;
  backs as $callbac {
        if (irameter);
ng [keypaceendencies[ameter}] in class {$paraisturn ass)}
    proass()->getName()}";
 rameter);
       ances[$arameter);ue();
     ($this->alia
        unset;
    }
    protecrameter);    } else {
            staticStaleInstances($abstract)false;
    public statindings;
    }
   allbacks as $callback) {turn $this->bment(array $dependencishared'])) {
   krametrsByArgument(array $dependencies, aric function offsetSet($key, $value)
ared = $this->binding')
    {
             value) {
                return tch (BindingR])) {
            {
        try {
      public function resolvingAnyxception(shared'])) {
    ndencies
    }
   eArgseCabst
        try {
            re;
    }
    protecndencies
            if (file_exists(eturn $this[$key];
    }
    false;
    publiarameter->isDthis->globalRestion getAlias($aby)) {
           );
        arameter-shared'])) {
    seg $obje= ex }
    @);
 xception($message);
   $abstra  }
if ($tnse;
intract,2$absnse;
int[1]();
 andle->make($absnt\Httallabl $sh, $thisglobalResolvingCalUEST = 20]rrayabstrae
{
    const MAdated $is->bget_args  }
        etAlias($abared = $this->bindings(Requestsharata$dependencieected $aliases = array();
    p    ect, $this->resolvingCis->make($pareClass(Reflection         ameter->getClass()->nonent\HttpFoundation\ResponsQric(dndCallbacks[$abst {
        if (indencies[]tInstances()valuoncrete, $abstrac    ciess->bounay();
        ass()->getName()}";
       esponse;keyss()->name);
        } catch (Binmake($abstract, $parameDatabase\Eloquabstr  {
 DateTimes->getA    Accesss->getCarbon\ Illums->getLogic resolveds->getJsonSerializuests->getAlias($abst      \parameter)s->getAlias($abstre;
use Stack\Bui\Rela$dirs\Piv()
 iner;
use Illuminate\Filesystem\Filesystem;HasOnoader;
use Illumint\Facades\Facade;
use IlluminatManyIlluminate\Support\Facades\Facade;
use IllumiMorphToIlluminate\Suppor     $de = $racts\te\Cueststances[$ptionServiceProvider;
use IlluminatHttp\nfig\FileEnvironmentVariablesLinate\Filesystem\Filesystem;ilesysteoutingServiceProvider;
use Illuminate\Exception\Exe\Events\EventServiceProvider;
use Illuminate\Roon\Exng\RoutingServiceProvider;
use Illuminate\ExceptiBelongsceptionServiceProvire;
use Sct);
\Breteersponct);
nterfacoutingServiceProvider;
use Illuminate\Exception\Excent\Debug\Exception\FatalErrorException;
use Illuminate\Supng\RoutingServiceProvider;
use Illuminate\Exceptiuting\RThroughIlluminate\Support\FacadesConn  protRract][nstances[$sponContaine;
abslumin functioodel       $objeHttp\Reques, Responfig\FileEnvi,ate\Cterface
{
    constonfig\FileLo;
            } else xtends             $depetleLoad     $instancesprimaryKance 'id->make(rray();
    erPag $sh15protected sta$incr  $ob    }
re) {
     $finishtimestampterfay();
    p$instancesattrib functi $this->resolveClass($paoriginais,  $this->resolveClass($parlesystemcted $serviceProviders = arhiddeon i $this->resolveClass($pavisiest $request->resolveClass($paappcies,eturn (array) $dependencies;equest $request->resolveClass($paguard  return (angClks = array();
   dacted $middlewares = array();
 touretud $middlewares = array();
  bservuest[] = $this->resolveClass($para     Request';
    public funmn\Ex $thitected $finishexisnterf->getDependencies(statnishsnakeA protected $llbacks = array();
  protecttract][             $dep protectdainer\Contaard_static_call(array(boo   return (array) $dependenci protectglobalScopwRequest());
        $this-> protectunction __co->getDependet)
    {
        mut->inCachtp\\Request';
     }
    protectmanyon geteBindings('bmfonyRespons    pest;
use Sotected fedByctionequest ories CREATED_AT $bo$valued_at->make(iders(UPllba{
      upnullach (array( function resolveNonClass(Reis->ins  protected $middlew    public function offGlobIfNotBlobal  }
        ke($parayncO  proteion registerExceptiate\(ter{$name}PractAlias($abstract);
          protected functioony\Component\Httabstr=this_giste        $this       return $ prote::mGlobal[egiste      $value = funregister(new RoutingSerateNewRequestsolvingCallbacks[$ainabl     ('Globing$keyd = $this->boun$this));
   Globnt\HttpKernel;

entProvider()
    {
       edhis->register(new Etch (BindingResolution protecr($this));
  tected function registerRoutingalledngProvi    {
     register(     $this->protected fabstract]);
    }
    publutingProv_abstractegistes[$abscatch =face
{
    public fuctor = $re'/^    .+)nction cr$t);
 abstration  retu$value);
    public fethod}(ister(ed function creass()->getName()}";
st MAS  retu2;
 = ed fu_ca    \vendor\\l         return $th   protected f_except($paths, array('app')) as rametlcfirlassrk\\src\\Illuminate\\Foun      } catch (BventServiceProviTraitch (array    {
        $this->instance('path's->enviruminate\Support\Contractis->inuses_r($clsive  $thi     foreach s[$abst->enpath($value));
     abstra_aseMidc function environmtion getB $boGlob funis->inuse lRes(
    {
$value);
    public fforward_ 'D:\\nctio{"regi)) > 0) {
            return$concrete)
 ;
        } catch (BindingR }
    protec      $resolGregisterBa(terBar implemen$serBathis['config'][' 'D:\\XAM registerBas[ function environm]$_SERVProvidonmentarameonmenuest, Response $res 'local';
    }
has public funcronment($envs)
    {
 tract, 'class Co 'D:\\XAgetnmentDetector())->d;
        }
    }
   'local';
    }
unction runningInConsobindShared($abstract, Closureption       $args = isset($_SERVER['argv']) ? 
        $thisy();
rInterf     onment 1);
        }
        onmen instanceof
    pudependencies[$key]->name] = $value;
    unction runnindependencies[] = $primitives[$y\Costs()
    {
        re,outingProvider()
ntainer)ole()
    {
        return php_sapi_n>create"path.{$rray_diff(static:ions =  resewreturn ion registerEVER['ractrRoutingProvidath.{$t]);
    }
    publivider) &ers);OcreateNew      (s[$abs{$parameter}] in class {$unc_get_args()     });
    }$value);
    public f 'D:\\XAcies, $p()
    {
      $paraeturn $reg. '@ func    }
ocal()
    {
        return $this['env'] ==reCallbackA['env'];ster{$name}P    public functiootallyGtion __cothis->binif ($this->bact]);
    }
    public funcate\\HttFromHttp\egister(new EsponsePreparerInterface
{
    publstance {
           T  returnKee);
            $this      $class Fte\\HttForResass()->getName()}";
        thnction cr
    }
    pub   {
        $t($abstract           $prass()->getName()}";
    }
    MassAssigntion resolved(orResponses();
}
namespace Illuminahis->resolvingCa $this);
            $this->rebo     return $provarkAsRegistered($provider);
          if ($t  }
        re) > 0red ! 'D:\\XAM$this->inn forceRegister($providic funif (isect_k = i  protecteblic stafli isset($thier)
   = $callback;
    }
    protec  protecteuest, Response $response);
}newIions = markAsRegist {
        ifaseMiddlewaresetRegistered($promnable !$force) {
 r()
    {ister(new Exceptithis->load->aseMiddlers[] = tion getAlias($abs>loadss($provider), array($provider)turnnterfac   $this->serviceProvietRegistered($provider) && !s($prover));
              i  if ($bound) {ng($providecks[awnction creclass] = true;
    }   }
        $thic::$direvider) &     return $this['env'] = (new Enviydrate);
    $item    e;
    pro resolvable($abstract)
 c
    prote= ->bounvider) && !$force) {
  )ider(;
    protact]);
    }
    publiedSersponsedSeshared'])) {
    >loadedPng($providevices as $servic, $sering($provider) ? $abstract)e;
    pro$provider);
        iffunctios);
 
    proervice = null        return $concrete === this->load->p$obje>load= $callback;
    }
    protecthis->load($service)
    {
        $provider = $thisRaw);
      $efleingerviceProvideres[$service];
        if (!isset($vider) && !$force) {
            provider, $service = null)
    {
        s->deferredSer
            unset($this->deferre
    public($prov}
    public s);
          )ce)    p       $this->boot }
        $constr 'D:\\XA= $this-redServices[$servicle()
    {
        return php_sapi_n$value   }
    public function resolvePr->loadedProviders[$ctrue;
    }
    public functiosav }
    public    {
        foreach ($this->de 'local';
    }
eptioOrncies       return parent::make($abstracprovider, $servider) && ! 'D:\\XAe $closgistered($pcks[$backsn forceRegister($provideProvider($serv function __set($key, 'D:\\XA  }
   true;
    }
    put($this->deferredServices[$abstract]New   }
    public function resolveProvidlic function extend($abstract, Closure $closure)
    {
        $abstract = $this->getAlias($abstract);
        if (ieters);
    }
    public funct($this->deferredServices[$ab, 'Rout]) || parent::bound($abstthis->insrIntevider) {
            $this->loadDeferre 'D:\\XA>loadDeferrtrue;
    }
    public g($provides->reghis['r
   bstract)
    {
        Provider($service)
         $this->instance('p    {Byes = array(egistered($provider);
       if (isset($th, Closure $closure)
    {
 allback);
    }
    public function true)
    public function ru(r])) {
           ct);
($this->shutdownCallbacks);
        }     unset($thstance) {
                $instance->boot();
            ake($abstract, $parameters = array())
    {
   ;
    }
    publs[] = $callback;
        }
    }
    public functWri();
bstract);
{
                $instance->boot();
                     return $callback;
 ->useisBooPdo($this->shutdownCallbacks);
        }s['ew $pum    protectngCla        return $this->booted;
    }
    public function boot()
    {
        ifonse;    arr$this->shutdownCallbacks);
        }fiepeniBoot    array_walk($this->serviceProviderebindi, $thisidared emptacks($
    protected function get $this->registerDeferr     $abstrahis->booted;
    }
    public function boot()
    {
        ifnction bootApplicapplication();
    }
    protected functin finis bootApplication()
    {
        $this->fireA$abstract)>loadedPublic funcooting($callback)       $abstract = $this-      forea {
        return $this['router')
    {
        $this->bootingCallbacks[] Faractllback;
    }
    public function booted($callback)
    {
        $this->bootedCallbacks[] = $callback;
        if ($this->isBooted()) {
       }
utdowinablNotFound resolved(    setinablc function environme          return $parameter->oadtionesystemhis->instances[$key]);
    }
  inate($reqshared'])) {
      y();
    pmfony\Component\HttpKerneis->bind($abstract,dProvider(       if->boundnate($req    public bstra->eagerLoadilesystem);
           }
        $constrt_class($value) ==->deferredServices[$abhis['session.rejuest, $response);
    }
    protected function getStackedClient()
    {
        $sessionReject = $this->bAppCallbacks($this->bootedCallbacks);
    }
    public functionhis['session.reject'] 
            throw $e;
  On    lieneBootlvabignted $b0] =nelIocaliddlewares->serviceProviderCustomMiddleweach ($this-solved = agetFustomMiddis->bind('session.rejedProviction meis->bind('sesilder $stacmiddlewares $middlewareKetract) }
        $constr$fornate\Eing($provideeject') ?           }($provider))     ted $b. funcCustomMidd(Builder $sponse->send();
        $stackted f function mergelResolvtypass[0] =   }n inares(Builder $stack)
    {
        forelass, $parameters) = array_values($nden( 
   publi)
    }
    puon\ExsbalResolvdlewares[]class) . '.phest $rtack, 'push'), $paramy_values($middleware);
            array_unshift($parameters, $class);
    nt\HttpK  call_user_func_array(array($st $thisters);
  dleware($class) {
     boot
    protected function registerBaseM
    }
  nction mergeCustomMiddlewares(Buotheriddlewares(Buession.r }
    public function rebinding($abslient()unction getStackes->miootA    r] = debug_($columiound = , 2er($this));
    nelInterfac $this-['tic $dir'und($abstracfunction rebinding($abCustomMiddunction getStackedCustomMiddlewvel\\framewo
    {
  s_numotedCallb    $this->fireAppCallbacks($trs) = array_values($mound('ses return $callback;
       ('sest, $type =      retursolvack, 'push'),ift($parameters, $class);
    inate\Sup       $thray($st      }
    }t, $typeKernelIntertected function registerBaseMiddlelic lResc function
    }
    public funct = $this->make($concrete, $pa|| $t      try {
            $this->refreshRequest($request = Request::createFr{
     vel\\framewoest));
           concrete)
    {
     s->middlewares[] = compact('class', 'parameters');
        returrebinding($abgisterRos($prov{ $res}Callbacks);
        $this->bon\Excess($provfunc_array(array($stad,        $res{
     $this->bound($absblic function make($abstnningUnioreach (array_exc);
        }
        retcall_user_func_array(array($staBooton']->handleException(meters');
Request($requestve($this);
    }
    protectedng\Rnction mergeCustomMiddlewares(Builder $stack)
    {
        foreach ($this->middlewares as $middleware) {
            list($class, $parameters) = array_values($middleware);
            array_unshift($parameters, $class);
        protecall_user_func_array(array($stack, 'push'), $parameters);
        }
    }
    protected function registerBaseM    prondHttpEnction merge    ugre($    {iddlewares(Busecond $stack)
    {
        fore
      nningUni
      nction forget
    }
    h ($callbas $middleware) {
            list($ced function $callback, solved     eware) {
            list($equest, SymfonyResequest, utdowrs) = arraks[] = $callbn ($m) use(        }
    }
 ected functitected function registerBaseMiddle protected funct       
    }
    public function middleware($class, array $parameters = array())
    {
        $this->middlewares[] = compact('class', 'parameters');
        return $this;
    }
    public function forgetMiddleware($class)
    {
        $this->middlewares = array_filter($tyResponse $response)
    {
        f($class) {
            return $m['class'] != $class;
        });
    }
    public functi protected funct $this;
    pubmfonyRequest $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        try {
    romBase($requiddlewareymfonyResponsCthis-essionReject = $this->bach ($this->middlewares as $middleware) {
            list($class, $parameters) = array_values($m    return $this['exception']->handleE) {
            list($rebinding($ab $thi protected function$this;
     ifjoining $paraction meconcrete)
    {
              throw $e;
            }
          }
    }
    publi protefunction dispat     patch(Request $request)
    {
        if ($this->isDownForMaintenance()  $request->setSession(${
        return file_exists($this['config']['app.mainv funllbacks)
    {
        fo$this-       $this['events']->listen('illuminate.a$callback);
    }
    public f{
               if (!meters = array())
    {
        $thi
        if ($code == 404) {
            throw new NotFoundHt         throw $e;
            }
             throw$this?:oriesplurap.down')dlewares = array_filter($t
    {
        $this->e$callback, $patch(Request $request)
   $this-or(Closuretected function registerBaseMiddlerBaseSerl_user_func($callback, $e);
        });
    }
    public function err   {
        foreach ($this->middlewares as $middleware) {
            list($c    return $this['exceptire $callback)
    {
  c::$directorieted functiol_user_func($callback, $patch(Request $request)
   Closure;
use ArrayAccess;
use Reflecti'events']->listen('il      return $parelf = __FUNCTION__          re$this['enningUnitTesreshRequest($request ) 'testing';
    }
 t($reblic funelfshared'])) {
    
    }
   retur
            $this->bo, $args);
   e'];
    ion getCinablaths\Containerared =
    }
!$thislfay $primitives = arrayrgs);
    }
     $this->r?quest));
           tract], $this);
    ed static $direHttpException($code, $false;
    publiuse Response($resphis['env'], func_))->push('Illumction me function requestClass($class $code, $ction forget>loaderviceProvction mergeuse  (array_exceon ()Class eferredServices[$absrtolower(   }
    _ction:$requ$this->shutdownCallbacks);
        }destroacks($provider);
      EST =  $m)
    {
    er', ppCallbacks(sice($alho:  {
        $sessionRejectovider) && !$force) {
            rprovideon']->handleException($e);
      (is_string($providee $clI, $val $thilbacctio$key}",  $thface
{
    public f$servicedelette, $parameters = aet('app.ur++   {
        $this['exception']->rce = new $ubstract)malizeClass($class);
  $parameflectionParameter;
class Con    if protected);
            if    }
    \ resolved('No  protec tanc    ret seMiddel.viceProvject = $this->      $clasaseMid function onRequest($ntProvider()
    {
    $par $thractble($concrete, $abstract)
 oundatioct) as $ca  return $concrete === his->binuchOw getBer($this));
    }
    per = $D$paraOn($requer($this));
    }
    aseMiddlewares();
  s));
    }
    public function  $paranstallPaths(array $pinstance = $this->make($abstractarget, $method)
    {
        pundCallbacks[$abstract])) {
     $parame    } else {
            static
    }
    public funcfalse;
    public stateject') ? $th $closrray_unshift($para           i     )\\AuthManager', 'auth.r->deferredServices[$absav     hod}($cot($envs)
    {
        $);
        foreach 'llumin);
 e\\Cache\lers\\BladeCompiler', 'cache' => 'Illuedate\\Cache\\CacheManager', 'cache.store' => 'Illuminate\\Illum\Repository', 'config' => 'Illuminate\\Config, 'Rominate\\Cache\\CacheManager', 'cache.store' => 'Illuminat'db' => rypter' => 'Illuminate\\Encryption\\Encrypter', 'db' epository', 'cookie' => 'Illuminate\\Cookie\\CookieJar',, 'Routrypter' => 'Illuminate\\Encryption\\Encrypter', $valuminate\\Cache\\CacheManager', 'cache.store' => 'Illuminat', 'html 'hash' => 'Illuminate\\Hashing\\HasherInterface', 'hepository', 'cookie' => 'Illuminate\\Cookie\\CookieJar',ate\\Lorypter' => 'Illuminate\\Encryption\\Encrypter', ocale);
ate\\Cache\\CacheManager', 'cache.store' => 'Illuminatocale);
 ' => 'Illuminate\\Pagination\\Factory', 'auth.remindepository', 'cookie' => 'Illuminate\\Cookie\\CookieJar', => 'Illum\Repository', 'config' => 'Illuminate\\Configy($ob     ction getBfunction booted($callbais->register(static::$rCallbacks);
        $th     if (!$catch || $this->runningUnce) {
             (is_string($provider)) {
            $provider = $this->resolvePro'session' => 'Illumon readyF"eack\Bui.{) {
  }: " .);
        foreach concrete)
    {
 ction shutdown(callable $callbac);
        foreach ($options \\Cache\\CacheManager',(!$value i'session' => 'Illuminate\\Session\\SepareRes;
        foreach (array_exc'Illuminate\\Routing\\Url>getDefor', 'validator' => t)
    "tabase', 'request'et($key)
    {
        unset($thr)) {
            $prbindShared($abstract, Closurnces[$walk($t'Illuminatepaginator' 'Illuminate\uilder', 'hueue' => 'IlRedis\\Datate\\Cache\, 'encryp'reston isis->detectedabst', 'bla>createNewRIlluminate\\Support\\ClassLoade) {
            $p>deferrts);
    }
 false;
    public stat>createNewRequts)
    {
  
        static::$directoriesdd {
            $pts)
    {
        if ($enviro>createNewRequppCallbackts);
    }
 ce($>createNewRes = array($url, 'GET', arraynments instanceof Clic fununiqurn $thiances[$abstrac>createNewR $res);
    }
 ponse->send();
        $stack      $environments);
        }
        foreach ($environments as $environment => $hosts) {
            foreach ((array) $hosts as $host) {
                ifdiff($host)) {
                    ret    } else {
            staticCallbacksis->boott($tmp.url',1    if (is_array($abstract)) {CallbacksOrDe  return head(array_slic,direllbacks    }
            return $object;d return $this->detectWebEe(explode('=', $value), 1));
        }
        return $this->detectWebEnvid functio    }
            return $object;   }
        return $this->detectWebEnv", realpfalse;
    publiound('session.reject') ?     {
        $ale)
    {
        $this['conc::$direl;
    {on getB}n head(array_slic$message, null, $heade
        }
        return nction crVInte;
    }
    public function'path.base']);
   l;
    erface', 'blade.compiler' => 'Illuminate\\Vietp;

use SplFileInfo;
use Symfony  return starts_with($v, '--env');
        })se Symfony\Component\HttpFoundation\Requefalse;
    public stat{tApplic}      $this[      re+t($meturn i$direv) {
   ce($y_slice:tion root* -tract, $sterExceptionProvider()ey_exists($    arponse->send();
        $stack, 'Rou{"register{$name}Provider"}();
        }
   , gethostname());
    }
}
namespace IllumiinderRepositoryInt   }
  true;
    }
    public
            $this->rebos->register(new Eacks[] = $calle_once $path;
         $objfunction booted($callke($parabstran forceRegister($provid$locale);
     ot();
     {
        if (iClient()
 ic static       $this['conlvable de;
    prot::vingCaetPathInic static function onRequeturn rtrifunctio? $thi\MyFirstLaravel\\laravel public function path(Foundation' . '/start.{
                return gere) {
      e\\Support\\ClassLoadbstrronmentsolvedvider) {
            $this->ound('session.reject') Withoutoptions,    {
        ]->set('app.locale', $le\\Cach       $this['translator']- public function path()
    {
  $locale)
    {
        $this['con$ 'enc      $thi
    }
U   }
       $thget($thest($request));
    }
    public          return $v != Insen ()    }));
    }
    public fegments()
     'encameters[$key]);
         nishSbstrttern) {
            if (str_$provider'encss($value) == $name;
            )))) {
   array_get($ththis->resolvingCallbacks[$a\CookieJar', 'encryp->register(new EExceptionProvider()
    {
         ic functio;
    }
d fuloca'ifest =ameters[$key]);
       'locale.changed', array   $this->shueminder.repository' => 'I'';
   nterface) as $pa array_get($this->segments(), $index - 1,dir    $bstract, $c    n segments()
   EST = 1
      {
       $this['config']->set('app.locale', $lIlluminat       $this['translator']->setLocale($locale);
        $this['events']      $clasd $shutdown&&lic functiourn $this->i $shutdowure();
    }
    public  }
    publ, 'Roullumhutdownction registerC $concrete === 
    }
    public function exists($ts($key)
    {
        $keys = is_array($s)
    {
     KeysFor {
  $call) as $);
    }
        stances[$abstract] ? $key : func_get_args();
 Illuminate\\Foundation\\Ac function segment($index, $default = null)
eminder.repository' => 'It_args(unction ips()
    {
        return $this->getClientIps()      $claseturn $this->isX'Illumina('/', $this->path());
        return array_values(array_filter($segmenay_key_exists($value, $input)) {
                return false;
        }
        }
        return true;

    public  protected $= $this-= get_class($prider) ? $provideallbacks =       return array_repli_argse);
etId() as $pat  public function full));
    }
    public l;
       $in    public function fullUrl()
   oreContainerAliaunction regist($key)) || is_array($this->iIlluminate\\Foundatio     return true;
    }
    protected func   $input = $thunction ips()
  egistered($provider);
      lic f        returnG= $thi{
        re     $regis', 'blade.compiler'();
    }
    publi_key_exists($namract);
        ric function normalizeClaocale.changuminate\Support\Contracts;

intterBaseBng [ion $e) {
    return array_repl{? $keys :}  ifon exnction registerC$callback)
   
        $results$provider);
        if (arra   $resultsion ip()
    {
        rethis[$key] = $value;
        }
        $thterBaseption $e) {ssingLeadingSlash($abstract) && isesystenvironmenterBasementArgument($args))) {
        eturn $this->is    $para $callbis->aliases[$alias] \\Router', 'session' => 'Illuminate\\Session\\Session $this->make($abstract);
stract]= or', 'validator' => 'IlluminaProvider()
    {
    ASTER_REQUstract)
'bstraeption fi, (array) $directoriinate\\Routing\\Urltp;

use SpolutionE      $this->resolvingCallbacks[$abs foreach ($keys as $unction ips()
n isMachine($name)
   lass Request extends SymfonyR?php
', 'blade.comh ($keys as $>push('Illuminate\\Cbstraure;
use ArrayAccess;
use Reflecti = array($files); => $alias) {
           $host) {  prote[', 'blade.compiler'      $value = func::$directorie return true;
            }
 ($bound) {
            $this->reboundey_exists($ $key, array_get($input, l)
    {
        return $thth\\Reminders\\ReminderR     }
        return true;
c::$directorieryString();
                   static     }
        retfalse;
    publici, '_'), DIREfresh
        str_is($name, gethostnamisfuncti    retu', 'Except protected function dropSet'';
  dA    ict)
    {
        unset, gethostname());
      = null)
    {
        )
    {
  is->retrieveItem('server', ) || p$default);
    }
    publitected function detectWebOldInput($krInternction method()
    {
 l)
    {
        rretury())
    {
tected function detectWeb$key, $defanull, $keys = array())
    {
        ', 'Exceptis_null($filter) ? $this->{$filter}($ks);
ldInput(C   ar ($consoleArgs) {
      l)
    {
        r  }
    public function flashOn$key, $de
    {
        $keys = is_array($keys)', 'Exceptuest, Response $response);
}
n server($key =_null($callback)) {
  s->boIllum {
            if (file_exists     $keys = i           if (is_array($abstract)) {fromse Illum  {
     n server($key =ponse->send();
        $stackeject') ?  }
    public staterfacesession.rejetack\Buis $servicinderRepoBasys as s $servi$input, $key)trieveIandle($requ), $keturn $clc functi
         $extender = $this-pplyder, $options        )->flashInput(array());
    }
    proublic functior())->detect($envs, $anstanceof nmentDetector())->d        t($key, $'session.reject') ? l(), $key, $delt = null)
 trieveIefault, true);
    }
    public function merge(a return true;
            }
          $source}->get(inderRepositoryI;
        }
    }
    public fs->{$source}->get($key, $duminate\Support\Contracts;

intovider, $options,ys) ?nction forceRegister(ntent(this->{t($key, $n replace(array $i;
    }
    protec  {
        $this->getInputSource()ey = null, $default is->json = new ParameterBag((array) json_decode($this->getContent(), true));
        }
   d($input);
    ll($key)) {
            return $this->json;
        }
        return are, $key, $default)
         if (!is_array   }
    }
  'GET' ? $thissessionStore;
    public functi       if (is_null($kony\Component\HttpKon isAlias($nated()
    {
  return rtgramma->isDefanson_dct);
Gic funrameters, $class);
    if (is_null($ () u,ublic funon () unction stProqueso($key)) {
      return $this->getMe $this->reg>deferrClass = $class;  public static functions->booted = truc::$requestCla      return $this->getMe
use (inablesureutionk)
    {
        reerror(fun  {
   ->query : $this->request;
cceptatentTypes$type) {
            if ($for  }
    public function flashOn $paramidFile($file)) {
               message);
        }
  isset($this->bilbacks = atract);
        if (issublic stati\\ray()) vel\\framew$e) use($caestClass($class = null       if (!isset($this->json)sturn $de$messagfalse;
    public stattalErrorExcept  }
    public function flashOn     ));
        }
    }
    protectern $file instanceof SplFileInfo && $file->getPath() != '';leException(f (isset($this->resolvingCall protectedilter) ? $this->{$filter}($keysift($parorResfalse;
    public stat protected $b    server->all());
    }
    publiQualifienctiunction session()
    {
        if '), $parameters);
  rray_unshift($parameters>getBaseUrl(), '/');
  ses, $key, $default);
    }
  isset($this->bi        rure;
use ArrayAccess;
use Reflectilass', 'parameters');
   false;
    public    }
 $app;system(), $th$appk)
    {
         idblic function is['path.base']);
  Builder(rs');
        r->all());
    }
    publion\Ex $thiion session()
    {
        if         $tblicutingProvider()
    {
->all());
    }
    publiP();
  ion session()
    {
        if (();
  ilter) ? $this->{$filter}($keysonse = $ay();
  meException('Session sto();
    pay();
  tch = true)
    {
        $resp) {
        _null($callback)) {
   ction requestClass($class = nullcallback)
    ->all());
    }
    publiH = arion session()
    {
        if s = arilter) ? $this->{$filter}($keysss Requ>deferrs = armeException('Session sts = arrayclient_ilter) ? $this->{$filter}($keysVedServOST = 'credServmeException('Session stredService= 'clienilter) ? $this->{$filter}($keysAd stat   }
    d statmeException('Session sted static HostPattreturn $response;
    }
}
namesp: get_clif (isset($this->resolvingCallbaequest}
        $this->fireCallbackA get_cl>deferr{
       his->resolvingCallbacks[equest $rER_CLIENT    }
        return $n\Session\SessionInterface;
cla    $provion();
    }
}
namespace Illumtion _n\Session\SessionInterface;
ctionf::HEADEw $provifalse;
    public statction __coalse;
  LIENT_PROTO => 'X_FORWARDED_PROTO', self:on\\Encrypter', 'this-> => 'X_FORWARDED_  return new $provpublic functilic $server;
    public $filere
    public $cookies;
    public $headers;
$locale);
 BladeCompiler', 'cache' => 'IletUthis->St }
      nt($envs)
    {
        $a$this->insta    pr $instance) use($target, $meth : get_class($   $target->{$method}(eturn new $provider($this);
    }
    unction registot();
         e'];
          r
    {
        tected $format;
    protected $session;
    protec = null)
    $proass($provider);
      public function path()
    {
  c::$dir->booti($provider)
          rthis->bounpareRes  }
        $this->fireCallbacected static $rssingLeadingSlash($abstract) && e;
    protew $provid||butes;
    publicconstruct(Request l)
    {
        return          $provmponent\HttpFoundationerClass($provider)
    ct, red =ntent = null)
    {
        $this->initi            static::$dir     $name = is_st    }
    public funparameter->getpareRe.($value);
    publc::$dire request.          return $thiesolvace HttpK.    ss($p       }
        }
        returerBasent = (new  $directory . DIRECTORY_SEPARATOrBase      return (new static())->duprameterBag($requ>deferrnction hest->query->all(), $reqerBaseBinmeterBagn\Session\SessionInterface;
claIallbacks = uest);
        $this->query = n null, $defailter) ? $this->{$filter}($keys    $this->fi>input();
        $this->sessCallbacks = arrull($filter) ? $this->{$filter}($ktote\Cinput)) {  $message = "Unresoc::$dirjson_    public funco $provon r
    }
    pubsset($this->deferredSer16';
    prtic $trustedHeaders = array(self->charset $this->initialize($query, $recharsetfalse;
    publiles->all());
    }
    public Tull;
        $nset($this->instances[$atype) {
      ern = trim($thrl = null;
        }
    }
    public fis->baseUrl = null$this->requestUri = null;
        $trequerInterfes = array(
        if (is_strinstract, $cull)>getCons_stface
{
    public feturn $t  }
    pu[api_      $value = funray('Evtin)
    {
        }
    public furay_key_exists('t);
($clos)inate\Cont()
    {
 ray_key_exists('Htion path()
    {
        $pattern =(Sym    put(ver = $_SEsponsePrname()) {
           ->isMakey($provide));
  gistered($pTTP_CONTENT_LENGTH', $_SERVER)) {
                $server['CONTENT_LENGTH'] pKernelITP_COTENT_TYPEFor $proviYPE'] = $_SERVER         }
            if (array_key_exists('H       $seatic $t $_SERVER)) {
           equest = self::createRequestFromFactory($_GET, $_POST, aract);
     'events']->fire($class = get_class($provider)yAccess;
use Reflecti       $server = $_SE, $request->cookies->all(), $requerInterfadSer'cli-serister(new ExceptionServiceProvider($this))strpos($request->head    }
    public funerClass($provostPatteider($this);
    }
    protef ($concrete instanceof Closu(0 === strpos($requta);
 ->isMacombiis->    public st      $fiblic stat
        }
        return arrathod = null;
   $this->requestUri = null;
   abstract]);
    }
    public func', 'PATCH'))rBag($requesponsePreparerInterface
{
    public fted $locale;
    protelient_hTTP_CONTENT_LENGTH', $_SERVER)) {
                $serve     rInteptions = arrnt(), $datf (isset : func_get_args();
 omBase($requrInte= null;
        $dProviders)) {
   class ConrInter.5', 'HTTP_ACCEPT_CHARSET' => 'ISO-885cation/xml;q=0.9,*/*;q=0.8', 'HT 'D:\\XAMPP_NEW\\htdocs\\MyFirstLaravel\\lar       ion;

use SorResponses();
}
namespace Ile)) {
         ion $e) {
||ceptio=> '127.0.0.1', 'SCRIPT_NAME' =quest = self::createerver = le);
        $this['events']is->mak {
        if (st->server->get('REQUEST_METHOD', 'GET')), array('PUT', 'DELETE', 'PATCH'))rBag($request);
        $this->query = nntent(), $data);
        ient->resolve($this);
 new ParameterBag($data);
      array(), $$this['rnction resolveProviderClass($prov 'client  $keys = is_array($ }
    protected function mahis['rered($provider)
     'client)
    {
        unset($this->insta (!i443;
            } else {
      xt/html,n\Session\SessionInterface;
claey_exists($nam->serviceProviders,nction create$server['CONTENT_TYPE']           $request->req.8', 'HTT          $se $content iron'HTTP_C($pac $requestFactory;
    publll(), $request->fily\Compos_string($prov  if (str_is($er['SERVER_PORT'] = $componentotected function getStackec::$directories esystemists('     if (!$catch || $camer $stacss'])    $server['PATH_INFviderClass($provideray($stss'])) {($this->aliases[$abstract]);
     ilesystemhipturn>bindinST, arr'];
             return $this->getClientIp();
          }
        if (issfalse;
    publiP_ACCEts, $key, aractory($_Gurn $provierver['PHP_AUTH_PW] = $server['HTTP_HOST'] . ':' . $components['port'];
  tFromFactory($_($name, $this->loadedPro 'REMOTE_ADay(), array $server = er' === p.0.1', 'SCRIPT_NA 'HTTP_ACCs['translator']->setLocale(R['HTTP_CONTENT_LEN$this->loadedProvider         $server['SERVER_NAll($filter) ? $thi}
        switch (strtoupper($PUT':
             }
    public fuer['SERVER_PORT'] = $components['port'];
$this->aliases[$abstract]);
  ay(), $_COOKIE, 'path'] = '/';
        }
        switch (strts['path'])) {
            $components[    {
        forey();
    prn $this->s'])) {}str_is($name, gethponents['qtions = arrilesyste   {
        return $this[sponse;
use Il('ts['path'])) ;
    }musterver['SEtereject of $app; fun'ct, $parameesponsePrelesystem\FFilesystem;nt\HttpKeic function setLocale(ser'];
        }
        if (_url($uri);
mponentssulnvironment());
  ser_func($callbackr['HTTP_HOST'] .   $this->languages = '] = $components['pas'geay($tstudly    $server    TENT_TYPE    }
            return $object; {
                    $servemponent\HttpFoundation\Reques{   $server['REQUEST_URI'] = $components}st = $paramete] . ('' !== $queryString ? '?' . $querySGET, $_POST, arrnull, $keys = array()) 'POST':
       {
                    $server['CONTENT array();
   EPT_LANGUAGE' => 'en-us,en;q=0ce($O-8859-1,utf-8;qt()
ll($filter) ? $this->{$filter}($keys. $queryString : '');
        $server[      $clashasS['HTTP_HOST'] . ':' . $componenASTER_REQU's $server['REQUEST_URI'] = $components=0.7,*;q=0.7','QUERY_STRING']p;

use Sp$server['CONTENT_TYPE'] = 'application/x-www-form-urlencodered =nction getRegistered($ 'POST':
      lush()
    {
 $server['CONTENT_s = array())
    uest = self::createll($filter) ? $this->{$filter}($karray $cookies = nuild_query($query, '', '&');
        }
      ray $server = null)
    {
        $dup       }
        }
        if (i' === p>getClientIps();
efa els 'localhol)
    {
        r,   public function est as SymfonyRequ->isMachine($host))null)sharokies);rgs();
        return $this->fl            $dup->  {
        foreachma:$registered) ' ==FerBagn segments()
       self::$requestse Illumname()) {
      ['CONTENT_TYPE'] = 'ption("Type127.0.0.1', 'SCRIPT_NAquest !==c_get_this->dePUT'rver($key  null) {
            $dup->   }
    }
   (\\d{4})-onten2Types = nulunctioges = null;
        $dup->charsets = null;
            $'Y-m-or' =rInter->est =OfD$method = 'GET'));
    }
    public     $dup->requestUri = null;
    rverBag   $server['CONTENT_ $query = array();
   t] = $cl$dup->fosure $closure)
    {
        $res   $request = $paflectionParameter;
class>languages = null;
        $dc::$direts = null;
        $dup->encodings = null;
        $dup->acceptableContentTypes = null;
        $dup->pathInfo = null;
    if (!$dup->getRequestForl;
        $dup->baseUrl = null;
        $dup->basePa. $queew HeaderBag($dup->server->getHeaders());rverBag($server);
            $dup->header       return $dup;
    }
    publicdup->format = null;
        if (!$dup->get(ets = nuack, 'pueateRequestFromFactory($query, $request
            $dcomponents['host'];
        }
  $abstract);
  sJson()
    {
   );
            $dup->he   }
        return arraplic}
    publieresol resolvable($abstract)
 n sprintfgetMetho?:->app = $ 'blade.compiler' => 'Illuminly($keys)
    {
       $filesn $this->flash('o   }
    pub  protected $middl_n spri            $reque  if esol['path'] = roviders[$provider])) {
        Services = array(nputSource()->all() +          return $ccks[$ = (new $this->method =        }
        }
        if (isset($com       parse_str($request->getCo   public functin $this->reboundCallbacks[ices = array(() as $type) {
     onPrllbacks)
    {
        fo>request->all();stati  public function input(onPrprotected function dropSnProvider()
    {
     ronments, $consoleArgs = null)
 vider()
           pub     }
 resolvable($abstract)
   public functiofalse;
    }
  l)
   'CONTENT_T       return (new static())->nProvider()
      if ($environments   protecteoreach ($this->heLIENT_PROTO => 'X_FORWARDED_PROTO', self::HEADER_Chis->getSchemeAndHttpHos->all()         $_SERVER['HTTP_' . $key[' => $_POSarameter)
 $request);
 uestOrder LIENT_PROTO => 'X_FORWARDED_PROTO', self::HEADER_C)
    {
>getContent();solvable($abstract)
 
    }
    public function exists($key)class Conmeters;
                break;
   
    {
        $k function setLocale($loc
          meters;
                bre>getContent(); {
        $sessionReject = $this-> {
         protectedng [ => $_POST';
                $server['CONTENT_Ts->all() 'CON     s['translator']->setLocale(unction registerEv            $query = array(cted $encodings;
    pro       $this-unctio, strtolower($requestOrdabstract]);
    }
    public funcustedProxies(arePreparerInterface
{
    public f $server['CONTENT_TYPE'] alse;
    }
  .0.1', 'SCRIPT_NAME' =s;
  
        }
        if'CONTENT_TYPE'] = one $thbstrIE);
        $req;
   ull, $defau' . $keyIsf (is_nf ($Equivalturn ss($provider);
        ifr_replace('}', '\\}', $hostPattern)rn $closure($resolver($conta    estFromFactory($query, $requestosts = array();
    }
    public statconfig']->get('ap$path ] = implode(', ', $vueryString = '';    protecteatterns);
        sel;
        $this->$dup->languakey_exi;
   $dup->langua    returf::$strcmp(= $_SERVERkey_exi, = $_SERVERder name f    rder) {{
        if (isset($this->ag($request);
        $this->query = nponents['dHeaders[$key] = $value;
    }
    publ->retrieveItem('query', $key, $de        }
        ponents[gumentEn $this->reboundCallbacks[$_exists($key, se $server, $content);
                throw new \Inv}', '\\}', $hostPat         }
        }
        $request = aquery'])) {      $_inate($request, $responsekey "%s".', $key          }
 LIENT_PROTO => 'X_FORWARDED_PROTO', self::HEADER_CLIEted()
    {
        return is_array($keys)tract][
           this->glois->loadDeferredProvider($a    $parts = array();
unction session()
    {
        if e;
    protected    public function flash(           Requefalse;
    public statn useArraySesexplo;
    public $query;
    public $server;
    public $file  foreach (explode(n useArraySessions(Closure $callbis_array($keys)return fo           $   unset($this->def  {
        return php_sapi_namen extends Containe
        $keys = is_array($keys)return forward_ings;
    protected $acceptableCe($keyValuePair[0])Containe return foublic $cookies;
    publieturn fo| $conc($order, SORT_ASC, $parts);
        retuis->me($keyValuePair[0]);
        }
    n enableHttpMethodPa  return isset($this->deturn php_sapi_name     parameter));
        }
        array_multisstatic::$requestings;
    protected $acceptableC
        return parameter)y(static::$r true;
    }
    public sstatic::$rpartde;
    }
    public function get($key, $defais->m
        return self::$httpMethoresult = $this->query-  return isset($this->deferredSe('HTTP_CONTENT_TYPE', cted function registerRoutingProvider()
    {
        ter', 'session's, array('app')) as               break;
   this->request->get($key, $thi
    {
        unset($this->inst       $this->pathInfo = null;_      nents['port'])) {
 kies->all(), $request->filess_string($ function getSession()
 = arring : '');
        $server[));
        }
        ree, $this->loade null)
    {
        roff$resprovide}
    otected function keyParametersByArgu{functioes[$key]->name] = $value;
    }
    G $inpnction hasSession()
    {
  return null !== s->session->getName());
    }
    Sction setS   return $this->hasSession() &null !== ->getHeaders());
        $this->conten setSUs->makn setSession(SessionInis->make($parnull !== $this->session;
    }
    pub__turn $t        return $this->sessturn $this->bray_key_exists('HHOD']             }
        if (), $content eryString = http_buf::$t_forget($result      }
        if (iss   return array($ip);
        }is->maknents['port'])) {
 is->make($par$request);
     r['PHP_AUTH_USER']         }
  rn array($ip);
        }is['eon getBoot    }
    }
    public functio'applicatin getBoowalk($t public fu$k, $v) {
     foreach (str_split($requred = $this->bindingBuilder())-c functionreturn isset($this->alia = $this->bound('session.reject') ? ;
        $this->atch('{((?:\\d+\\.){3}\\d+)\put = $t', $clientIp, $match)) {
  pMethodParameterOverride()
  ientIpypes    ] = $ip;
        $ip = $clientIps[0t);
        $this->mergeCustomMiddlewaresatch('{((?:\\d+\\.){3}\\d+)\ack, 'puils::checkIp($clientIp, self::$trustedProxitic $directto, $keys);
    }
    public function fnt = $c   return array($ip);
        }wakeuis_array($keys) ?  }
    protected function regite\Foundation;

use Closuder;
use Illumina;
 if (isset(' => 'en-us,en;q=0abstract])) {
         null;
     this->server->get('SCRIPT_NAME', $this->server->get('onst VERSION = '4 ''));
    }
    public f = $content;
      getPathInfo()
    {
    re;
use e();
        }
        $deptComponent\HttpFoundation\RealuePaiorson extends Fa    ynClass($pe;
use Man   $       $objen extends Container impleme        $instancesappray) $dependencies $this-> $booted = false;
    proeBindings($request ?: $this-dencisnction getBaseUrl()
 ed static $directories = ar
   ,asePath = $= $thisis->basePerns = array();
    protecstatic    {
        $thisrepareBf::H>basePath;
        if (file_exists(luePair = explop]#', '', strtolower($rs->midparameters'] = compactparsach (explodract) || $this->isAli      if (ar);
            $s[    }  protected functio         $partter)
    {aluePair = explod   return $value;
    etPdoForTy(arreturn $thi
    pub
    {
        $alias{
            if (lic functioreentTrs = array())
    {
        $abst              continue;
    if (ger', 'auth.reminder.repository'n getPort()
    {
       false;
    publi>isSecuEADER_s $middlewareDokies)'/json');
    }
    puc::$dirStr::ciesubli
    }
 walk($t    ahis->::wsBoo($va?face HttpK:ted     }
 2 funclass;
lResolact);
        }
    }
    public pues[$a>isSecure() ? 'https' : 'htag($fileiseturn $) || $this->isAliis->make($par{
            if (s::requestClass(), $method), $']'));
         }
    public function rebindedProxies) {
            if _CLIENT_PROTO], 'http')) {
                protected function dropelf::HEADER_CLIENTt, ']'));
   fsetUnset($key)
    {
        unset($thrd fu      if (false !== $pos) {
     ($host, ']'));
        _CLIENT_PROTO], 'http')) {
                  {
        return $this->b{
            if (self::$trustedHea              continue;
) || $this->isAli', $parameters = array()re $thiPdoaluePair =, 'parat, $concrete);
        }
        $n getUserInfo()
    {
      {
        forean seHEADER_CLIENT_PORT] && ($port = $this->heustedHeaders[self::HEADER_CLIENTt(self::(            bootQUERY_Seadrn $userinfo;
 blic fun$this->bind($abstract, $extender,ENT_PORT] && ($port =ony\Component\HttpKfir->ge     $this->fig        if (self::$tr       return   if (nulassword()
    {
        return atch('{((?:\\d80 || 'https' == $scheme on () fig $response);
        $thrustedrithodPar      [':' . $   $this->bo$port == 80 || 'https' == $sc:' . $ && $port == 443) {
            return $this->getHost();
     if ( }
        return $this->getHost() . lic function fl $thisolvingCa        return $this
    }
    protected funct       aluePair =  $this->loads($this->header('CON       }setFetchinabarray(), $s['      ']['uestuse .f>get            }      $clasrn $recked('resolv($value);
    publHeaders[sel$this
        return ost();
    }s = $th    }
            if (
   ] = implodes->baseUrl;
 ())) {
        his->       (y)) {
             }
    protected functionarametcis->   $this->boo           re '://' . $thisPaprot_HOShInfo() . $qs;
    }
    public function getUriFophemeAndH($path)
    {
        return $this->getSR'SERVER_dHttpHost() .rvice = nullfunc_get_args();
     ('SERVER_POurn $this->gabstget($input, $key
    public functiose;
    protectedfault);
    }
    public funf::$trusted   {
        return $t   $response == '\\') {
                }=->dead's->getQueryString())) {
        rn $u));
        rettpHost()
    {
 viders)) {
     _PROTO]  $this-->getQueryString())) {
        blic func));
        retHEADER_CLIENT_PROl : $qs;
    }
    public function isSecure()
    {
       if ('http' == $Headers[self::HEADER_CLIENT_PROTO], 'http')) {
                return 4ublic function ost();
    }
    public functioelf::HEADERblic function getRr, $servicrt();
           elf::HEADER $respon    {
        return $this['public function resolved($th) {
  act)
    {
    edHeadureturn issetgister($instance = new $Headull) {
            $dup->cookies) {
                      $_POST = $this->reques   }
    public functio     }
   $thisrs[$key];
    }
    publis[count($elements)explode('=', $param, 2);
   st = $this->headers->get('HOST'))parts[] = isse null)
    {
        rdencie  if ($h(Request     public static functio0 || 'https' == $scheme dParameterOverride()
    {
    ->server->get('fo()
     $param[0]) {
                continue;
$key)
    {
        if (!arraientIps[] = $ip;
        $ip = $clientIps[0 array_reverse($clientIps) : array(this->headers->gefunction issure($abstract, $concr        }
        return $this->paif (isset(sePath = $this->prepareBasePath();
    {
        return $this->isSecure() protected static $diretp')) {
                retu    if (!($host = $this->server->get('SERVE;
        }
        return $this- {
        lder;
uPDOIlluminate\Suppor = $this->resolveNonCrt\Contracts\ResponsePreMySqlplace('/(?           }
            }SQLBooted()
               }
            }  regres        throw new \UnexpectedValueExcqlpendern(sprintf('Ugister = $this->prepare;
            } else {
           function resolveNonClass(ReflectionParameter $pr)
    {
        if ($parameter->isDefaultValected function registerBaseMiingC['schem        reture];
        if (!isset($thort();
       eaders->http'        return $this->ge$port == 80;
    }
&& ($is, $deep))) {
                 returnblicisBooted()
    {f ('POSthod = 'GET', $parameters = array()$valueSingl $this->headers->get('X-HT       break;
                           $this->ethod) {
    tiable.";
        do{
        return = static:ethodParfunction ders->get('X-HTTP-MRIDE')) {
          
            un   }
    publp;
 do }
      lic functiOST')('_methoprefix           = strtoupper($method);
               method = $this->head$httpMethodParameterOverride) ers[self::HEADER_CLI                $this->m }
    pubsBootedREQUEST_MET>push('Illuminate\\Clower(current(explode(',       if ($methde(',', lic function $this->method;
    }
    public f   }$httpMethodParameterOverride) && (  }
  $registered) {(static::er($this->request->get('_method', $this->querys->mt(static::= strtoupperc function eturn array(key($definition), curreat]) ? sta
        }
        return isset(static::$formats[$format]);
    }
    publics->de ($p'path.base']);
    }
    ces[Type, 0, $pos);
        }$mimeType)
    {
        if (false !== ($pos =);
    }
   $httpMethodParameterOverride)  $thiType = substr($mimeType, 0, $pos);
        }
 $this-  if (null === static::$formats) {
            static::es) {
         {
        if (false !== ($pos = strh (static::$formats as $f
    pubidFile($file)) {
         ('_meth     ]UESTn forceRegister($provide) {
          
    }rdepen) {
         n isValidFile($file)
    {
   ) {
         estFromFactory($query, $request,ormats) {
          ethod) {
      cation/jces[($consoleArgs) {
            
    pu->isMachine($        r = 'ht}
       f ($host $this->_PROTO] && 'https' === $this->headers->eTypes)
    {
      ');
        return !->files = newadride = Reque        }

      ay()    lResHOST')) {
            if (file_exists(        $this->$httpMethodParameterOverride)      if (ar('_method', 'POS($host = $this->headers->get(self::$trustedHeaders[se'A :' . $p_builbe spec$thislic function setLocale($locale)
  globalResol!== ($       "dbself::$tor.s->g_method', 'POS}"callback)
    {
        $this->globalResolvingCa(isset($components['userswit     rmat($this->headefunc_get_args()rame 'mysql':tances[$abstract]) || $s}
              set(, $hostPatternrn $thpg->defaultLocale;
    }
    publicst));
        }tLocale($locale)
    {
 sqlthisfaultLocale;
    }
    publicception(sprin$locale);
    }
    public srvction getLocale()
    {
          public functtLocale($localstract]);
    }
    t(self::$trustedHeaders[selUns    $dedtDefault[>locale) {
        ]rn isset($this->resolved[$abstracnction getCon->get:' . $,
   ies && self::$tc functi   }
    s, $i($defaultedHeaders[self= is_bool($this->input($key))= $locale;
        if (null === $thion.{rn in_a     $this->setPhpDefaultLocale($locale);
        }
 }
      this->getMethod(), array('GET', }
          }
    public function getDe:' . $  {
        return $this->defaultLocale;
    }
    public function sede($keyValuePai  throw new \LogicException('gale($locale)
    {
        $this->setPhpDefaultLocale($this->locale = = $asResource) {
            $this->content = false;
            ic function getLocale()
    {
        return null   if (null === $this->content) {
            $this->content = file_>defaultLocale : $this->locale;
    }
    public = $asResource) {
            $this->content = false;
  hod($method)
    {
        return $this->getMethod() === strtoupper($this->c  public futhis->server->get('SCRIPes (nulder;
uSymfony\
   onem\Ftracackedsystet = $this->gethe' == $this->headers->get('Pragme') || nguage(ar implements     ray $locales = n  if (isset(ray $locales = nudencies,
    {
        $prefabstract])) {
         clasublicLocale($l
            $querturn Needs->reboundprotected static $direcks[$abstrOnreturn inces[$abstract];
        }
        $concree') || 'no-cacClosurironmen Illuminate\Http\Rehe' == $this->headers->get('PragmCookiironmenhe' == $this->headers->get('Pragma');
    }
    public function getPreferredLanRred === tpreferredLanguages as $lanKernel= ($positio    $preferfunctioiddleware       $obje = strpos($languagePath();
        }
        return $thism      iceProviders = arrary, protected static $directories = ar = strpos($languageetUri,ges = $t               i,       }uperLang;
    }
    public function geurn $this->baseUrl;
    }
 ge;
     perLanguage, lace('/:\\d      iages      if (!in ($attributes !== null) blic$preferredLanguag   $respon = strpos($language::MASebouREQUEST$comp getkey));
    }
    publi  if (nuheck->rebouGET, $_Pe') || s->re static::nor {
        if (s') ||     re    th()
    {
       $anguagetion getHoest =e') || {
        if (null !{
     ) !== 0;
}
        anguage   return $this->getSc        $sh   return $r));
   urn isset($prefes[0] :  if (null !== $this->languages) {
            return $thisResourclo   {
    s->headers->get('Acc       returddreach Toguage;
 tion isBuiectedeaders->get('Accept-Langua) || $shared === true;       if (file_exists(unction getLanguages()
    {ic $registered = false;
    publ public function geerLanginate\\Session\\SessionManager', 'session.skey)
      return $this->gerLangfireResolv    }
    public functio      i$thiss[countD' . $($key, Types)) {
  tch (BindingResolutionException    }
       ic $registered = false;
    publ->boun->languages;
    
           $languag    publ
        if (!$
        if (null !              }ntIp = $match[1];
         s) {
            static::initialtrstr($lang,ray $locales = nu         oviderRepository(n        bstract)
    {
   if (co    pGarbset('         if ($       break;
            dery) ic $registered = false;
    publiurdedPrrn function ($c, $para?.*rray()) ueResolvingCaU    bstract)
     = array())
    {
   sJson()
, $keys)      l    conta array_keys(Act);
        funcur, $this);
            $this->rebCharsets()
    ;
        }
        return $this->languageper($this->servef ($i == '_' . strt    ret segments()
    {
    ocale)HitsLottrue);:$format    return $this->languingCaleturn is->g $this->gstracfet asSd funtion\\Factory', 'view' => 'Illuminat        retueader::fromStri$httpMethodParameterOverride)y, '', 't   sta1          }lromStr'](!$th<ort;
    }
ceptableCoound($ablosure)
    {
        $resol explode('-', $laguage;
 age'))->aldPreferre   }
        return $this->languages $this         }
ll !== $this->languageIsPersdencitFor       }
        return $this->encodis->headers->get('Acc$class[[self::HEADEtes);$claluminate\\Foundation\\Age'))->alurn false;
hodPaach utdow       $     ifiler' => }
   Iings['     $this-ach  public tion (   pth      ['unctioOST') (is_null($directo'/';
        }
        switch (str public function ONTENT_LENGTH'))) {
                $_S       return $this->encodi, 'lpublic ') * 6edHeaders[$keys = $this->server->get('X_ORIGINAL_URrn $this->encodings;
        }
        return $this->encodings = arraimeTypes) ? $mi'expire_on_ges[]'] ? 0 :s;
      nowall(addMinstatices;
     ORIGINALos($host, ':');
    }
    public fuguages) {
       etect($envs, $args);
    }
    RL');
            $this->server->remove('HTTP_X    pubt('_format', $default);
        }mlHttpRequest()
    ));
    }
    puhis->method = strtoupper($this-per($thsolved = aemove('IIS_WasUrlRewritten');
        } elsey $services)'_method', 'POST'          pu               }
        }
        if (i                 } else {
                       $lang .= f ($i == :' . $l_user_func($ca        = $thier::fromSc('UNEe;
    Accept-Encodiurn ''                 }
            }
  ntrolDirective('no-cache') || 'no-cach->get(ng'))->\FileEnvironmenhe' == $this->headers->get('Pragma');
    }
    public function getPreferredLanguage(array $loBag=== 0) {
                $requestUri = substr($rguage(arrtorage\MetauestBag     retument       $obje       if (empty($locale$instancesit;
     if ('' != s[] = issearray();
    protected $middlewares = array();
 baoting(functition);
           ei = $thUri .= '?' . $thisth)   protected $loadedProviders[0]) ?   }
        }
   st =instance('requestUrl) {
            $this->balResoleAndHttpHost) === 0) {
_PATH_INF $this['events']->until('ills->get(sel$thi        return nderRe
        reay_values(array_iATH_INFile($kH_INFO');
           if;
    edProviuestUri = $eturn $ipAddresses[0];
    }
     }
 $key => $value) {
     oad . strtotr_is($name, gethostnamhas('_tokenhis->getQueryStringthis->megn ge  }
  }      }
        retlt = null)
    {
       $thiturn true;
    }
    protected func $this->serve      }
            }
 es->all());
    }
     PUT'return isset($pfunction setT->isMachine($host))his-ntent) {
   if (basenament()
baault = null)
    {
       it     $Llderr->gE');

    {
        bag         $parl = $this-th) [_SELF'
      $re         }
            $query = array(), array $reT_NAME')) === $, strtolower($req $thisrver->get('SCRCRIPT_OD', 'GET')his-ypes)) {
               ? {
        $tquest;         mentArgument($args))) {
               $path = $this->se;
        }
    }
    p$file = $this->server->get('Shis->serverstrto$this->server->get('), $force = false)
    {
    okies);
     T => 'X_FORWARDED_PORT');
    pr$this->s = new ServerBag($server);
    {{
        return rtrim(pregs = sub $pos);ublic function mak }
    publicr->gettring($trray_values($new ParameterBag(KernelIn $instance) use($target, $method) { $pos);
        }
    his->share($closurs($this   }
    }
   [a-f0-9]{40}unctio   }
    public s = $this->server->gseUrl && false !=decode($keyValuePair[1]ha1( ($tid(EAD'    {erverr   stom(25x, 'microNAL_U();
  dHeaders[$key] = $value;
    }
unction session()
    {
        if RVER_ADDR', '');
            }
 s     ifexplode('=', $param, 2);
   e = basename($this      $requestOrder = public  }
  = $this-              }
            }
  protected $middlewares =          ifigthis-ypes)) {
         $default = null)
    {
        redReques$url = $otected nelIn($baseUrl);
        if (empty     url = $($this->server->get('QUgs = arraurl = $thrse($segs);
            new ParameterBag(   public quest();
    }
    publ       if ($baseUrl && false !== ($prefixename)) {
            return '';
      erver->get     if (strlen($t $request, $type = HttpKernelI }
        if (et('SCRIPT_NAME')) === $filenabstra      }
            }
 ddB$file Tois->server->get('SCoreach (geFlashth) if (empty($baseUrl)gs = arra $thi& $pos !== 0) {,        $las $parameters;
      return rtrim($th     $this->server->losure)
    {
        $resoleUrl();
        ifuminate\Support\Contract     $baseUrl = $this->server->get('ORIG_SCRIPT_NAME');
        } else {
     putg . $baseUrl;
                  $file = $this->server->get('SCRIPT_FILENAME', '');
  ic::$directories {
           = new ParameterBag((array) json_d('f    .olor' blic funes(arolrn, urldecode($this->pathsponse;$reqnput, $key, $default);
    ', $();
        i>getBaseUrl();
   new  if (null        $basePath =        }
         if ($plve($this);
    }
    protectededRequestUri = substr(function isDeferrgetBaseUrldown');dHeaders[$key] = $value;
    }

    }
  NTENT_TYPE', 'CONTENT_LENGTH'))) {
                $_Snction overrirequestUri, st0] === '[') {
                $Ips[ = implode(',YPE', 'CONTENT_LENGTH'))) {
        reques';
        } elseif = implode(', ', $value);
            } elted ldIn', $bey, array(     foreach ($envl }
    public   static::$foypes)) {
         f::$trustVER))?questOrd$requ{
  :)) {
      ())) {
   ts, $consoleArgs = null)
  static::$formats =estUri, strlen($baseUrl)))) {
   $iaticT':
         ('_old_ext/c
            $reqTH'))) {
             ext/cstatic function initializeFormats()
    {
   = arrlResolvs->set('_format', $thstFormthis->headers->get(seeif (null s($this->session->getName());
    tic::$fo array('ss[0] == '\\') {
          
          = null, array $files providivate funceparerInter  public static function setTre)
 s(arr    ted $>s('Locay\Coms->retrieveItem('server', (('Locale''] =e)) {
    tect($environments, $consoleArgs = nul
           return $this->hasSessi['sches'), 'json' =->content) {
' . $this->g    rameteT_FILENAME' => \\', '/', $bYPE'] =)
        $this->fireCallbackArray(a
            
        }
    }
  if (0 !== strpos(
        try {
  }
      shri, '?')) {
   }
    }
    pujson($key = nPUT'Old
         ivate functidecode($string), $prefix)) {
    tatic:['scheme']) _HOST => 'X_FORWARDED_HO     > array('applirray('application/atom+xml'), 'rss'rete sta  $baseUrl = $this->get = 'hNew
    ult = nulleUrl();
        if (null os = strpos($requestUri, '?')     if (null cation/javascript', 'applicakeep strpgp]#', '', strtolower($rell)
    private functs) {
ll)
 foreach ((array) $hosts as $host) rray(), array $at{
           $preferredL$len}}#", $string, $mquery, $requray($mimeTypes);
    }
    pub), array $a['schem{
    $content);
    }
           if ($this->isMachine($host))nfo = '/';
        if ($p("#^(%         $basePath =stUri, '?')) {
  ishCallb    } else {
            static::$dir}}#", $string, $match) Request) {
            '/';
        }
           if (!is_null($eUrl();
        if (null  instance of Sy   static::$directories     foreach (static::$directoriest->all();
        $_SERVER = $this->slic statarkAsRegistered($provider);
     tion setTrustedProxies(arublic function getRegistered($}
        $len = strlen($prefix);   return $this->server->get('$inputt;
    }
    public function setR;
    }
    protected staat = $format;
    }
    public fu readyForRes 'rdf' => array('applquestUri}
    protected static{
            if (file_exists(le
         if (empty($basename) || !strpos(rawurldecode($ {
        if (ihis->NAME');
        } else {SELF' $paramtect($environments, $consoleArgs = nuly($objde('=', $param, 2);
    rameters = a      $requestOrder = prS     $^\\[)?[a-zA-Z0-9-:\\]_]+\\.?/', dirnam '/');
    }
    protected funcs, $pr->g        } elseif ($ME');
        $seg = $segs[$inds
                $baseUrl =b        es->get($key, $this, $dee$thist;
    }
    public function setRis->parametis->seation/xy)) {
      ->get('CONTENT_TYPE'));
    }
    public function senam     if (!$d
    {
        $ }
    public function handle(Sym->server->ge $request, $type = HttpKernelI;
          $this->parameters) ? $this->files,rameters[$path] : $default;
        }
        $th) oot = sub      ++$index;
            } while    }  clone $this->server;
        $thi      } el
        $this->request = new Par
                if (null !== $cur {
     '/');
    }
    protected function prvalidArgumentException static($que    } ees !);
      40        return (new static())->dupublic fs->set('_format', $this->rver->get('SCRItions = arrpublienceA    us,en;q=0.5', 'HTTP_ACCEP';
        }
   = $char) {
      tect($environments, $consoleArgs = null)
'/', trim($file, '/'));
HEADER_CLIENT_IILENAME'));
redLanguages, $locales));
  ? $preferredLan       }
                if (!is_arry) {
       e('UNEeAndHttpHost) alidArgumentException(sprintf('Un
        if (!$preferredLanguagearray $attributes = null, ars($currentKey, $val\InvalidArgumentException(sprintf('   $class = static::nore Illuminate\Foundation;

use Closue') || 'no-cac->get('SCRIPT_NAME'                  if (false !== ($p_INFO')) {
            $reqng'))->\Null      }
           retueferredLanguagdencies,               if ('' != if (null allCustomncies($pa once w       }
                if (triev . strtoentTyp::       throw new \InvalidApublic function isMethodSafe()
    {
 nt(),       rmat = $this->getFormat($tyrver-Host();
    }
    publir->get(.      OST'$forentKey .= $char;
 )
    {
        $scheme = $this->gnction gach  function set($key, $vaquestUri) >=  $this->parameters[$key] = $v= $this->h    }
        return $thformed path.  $requestUblic function h (!($host = $tue;
    questUri)   }
        return $value;
    }
    pFilts($key, $this->parameteRIDE')) {
          Nativts($key, s) {
            static::initializeFoy, $default, $iable.";
            thr $this->parameters[$key] = $vers, ar      unset($this->parameters[$key]);
   eg_r public function getAlpha($ers, ar) use($a$deep));
    }
    public function getth) {
  s($key, $this->parameterers[self::HEADER_CLI
     uncti'/json');
    }
    pub     throw new ->parameters[$key] = $v $reqace('/[^[:alnum:]]/', '', $this->get($key, , '', $t public functions && self::$tr      $this[ind($abstract, $extender, $th '', $this->filter(     return str_replace(array('-', '->parameters[$key] = $valace('/(?ace('/[^[:alnum:]]/', '', st = db']e(urldecode($keyValuePair[0]));
      turn $value;
    }
    pupc function set($key, $value)
  ethod', $this-is->    d('apc    }
            return $object;nctionMemrPathd  if (!is_array($options) && $options) {
            $om  }
    ons = array('flags' => $options);
     WinrPath  if (!is_array($options) && $options) {
            $owARRAY;
ons = array('flags' => $options);
     Rediss($key, $this->parameteret('SCRIPT_ptions) {
       ng'))->a'r
   Types)) {
  ArrayItelias($is->ders;
       QUERY_QUEST_METHOD', 'GE= null, $deep = false, $filter = FIypes)) {
                formed path. ArrayIte$deep));
    }
    public function get          $InvalidArgumentException(sprintf('Malformed path. or($this->parameters);
   ;
        }
        return $value;
    }
    pr', 'name', 'size', '::make($abstract,    $r}
    public function remove($key)
    {
        unset($this func_         public function getAlpha($kPath($estUri = InvalidA        $rExceptionServiceProvider($this)); Symfony\Component\Htn set($key, $value)
    {
        $this->parameters[$key] = $value;
    ponent\HttpFoundation\written');
        } else->encodi- 1];
        } elseif (!($host = $this->hear->get())) {
            if (!($host tp')) {
    if (!is_array($options) && $options->parameters[$key] = $v    public fun          if (!($host = $this->s       'SERVER_NAME'))) {
                $host = leInformation($v, 0, $pos);
    this->server->get('SCRIPT_NAME[0];
        }
 Interface, Termif (null !== $currentKey
        return $thisc  throw new eBindings($request ?: $this-:' . $ll === $this->baseUrl) {
            $this->baseU       }
            }
        }
       null)
    {
Interfacef UploadedFile.');
        protected static $dire    $this->parp]#', '', strtolower($req' . $port== selfROTO], 'http')) {
  = array_keys($elf::$trustedProxies) {
          if (null === $this->rile['error']) {
       $this->server->garray $f once w($bound) {
            $this->reboror']) {
       tDigits($key, $default = '', $deep = f   $this->para::make($abstract,eturn in_nction funuceptionH once wh. '], $fip = clone $$port == 80 || 'h      if ($fil     if (null === $this->requestUor($this      throw new \InvalidA_CLIENT_PROTO]))) {
'] = $components['passtion isL
    {
        return $this->$query !==ction isMethod($method)
    {
        return $this->getMet], $fis->headers->aramed() === surn isset($this->resolved[$abstrac       throw new \InvalidArgumentException(sprintf('Mal 'convertFileInformatioon getAlphapath. Unexpected "[" at posit       }n in_arrperLanguas as $key => $alias) {
 data['name'])) {
            rrequest)($coLIENT_PROTO => 'X_FORWARDED_PROTO', self::HEADER_CLIE], $fi:^\\[)?[a-zA-Z0-9-:\\]_]+\\.?/',{
     ) {
            throw new \UnexpectedValueException(sprintf('Invalid Host "%s"', $host));
        }
        if (tUri = $$trustedHostPatterns) > 0) {
            if (in_array($reach ($eferredLanguages = array();
        foreach ($etMethodach Ja
        $instances       's->make( $instances nction ict], $this if ('' != eric( return (array) $df (null === $this->mation/x-xml')meters = a  $m) use($ array('CONarameterBag
nique(array_len($req    unse$locales[0];
    }
    s->mid($class>getClovate function    And   {
   
        forearldecode($trpublic ay())
     }
REWRITNAL_UR +0 === strpRL');
           $this->booestUrirray();
         ifster(arrayred = fal }
   T_MD5' => t }
    public function keys()
  eay $files();
       rs = array('CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => NTENT_TYPE'])) s = array();
      262800eaders =lseif (isset($contentHeaders[$key])) {
                $headestr($requestrs = array('CONTENT_LENGTH'_AUTH_USER'])) {
            $headers['PHPGTH' =-ER'] = $this->parameter   $requestUri = substr($requestpace Il   if (!self::$trustedHeade) {
            tion g) {
            return $match[0];
 THORIZATIONestUri, strlen($baseUrl)))) {
            return '/';
   tion gstatic function initializeFormats()
    {
   
     Key = null;
        n fattpHoy\Component)efault;
          rn in_array(strtoloach ct,         $authorizatist($request));
    }
    public ECT_HTTP_atch('{((?:\\d+\\.){3}\\d+)\\:\\d+'head  $tRIZATION'];
            }
 ldecode($truncattion g[!== $au  return '';
    each ($ fireResolvingCallbacks($abs
      ');
        return !is->make($par
       trrpos($host, ':');s = $this->server->gerameters as $key => $value) {  }
    public function seton getRsolved = a } elseif (is_AUTH_PW'])           $authorizationHeader = n0) {
                      list($headers['PHP_AUTH_USEs->middPW']) = $exp             ect, $this);e {
            $arn self::$trustedHeaders[$key];
    }
    pumespace Ikey, 5:^\\[)?[a-zA-Z0-9-:\\]_]+\\.?/',tion g, 'size' => $data['size'][$key]));
        }use Illuminancryolved  }
    \Component\HttpFoun }
          reypn resolvedreferredLanguages = array();
        foreach ($preferredLanguages as $language) {
            $extendedPreferredLanguages[] = $language;
            if (false !== ($position = strpos($language, '_')))this-         $superLanguage = substr($language, 0, $position);
          e   }
     ge, $preferredLanguages)) {
                    $extendedP    }
          retu       }
            }
        }
        $preferre     retuod(),    return $heredLanguages, $locales));
        return isset($preferredLanguages[0]) ? $preferredLanguages[0] : $locales[0];
    }
    c::$directorieaders =on getAlpha$this->lanAuth\\Auy => $v        meters');

     nvironments);
    }
    protected fu=> $ic $registered = false;
    publ {
       URI');
         sponsePrepare{
            $ketry5', 'HTTP_ACCEPT_CHARSI');
            {
          $this->set(key, 5)]csLocal()
    {
   0] : $(aders['PHP_AUTH_ $0.5', 'HTTP_ACCEPT_CHARS_keys($this->headers))) + pper($request->roxies()
    {
        return spublic function  public function __toString()
ntent = 'his->p  return $prefix;
       ent) {
  is->paabstract]s->set( $provi .= spriolved = aaders = a{$max}s %", $name    }
    public function __toString()
  }
    }
   s as $value) {
       $s->set( getHeaders()
    ders) {
      ECT_HTTn __construct(array $parameters = return $tlf::createRequeslue);
            }

        try {
           $index = eturn $t (count($exploded) == 2) {
    key => $(AcceptHeader::fro(!$this->headers) {
        eRequestUri()
   move('UNEhost', 'HTTP_USE{
            $keyaders =      returnlue);
     key => $vcay $hy\CompsLocal()
    {
 pareRequestUri()
    {
       1;
     u{
      ;
   as $key =  $this['events']->fire($class                 if (    }
    protected, $defaulkey, 5 t = n'');
        $server['QUERY_ $requestUri->set(
        
       ->set(E>headse Sytion (             return    {
     retuisS
    }
;
      tracunset($, 'size' => $data['size'][$key]));
        }
        return $files;
    }
}
na');
    }
    public function getPosition = strpos($language, '_')))pace          $superLanguage = substr($language, 0, $position);
                 rn $headers;
    }
}
namespace Symfony\Component\HttpFoundatny\Componfunction        }
            }
        }
        $preferreurn '';
ded = expsArray(array('error' => $data));
        return isset($preferredLanguages[0]) ? $preferredLanguages[0] : $locales[0];
    }
    pe'))->all();
        $this->languages = array();
        foreach {
        if (i          $snHeader;
      ion k .= spriared'])) {
            
    public function g        }
   e)
    {
        $key = strtr(strtolow$dependency)) {
        }
             }
        return $f     it $thre\Utilic fingolowst;
useturn array_key_exists(strtr(strtolower}
   R     ce Symfo    }
           if ('' !=  request.  public funipquery-MCRYPT_RIJNDAEL_128viceProviders();
  dTTP_>get($kMODE_CBC        }
        locke(ex6s->baseUrl) {
            $this->banents['port'])) {
  pos($aprovid= $_SERVER request.');
        }
        key => $veturn false;
    }
   iv = mng()
_nction_iv& $pos !== 0vS  $th   $pathInfoon conizl($key)) {
     applicatuse 64;
        $thispadAndM {
         x = vtic::$requestCa
        reh     ntrolt = null)
    {iv === $defypes)) {
         t = null)
    null;
      compact('iv    rInte    prc) {
_PROTO] && 'https' === $this->hea if (null === ($value = $content);
    }
    public st->gead>boo(       $las127.0.0          $header = arrakey => $values)e, $th+ 1;
    rray('appli+ 1;
    publalue =::requestClass(), $method), $p     }
['sharediable.";
         ($parameull !== $bte\CP= $valis);
        }
    }e $default = nul_null($con($par[DATE_RFponent\HttpF    return $dolHeader());
    ivmponent\HttpFoundati         $lasault = nripble (%s)get('ORIng()
aders['=== ($value =     {
        $scheme = $this->geontrol);
    }
    publicfalse;
    publarray_map('strlen'}
        retu        $
    }
    public function addCacheControlDirective($key,sort($this->h'config']- foreach ($this->he    }
    aders['PHP_AUTH_($      Mess = $t['path'] = '/';
        }
        switch (strthis->set('Cache-ContcheControl[$key] = $value;null;_null($sCacheControlDirective    }
        $thi. $que = $valu $content ame = b->set('Cache-Contey)
    {
        unset($this->cacheControl'public      lic function setLocale($loc, $defaublic Mac    call_user_func($callbaeturn count($this->headers);MAC i    blic lic function setLocale(        re($parstrtolower($key), '_', '-');
  ader()
  is->insta;
    }
    public fucheCotic $dirt_args()'openssl;
     _pseudo_bytethis->getQueryStrind)
    {
  Rbstrme->headers);OpenSSL $dataanguais  as i{
            ret)) {
          [$ke  {
 unction con if (!ixtB    (16;
    }
    palcMkey))    _hmac('sha256    $pathI      rective($key)each ($par  }
    pu }
     ted function loadDeferrer($key), '_::equals(
            }
      ());
    22, de('    protectetabasealuealue))) {
            throw new         y_exists($key, $this->headers))
            }
      iv));
addCacheContro   public functio
    {
        $resolv, $this-eturn false;
    }
   palue;
          $k-urrelDefarInterf%1])] = isset(          $headersapplic '/');
epeat(ch($thiHostPat$this->getUrlencodedPrefix($reques($key, $this-eControl[strtolower($match[1ordy\Compo[($larraytch[3]) ? $mat) - \Illuminate\\ {
        if (le (%sI = sub}
   }
    publ?     tr=== ($valeademfon-   retfunction duplicate(arr        throw new \RuaBag;
interface SessionIn }
    public staeaderParametch[3]) ? $matcion stferredServices[$abace
{
    publion getNam
    rue);
         public func-1     return $cacheControl;
    }
}
name public functionuest; $pos);
        }
        eaders[t = co||rameetDef    ($key)
nction has($name);}
    punction has($name); arra    {
        if (false !== ($pos =
    pubntentTypes) {
         = arrautiniv_s           
    public funpubl    {
        if (false !== ($pos = getDate($ke               $par    ret('>get($kDEV_URANDOMarray(), array $files = a function isStarte function setLocale($loc   public function iStarted();
    public function registerBagSessionBagInterface $bag);mt_s  sta);
        }
     >get($keANDasSession()) {
            throw n);
        unset($this->headers[$key]);
        if ('cache-control' === $kecoun, $th(ue, $thde('=', $param, 2);
    , $this-ue, $thrldecode($truncat, 'RouB   $   pub initialize(array &$array);
   HttpHoes);
(), array $request = arublic te;

 clear();
}
namespace Symfony\Component\HttpFou->retrieveItem('headers'ymfony\Comp;
        }
    }
    p   $key  public function replace(array $attributes);
    publthis->server->get('SCRIPT_NAME'Facad    e SymfoLog
        ($name        if ('' !=eturn php_sapi_name($nameRequesset(       foreach ($heade'logon\Sessiomake($abstract, $parameLo
    {
 Monologony\osition %      }
        $dependencies = $conalue);
  yArgument($dependencies, $parameters);
        $instances = $thisay();
    protect$dependencies, $parameters);
      lundatedProvi);
  r     oundat $qs;
        ney)
r = array(),   }
        return $values) {
    $this->ion r'] !=ndat, $request, $attrirn $refle('Psr\ony\
    g) === 0) {
instances);
    }
    protected functionarameton r]ct('cla\Http)) {
        $scheme && $port == 80 || 'h->name;
. throaders->get('CONTENT = count($codes); $i <$this->name = $n
        $this->stor     } catch (\Exception $e)      $dependencies[] = $primitives[$pa   {
 Key;
    }
    public funct $first ? $default : array($defany\Compone      }
     use Illuminate\Container\Containernt\HttpF   $currStreaE')) ===y_exists($name    puion    publoundation\Sent\HttpF      ter\Lin               }ts($name, $this-ErrorLogbutes);
    }
    pub   $currRounsengeg_rbutes);
    }erviceProvider;
use Illuminate\Config\FileEnvironmentVariablesLoader;
use Symfony\Component\HttpKerne>keyPar $att        if ('' != m  publ            $depenevss = $class;'reshReKeyinfoeKeynotinctio'warpExcplacee, $onsoleit
   placalery => e = 'hnc          $instances tatic::$requestCed static $directories = aron get($name, {
      ,$parameter)     foreachrotected function prepareBaseU
      oundat       returray_map(array(lias($key, $alias);
        $host, ']is->query->get($key, $this,ders->get('X_ORIGINAL_URL');
             publi$clientIps[$key]);
            }
   ireAppCallbackrete = null  static::initializ   }
        retIterat    publ  }
        reeControl($values[0]);
     atch('{((?:\\d+\\.){3}\\d+)\\:\\ remove($  }
    public function getClientIp()
    {
        usreg_rtClosure($ttriblass
    pvate $storageKey;
       ->server->geLtribSymfv$this->registction remove($odedPaname', 'set('SCRIPT  {
  ttributes);r()
    {
    kies = array(on(sprintf('    retur'cli-server'okies)t\HttpFounray $server = array(), $contenuseDailyerator()
    {da)
   ic fun       return new \ArrayIterator($this->attributes);
    }
    public function count()
    {
        return count($tbutes[$name] : $defSessionBagInt   }
}
namespace Symfony\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundatiome, $thi  }
       returnlesAr     uste = me, $this->attr::OPERATING_SYSTEM new \ArrayIterator($this->attributes);
    }
    public function count()
    {
        return count($tme, $this->attr    ivate $la   }
}
namespace Symfony\Component\HttpFoundation\Session\Storage;

use Symfony\Compo>files;
        $this-ion\Storage;

use->query : $this->request;
        retur>serverGTH' = Closure;
use ArrayAccess;
use Reflebutes);
    }
           if ($thison getDe}
     {
        return $th
    pfaultLocale;
    }
    puon get($name,::DEBUGale($locale)
    {
 ic fuf ($timeStamp - $array[self::UPDATED] >=INFOale($locale)
    {
 ion repf ($timeStamp - $array[self::UPDATED] >=NOTICEale($locale)
    {
 e(array f ($timeStamp - $array[self::UPDATED] >=WARNINis->updateThreshold) tributf ($timeStamp - $array[self::UPDATED] >=ERRORale($locale)
    {
 
    {
  f ($timeStamp - $array[self::UPDATED] >=CRITICALale($locale)
    {
    $thf ($timeStamp - $array[self::UPDATED] >=ALER flashEmeta[self::LIFEattribute;
    }
    public function stampNew($lifMERGENCYpublic functio     }
faultLocale;
    }_TYPE'));
    }
    public function s
    }
 e($n
claslic function setLoc    return $parameter->getDeforeach (self::$fileKeys as $k) {
_FILE == $file['err=> 'Illuminate\\Session\\Sh('#[^a-zA-Z0-9._-]#', $value      
           hasaramebee;
   lic function setLocale(xists($name, $thilass Envi'it, $param.   {
  Repository', 'config' => 'Ilon handle(Symf public $request, $type = HttpKernelIn       retu_DIGEST'] = $authorizatio
        return self::$httpMethodParamexists($name, $thi            } elseif (']' === $c= null, $deep = false)
    {
        if ($this !== ($xists($name, $this->attributes)) {
  ie($key)
    {
        returnLognull($t
clas   privateD'));
    }
texcss'blic function getContent($aurn $this->name;
    }
    public functireated($lifetime = n fir   {
        $timeateFromFo
clas    prageKe
     dex =ve('IIS_W     $this->attributes =& $attri     i new \ArrayIterator($thisAUTHORIZATION'];
            }
(preg_match('{((?:\\d+\\.){3}\\d+)\\:\\d+}}
    st, $attslirequ   $authorizatbstrER_CLIENT_IP])));
        $clientIps[] = $ip;
        $ip = $clientIps[0];
        foreach ($csByArgumtribu  if (null === $currentKverBagon share(Closure $closupublic function zationHeader) {
                i private $qu  $t      return $thiss::checkIp($clientIp, e
{
    const MASTER_REQU'add   } else {
 catch = true);
}
name        }
        name];
            unset($thition isMethod($method)
    {
  Badon getsten resolved($on geter($;

use ] doeme;
  aseMiurn isset($this->resolved[$abstrac   $value = arrayt('concrete', 'shared');
       , $concrete = null  static::initializname]);
        }
        return $retval;
  ;
    }
    public fuvar_ex  $dr()
    {
        }
        $thitern));
        }
    public tions = arronst VERSION = '4else {
                $parts = exploisset($parts[1       return $iutes[$parts[0]] = isset($parts[1]) && strlen' => 'en-us,en;q=0.5', 'HTTP_ACCEPT_CHA  $parts = explode('=', $bit);
          is->charsets     $attributes[$parw new \InvalidArgumentExcepti:CREATEComponent\HttpF   $currpHost) === 0) {
      ts($name, $this->attributes);
    }ey;
   }    public funclity : '');
  public function resolvedag implemen      $this->ba    public funcabstraciders( $thie(ex0rder) {iders(= $t = 2e) {
         eated(   r5) {
         eturn $ = 3e) {
         fetim = 4e) {
         lic func = 5e) {
         
     }, ('/[,;=]/', $vEATED];
  = 6array_keys($thiPIe(exequestClass, 'createFromttributes;
    100pace  $thi', retpace = $talue5
    {eated(',%s="pace eturn $',me, pace fetim',, arpace lic func   } $this-
    ',));
n $thiATED];
 Request $request = protect9._-zo\Eves->server->get('QUERY_STRING')) {
  lse {
  set($keray();
    prn isse set($key, $values, $replace = true= $path[$i];       $th {
        iis->instlity;
    0;
    private $attributefilename = basename($this->server->get('SCRlues)     $this->qu'Session storn $this->quuality;
        rtUri = $requestUri;
        if (false !== ($pos = strpos($requestUri, '?'))) {
            $  {
        eturn $requestUri;
    }
    return array_keys(uh'])fs->cacheC     $thue instanceof UploadedFile) {
         pop'/', trim($file, '/'));
RIPT_NAME');
    $th 0, $pos);
        if (!arr
               You  }
ed tonull {
  y($q->booes[0]) ?   }ck       foreach ($this->cacheCoemValue
    }
    public fu                   throw new \Inng'))->:^\\[)?[a-zA-Z0-9-:\\]_]+\\.?/',     $this->quc function hasAttribute($nturn issetes as $key => $alias) {
     ) {
(Requeste;
         0, $pos);
        if (!array_key_exists($root, $thisturn issestLocale($   }
 (Requestsax = c($co orquery, 'egista   }
nvoke '&');
TP_X .ode('=', $bits as $ke$prefix, '' giv{
        ceof Closure;
    }
]);
    }
    peturn $thimeStamp = time();
        $this->meta[sepopturn isset(       return isset($this->eturn $thies[$name]) ? $this->attributes[$name] : $default;
    }
    public functioeturn $thtributes()
    {
        return $this->attributes;
         }
    tch = true)
    {
        $respoturn $thiion session()
    {
        if (! }
    public function getIndex()
  addqs =ion;ity = 1.0;
    private $index = 0;
    private $attributes = ($this->attributes[$name]) ? $th
        ifng'))->at($this->attribute'ph prostderutesed $meth $thigetContent() can only rn $it $regispublic func);
  ract) }
    public funcattribuiddlewaresRVER;
        if ('cli-ser     $thin __construcisset($t';
                }ion countisng'))ared);
     $thisces()

}
namelse {
              ms);
    }
  rray(), array tract)
    {
        return $concrete  if (str_is($    {    ($this->ite->path());
        return array_values(array_filter(   return public f', 'url' => 'Illuminate\\public fount($trn $}
   Zone(base_     }
_public f    })_AUT'UTC          $lang =       } rettes;
    }alue = $, fa= $_SERVER0;
    prlue;
         fe;
   P_X_et($value)
        {
  _is->falue)
    turn $'chann($value)ilename = asUratIGINAL, fa$this;
  p;
    }
    public U.uRE))printf('%.6F',       $truncatedE)));
    m;
       eaderllumic f => 'HTTP/;
       is->
   $itemencodedPrefix($str {
        if (ieturn $thisng [     $ite) {
            $th  retur = count($codes)     $ite = ar ret'')) {
              while= array();
    attribut[($this->ite]_IP])$loca(Accepd) {
            uasort($th$this->langu sort) {
            $ms);
    }    return f segment($index, $default = null)
    {
        raddDeshRstorageKeprivate $index = 0;
    private $attributetender = $this->g   retu));
    }
    1.0;
    prattern)
;
        }
    }
    public fdd              return $a->getIndex() > $b->getIndex() ? 1 : -1;
                }
      = $t     return $qA > $qB ? -1 : 1;
            });
    Non re    $this->sorted = true;
        }
    }
}
namespace Symfony\Component\Debug;

useeated(     return $qA > $qB ? -1 : 1;
            });
    W(array    $this->sorted = true;
        }
    }
}
namespace Symfony\Component\Debug;

useeturn $     return $qA > $qB ? -1 : 1;
            });
    me, $    $this->sorted = true;
        }
    }
}
namespace Symfony\Component\Debug;

usefetim     return $qA > $qB ? -1 : 1;
            });
    C    {
     $this->sorted = true;
        }
    }
}
namespace Symfony\Component\Debug;

uselic func     return $qA > $qB ? -1 : 1;
            });
    A  $t    $this->sorted = true;
        }
    }
}
namespace Symfony\Component\Debug;

use
    h;
    public function __construct($debug = true, $chattributt = 'UTF-8')
    {
        $this->debug = $debug;
        $this->charset = $charset;ATED];
      return $qA > $qB ? -1 : 1;
        eturn php_sapi_name);
  ers->remove('X_ORIGINAL_URL');
ovideed $methoEG_SPLI  return $old;
    }
    public function haimplode(',',    }
    public function cookie($kEG_SPL[lSafeHders->get('CONTENT_TYPE'));
   }
    public function s);
   " func
class. '"            ret,atio    rof:priva   }
    , est, $attstan       if (null =   unset($server['HTTPS']);
 his->failSafeHandle($ex  return $old;
    }
    public funtoon get($->meta[self::UPDATED];
    key]);
    }
   imeSta&&');
    (__CLASS__, $coontai   puupp   tr    {
        return iit($requeions t0])) {
            ob_start(array($thispublic function __set($key, $clas');
        $requestOrder = prpublic fuMemoryException) {
        return $this {
        returERVER;
        if ('cli-ser    {
       isset($this->items[$value]);
    }
    public fu $a->getQuality();
     rtrim($baseUrl, '/');tedProxies()
    {
        return self::$trustedProxies;
    }
    p];
  ity = 1.0;
    private $index = 0;
    private $attributes = arisset($this->caughtBuffer[0])) {
            ob_start(array($this, 'cleanOutput');
class M        echo $this->caughtBuffer;
            $caughtLength = ob_get_length                 private function $qA > $qB ? -1 : 1;
            });
 reshR    $this->sorted = true;
        }
    }
}
namespace Symfony\Component\Debug;

use          return $qA > $qB ? -1 : 1;
            });
 ic f    $this->sorted = true;
        }
    }
}
namespace Symfony\Component\Debug;

use Symfony\Component\HttpFoundation\Response;
use Symfoion reent\Debug\Exception\FlattenException;
use Symfony\Component\Debug\Exception\OutOfMemoryException;
class ExceptionHandler
{
    private $dee(arrivate $charset;
    private $handler;
    private $caughtBuffer;
    private $caughtLength;
    public function __construct($debug = true,e(arrayrivate $charset;
    private $handler;
    private $caughtBuffer;
    private $caughtLength;
    public function __construct($debug = true,eret = 'UTF-8')
    {
        $this->debug = $debug;
        $this->charset = $charset;
    }
    public static function register($debug = ttribut = 'UTF-8')
    {
        $this->debug = $debug;
        $this->charset = $charset;
    }
    public static function register($debug = t
           $handler = new static($debug);
        set_exception_handler(array($handler, 'handle'));
        return $handler;
    }
    public f
    {
         $handler = new static($debug);
        set_exception_handler(array($handler, 'handle'));
        return $handler;
    }
    public fuion setHandler($handler)
    {
        if (null !== $handler && !is_callable($handler)) {
            throw new \LogicException('The exceptioreatest be a valid PHP callable.');
        }
        $old = $this->handler;
        $this->handler = $handler;
        return $old;
                  r must be a valid PHP callable.');
        }
        $old = $this->handler;
        $this->handler = $handler;
        returnmake($abstra '');
 lf::$trustedH';', array_map(functio $position + 1;
                    $class = $this->abbrClassrn $headers;
    }
}
n        $content = '';
        if ($this-rn $headers;
    }
}
nor could not be found.';
                brern $headers;
    }
}
n->getStylesheet($exception)), $exception-rn $headers;
    }
}
nheet($exception));
    }
    public functiorn $headers;
    }
}
n    header(sprintf('HTTP/1.0 %s', $exceptirn $headers;
    }
}
ntanceof FlattenException) {
            keys($file);
                  $this->sendPhpResponse($exceptiorn $headers;
    }
}
n
    private function failSafeHandle(\Exception getPathInfo()  $string = $thComponent\HttpFoundation\Seult = null)
    {
    reture . ($this->quality < 1ll)
    {
        return Interface, TermAnterfacng'))->         $supHost) === 0) {
        if ('' != 
class MATED] >= $this->upd.= '?' . $thubest $rllbacks = array();
  verBageturn $heality = $quality;
   l === $this->baseUrl) {
            $this->ba{
                   ncti = str_repla    public function offstion ha  }
    public function cintf(' inrintf('       }
        $this->caughtBuffer = unction n sortact])) {
            $thlse {[ser_fun] >nction ($();
        }
        $this->caugattribBract]     } else {tected $parameters;
    pub;
     ys) ? $ sortpos = strpos($requestUri, $b\Exceptio  foreach ($attributes as $name => $vages[]       $this->add         $this->quality = (double) $value;
        } else {
            $this->attributes[$name] = (string) $value;
        }
        return $this;
    }
}
namespace Symfony\Component\HttpFoundation;

class AcceptHeader
{
    private $items = array();
    private $sorted = true;
    public function __construct(array $items)
    {
       rn self::$trustedHeaders[$key];
    }
    putem) {
            $this->add($item);
        }
    }
    public static function fromString($headerValue)
    {
        $index = 0;
        return new self(array_map(function ($itemValue) use(&$index) {
            $item = Accepnent\HttpFoun(isset($trace['fil'%l'), arradCallbacks = array();
    , arra->middl, array($tra    $headers['PHP_AUTH_DIGEST'] = $authorizatio   $this->meta =& $array;
\'[^\']+\'),         ._DELIM_CAPTURE);
        $vaa, Ariaation\Session\Storage;

usenction fullUrl()
    {
        $que, sans-serif; (FlattenException $except           
        }
      = sprintf('            sh()) {
            
 erif; color: #333 }
            .sf-reset .clear { cl      ] = $this->meta[self::UPDATED]();
        }
        $this->caugsetB = stn->a= stInterface
{
    public frace['file'], $trace[    $headers['PHP_AUTH_DIGEST'] = $authorizatio  .sf-rArgumentException(sprintf('Malfo], $trace['line']);
            __url == arurn array_key_exists($key, $this->   if (strst}
    public feControlDirective($key)
    {
      path'] = '/';
        }
        switch (str{
        $this->meta =& $array;
        if (isset($array[ $first ? $default : s']));
           Interfat class AbstractProcessingHandler extendnamespace \Suppor
{
    public function hSuppo(array $record)  pro   proctedif (!$this->is\Supping(array();) protected tic return false;rotected }rotected array() = c $regiplluminRray()alse;
   ($class)
 array()['formatted']ss = statigetF (statir()->h (stalass($class);
       $regiwritelass($class);
     unction load ==s = statibubbld($clas   {
 protected aespace  static $d$path ies = array();($clas    requirstatic $dc::normalizeClies = array();
    protected stat= static::normors public static fforeachatic function norma as $tion norm public static f       $class call_user_stat(== '\\') {, return true;
 class)
    {
        {
     unctionarray()        }
namespace Monolog\\Suppor;

usp';
    }
Loggubliphp
naStream\Support;

class ClassLoIlluminate\Suppor    prot   requir$s
    rue;
           $urlrue;
   ivate $errorMessagd($clas  static::$filePermissionered = spl_autoloaseLockinge\\Suppected static $d__construct($regist, $level = tion r::DEBUG, $s)) {
 = true,ssLoader', 'load = null,    }
    pu =n load;
    protected parent::ddDirectoriesries)tatic::$dass);
     statis_resourcees($dire  public static fexists(registss =registered =\\',  elseon remostr= falies($directories = null)
    {ur)
  if (is_null($directoriepublic static fthrow new \InvalidArgumentExcepic $('A 
      must either be a veDirect or atoriing.'ass);
        {
       $regi = array_unique(asLoader', 'load'));
 ries = arrayge(static::$   }
    public s       ected static $dclose(;
    protected statemoveDirector
    {
       public static ffse CloctionClass;
us   public static function g
        rray                requirath;
                return t
    protected statis;
use ReflectionClass;
use ReflectionParastatic $regiurl
            $class irectories Logicatic::$direM 'longtories, url, thetories, can notrectopened. This mayrectcaused by a prematurlResll touse Clos}
    public lic static funnull)
    {'\\Illuminatotected $resos->boundset_'\\Il_irectoreturn 
class, 'customE\\Il\Suppor')ted function re)
    {
        f  pr
class CvingC'acted function restatic func = array_unique!=tected
            $class @chmod{
        retction getDirectories(ted function resolvable($abstrestorestract) || $thi isset($this->bindi $instances = array();
    protected $aliasayAccess
{
    protected $resocted $reboundCallbacks UnexprequiValueatic::$dirsprintf('Tllbacks = or Load "%s" coulday();
    prot: ' .tract)
    {
       blic func;
  n str_replace(array('\\', '_'), DIRstatic funcectories;
e ReflectionParamlock
class Contain, LOCK_EXer implements ArrayAcf$path =extractAlias($(}
    )    foreach (static::atic function r       list($abstract, $alias) = $this->extractAlias($abstrUNer implements Arr        (arrayainer;

ustract);
    }
   ($codfalsmsgray();
    proteract)
    {
        repreg_replace('{^
    \\(.*?\\): }', ''nceof COR, $class) . '.php';
    }
    public static function register(RotatingFile\Support;

class)
    {
              static::$Load) . e\\Support\\Classmax    s>rebound($abstractustred')->rebound($abstracnexected oad'));
        }
    $this-as $die\\Support\\Classdat  return functatic function addDirectories  $this-nceo);
     = 0ctories)
    {
        static::$directories = array_unique(array_merge(static::$directories, (array) ction getDi) . )
    {
his->reboueters);
         $meth(int)act);
        }
eters);
   e($abstract,otecies DateTime('tomorrow
    public s);
        };
as $di = '{}
    pu}-{para}'abstract)) {
    parameters>binY-m-dcrete, $sha$directories));
    }ectories (!$td    ) . ()ctories)tatic::$dies = array_uniqu_merge(static  }
     minate\Container;

use Closure;
use ArrayA$directose Clostic function rctorATOR . $clasrotected frectories = null)
    {rted f       reture = $abstractected static $dset = null)as $diete) {
   as $di, $parametersrete, $parameters);
        };
   }
    {
        return func$shared);
        }
    $parameters = arrries = array();
  act, $concrete = null)
 abstract)) {
      {
        rlved = array();
    protected $bindings = array();
    protectedrrayon ($container) use($closure) {
            srotected f = !Load_exists{
        rer implements ArrayAcInstances($false)
    {
<    foreacparatime']      if (!isset($this->bindings[$abctorblic function bound($  {
        retur   {
     $directo$path = $directory .lved = array();
    protetatic $oete, $parameters);
   re $closure)
    {
        $this->bind($abstractfalse)
    {
        if (!$this->bound($abstract))stat0on ($containe);
     public static functio($class)
    {
      lo     methglobtract, $conGlobPdirecn(        if (Instances($      $me>=bindn  }
abstrac  public static functio($class)
    {
     usorosure $clos,    }
    ($atati public static functionstrcmp($b, $aer implementis->bind($a)
    {
 ies =_slictorstract]['c        $this->bi0] =Loadprotected $aliases =is_$patableete) {tract]);
    }
    puunlinklic fun str_replace(array('\\', '_'),lved = array();
    proteconcrete = null)
 ete, $parametersLoadInfo$thiathinfoings[$abstra) . is->bind($absrete = null) =ontagetClosurs->isAnd($abstracte);, $conc), s->isAl>extract['        '], paratract, $ure($contai    stract]);
dir    $b . '/ete = null,          retu      return fu!empt$abstract]);
;

clload']irectories = null)
as($abstract,.= '.ete ={
            $this->($class)
    {
     ECTORY_S;
        }
 losure($this->instances[$abstra    }
    protec$alias) = $this->extractAlias($abstract);
            $this->al;
  , $alias);
        }
        unset($this->aliases[$abstract]);
        $bou'*aliat);
        $this->instances[$abstract] = $instance;
        if ($bound) {
            $this->rebound($abstract)rray(  }
    public function alias($abstract, $alias)
    {
    ;
  OR, $class) . '.php';
    }
    public static funcas $directarget, $mInterface;
i{
       \Suppor {
          protected static $dstered = fa      return true;
  ected static $directories = array(); $method) {
            $tarBatche) use($targets($instance);
        })pushIlluminoe inallbacktion rebound($abstract)
op {
       ($instance);
        }))) {s $directthod)
    {
       $h (statir($instance);
        })es as $directo;ass) . '.php'Illuminate\Support\Facad    gister(Appt;

class
    p         static::stracll_user_func($c    pAc       f
    protected unction'appcrete, lass) . '.php'e);
       atic::$dilic stareboundCallreturn $thatic::$diDisplayebinding($abstract, function ($appdreturn(atic::$di $etic::$di$this, $instance);
       reboundCallbacks[$abstract] staSymfony\Component\Debug  $abstrac    publis($abstract);
        HttpFoundract,\JsonResponad($ister()stract return a implef(sts }
        return array();
    }
    static::$rstracte\\Support\\Classunctio$thi = array()) use($abstract, $concreset($this->insta       if    re($concrd}($concrete, $parameters);
        if;
       if ($thi          st, $parameterable($concrete, $    }
    public functn make($abstract, $parameters  }
    public static funce, $parame public static function    $this->insta     }
 '\\Il' => $parameterries luminat
   
    abstract, $object);
     $thilinresolved[$abstract] =Line(trac500er implements ArrayAc  {
               $o->createeResolvin$parameters = {
            return $this->reboundCallbacks[$abstract] staWhoops\Rulias($abstract);
                return s->instancct) && isset($this->bindinKernel  $abstracindinatic::$di {
        php
nalash($;
        }
        $concrete = $this->getConcrete($abstract);
   wash($ ($this->isBuildabunningInConso{
      ay()) use($abstract, $concreRunt]['conc    protected functhis);
            $this['concters['concrete']          stprotected functters)protected function mi     if ($this->isShared($abstract)) {
            $this->instance$ct)
uract,parameter instanceof tract;
            }
  ?ract, $object);
S     Code() :protabstract)) header     return $concrete($this, $parameters);
        }
        $reflHoncretw Refs->isActory . DIRECTORY_      if (!isstrpos($abstr->irectoatic::$dir$parameters,        ,($concret>bindings[$abstract])) {
            if ($this->Closurt])) {
);
  missingLeadingSRefletic $Ftatic $])) {
e);
        }
    }Conpace s$abstractPre$dir  {
        ) {
                $abstract = '\\' . $abstract;
            }
   s($abstract);
        if (isset($this\Fatal();
        if), $ies, $para        ($abstract)) {
          r);
        }
  e\\Support\\Classplai  return an function ($c, $pf (irn $reflector->newInstanceArgs(e\\Support\\Class || $thractet [{$concrettion getDependencass parameters, arsingLeadingSlash($abstract)
);
        }
        $dedependencies, $par, }
        return array();
    return $refleeter) {
            $dependencyArgs($instancelosuf (i($this-his);
            $thisarameterotected funcct)) {
     return $refle     return $reflector->ves)) {
        itives[$paramArgs($instances);
  ke($concrete,;
        }
  ters);ndencies, $parameter    }
    public functregisrect$environf(sthis);
            $thislse {
   (!$concrete is->bind($abstracts->resolvet($this->insta       return fu             != 'tes);
 'losure) {
            stse {
  Shutdow      return (array)_array($abstract)) {
          s->resolveClass($param
    protected $abstract) || $this->isAlias($abthrow nks($a            {
        if ($parameter->isDe   }
        retuilable()) {
        tic::$direturn $parameter->getDefaultUncaughtatic::$di();
        }
        $message = "Unresolv(ReflectionParamereboundCallbacks[e {
  _sReflect;
   :$dirarameter->getDefault(Reflect();
        }
  ected static $directoValue }
    pubmed = falsfunct=);
       ethod =contex    et [{$c $this->instances[$atract)re    = fa) &tories) public static firectories();
        if(>make($parod = $absarametetClass(object;
            if (is_null($objectthrow new BindingResolutionof Closure) {
  resolveNss = stati$insCtract\Supporssset($this->bindint]) || issetrrayalse>insta  public static function= static:  }
   if (!iss   {
    cted function getConcrete($abstracn make(new BindingResolution$parameter);
            } elaringClass()->getName(    }
    }
    protected fubstracthrow new BindingResolution->sendis->share($closected static $directo(Reflectd function extract'\\Il = tract)get_last       return fuameters)
 '\\Il  public static fexpace ks[$abstd[$abstract]) || isc $registies, ($typunction instance($abst  $resolver = $tn resolvable($abstract)
 throw new Bindin    ies, $para          rre $c retureter->getDefparameters;
   arameter)
    {
        if ($parameny(Closure $careboundCallbacks[$abstin_s->isAlisolvis->isAE_ERROR, E_CORbackArray($obMPILbackArray($PARSEter)
    {
        try {
           d funct    }
    }
    protected frameters as $krsByArgument(array $dependenc,r->nam
        }
        $message = "gCallbacks);
    }
    protecte$fromunction budirectories, (array) )
    {
        endencies0] = || $thprotected $aliases = array()irectosncies[$key] || $th, $parametersract]);
    }
    pu);
 ins->instances[$abctories))  return $concrete($this, $parameters);
     ract]);
    }
    pubnsta    return $c$reflector = new  (isset($this->bindi= $this->bindings[$abstractlectionClass(n resolvable($absttry            $class = stion keyPa || $thi
    {
      nstance  foreach (e {
            $c
    (  $abstrac $d = $this->bindings[$ad === true;
abstrac (stancies[$key]-ted function resolvable($abst;
    se      {
     && ameters)
    {
        foreach ($pa DIRECTORY_SEP>instancisset($this->er $parameter)
    {
        if ($parame        unset($parameters[$kof Closure) {
  n make(this-> {
         ?  } elseif (is_null($de:ies[] = $primitives[$ptory . DIRECTORY_return $th         sset($this->bindingse;
            }
        }
    }
    pubetConst;
    }
 tion isShared    protected funcl($const
      ull($constructor))blic funcctory . DIRECTORY_   {
      ries NumberOfParamet = "Ta== 0 ||      }
 in{
    {
      tion isSharedabstract], $this->aliases[$abstrstancull($constructor))lic function forgetInstance($abstract)
  pes()
    ters);function forgces()
    {
abstract)) sset($tharamees()
    [0$abstract, unction!n $this->ries Chp
n()   $tn offsetSet($key, $vgistIcrete($>instances[$abstract], $this->aliases[$abstcrete instanceof    {
          }
    public static funcaramerectories = null)loc    {
  $ect);
        $nsta inrete =->bind true;nsta:   }
    pu  }
  ]) ? $this->als[$abstrValue);
 return $co || $thcrete =       $cted function getConcrete($et($this->bindings[$key], $.])) {
    protected static $d'\\Il  public f$instance
    protected    retunshifstract, $endencied fuinstance = $theturn $this[$key];
   
   ed fun public function __set($key, $val     }
      rs[:$dir$value;
abstract], $this->aliases[$abstey => $value) {
          object, $this->globalResolvinresolveNonClass($key => $value) {
            if (eter);
            } elprotected funct->reboundCallbacks[$abstphp_sapi_ull)
     'cli      return $this[$key];
   setif (i(ndencyme, $primitives)) {
                $depend           return $this-> }
    }
    protected fRoutetion getReboundCallbacks($abstract)
    {
        if (isset($this->reboundCallbacks[$abstrrnterr])) {
            return $this->View\Enginproter->getConstruphp
nacts;

Resolv>getDependencies($depenace
{es($parameters, array $primion prep= array())
    {
        $dependelse {
      s;

,getConstblic funcr $dependencies[] = $this- prepar[minate\:$dirr;
use I$parameter);
            } els prepuminate\sure;
use ArrayAccess;n getBuilder;
use dluminate\     foreach ($parameters as $knate\Container\Cothrow new \InvalidArgumer;
use Illuminate\CoIlluminate\ntainer;
use Illuminate\Filesystem\Filesystem;
ur($class, 1);
     acade;
use Illuminate\Eve($class)
    {
     irectories = array_diff(static::$dir"cts;

 {minate\}ay();f   r.">bindings[$abstract])) {
       ontr;
];
        ontrFindebinding($abstract, function ($appfind($view($instance);
        })addL      $clos     $terface;
use Symfony\CompoN . '.php($) . '.php($mestantion rebound($abstract)
repene Symfony\Component\Debug\Exception\FatalErrorExceptaddE   $this   p  $this$this, $instance);
       el\Http
            restracystem
use SymfonnsePrepa    rface;
use }
        $rface;
use Symfony\Componenete)
    {
   crete'];
    }
   as($crete'];
    }
   leInes($parameters, array $primitstans($parameters, array $primifony\Compements Htt'blade.phpte);nsePceptionDirec HINT_PATH_DELIMITERr->g::crete,         $dependencies = arrause SymfontpKernefireCalFoundHtotingCallTerminableIntthis-ete, $parameters);
       nctioKernel\Exceencies[] = $ndHtmake($dHttpExceed function getTerminableirectories = null)
    {TerminableInttdownCallbabject;
            if (is_null($objectrminab     te\Config\FileLoader;
use Illumis App[y();
vents\EventServiceProvider;
use array();
   throw new \InvalidArgumentExcephasHintract(stae Symract, $trimay();
  protected $deferredServices = array();
   ss = statirmin SymdPathontray();
 cted function getConcrete($abstracst $request = null)
    {
In   $stp\\Reesolver($ray()re) {
            $value = functi {
        $this->regis
    protected listComponent\DebuleInt$directories  Symfony\Seg     ->registerBaseBinrameters as $k        $thisleInesolver($imple();
  '.phpxception$alias] = $abstract;
    }
    {
        return forwof Closure) {
      retuck)
xpl newct)
  ::   const VERSION = , >registerBaseBinstat, Closu$this->i)    2  if ($parameter->isOptionr;
use Illuminate\Config\Fiontr [{p\\Re}] has a    array ) . er;
use Snew \InvalidArgume!r;
use IllumiteFromG$this->i[0]vents\EventServiceiceProvider;
use Illuminate\Config\FiNokey)
ias($ defin);
 oreSerg') as $nam}]    foreach (array('Eve   publi$this->iceProviders();
        $this->regis    $this->regiseServis as $callback) {
      ss(Ref)is));
 [0] ==athlizeClass($class)
    {
        $keyossibation\Releurn forw), $container);
        }s->bindings[$abstraotect) {
  leIn   $= array(nstances[$c function instance($abstider()
    {Provider]) ? $this->aln resolvable($abstiases[$abstract]is);
    }
    protected function registerBaseServiceProablesLoader;
use Symlias] = $abstract;
    }
 (new RoutingServiceProviobject, $this->globalR   retmap(oncrete'];
fony\Compo u!issProvid return function ($contagetClosure.te);/
   Provid.}
    pufony\Comp($class)
  esolver($downCallbac    public function handle(Rmponent\HttpKernel\Excony\Component\HttpFounray()equestnstances[$key]D:\\XAMPP_NEW\\htdocs\\My Symfony\Component\Debug\Exceony\Component\Htimplemenon registimpletic function remxception', 'Routinlobals'));
rectories = null)implements Ht_merge.";
    teFromGlobals'));ebug\Exception\lic static function gteFromGlobals'));ue;
        $th
namespace Symfony\Compon;
use Illuminate\Support\Contractn startExceptionHandling()
    {
        $this['exception']->register($this->environment());
        $this['exception']->    {ss, 'createFromGlobals'));
    }
;
    }
    public function environment()
    {
        if (count(func_get_rface;
use Symfony\Compo
    protected stat($;
usxs['exceptsearch->instance(e()
    {
        rer\\Ccallback", realpath($vaun
use IllumiTerminable[null;
xception\ExceptionServue)
    {
        $thTerminabletion ny\Compone]);
                $paramet 'Illuminate\\Http\\Re')) as $key => $value) strpohis->regiquest', $request);
        ) > ctionCleturn $this[$key];
    publicSymfon->reboundCallbacks[$abstfunction regn forceRegister($provider, $opt  $thiernelInterface
{
    const MASray();
    eRegister($provider, $opt'Ill    }
    public function regist    {
        if (count(func_get_getce;
use S    }
    public function registres = array();
 ;
use Symfony\Component\HttpFound $concretttpKernelInteluminatProviuse Symfony\Component\HttpKernel\Te);
       Bag $this, $instance);
        }
    nterface Clo pubthis->$thiSerializ ($options         return new $concrete$thi pubeters = $this->         return new $concreteAes =
        }
        $this->markAsRegistered($provrovider = $this->resolvensePrepa        }
 }
        $ider);
        if ,oreach ($o,s as  public functiorovider = $this->resolvestered$key => $valony\Component\Httpmake($pements HttpKernelInterface, inate\'4.2.make($p6';
    protected $booted = false;
ies = aass($provider);
     }
    protected functiurn array_0] =keybstravs->a      if (!isset($this->ss($pro[$valnt()on registse($nbject;
            if (is_null($objectadd($vals->make($p        return $value;
    isUniqueublic function rame) {
                return get_claequestreturn cted function getConcrete($abstri';
    }
    public functioion']->return g        return $valueass($provncrete($thirovider = $this->resolverectories = null)ass($providovider;
 ct);
        }
  ct);
               returstatic function gass($provider);
tion']_recursise Inction loadDefs->make($pdebug']);
  $provider)
    {
         (isset($this->resolr)
    {
        returony\Component\Htass($providon registnction loadDef
    public functiption']return get_clavalue!       $thmake($para  protected funi';
    }
    public function rublictected $bootedCallbacks   return $this-rctedkeyDetect'      return $this[$key];
   set($this-e(array_meey_existsted $bootedCallbacks ass($providmeters)
 is->l  }
    pas)
 inate\ Refosure)
   ublic fufunctioredServices as $s, Closuis->deferctio  }
return ge0] :edProviders[$provider])) {
    DeferredProvider(erredProvider($provider,es[$serviesolvingheck         stance;
        if (edProvkeyt])) {
  lic function loadDef     foreach ($parameters as $ktrans= ne       foreach (et_claProvider(, $valsterBaseBindings($request ?:et [{$concret});
        }
    }
   lic functirvice]);
        }
        $this->register($instance = new $provider($$ay()array())
    {
ack) {
            ction ($key, $value) ul)
    {
 ctories = null)bstract);
 tion']->aay_menction () use($inoreach ($th   $instance-sterBaseBindings($request ?: aed $resolved = array();
    prote);
        }
        return parreturn Keyder($service);
        }
        $thierredServices = arders, function ($key, &unction r          $this-etClosuInterface,$name, $t, ':keycted function reparent::, $alias);
    ct, Closiases[$abredProvider($serv$thiProvider($provider, onProvider()
    {erredServices fireCallbackArray($object,r($instance = new $es[$service];
        iabstractt($this  }
    puabstrac:tract, $            unset($this->deferrss] = truees[$service];
        if (!iss->loadDeferredProvidClass($provider);
        }
  es[$service];
        if (!n forceRegister($provider, $opti      $this['router']->before($callbnction before($callback)
    {
   undCallba($abstract,ure)
    {$bootedCallbacks = array return $ohared($abstracturn $this['router']->after($callback);
 isEound)   {
        return $thsolvingAan  }
    }
    public function ma$thiobject, $this->globalResolving Closnction forceRegister($provider, $tdownCaes[$service];
        , Closu     foreach ($tCOUNT_RECURSIVE) -seArraySessions(Closur  {
        $provider = $thistoider)} else {
            $this->shu     return $i';
    }
    public functiojg($provider, $type = self::MASTER_REbstract)($callbasion.reject', function () use($thi($o::$dimetho
    public function usion _ennstaySessionooted;
  , {
         }
    public function boot__toS       else {
            $this->shu()
    >bindings[$abstract])) {
        }
    }
    protected frBaseion getReboundCallbacks($abstract)
    {
        if (isset($this->reboundCallbacks[$abstrleIn])) {
            return $this->g($provider)) {
            $pRe
use
        }
      protected static $dris->b $this, $instance);
       st as Symfoider)et($ththis->etConstructore);
        }
    }dedProvide$callback;
    }
ontracts;

iacts;

      if ($this->booted) {
            $provider->boot();
        }
      $this->markAsRegistered($provider);
        if ey, ider);
   {
            return new $concrete()s->bootedCallbackey, nyRequest nsePreparBasevider;
    }
   et($th,ull)
    {
ony\Component\HttpKactorf ($this->isBuildainate\tpException;
class Apn function ($c, $paral\Exception\NotFoundH';
    protected $booted = false;
  = witnse = wit,arerInt$dependencyinate\FostClass,rovilosurea    } catch (BindingResolu $this->creis->andle($req\laravel\\frame= array(abstract)) {
    inate\lewargetStackedCct)) {
     respons   p = with($sta$shared);
       {()
   ncrete($thiider);
    }
d',        }
   :      $thi    $respoeter);
            } elstion  public function erredProvider($provider,);
  ->instovider;
u
use $coreturass);
        tion keyPption']$value;
   }
$value;
Alias($aReject);
QueuAlias($name)
 Builder())->pu->flushS$constsIfDonter
usetion );
    }
    public tion ke?:is);
    }losure($this->instances[$abstracis->mergeCustomete, $parameters);
    rgeCustoincr     r $staameter);
          ues($middrsByA
   s
   bstr      returneject);
        $tt($krgeCustomMiddlewaresnshift($parametdeware);
            array_eturn $tddlewares as $middlew}
    public functionlewares(stract, Closure $canv= null)
   lluminate\\Cookiminate\Filesyste     oncrete'];
eateNeath.{env    foreach ($parametersenv$refle   }
    resolver, $closure);
        foreach (array_ex$class, $parameters) = ar     return $renate\erDeferregister($$abstractga $diDataected fun);
        foreach (array_r($this->mi
    {
        re
    {
    tion']->setDebues($middeflehared     \\Guard', closure) {
         m['clay, $value) use($name) {
         ngs[$se($nncrypter'])-nyRequest ract]);
    }
    pub['clet_class(STER_Ray())
   nce)
    {
        if (is_arrayct)
    {
   'cookie'])->push('Illuminate\\Swith!$this->TER_RErredProvider($provider;
    }s->isAlent::ectories = null)
    {['class'] != $class;
     equestance->boot();
  $shared = false;
   c function         $this->on markAsRegistered($provider)
    {
        $this['events']ne($this-getStackeies = a)
    {
        $sessionRejecteturn $this->bt();
       
        });
  makeestClass,ion h   }
    public function boott();Values     $this        return $valuets']->un$provider));
        $this->serviceProviders[] = $prouest)
    {acks($s
   ts']->un->loadedProviders->runningUnitTests()) {
               n $this->prepar    dedProvideron registes']->untsterBaseBindings($request ?: $thin forceRegister($provider, $opti = witpublic function finish($callback = with($stathis->getRegistered($provinate\iddleware($class)
    {
        $this            unset($this->deferr Symn ($p) {
            $p->boot()andle($req      unset($this->deferr
            return $y)) {
         'cookie'])->push('Illuminate\\Strue);
   }
    public function register($ {
        $this->finishCallbacshReqProvidel\\vendor\\laravel\\frames['session.rejeturn $this[$key];
   offsovidif (!$thi')) as $key => $value) {
          if (!$this->bootedion handle(quest');
    }
    public fuGeferred else {
            $this->shu throw $e;ce('request');
    }
    public fuSeferredProse($na($abstract) && strpos($();
               }
    public function bootlic fuUargs);>finishCallbacks as$args);
    } throw $e;   }
    public function boot&_{
          foreach ($cal $callback) {
            call_user_func($callback, __ {
     ponse);
        }
    }
    protected function fireAppCallbacks(array $callba__ption']>finishCallbacks as $callbption']->regillback) {
            call_user_func($c_   {{
        foreach ($callbacks as $callback) {
            call_user_func($c__$valissethodkedCls()
    ? $_SERVER['argv'] :starts_tected (!$valu'tect'     foreach ($parameters as $ktectesnake_case(suespa  $value =4tract($key);
    }ception\ExceptionServiceProviderBadM(!$vaCalle\Config\Fises()
eSer (!$va}] doespath(terEv on leIns['app']));
    serviceProviders, function ($p) {
            $p->boot()nction booamespace Illuminate\Support\Contracts;

inte;
        }   protected fProviderClass($provider);
est);
    }
    public functioooting($callback)
    {
     acts;

intephp
naPhpleEnvir}
        $cClosure $callback)
    {
        $this['events']->listen('illuminate.adleware($class)
    {
        se($aterequest);
ntenancee => $provider) {
            $ttpException($__message__ion h $sessionReject obLies)
  ob{
    ies)equest($requob_
    equest($requack;
    e, null]) ? $thiss[$abstract]) || $includeage,ession.reject'bstract)
    {
        return $concret     }
      ontrncies[$key]-    }
    sterBaseBindings($request ?:lst';
lic funcleatected funct], $this->aliases[$abstract]) $e);
        });
    }
   
    protected whunctcallbackction m >
    }
             $thisob_endk)
    {eption\ExceptionServicePro$onyRequesss) . '.php'&& isset($this->bindings['\\' . esponse;
ih ($thi    proce
{
  TTP_CONTINUE = 1ectionClFatalErrorESWITCHING_PROTOCOLS $e) 1se($callback) {
 PROCESSING $e) 2se($callback) {
 OK = 2 use($callback) {
 CREATED funcser_func($callbackACCEP
    {
 });
    }
    publNON_AUTHORITATIVE_INFORMATION func3 Filesystem(), $thiExcepENT func4se($callback) {
 RESETtEnvironmentV5er_func($callback,ARTIALtEnvironmentV6se($callback) {
 MULTI_STATUcall207      return new FLREADY_REPOR
    {
 8se($callback) {
 IM_US    {
2ew Filesystem(), $this[PLE_CHOICEcall3 use($callback) {
 MOVED_PERMANENTLYifestser_func($callbackFOUN   {3ew Filesystem(), $tSEE_OTH= '4.3   public function geT_MODIFI    {3VariablesLoader()
 USEk, $Xositorn new FileEnvironm   {RV     reew Filesystem(), $TEMPORARtionDIRECnmen3]);
    }
    publiroviderRepo)
    {
      rRepository()
    BAD_REQUESnmen4 use($callback) {
 UN'path']Z    {4user_func($callback,AYMEllbacQUIR  retur});
    }
    publFORBIDDE  }
4etLoadedProviders()
   , $manif4VariablesLoader()
 METHOD)
   ALLOW  return new FileEnvironmull($ileLoABLn $e4rredServices(array iders['patENTIC;
   rvices[$servic);
    }
    publiisDefer_TIMEOUrredSerRepository()
    CONFLI
    409se($callback) {
 GONs = $1use($callback) {
 LENGTHrvices[$servi1ser_func($callback, ECONDItic::FAILlhost')});
    }
    publ functiorn sTY_TOO_LARGconfig  public function  functioURIRVER);O     41turn $this->loadedPNSUP getPr_MEDIA_TYPconfig }
    public functiDeferED_RANGE::$reSATISFIlass = $1ew Filesystem(), $EXPEC . 'y($url, 'GET',);
    }
    publiI_AM_A_TEAPO   {
1rRepository()
    UN, $e);
lass), $_SEt('c2});
    }
    publabst  retu2  public function url, '_DEPENDENCc funcariablesLoader()
    { setblic_WEBDAV_ADVANCED_COLLECd_stSn fos[$s    POSAL     n new FileEnvironmUPGRADErvices[$servimanifest = $this['cers = array($);
        $trRepository()
    VER)MAN$serDefercall42       $url = $thi functioHEADER_FIELDSRVER);
       3ser_func($callbackINTERNAL_c funRackArr      return    static::$reIMPLEedSe    {5ry(new Filesystem()ion GATEWAc fu5st);
    }
    publicRVICE   {VAILlass = 5   public function rtisan'on setReque5VariablesLoader()
 VERS_stameter
    }
 ', 'an new FileEnvironmVARIANl($cSO_NEGOTIATE     ERIedSenfig'5rredServices(array INSUFFICIdSerSTORA     5s;
    }
    publicLOOP_DETECate\\AutrRepository()
    
   EXTt('ae\\Aug']->get('app.url',NETWORK return static::$requestCla5);
     Mainten$concreters, array $primieject);tpException;
classerload'));
        }
        = ne'encrypter' => 'IlluminTexate\\Cookie\\Cookicharses = array()) uct)
   rypter', 'dements Htt100bstr'y($s  if',l_uste\\ESwitchctedProtocolepar102te\\EIlluminate',unctte\\EOKesystcher',C     desyst\Fileset($pate\\Htm3te\\ENon-Autho    tive uminate\\Htesyst4sh' =>  y($stacesyst5te\\EReset'html' => 'Il6Filesyartial'html' => 'Il7te\\EMulti-ector esyst8\\Formlready Ron $ete\\Ht2der', IM Uste\\H3tem', 'e\\Trple Choiceion\3=> 'IllMonctier',a    lynate\\Files    rnate\ash' =See O $dinate\rface', t Modifi\WriterluminaUse=> 'xator' der', te\\rv\WriteruminatTemporar'logdirec=> '3Translar', 'pagi> 'Illuminat4tem', 'Bad Reques'redircher',Unaluminazte\\H4\\Filesyayes;
 llumiror', 'rash' =ForbiddeerIn4', 'auth.reluminate4Illumineturn $h.reAllowor', 'rder', , 'roBuild pubte\\Huminat\Auth llumenti     $tminate\\Redisranslallumina (!$tou'redir9te\\Evenfli 'redi1em', 'Gon\Rout1cher',Lengthte\\Session\\1\Filesysecondiic $dFaillluminaash' =Manager'Entity Too Lar  {
 41rface'llumina-URIRoutinoilesy41lluminans}
    ed Media TypUrlGeninders\\uminaed Range', 'rSatisfing\\Rout1uminatEset($    {
anager', 'uranslaI\'m a teapo'redi2\FilesUnc::norm('Illnate\\    $ash' =
   or', '2rface'anager Dn;
usenc;
    luminate\\sswoproteWebDAV advte($d col($conststancte\\der)posal}
namder', Upgrad$thinate\\Redi2\Queue\ote\\RemoteMts, $consoleAtore' outiMan'loguminaion\43cher',Manager'sage = FieldsRouting\\UrlG5tem', ' {
  nal Sosurrnal())$envicher',h.reI
       te\\H5 => 'Il> 'IGatewa;
  5\Paginatirvice Unavailng\\Rou5', 'autvironme', 'session5Illuminrror VJar', w\\Facidation\bEnvider', Varian'rouso Negotiates (ch (rif(stal)bEnviuminatInsufficieManStor   {
 5SessionLoop De requienvite\\Sesh.rece;
ud }
     }
    etworkn' => 'Illuminate\\Sessio($instance);
        })ddDirectorieseject);r->getCl         20od =concrete);
        $sessionReject = $thiconcrete);instantiablesage = }
 message);
     ion bound($aon forget$host) {
is->bind($abstractquesctor = new       turn 'production';
  > 'Illumvironme('1.0tract, $closure);
       oncretss = (' if Response($value);
ction';
   if    pr  if (!$thrray_m      if (!$tZone('UTC')']->isStarted()) {eturn $this[$ct)
    {
            $host) {
                if ($this->isMachine($host)) {
         is not instct)
  $host) {
Exception($message);
       his->serviceProviders, function ($p) {
            $p-me]);
   rror/%s      
    $this-Jar', eturn stalluminate\h($v, '--env');, 'dstati
ete = null,concreteblic function ision forget    }
    public function boot__cl retst)) {
                    return    }ction isMachine
        if (count(func_get_argsare(Manager'$r      n_array($this['envoncrete);
eInfo;
use Symfony\derClass($provideuminate\\Htack)
||        $thi, '--env');
     s->isA204ory',ublic function __c    }
        }
  this-$abstract = $th {
      remove(Eventent-y', ' instance()
    {
        return $this;
 remote
    public s$shared = false;
   static {
        if $this;
    }
 ract]);
    }
    pubwn(callabluse Sym            }
    pu));
    }
    publct, Closutects->getSc&&er),imey', chemeAndHttpHostMl()
   e = new $->register(new EventServic function rett()
    {
    urn l()
   tBaseUrl(), '/');
 ic function bindInstallP call_\Databis->registereryStr?: 'UTF-8crete, $sha    public function root()
    {
        return rtrim($thisgetUri()), '/');
    }
    pu'    /html;       r=ete =      re {
            $sharure);
    }
  nv']  {
      s['et()
    {
     pattern 'bstraSEPARATOR n == '' ? '/' : $pattern;
    }
    publ      r;
    }
    public function path()
    {
        $pa? '/' : $pattern;
    }
    p($naim($this->getPathInfo(), '/');
       rnelInterface::MAS {
        if T() user-E  re prot    }
    public function pathd()
    {
        return $this->ge }
    protected functmeAndHttpHisses()
('    ts()
    {
        $seglemote'e;
 '/' : $pattern;
    }s->path());
        reStore;
    public function instance()
   face::MAS     rce('/\\?.*/', '', $this->getUri()), '/');
    }  returctoris $pa));
    }
    public function bindInstallPaths(arraystat{
    iron    meAndHttpHlosure($res('{
      return _null($value = $this->getEnveEnvironment($envir1
    public static fun          OR . $clastrueEnvironment($enbstra'no-cachresotion\Request as $patternisSe- $conol_null($value = $this->getgetUri()), '/'pragma$patis->isSecublic function bound($getUri()), '/' publi ret-1er implements ArrayAccess
{
enonstIEOverSSL $clatibilind) use Symflback = null)
    {
        if (is_null($callback)ametsage = "T
    protected statn $v !=_sturn     foreach ($parameters as ($class)
    {
     n $v !ame]);
   
            return starts_with($v, '--env');
        });
    }
    pted fuh($v, '--env');
   handle(SymfonyRequest
    {
        allPrClosurCvaluider($ract, ) use($nalizeClass($class)
    {
  ey : fquestse($name) {
         ey_exists($$key) nctirete =se($n,n decorn true;
    }
    public funcn bindInstallPaths(array {
            ca
    publicCookitruequestc
        $this['excepsetd func$hos    t()
    {       {
       is->aolOrArray = is_bE $this(!$thlOrArray = is_bshRequOrArray = is_bDomainy));
        isS
   rray($this->inis    Onlte.appisStarted()) {
            $this['session']->start();
    amet  return 
    protected echohis->shutduminate\\Co    public function all()
    {
        return a
}
namespace Illuminate\       $inputurn 'production';
  array_replnstance('Illumstatic $t])) {
 'fastcgi_finish_use Symts()
    {
       l();
        return arError($callbatories)) e);
} publPHP_SAPItion isEmptyStrinuest', se ClOutputBuffray 0ted function '';
    }
    public function all()
    {
        return     }
        ret extend($abstract, Closupubliost) {
 tract)
             retstract)
  mernction getEay_get($i$val publs->isAlon getEnv's, functiohead{
            $this->{"reg isset($this->aliases[$name]);
    }
 rror(fun      {
 (arraector}
    lic objectcode, $mes   $s, function ,tion bgiven }
 getis->$key, arra']->isStarted()) {
   >input(), $thi    as);
    ), $this->files->all());
    }
    public function input(     return else {
            $this->shutduminate\\Cois->instance('request', $rEnvironment($enieJar',         }
    }
    prorts_wit  $thJar', 'encryl, $default = null)
    {
        return $this-  {
        returresponse)
    {
        $this-Jar', 'encry     $this->finishCallbac  }
    protnstance       ted $bootedCallbacks = arralluminate\uest;   retct, $corn aed function getExtend Reqarrayh ($keys as $valueirectories = array_diff(static::$dirme]);
    }
 t($en       rn arion bi   }
 array }
 $this']->isStarted()) {
   ct, Closure $cl   puctories = null)
    {
   }
    );
      self::', 'events' [$this]$cli    if ($this->isValidFile) {
      ack = null)
    {
        es = array($files)SEPARATOR . 
        foreach ($files as $file) {
  rn true;
            }
        }
        return files as $file) {
     pr = null, $default = null)
    {
        return $this-ector = new is);
        }
    }
    publlluminate\\Encry   $input = $this->all();
     r(athInfo() $sessionReject = $thiQueryString\\Database\\Dl, $default = null)
    {
        return $this->    }
 eItem('query', $key, $default);\Database\\Dif (is_null($callback)) uncti publray();
    protected $nyRequest
{
    protected $json;
  0, 'haiter'ate\\or' =questinateublic function __construc load($class)
    {
     equestClass {
        iunctin ip()
Dllumi{
  
    isse'value) return true;
    }$filter) ? $this->{$fit;
    Response($value);
        load($class)
    {
     eturn $this->bisVrrayatl)
       $this->iisFresh    }
    public function bootys) ? $keck) {
            return $callbaTtck)
  on forceRegister($provider, $       $keys = ifalse)
    {
        if ($regi{
        if Last-minder' =is_array($ke$keys : func_gETagic functublic function hasCookie($k(arra
}
namespace Illuminate\Http;

 explode      $this->session()->ected($abstract)) {
       {
     dd      $this->session()->flashInp$keys = is_array($key) ? $key : func_get_args();
   tPectedlush()
    {
        $this->sessieItem($source, $key, $defau }
    protected function retrievon()->flashInput(array());
   lt)
    {
        if (is_null($key)) {
            return rotecttpE  $ke     $keys = is_array($keys) ? $keys : func      $this->session()-rote-r  }
    p      return $this->flash('pAuthce()->replacellFinishCallbacks($request, $respopublic function replace(array $input)
       {
   (!is_)) {
           {
        $thismfony\Component\Htt{
    if (!$tn bind        return $m['ce)), '(!$tz ret {
            return heaprotected function retriev, '/'is->jso     if) {
   'D, d M Y H:i:s  retu GMT  {
        if (is_null($key)) {
            return getA   $($keys as $key) {
          ($getAli $this->input();
  ('A     ($keys as $value) {
   ct, $coction markAsRegistered($providmax(;
  ({
  osure)
    {
   y_get($thiU pubtected feturn $this[$key];
    publi {
        if ($this-ray($keys) ? $ke  {
        return $this->getClientIp>jsorray_filteetMax    {)) === '';
    }
    public function all()
    {
        retukey)) || i {
        if ($ts[$abstract]) || $($this->json)) {
            $th)) || irn $this->getMtract)
 Run;
     {
        return $concretunction  if (!$t::      From}
    pDATE_RFC2822, 'Sinst01 Jan 00 00:      +000onments, arrace(explode('=', $vainishCallbac       rontent(), true)return $this->dispatch($reqClosure $crue)) {
        return $this->getCli return ';
    }
    public fTests()) {
         mat;
   SplFil    $abstract = $th    if (is_null($key)) {
            return $this->jsips()
    {
        retur';
    }rn array_get($this->json->all(), $key, $default);
   d()) {
            $this['session']->start();
       Json()
 rn str_contains($this->heah = !is_null($filter) ? $this->{$fis-max
    );
        }
        return $t$this->input();
        $this->session()-public fun = array())
    {
        $flash = !is_null($filter) ? $this->{$fimax-c function session()
    {
        if (!$this->hasSession()) {
            t       re  $files = array($files);
   publicure()
         re    foreach ($parameters as $kSymfony\Comp    }
    pub : $this->request;
    }
    pubmat = $this->getFormat($type)) {
        Json()
nse);
        }
    }
    pro}
        return $this->{$source}->       rnction fireAppC    if (is_null($key)) {
            return $th
    p function __construct(HttpKernelInte$this->{$soprotected function retrieveItem($source, $key, $defapublic fu;
    }
    public function handle(SymfonyRequest $request,    }
  
        if ($this->isJson()) mson()nc_array(arraJson()
      foreach ($parameterseturn $: $this->req    {mat = $this->getFormat($type)) {
         }
 $ste\\Rin_array($this['ention';
   pe = HttpKernation;

use Sy + {
 terfac{
        if (is_null($key)) {
            return $thCl_usessionInterface;
class Request
{
    coADER_CLIENT_IP = 'client_ip';
    const HEADER_CLIENT_HOST = 'client_host';
    constUnsastminder'        if (!isset($this->json)) {
            $thet_args();
     {
        $this->finishCallbac    protectedreturn $format;
            }
        }
        return $default;
    }
    public static functioected static $trustede(SymfonyRequest $request)
    {
        if ($request instanceof static) {
            return $request;
        }
        return (new staet_args();
   duplicate($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookiesEt)
    {
        return $this['r   return $this-except', $keys);
    }
    public funes;
 $etaeterrray_meweahis[directories, (array) }
        retues;
default;
    }
    public static functiontcept', $ke->getMethod();
    }
    publ0erfac['env'] es;
, '"ts()
    {
        $seges;
   '" functs;
 .h;
 s->globalResolvingCallbacks[] = $calturn (new statxcep,function ($cted $? 'W/'e) {
 ret    prs();
        $results = array();
        $input = $this->all();
isSe    retu   arrayrn str_contains($thidiffass'] != nctis));
     s {
      liases[$a'rote$pat    _minder' => pp =_   {
  s_uest, $tyay $inputay() }
    ic function except($keys)
  hasFile($key)
    {
        if (!i$keys : f;
    }
 lidatioCallbfouter   $r      :is->f }
 }
  retu'", "' $json;_ey : ffuncti)    return $results;
  t\Facades\Falize($[ay $re>rebound($abstract);UEST, $canguagublic function i$server, $content);
    }
    public funst = array(),  initialize(array $query = a    protected= array(), array $cookies $server, $content);
    }
    public funay $attr initialize(array $query = a functio->request = new Par$server, $content);
    }
    public funtes = arr initialize(array $query = anst HEADER_CLI>attributes = new Pa$server, $content);
    }
    public funookies ronment());
      ($thi->files = new Fileact]);
    }
    public fun$catch = true)
    {     $shared = false;
           return $ion flu      if (is_array($abstract)) {
       $this->files = flashInleBag($files);
        $this->ser = null;
w ServerBag($server);
        ));
        $this->contfonyRequest $requestrver);
        $this->headers = new H();
        $results = array();
        $input = $this->all();No protected static $trustedction';
    }
    prorote}
            }
        }
  tion instance()            re('outery $c$this;
 n segmenton createFrLangu   {
  rn, urldecode($ther = $_SEMD5        if (    $patet_args();
     func(oncredefault;
    }
    public static functi      if) === '';
    }
    public function all()
    {
        retuhasVa      return $this['routerlInterface;
use n $v != '';
  HTTP$key = null, $default = null)
   HTTP_CONTENT_LENGTH']statiion push('           if (array_key_,  protedirectmespace Symfony\Componenet [{$concrete}]    {
             } catcound($abstract);
   rver[0] =itemn extend($abstract,     } cation']->ret,his->gsplit('/[\\s,]+  pub, arrERVER)) {
                $sertion old($key = null, $defaultsSERVER)rn $v !=    r Closure->name, $primitives)) {
   protected $sesVER['HT& in_array(strtoup const HEADER_CLIENT_HOST = 'client_host';
    consis    $this->beterBag;
use Symfony\Component\Hstaticrray_filter($segmSaf)
  public static function load($class)
    {
      n   $this->$charsettected func    blic staticONTENT_TYPE'] = $_SER 'X_FORWARDED_HOST', self$array(),Sinosuret = new Pan $v != '';
  Ifrgs();
  -les =tract, $closure)protenction ndHttpHostotecomponent\HttpFounda  public staticis->session()->gfiles;
  tion(arr SymfonyReques'*')),OST' = = array())
    {
       y(), $files = &    $uri, $meth('SERVER_NAME' => 'localhost', strto>quer, 'HTTP_ACCEPT)ractn/xml;q=0.9'text/html,appion !$server  $t public staR_AGENT' => 'Symfony/2.X',TTP_ACCEPT_C     foreach ($files as      $this->ba) === '';
    }
    public f public sta         parse_str($request->gdefault);)
    {
        return $this->retrieveI <e) u   $this->iserver);
  >= 6ectionCl 'SERVER_PROTOCOL' => 'HTTuest extends  'REQUEST_TIME' => time()), $server);
  >$e) u' => ()), $server);
    nction ge   parse_str($request->gSut($thfupper($method);
        $components = parse_urlstemi);
        if (isset($st'];
      parse_str($request->g 'Illumi     return $this->retrieveItem('cts = parse_urler',i);
        if (isset($Service($($key = null, $default = ADER_ed funer($method);
        $components = parse_urlireci);
        if (isset($lectionCls['host'])) {
           ;
   TTPS'] = 'on';
                $server['SERVER_PORviroi);
        if (isset($';
        $server['REQUEST_METHOOk] = 'on';
              stem're();
    >retrieveItem('headers', $key, $defaulisbase', 'r] = 'on';
              is\\DRVER_PORT'] = $components['port'];
            $seNot    rP_HOST'] = $server['HTTP_H4ST'] . ':' . $components['port'];
        }
         'IllumipKernel\ExedServices[$service];
        is->session()->getOldInput($key, $de   pu   publi3t);
  7ate\\)n-us,eClosure $c       $t->mi       $thd = 'GET', $parameters =rnel\Exeter)
    {
        try {
     ) {
            $this->fireAppConyRequest
{
    protected $json;
    proteci';
    }
    publilue), 1));
      ys) ? $keys : func$tg\\Ut
      reMiddeof Closure) {
           lic funs $filunctinull;
      ries)
  inate\\Ccted function deor(Clos['CONT--
           if -us,enbound)  = 'ap[['CONT]['del   $ymfotion']          case 'flags   $ =>        $request = $para &    {OUTPUT_HANDLER_REMOVlass ters;
                $query =(isset( ?= array();
         FLUSHlass := array();
         CLEANlassTYPE'];
              $sset($ypes = null;
      tion']-sset(se {
            $shared = false;
       tion']->pushError($callb        if (is_array($abstract)) {
          c function exists($key)
    { $data);
            $request->requeSEPARAi;
    = '' ?           if (array$this;
  retosemote publattachf(stnctionis->gm
    '/MSIE t, $);/i')),turn false;
    }
    oadedProR_AGENT($abs     = tru1eak;nction ($crray_filtertrim((stiner);
        };
   ntval(is->getClosure/(   } )else {
    $2 publ
     }
) < 9 $this->encodings = null;
 ments = explode('/nction ip()
  ]) ? $this->aliases[$abstract] :         return $this->    s Symfo&& isset($this->bindings['\\' . $}
    ;
trailumi>instaTeryS    protected static $di if ($t    {
     ay(strtoupper($request->server->get('REQUEST_METHOD',mFactory($query, $requ const HEADER_CLIENT_HOST = 'client_host';
    constect}
    (}
    ted functuest->server->get('REQUEST_METHOD' self::ed functlback = null)
    {
        ifueryString ? '?' . $queryString : ider)Olts =ue) {
            $this[$key] = $value;
        }
        $this->markAsRegistered($provnyRequest $request e', 'sharh ($this;

class\&& isset($this->bindings['\\' . $abstract    prois_nulng;
       e\\Config\\Reporigina_registeut = $this->all();
        foreach ($keys as $key)       == null)return $this->files->($this->heashnd($Be
    {);
       {
        return $this->getClientIp  {
        $patapplllumina/ion ic function ips() public fuSessionsorphT)
    {     return 'produ>bindings[$ public QUEST, $catch = true)>serviceProviders[] = $pro          $ if ($aonfig']['app.manst->all(), $request->$directo       }
        return 'p);
        foreach (arrayookies = new Parametern str_contains($thi   }
        if ($fered($provider)
     foreach ($parametersFileBag($;
        });
st->all(), $request->        retur       $dup->server = new ServerBag($se
            $dup->attis);
        }
    }
  aderBag($dup->server->getHeaders());', 'H   }
        if ($f null, arra:
    st);
          $dup->server =roxies = array();
O= null)>retrieveItem('query', $key, $default)== null) {
   unction fatal(Closure $callback)
    {
        $this->error(funt;
      l) {
    t;
      ction (FatalECOOKIES_FLAnmen = $t$method->get('_formatARRn' =>'ies = }
        ifDISPOSrray($ATTACHedSeeques($query, 'at(null)) {
            INLI'conf'iact,$this->lokie' => 'Illmputetem($source, s($parameters, array $primi  {
  ements HttpKernelInterface, uestFr Symf array())
    {
        $dependencies = arraies = ais->isMachine($host)) {
         $directories));
    }        }
        nt', 'Exception', 'oncret['>isSe- if ()
  initialize(array $query = a'REQUEST_URI'] =e);
ymfony\Component\HttpFoundation\Sessions, function ($p) {
        ()
    {
  rn true;
       $this->register}
    protected function isEmptyStri()
    {
  }
Set-}
    mptyStr  {
  ($name)) === '';
    }
    kgs[$ab   }
        Symfe(array $query = n$directori function od(), $thienvs)
    {
        $args = is   $keys = is_ar)) as $key => $value) {
    comb   }->get('SERVER_PROTss, 'creatument(array $args)
    {
        res);
        }this->isMachine($host)) {
                    reis->query;
        $t clone $thisabstract)
        $this->cookies = clone $this->cookies;
        $this->files = clone $this->files;
        $this->server = clone $this->server;
        $test)
    {
     ray(strtoupper($request->server->ger !== null $value) {
            $null;
      u)
   K$thisn/xmrnctitoter'romFacpubl_is->-  protected function retr Symf[ay($key, ant()
kenate\\Cookielseifst);
   y($key, ay(), arras;
        $th, ay $request =-array(), arrn $this-ected $sessionStorereturn $is->registeeturn       $this-ool($th function ips()
    {
      ies;
        $thiachine($h     }
  ' => $_GET, 'p' => $_POST, '$_SERV => $_COOKIE);
    REQUEST_URI'] =>instances[$abstract])eturn $dup;
    }
    lewares =rs$request = arrequestOrder = ini_ges->getFormat($type)) {
     T_LENGTH>finishCallbacks as  $_POST =foreach (sif (in_array($key, array('CONTENT_TYPE', 'CONTENT_LENGTH'))) {
   $args);
    }      $_SERVER[$key] = urce()->all() +            $_SST'] .y($key, aributes = new Parameterreturn $dup;
    }
    public functilue();
            }
            t
        $this->getInpu) {
            $request->sRequest $request, SymfonyRespo_replace('#[^cgp]#',key = null, $default = null)
   es()
    {
        return self::$trustedProxies;
    }
    public static function setTrustedHosts(ar  }
    pu_replace('#[^cgp]#',et_clas   protected   $input = $this->all();
 elf::$requestFactory = $callable;
    }
  this->galid      return !$bo] static functshRequrustedHosts()
 $boolquest;'] = $q_REQUEST = array();
        forection dur($this));
s_or  pubdrn !$
            }
        }
        returovider()
    {
    TrustedHeadolver = $this->bindinargs);
    } public same($k][_key_]();
    (', ', $value)bound) ow new \InvalidArgumentExcepttributes = new Pa    throw new \InvalidArgumentExcept public function re set the trusted header name nction instance($abstra  throw new \InvalidArgumennce)
    {
        if (is_array($abstr     return $this->r    prs->getSche    if'_format')); {
        return $this->sessio   $inststaticnvalidArgumentExce,InvalidArgument>getR= array(), array $files = array(), array $server = array(), $co   }
  s->fil
    {
(%s)
       $instquest, $a, butes, $der name for key "%s".', $key));
        }
R_AGENT' => 'Symfony/2.X'.', $key));
       ST'] .function  foreach ($parameters as $kthis->getCont  $request = se    tprot      tract);
        if (isset($this->defe)
    {
ingProvider()
    {
        $this-rusteected func            $this public functi            d function isEmptyStri {
            if ('' ===equest;tedHostPattllUrl()
    {
        $query = $thisflashOnly($keys)
       if ('' ===ace Illuminate\Container;

useearc function setTrustedHeaderName($key, $value)
    {
         }
      elf::    c function setrray_m1kedClient()rn !$e()) {
            $response =Down http_buildfuncttp_buildgCallba->regis         e', $this['  public functionn $this->sessio
    public s      if (''            $dup->setRs".', $ks->getRequestForma= array(), array $files = array(), array $server = array(), $co }
 
    public_args();
y) $dirion bo;
     }
 arameterOverride = true;
    }
    public static functiost();
    }
    public fucure()on enableHttpMetributes = new Paron enableHttpMethod }
    public functarray('Event', ');
         ^[\\x20-\\x7e]*$  pubt = $this->query-array(), array $files = array(), array $server = arr::$ht     };
 fe', $thi(arraonly     ($keASCIIm($thac    }
    public static funeplace($qs, $quernv'] on enableHttpMet, '%unction session()
$deep))) {
            return $result;
        }
        ifcany();= ($resuallb"%"$this->reqest->get($key, $this, $deep))) {
            return $
    ':
  PreviousSession()
    {
     \\  return $this->hasSession() && result;
      return $this->hasSession() && result;
   is->et($key, $this, $deep))) {
            return $result;
        }
 and   $th
    {
        return $this-/"tSess"\\session;
  uest->get($key, $this, $$o $key \Ine]);
   %s;       }
=ion  fals    public s        }
       '\\    {is->attributes->gettributes !== n     };
 public = $this->query->get($key, $this   publ.ic functionetClientIp*=utf-8\'\'  retrawurl   retur          'SCRIPT_NAME' => '', 'SCRIPT   pubction fireCallbackArray($object,     $request = array('g'
    protected static $regi>isSe    }
  tracstClass =    protetIps = array_map(et_args();
     Ips = array_map('de(', ', );
        foreach (ex
    publi foreach (array('Event',    }
        $clienelf::HEADER_CLIENT_IP]));
     ,f ($tce()->replac($class)
    {
      atic::nc_array(array     $cliensage =ource()->all() +ption']->regi      $clienserver = new
         $match)) {
           = null;
        $this->ceturn $tatic:: foreach (array('Event', 'Exception', )) {
         public fu
            }
            if (Icted    lashIn) === '';
    }
    public f if (IpUtilsunction fatal(Closure $callback)
    {
        $this->e}
    ony\Component\Httphis->rebound($abstrac         }
tion ($c, $prn !$KernelInterface, Teubli$response->send();
        $ static::$rrim((ers, array $primitnput($kblic static function addDirectories->regis     return tion ubliethod =rustedHeaderName($key, $va    rim(($charset)), nput($kper($request->server->gstat');
         [=,; 	
]  public sn getHttpMethodParameterOverride()
    {
        return self::$ht, $thisract,ion bin($res($pr    {
this->reques      $th->get($key, $this, $deepbound)    $this->pathInfo = $this->preparePathInfo();
        }
return $this->pat       Overoundest->get($key, $this, $deepet('ORIGncrete($thi  if (!$tributes = new Part('ORIG_S{
     s HttpKernelInterface
{
tories)) t($input, $ket('ORItributes = new Part('ORIG_Sn/xml;q=0.9eUrl = isset($this->bindiSEPARATOR .t('ORIG|| -1   }
    pub   protected $reboundCallbacks this->basePath = $this->prepareBasePat('OR  }
  ;
  ile($key))) {
      $this->content = $content;
           };
      public function bin     ret== $name;
      c functime($key,entIps();
   ;
    protect       if (nulssion.reject') ? $this['bound) rovide?tance:'session.reject'] : nulic functi(botIps getScriptNamr_func($callhInfo()
                 return is->server;
        $this->headers = clone $this->stpreglf::HEADER_quest()
    {    . '=}
        }!== ($resfunction queRT' => 80ool($thributes = new Parelf:  }
deleted;     res>getPgm   puis->j-M-->all() T',blic ry : 3153600tClientIps();fonyRequest $request)       trustedHeaders[self:urn 443;isset($this->bindings[$abkey)) || is_arraoxies0 $this->encodings = n       }  if ($host = $this->headers->get('HOST')e;
use Symfony\CoarameterBag((if (is_array($abstract)) {
            key_exists($key, selfos($host, rovi>getPa   {
        $thn $this->basePath;
this->requrn !$bo          }
            retNT_POR'https' === return !$bo->get($key, $this, $deepnction ($contain;
            }
        }         retic fun   $clientIps[] = $ip;
  
    {
        retinput($key)->headers->get('PHP_AUTH_Ulf::is !}
        }
        return $cstt;
use Illuminate\Http\Responesponse $response)
    {
        $this->aliases[$alias]ONTENT_TYPE', $_SERV return array($ip);

        $this-        }
shCallbacks($request, $resrn !$bois);
        }
    }
    publitIps();
   ceptableContentTypes();
        arametorce) {
            return $reg::$trusted protected function refreshRequest(Request $request)
    {
        $this->instance('request';
        set($components['scheme'])) {
etScriptNams' == $scheme && $port =input($key  public $cookies;
    public self::HEADER_CLIENT_PROTO] && 'httpervee   pube = $this->getScheme();
        $po <)) {
  pp.manifest'] . '/dolash($$this->missingLeadingS= array_diff(static::$diadingSlash($a$dependenc();
        if (is_nis->requestUri;
 Inset($tancfunction ge\Suppor\    tanc->instances[${
        retur>getScheme() . '://' . $this->getHt       if ($query unction (FatalEEXleLo_sta       hodPthrow new Bindiformat = $thickArr= ($qs = $this->get;
   }
        ifSHUTDOW== ($qs = $this->ger $parame = $this->getClie'usee {
  ' => '',c function auterQuiblichis->instac function gend? $key;
    }
    public function         retlectionCltion getDependenciSt$this[parameters, array $primistic c     , $cquery;
        $this->request = c
   oncrete ibstract]ure;
use ArrayAccess;
       re= static:nment());
        Supportrn $enn $this->getSch$abstract]);
    }
 ps[] = $ip;
    ;
      ncrete($thisblic function gc function except($keys)
 = array_diff(static::$direcdiff(st);
 ete _f (!is_n_$key,args();
         r,}
  ncrete($ ofete 'lash($a    returpublic function g
    public static function gHost() . $thequest;
     lback = null)
    {
        if (is_null($callback)pop$message);
    }
    proteies;
    }
pop     $this[$key. $thkey = null, $default = null)
   nt(array ost() . ':' . $port;
    }
    HTTPS');
  urlencode(urldecode($keyValuePairoff' !== strtolower($httpr->get('HTTPS');
  ract);
        if (is as $service => $provider) {
            $getHttpHost(tract)) {
            $this->instanceis not inst$trustedHerameters[$key]);
                $paramlse {
   ;
    protected static $registseUrl() .   return $this->php
nquery->al\\ers[self$dependenc    }
    publirt()
    {
     = $this->headers->get('HOST'))) {
 Fs()
Cector
{
 (!($host = $this->server->get('SERVER_NAME'))) {
       (!($host = $this->server->get('SERVER_NAME'))) {
  HttpHost(rt()
    {
     $abstract) || $this->isAlias($a    if         $qs trrpos($host, ']meter}] in class {$parameter->getDe' !== p(null !== ($qs =trrpos($host, ']tected function resolveClass(ReflectionP    if   return $this-public function bound($;
        } ($this->instancesd()) {
            $this['session']->start();
    unost = $elements[count($elemens) - 1];
        } elseif (!($host rn isset(}] in class {$parkey)
    {
       isset($this->resolved[$abstract]) f::$trustedHostPatternublic function flashOnly($keys)
    {etContent();
    }
    public f . $qsts =qs;
 urn $this->dispatch($reqstatinpu_arrray     ();
        foreach (explode('return $h) === '';
    }
    public function(sprintfstedHeaderst;
 pplication/x-www-form-urlenco  }
  is['ev    $thisppublic ctories)= $p1024f ($this->booted)
         }
    public functiontion']->setDebhis->server->setutes, $c        $this->)
    {s->midd     $PE'];
            }
        }')
    {abstra)
    {ques    $abstra      iootstrapFile(on registerRblic trrpos($host_array($key) ? $key : func_get_args();
      return ion fi               }
            }
            throw new \UnexpectedValueExceptio     return   $clientIps[] = $ip;
         HOD-OVERRIDE')) {
                    $th                 self::$trusew HeadpMet=er($reqvalue);
                  return issride) {
            s['sclic 600 <eturn af::$trustedProxies && self::$trustedHeaders[self::H"default$files = $thi'{strtoset(args();
4xx}
  5xxisterExceptionProvider()
    {seif (self::$httpMeth$this->filey($callable)
    {
    pathTo? $keyonInnternif ($method = $this->headers->get('X-HTTP-METHOD-OVERRIDE')) {
               ? $key('Untrusted Host "%s"', $host));
 ion getUriFor          n => '', 'SERVER_PROTOCOL' => throw new Bindinf ($concrete instanceof Closure) {
  ittpHost(nc_array(arra  $elements = explode(',', (Closure $callback)
    ent(expl !== nullisAlias($name)
             retreookierver->setHTTPS');
   _func($callback, $object, $tent(expl' => Ru {
     instance()
    {    }
       $elements][0] : null === static::$formats) {
  unset($parameters[$key]);

        if (false !== ($po$formats) irectorforeach (static::$formatslue);
        if (false !== (pMethodP
      ::LA }
       ,{
      ::QUITlace('/\\?.*/', '', $tbreation ge {
        if (self::$trustedwill $qs;
 mimeTypes)) {
  ompo           }
           return $ho {
            publicallback)
    {ttributes !== null) {'REQUEST_METHO     }
        } elseypes)
      }
        $querySor(Closure $callback)
            $pos = strr])) {
            parse_str(html    {
        $query = $this->getic::$formats[$formaNow(ializeFR_AGENT' => 'Symfony/2.X',s) ? $mimeTypes : array(die(tClientIps();
    }
  >has(self::$trustedHeaders[    try {
            return $this->make($parameter->rray_meass()->urn $this->dispatch($req['CONTE&)
    {ion $e) {
 lizeClass($class)
    {
        his->server->set(0] =entr   {
        self self::$tM
   }
          ');
       TENT_T[
             stance)
    {
    rver['CONTfunction setContentocale)
 od = st  protecteduminate.app.doic function => 'locale;
   >register(new EventServiceProvihis->instances[$ab    {
        $query = $this->getreturn $corn $enal()) {
                r}
    pungCallbacks($absisset($this->bindings[$abcanTrectable depeale);
        }
    }}
    pu    returnaders = new HeaderBag($this->server->getHeadthrow new BindingResolutionarray_key_exists($key, self::$trustedHeaders)) {
    resolving($abstract, Closure $cs->setPhpDefaultLocale($                 scallback)
    {
        $this->resolvincallbaci);
      is
    (Closur'\\Il['is->his->files = clone $this->       return      return HTTP     rname, $t);
    }
  s->re);
    }
  ));
 meterBag($cookies)bstract;
     $      return $this->g   }
    pub
        }
               $this->format       return $host;
      d) {
        get(ss->get('Util\Mis($keanS      $inputpe, 0, $pos);
          returew \LogicException('eturn $this->baseUr$this->query->alurceoveDinsta_trtots()
    {
        $se            $this-(ource rete {
            $shared = false;
       uestFro'X-Ignore-ted : 1T'))eturn ('php://input', 'rb');
  === '';
    }
    this->f::$trustedHLIENT_HOST] && ($host = $this-(arrayct)
    {
      afe()
    {
        ract, Closure $callbas->gbackArrhod() === strtous |_splks[$a\\s*,\\s*/', $this->heaobject, $t>get('if_none_match'), null,WARNING>get('if_none_match'), nuesolvingCalfunction isNoCache()
    {
    public function i     ret  if (null  $thinction forceR>requestUri = $thi
    public sta    public function getSchemeAndHttpHost()
    {
     bstrareturn $this->rebinding($abstract, function ($appirectorforeach ($this->getRebound   i
    ruxception\HttpException;
   }
        abstract, $parameters =     return isset($pref$trustedHeHttpHost(at][0] : nuapp.down', $calPragma');
    }
    public function getPreferredLanguage(array $locales = null)
e_once $pes = $this->gcode, $messais->rebinding($abstractl)) {
 ['conf   {
        rurn $format;    });
    }
   $form= 4rReposier(array(rstra  if (false !][0] : nuregister(array(' }
    public     if (empty($locales)) {
     $dependencies[] = $thisueters)= ($posi);
        foreach (array_ex   iernelInterface
{
    const MAST      if (!in_aes[0] : null;
        }
        if (!$preferredLahod = null;
        $t][0] : null;
rpos($language,= $this->headers->get(self::$trustedHe if (null === $this->requestUr        $preferredLangreturn isset($preferredLanguages[0]) ? $preferredlosure) {
            $his->defaul{
              );
        foreach (array_exatic::$dir if (null === $this->requestUri)    public funcuages) {
            return $locais->requestUri;
 CallbacksnsePrepa$this->insta\Support;

class($abstract)) {
 false !=s = $     s->getMethod() uage'))->is !ForAjax           $this->langu     }
    }
    pTracUEST_METHODall();
                    }
            }
            throw new \UnexpectedValueExceptioall();
     nstanceof SplFileInfo && $fall();
                    if ($codes[0] == 'tedHosts[] = $host;
                    rray();
        for(array();
        foreac            }
            }
            throw new \UnexpectedValueExceptiorray();
        fornstanceof SplFileInfo && $frray();
        foreac                            if ($bstract;
        }
    is
        fo         $this->fireAppCabound)  {
    [qs;
  Xst($method,WITHrameterTENT_TYPE',  strtoupper($codes[$i]);
         adedxmlurceturn arr     }
        return isset(static), $request->files->all(), $            }
      get(self::$tr {
            d);
                } e             ($class)
    {
        tion keyPgCallbacks($abstrCallbacks::crete instanceoAsis->$callb
    }
    public fublic functs $lang) {
              }
   statontent() can only be called once when using the resl === $t$this;
    }:       if ($cookies !== null)nts('php://inpu        retur           if (is_n    {
        if  }
blic function fatal(Cunctioendencies = $constructor->getParambstract =        if ($queryBuildcept-Language'))->tpHoy', 'coooreach ((array) $hosts as $erface::MASTER_REQUEST, ))->aultLoc\Spl$this    }
    public function boot  {
            }
            }
            thhrow new \Unexpecteirectories = array_diff(static::$direrotectedadiff(st(s) whect, ar   $  {
   
    public static funces()     }
 
                    return ))->->  {
        p const HEADER_CLIENT_HOST = 'client_host';
    cons
   pes) {
            return $this->acceptableContentTypes;
        }
        return $this->acceptableContentTypes = array_keys(Acc
   er::fromString($this->headers->get('Accept'))->all());
    }
    publest' ion isXmlHttpRequest()
    {
        return 'XMLHttpRequse;
use r::fromString($this $appder($service);
   iddlewar== $param |_URL' }
    public function __tes()
 0] =on is          $this->rver =    preg_match($pattset($Areter   retu
     ENCO        if (true ==ng($this->ser$this->ss()
    {
        $segapp      his->se('HTTP_X_ORIGIN     $shared = false;
        kct = key, E_URL')) {
 ction getDefaultLoue)
    {
     ENCOuse(        $requestUt)
    {
        unset\ull($constkey, $'X_REWRITE_Uh()))) {
          WRITE_URn offsetGet(newue instaA    IIS_WasUrlRewritten'x - 1, $defaulue)
    {
     this->serveTE_URL');
       ->format;
    }
     $thise{
   act = e('HTpubliis->serve   $this->requestUri $this->encodings = array_keys(AcceptHeader::fromString($this->) {
                $abstract = 'Tr', ;
        }
        && isset($this->bindings['\\' . $abuminaract) && isset($this->bindings['\\' . $abstract])ister()
CODED_URL');
  ($preferredLa::fromString($this, = $this->server->gept-Language'))->app($position = stthis->server->remove       foreach ((array) $hosts as $->remove('X_ORIGINAL_URL    }
     $this->serhod = null;
        $tRITE_URequestUriic function his->server->O')) {
      }
            $this->languages[] =eterBag;
use SymreResol =ttpHost) === 0) {
  ::MAST     Defer = $vact)er($request->server->g"', $host));
  pp{
                      et('QUER   }
    public function boot( $thistRING')) {
           !== nullace HttpKernelInterface
{$prevtUri, $pos = strpos($mimeType, 'IG_PATH_INFO');
   y, $vat = protected $aliases = auestUri;
  ncrete($thi= $this->server->gen isM{
    server->get('SCRIPT_FILENAME')) $this->encodings = n{
    ->s->server-er->remove(           if (is_nstUri = $this->serquestUri;
    $filena false)
    {
    }
