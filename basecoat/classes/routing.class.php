<?php
namespace Basecoat;

class Routing {
	
	private $basecoat	= null;
	
	public $settings	= array(
		'use_pretty_urls'	=> true,
		'profiling'	=> false,
		);
	
	public $requested_url	= null;
	public $requested_path	= null;
	
	public $requested_route = null;
	
	public $current = null;
	
	public $last = null;
	
	public $profiling = array(
		'start'	=> null,
		'routes'	=> array()
		);
	
	public $profiling_enabled	= true;
	
	public $hooks	= array(
		'before' => array(),
		'after' => array()
	);
	
	public $run_after = array();
	
	public $max_routes = 5;
	
	public $counter = 0;
	
	private $default_routes = array(
		'default' => null,
		'not_found' => 'not_found',
		'static' => 'static',
		'error' => null
	);

	public function __construct($basecoat, $routes=null) {
		$this->basecoat		= $basecoat;
		$this->profiling['start']	= round(microtime(true),3);
		
		$this->setRoutes($routes);
	}
	
	public function addBeforeEach($func) {
		$this->hooks['before'][]	= $func;
	}
	
	public function clearBeforeEach() {
		$this->hooks['before']	= array();
	}
	
	public function addAfterEach($func) {
		$this->hooks['after'][]	= $func;		
	}

	public function clearAfterEach() {
		$this->hooks['after']	= array();
	}
	
	public function setUrl($url=null) {
		if ( is_null($url) ) {
			$url	= 'http';
			if ( isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
				$url	.= 's';
			}
			$url	.= '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		}
		$this->requested_url	= $url;
	}
	
	public function setRoutes($routes) {
		$this->routes = $routes;
	}
	
	public function addRoutes($routes) {
		$this->routes = array_merge($this->routes, $routes);
	}
		
	public function setDefault($route_name) {
		$this->default_routes['default']	= $route_name;
	}
	
	public function set404($route_name) {
		$this->default_routes['not_found']	= $route_name;
	}
	
	public function setStatic($route_name) {
		$this->default_routes['static']	= $route_name;
	}
	
	public function setError($route_name) {
		$this->default_routes['error']	= $route_name;		
	}
	
	public function run($route) {
		static $route_loop_cntr	= 0;
		$route_loop_cntr++;
		// Set convenience variable to reference Basecoat instance
		$basecoat	= $this->basecoat;
		// check if valid route is specified
		if ( !isset($this->routes[$route]) ) {
			// No route by that name
			// Check if there is a static template file matching request
			if ( file_exists($basecoat->view->templates_path . 'static/'.$route.'.html') ) {
				$this->routes[$route]	= $this->routes[$this->default_routes['static']];
				$this->routes[$route]['template']	= 'static/'.$route.'.html';
			
			} else if (file_exists($basecoat->view->templates_path . 'static/'.$route)) {
				// Create route using static route
				$this->routes[$route]	= $this->routes[$this->default_routes['static']];
				$this->routes[$route]['template']	= 'static/'.$route;
			
			} else {
				$route	= $this->default_routes['not_found'];
		
			}
		}
		// Assign route information to "current"
		$this->current	= $this->routes[$route];

		// Check if the route specified a layout
		if ( isset($this->current['layout']) ) {
			$this->basecoat->view->setLayout($this->current['layout']);
		
		}
		
		if ( isset($this->current['cacheable']) ) {
			$cache	=& $this->current['cacheable'];
			// Check for expires
			if ( isset($cache['expires']) ) {
				$this->basecoat->setCacheHeaders($cache['expires']);
			}
		}
		
		$this->processHooks($this->hooks['before']);

		// Make sure it's a valid route
		if ( array_key_exists( $route, $this->routes ) ) {
			// Check if http(s) is required
			if ( isset( $this->current['require_secure'] ) && $this->current['require_secure']!=2 ) {
				// Determine current scheme
				$scheme	= parse_url($this->requested_url, PHP_URL_SCHEME);
				if ( $scheme=='http' && $this->current['require_secure']==1 ) {
					// Redirect to https
					$new_url	= 'https'.substr($this->requested_url, 4);
					header('Location: '.$new_url);
					exit();
				} else if ( $scheme=='https' && $this->current['require_secure']==0 ) {
					$new_url	= 'http'.substr($this->requested_url, 5);
					header('Location: '.$new_url);
					exit();
				}
			}
			// Check for function call and/or file include
			if ( isset($this->current['function']) && is_callable($this->current['function']) ) {
				$call_f	= $this->current['function'];
				$call_f();
			}
			if ( isset($this->current['file']) ) {
				// Run route file
				if ( file_exists($this->current['file']) ) {
					include($this->current['file']);
				} else {
					echo 'NO ROUTE OR ROUTE FILE: '.$route;
				}
			}
		} else {
			error_log("Sorry, but I'm afraid I can't do that. " . $route);
		}

		$this->processHooks($this->hooks['after']);

		if ($this->profiling_enabled) {
			$this->logProfiling($route);
		}

	}
	
	public function parseUrl($url=null) {
		if ( is_null($url) && is_null($this->requested_url) ) {
			$this->setUrl();
		}
		// Check what URL format is in use
		if ( $this->settings['use_pretty_urls'] ) {
			// Determine path relative to document root
			$url_path	= str_replace(dirname($_SERVER['PHP_SELF']), '', $this->requested_url);
			$url_path	= trim( parse_url($url_path, PHP_URL_PATH), '/');
			if ( $url_path=='' ) {
				$this->run_routes	= array('/');
			} else {
				$this->run_routes	= explode('/',$url_path);
			}
		
		} else {
			/*
			 The route is determined by parsing the "page" url parameter
			 Multiple routes are delimited by a period (.)
			*/
			parse_str(parse_url($url, PHP_URL_QUERY), $tmp_get);
			if ( isset($_GET[$this->route_param]) && $_GET[$this->route_param]!='' ) {
				// Create a run routes list, to be used by subroutes
				$this->run_routes		= explode('.', $_GET[$this->route_param]);
				
			} else {
				// Use default route
				$this->run_routes		= array('default');
			}
		
		}
		//
		// Set the first route as the current run route
		// trim out leading/trailing . for security
		$this->requested_route	= trim( array_shift($this->run_routes), '.');
		return $this->requested_route;
	}

	public function processHooks($hook) {
		if ( count($hook)>0 ) {
			foreach($hook as $f) {
				if ( is_callable($f) ) {
					$f();
				}
			}
		}
		
	}

	private function logProfiling($route_name) {
		static $log_counter	= 0;
		if ( $log_counter==0 ) {
			$start_time	= $this->profiling['start'];
		} else {
			$start_time	= $this->profiling['routes'][$log_counter]['end'];
		}
		$log_counter++;
		$end_time	= round(microtime(true),3);
		// Log profiling information
		$this->profiling['routes'][]	= array(
			'route'=>$route_name,
			'time'=>$end_time-$start_time, 
			'start'=>$start_time,
			'end'=>$end_time,
			'seq'=>$log_counter
			);
		
	}
}