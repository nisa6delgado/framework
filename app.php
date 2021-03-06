<?php

use Illuminate\Container\Container;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Http\Request;
use Illuminate\Routing\RoutingServiceProvider;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class App
{
	/**
	 * Start framework.
	 *
	 * @return void
	 */
	public static function run()
	{
		session_start();

		header('Access-Control-Allow-Origin: *');

		date_default_timezone_set(config('timezone'));

		if (config('errors') == false) {
			error_reporting(0);
		}

		$whoops = new Run;
		$whoops->prependHandler(new PrettyPageHandler);
		$whoops->register();

		$app = new Container;
		Facade::setFacadeApplication($app);
		$app['app'] = $app;
		$app['env'] = 'production';
		with(new EventServiceProvider($app))->register();
		with(new RoutingServiceProvider($app))->register();

		$route = $app['router'];
		include 'app/routes.php';

		$route->fallback(function () {
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/resources/views/errors/404.blade.php')) {
                return view('errors/404');
            } else {
                $viewPath = realpath($_SERVER['DOCUMENT_ROOT'] . '/vendor/nisadelgado/framework/third/views');
                $componentes = \Netflie\Componentes\Componentes::create($viewPath);

                $view = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/vendor/nisadelgado/framework/third/views/404.blade.php');
                echo $componentes->render($view, []);
            }
        });

		if (file_exists('app/helpers.php')) {
			include 'app/helpers.php';
		}

		$request  = Request::createFromGlobals();
		$response = $app['router']->dispatch($request);
		$response->send();
	}
}
