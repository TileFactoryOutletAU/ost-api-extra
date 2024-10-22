<?php

namespace ApiExtra;


file_exists('../main.inc.php') or die('System Error');
require_once('../main.inc.php');

require_once(\INCLUDE_DIR . 'class.api.php');
require_once(\INCLUDE_DIR . 'class.http.php');
require_once(\INCLUDE_DIR . 'class.staff.php');

define('STAFF_ID', 1);


class Api
{

    private $apiKeyData = null;
    private $handlers = array();

    function __construct()
    {
        $this->register_handlers();
    }

    function authenticate()
    {
        global $thisstaff;

        if (!($apiKey = $_SERVER['PHP_AUTH_PW'])) {
            \Http::response(401, 'Login Required');
            return false;
        }

        $this->apiKeyData = \API::lookupByKey($apiKey);
        if (!$this->apiKeyData || !$this->apiKeyData->isactive()) {
            \Http::response(401, 'API key invalid or inactive');
            return false;
        }

        $thisstaff = \StaffSession::objects()
            ->filter(['staff_id' => STAFF_ID])
            ->first();

        return true;
    }

    function handle_request()
    {
        if (!$this->authenticate())
            die('Not authenticated');

        $method = $_SERVER['REQUEST_METHOD'];

        $url_parts = parse_url($_SERVER['REQUEST_URI']);

        $path = explode('/', $url_parts['path']);
        $path = array_values(array_filter($path));
        array_shift($path);

        $query = array();
        parse_str($url_parts['query'], $query);

        $body = file_get_contents('php://input');
        $body = json_decode($body);

        $routeFound = false;
        foreach ($this->handlers as $handler) {
            $handle = $handler->can_handle($path, $method);
            if ($handle & RouteHandlerBase::HANDLEABLE_ROUTE) {
                $routeFound = true;
                if ($handle & RouteHandlerBase::HANDLEABLE_METHOD) {
                    $handler->handle($path, $method, $query, $body);
                    return;
                }
            }
        }

        $pathString = implode('/', $path);
        if ($routeFound)
            \Http::response(405, json_encode([
                'error' => 'invalid method for route',
                'method' => $method,
                'route' => $path
            ]), 'application/json');
        else
            \Http::response(404, json_encode([
                'error' => 'invalid route',
                'route' => $path
            ]), 'application/json');
    }

    function register_handlers()
    {
        $dirPath = __DIR__ . '/routes/';
        $itemNames = scandir($dirPath);
        foreach ($itemNames as $itemName) {
            $itemPath = $dirPath . $itemName;
            if (!is_file($itemPath))
                continue;
            if (!str_ends_with($itemPath, '.class.php'))
                continue;

            $classesBefore = get_declared_classes();
            require_once($itemPath);
            $classesAfter = get_declared_classes();
            $classesAdded = array_diff($classesAfter, $classesBefore);

            foreach ($classesAdded as $className) {
                $reflection = new \ReflectionClass($className);
                if (!$reflection->implementsInterface(__NAMESPACE__ . '\\IRouteHandler'))
                    continue;
                if (!$reflection->isInstantiable())
                    continue;
                $this->handlers[] = $reflection->newInstance();
            }
        }
    }
}

$api = new Api();
$api->handle_request();

?>