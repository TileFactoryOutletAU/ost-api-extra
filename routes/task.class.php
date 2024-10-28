<?php

namespace ApiExtra;

require_once(__DIR__ . '/../route.php');
require_once(__DIR__ . '/../functions.php');
require_once(\INCLUDE_DIR . 'class.task.php');

class TaskRoot extends SingleRouteHandler
{

    public function get_route(): array
    {
        return ["task"];
    }

    public function get_methods(): int
    {
        return static::METHOD_GET | static::METHOD_POST;
    }

    public function handle($route, $method, $query, $body): void
    {
        $method = $this->parse_method($method);
        switch ($method) {
            case static::METHOD_GET:
                $this->handle_get($query);
                break;
            case static::METHOD_POST:
                $this->handle_post($body);
                break;
            default:
                throw new \Exception('This should not be possible');
        }
    }

    protected function handle_get($query): void
    {
        $tasks = \Task::objects()->all();
        $tasks = array_map([TaskModel::class, 'from_response'], $tasks);
        \Http::response(200, json_encode($tasks), 'application/json');
    }

    protected function handle_post($body): void
    {
        $model = TaskModel::from_body($body);
        if (count($model->error) > 0)
            \Http::response(400, json_encode(['error' => $model->error]));

        $data = $model->to_create();
        $task = \Task::create($data);
        if ($task)
            \Http::response(200, json_encode(TaskModel::from_response($task)), 'application/json');
        else
            \Http::response(400, json_encode(['error' => 'failed to create task']), 'application/json');
    }
}

class TaskSingle extends SingleRouteHandler
{

    public function get_route(): array
    {
        return ['task', ':id'];
    }

    public function get_methods(): int
    {
        return static::METHOD_GET | static::METHOD_DELETE;
    }

    public function handle($route, $method, $query, $body): void
    {
        $method = $this->parse_method($method);
        $params = $this->extract_route_parameters($route);
        $id = $params['id'];

        switch ($method) {
            case static::METHOD_GET:
                $this->handle_get($id);
                break;
            case static::METHOD_DELETE:
                $this->handle_delete($id);
                break;
            default:
                throw new \Exception('Should not be possible.');
        }
        $params = $this->extract_route_parameters($route);
        
    }

    protected function handle_get($id): void
    {
        $task = \Task::objects()
            ->filter(['id' => $id])
            ->first();

        if ($task)
            \Http::response(200, json_encode(TaskModel::from_response($task)), 'application/json');
        else
            \Http::response(404, json_encode(['error' => 'task not found']), 'application/json');
    }

    protected function handle_delete($id): void
    {
        $task = \Task::objects()
            ->filter(['id' => $id])
            ->first();

        if (!$task)
            \Http::response(404, json_encode(['error' => 'task not found']), 'application/json');

        if ($task->delete())
            \Http::response(200, json_encode(['status' => 'ok']), 'application/json');
        else
            \Http::response(500, json_encode(['status' => 'failed']), 'application/json');
    }
}

class TaskModel
{

    public int $id;
    public string $number;
    public string $title;
    public string $description;
    public int $department;
    public int $assignee;
    public string $duedate;

    public array $error = [];

    public function to_create(): array
    {
        global $thisstaff;

        $request = array(
            'default_formdata' => array(
                'title' => $this->title,
                'description' => $this->description
            ),
            'internal_formdata' => array(
                'dept_id' => $this->department,
                'duedate' => $this->duedate
            ),
            'description' => $this->description,
            'staffId' => $thisstaff->getId(),
            'poster' => $thisstaff,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        );

        return $request;
    }

    public static function from_response($response): TaskModel
    {
        $task = new TaskModel;
        $task->id = $response->getId();
        $task->number = $response->number;
        $task->title = $response->getVar('title');
        $task->description = $response->getVar('description');
        $task->department = $response->dept_id;
        $task->duedate = $response->getVar('duedate');
        return $task;
    }

    public static function from_body($body): TaskModel
    {
        $task = new TaskModel;

        // Title
        if (isset($body->title))
            $task->title = $body->title;
        else
            $task->error[] = ['type'=>'missing_param','param'=>'title'];

        // Description
        if (isset($body->description))
            $task->description = $body->description;
        else
            $task->error[] = ['type'=>'missing_param','param'=>'description'];

        // Department
        if (isset($body->department))
            $task->department = $body->department;
        else
            $task->error[] = ['type'=>'missing_param','param'=>'department'];

        // Due Date
        if (isset($body->duedate))
        {
            $date = new DTHelper($body->duedate);
            $task->duedate = $date->to_user();
        }
        else
            $task->error[] = ['type'=>'missing_param','param'=>'duedate'];

        return $task;
    }
}

?>