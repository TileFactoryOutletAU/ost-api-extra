<?php

namespace ApiExtra;

interface IRouteHandler
{
    public function can_handle($route, $method): int;
    public function handle($route, $method, $query, $body): void;
}

abstract class RouteHandlerBase implements IRouteHandler
{

    // Methods
    const METHOD_GET     = 0b00000001; //  1
    const METHOD_POST    = 0b00000010; //  2
    const METHOD_PUT     = 0b00000100; //  4
    const METHOD_PATCH   = 0b00001000; //  8
    const METHOD_DELETE  = 0b00010000; // 16
    const METHOD_OPTIONS = 0b00100000; // 32
    const METHOD_HEAD    = 0b01000000; // 64

    // Handleables
    const HANDLEABLE_ROUTE  = 0x01;
    const HANDLEABLE_METHOD = 0x02;

    // Convert a method string to a Handleable int
    protected function parse_method($method): int
    {
        if (is_string($method)) {
            $constName = static::class . '::METHOD_' . strtoupper($method);
            $method = constant($constName);
        }
        return $method;
    }

    public abstract function can_handle($route, $method): int;
    public abstract function handle($route, $method, $query, $body): void;
}

abstract class SingleRouteHandler extends RouteHandlerBase
{

    public function can_handle($route, $method): int
    {
        $method = $this->parse_method($method);
        $handle = 0;
        if ($this->route_matches($route))
            $handle |= static::HANDLEABLE_ROUTE;
        if ($this->method_matches($method))
            $handle |= static::HANDLEABLE_METHOD;
        return $handle;
    }

    public function route_matches($route): bool
    {
        $thisRoute = $this->get_route();
        $checkRoute = $route;

        $routeLength = max(count($route), count($thisRoute));

        for ($i = 0; $i < $routeLength; $i++) {

            $thisSegment = $thisRoute[$i];
            $checkSegment = $checkRoute[$i];

            $isParameter = (mb_substr($thisSegment, 0, 1) == ':');
            $isMatch = strtolower($thisSegment) == strtolower($checkSegment);

            if (!($isMatch || $isParameter))
                return false;
        }
        return true;
    }

    public function method_matches($method): bool
    {
        return ($this->get_methods() & $method);
    }

    protected function extract_route_parameters($route): array
    {
        $params = [];
        foreach ($this->get_route() as $index => $segment) {
            if (mb_substr($segment, 0, 1) == ':') {
                $key = mb_substr($segment, 1);
                $params[$key] = $route[$index];
            }
        }
        return $params;
    }

    public abstract function get_route(): array;
    public abstract function get_methods(): int;
    public abstract function handle($route, $method, $query, $body): void;

}

?>