<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Http\Controllers\Controller;
use App\Models\ServerRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RouteController extends Controller
{
    public function fetch(Request $request)
    {
        $routes = ServerRoute::get();
        foreach ($routes as $k => $route) {
            if ($route->action === 'route_any' && is_string($route->match)) {
                $trimmed = ltrim($route->match);
                if ($trimmed !== '' && $trimmed[0] === '[') {
                    $array = json_decode($route->match, true);
                    if (is_array($array)) {
                        $routes[$k]['match'] = implode("\n", $array);
                        continue;
                    }
                }
                $routes[$k]['match'] = $route->match;
                continue;
            }
            $array = json_decode($route->match, true);
            if (is_array($array)) $routes[$k]['match'] = $array;
        }
        return [
            'data' => $routes
        ];
    }

    public function save(Request $request)
    {
        $action = $request->input('action');
        $rules = [
            'remarks' => 'required',
            'action' => 'required|in:block,block_ip,block_port,protocol,dns,route,route_ip,route_user,route_vlessRoute,route_any,default_out',
            'action_value' => 'nullable'
        ];
        if ($action === 'route_any') {
            $rules['match'] = 'required|string';
        } else if ($action === 'default_out') {
            $rules['match'] = 'nullable';
        } else {
            $rules['match'] = 'array|required';
        }
        $params = $request->validate($rules, [
            'remarks.required' => '备注不能为空',
            'match.required_unless' => '匹配值不能为空',
            'action.required' => '动作类型不能为空',
            'action.in' => '动作类型参数有误'
        ]);
        $action = $params['action'] ?? $action;
        if ($action === 'route_any') {
            if (is_array($params['match'] ?? null)) {
                $params['match'] = implode("\n", $params['match']);
            } else {
                $params['match'] = (string)($params['match'] ?? '');
            }
        } else if ($action === 'default_out') {
            $params['match'] = json_encode([]);
        } else {
            $normalizedMatch = array_filter((array)($params['match'] ?? []));
            $params['match'] = json_encode($normalizedMatch);
        }
        if ($request->input('id')) {
            try {
                $route = ServerRoute::find($request->input('id'));
                $route->update($params);
                return [
                    'data' => true
                ];
            } catch (\Exception $e) {
                abort(500, '保存失败');
            }
        }
        if (!ServerRoute::create($params)) abort(500, '创建失败');
        return [
            'data' => true
        ];
    }

    public function drop(Request $request)
    {
        $route = ServerRoute::find($request->input('id'));
        if (!$route) abort(500, '路由不存在');
        if (!$route->delete()) abort(500, '删除失败');
        return [
            'data' => true
        ];
    }
}
