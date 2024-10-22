<?php

namespace ApiExtra;


file_exists('../main.inc.php') or die('System Error');
require_once('../main.inc.php');

require_once(\INCLUDE_DIR . 'class.api.php');
require_once(\INCLUDE_DIR . 'class.http.php');
require_once(\INCLUDE_DIR . 'class.staff.php');


class Api
{

    private $apiKeyData = null;
    private $staffData = null;
    private $handlers = array();

    function __construct()
    {
        $this->register_handlers();
    }

    function authenticate(): bool
    {
        global $thisstaff;

        if (!($apiKey = $_SERVER['PHP_AUTH_PW']) || !($username = $_SERVER['PHP_AUTH_USER']))
        {
            \Http::response(401, json_encode(['error' => 'login required']), 'application/json');
            return false;
        }

        $this->apiKeyData = \API::lookupByKey($apiKey);
        $this->staffData = \StaffSession::lookup(['username' => $username]);

        // Key exists
        if ($this->apiKeyData)
        {
            // Key is active
            if ($this->apiKeyData->isActive())
            {
                // Key matches remote ip or wildcard
                if (
                    ($this->apiKeyData->getIPAddr() == '0.0.0.0') || 
                    ($this->apiKeyData->getIPAddr() == $_SERVER['REMOTE_ADDR'])
                )
                {
                    if ($this->staffData)
                    {
                        // Set global staff member
                        $thisstaff = $this->staffData;
                        return true;
                    }
                }
            }
        }

        \Http::response(401, json_encode(['error' => 'username or api key invalid or inactive']), 'application/json');
        return false;

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