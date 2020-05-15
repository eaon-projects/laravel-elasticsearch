<?php
return [
    //ES链接信息
    'host' => env('ELASTIC_HOST', '127.0.0.1:9200'),
    //ES前缀
    'prefix' => env('ELASTIC_PREFIX', 'harbor'),
    //ES慢查询时间,单位毫秒
    'slow_time' => env('ELATIC_SLOW_TIME', 200)
];