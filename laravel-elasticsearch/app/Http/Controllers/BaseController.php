<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BaseController extends Controller
{
    /**
     * 写这个方法是因为PHP会把参数上有.等特殊字符转换成_
     *
     * @return array
     */
    protected function getOriginQueryParams()
    {
        $query = urldecode($_SERVER['QUERY_STRING']);
        if(empty($query)){
            return [];
        }
        $params = [];
        foreach(explode('&', $query) as $key_value){
            if(empty($key_value)) continue;
            $key_value = explode('=', $key_value);
            if(count($key_value) != 2) continue;
            if(strpos($key = urldecode($key_value[0]), '[]') !== false){
                $params[str_replace('[]', '', $key)] = $_GET[str_replace('.', '_', str_replace('[]', '', $key))];
            }else{
                $params[$key_value[0]] = urldecode($key_value[1]);
            }
        }
        return $params;
    }

    /**
     * 获取分页 参数
     * @return array
     */
    protected function getOffset($calc_start = false)
    {
        $page = (int)(request()->input('page', 1));
        $limit = (int)min(request()->input('limit', 20), 20);
        if ($calc_start) {
            $start = max($page - 1, 0) * $limit;
            return compact('page', 'start', 'limit');
        }

        return compact('page', 'limit');
    }

    /**
     * 解析请求参数中的with成数组
     *
     * @return array
     */
    protected function getWith()
    {
        if ($with = request()->input('with')) {
            $with = explode(',', $with);
        } else {
            $with = [];
        }
        return $with;
    }

    /**
     * @param $data
     * @param $total
     * @param $limit
     * @param $page
     * @return array
     */
    protected function returnList($data, $total, $extend = [])
    {
        $offset = $this->getOffset();

        return [
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => count($data),
                    'per_page' => $offset['limit'],
                    'current_page' => $offset['page'],
                    'total_pages' => ceil($total/$offset['limit'])
                ]
            ]
        ];
    }
}
