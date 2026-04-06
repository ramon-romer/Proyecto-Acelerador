<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Shared\Routing;

use Acelerador\PanelBackend\Shared\Http\Request;

class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $method = strtoupper($method);
        $paramNames = [];
        $regexPattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $match) use (&$paramNames): string {
            $paramNames[] = $match[1];
            return '([0-9]+)';
        }, $pattern);

        $regex = '#^' . $regexPattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'regex' => $regex,
            'paramNames' => $paramNames,
            'handler' => $handler,
        ];
    }

    /**
     * @return array{handler: callable|null, params: array<string, string>}
     */
    public function match(Request $request): array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            array_shift($matches);
            $params = [];
            foreach ($route['paramNames'] as $index => $name) {
                $params[$name] = $matches[$index] ?? '';
            }

            return [
                'handler' => $route['handler'],
                'params' => $params,
            ];
        }

        return [
            'handler' => null,
            'params' => [],
        ];
    }
}

