<?php
namespace App\EsModel;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Support\Facades\Log;
use App\Extensions\StopWatch;

class BaseModel
{
    /**
     * 日期格式
     * @var string
     */
    const DATETIME_FORMAT = 'yyyy-MM-dd HH:mm:ss';
    const DATE_FORMAT = 'strict_date';

    /**
     * 隐藏字段，用来附加文档修改时间的
     * @var string
     */
    const LAST_TIMESTAMP_FIELD = '_last_timestamp';

    /**
     * 索引名称的类型
     * @var integer
     */
    const GET_INDEX_BASE = 1; //基础索引名称
    const GET_INDEX_REAL = 2;   //索引存储在es里面的真实名称，通常是增加时间后缀
    const GET_INDEX_ALIAS = 3;  //索引在系统里面的别名

    /**
     * 最大文档数量
     */
    const MAX_RESULT_WINDOW = 40000;

    /**
     * 临时保存索引名称的
     * @var string
     */
    private $_new_version = false;

    private $_elastic_client = null;

    /**
     * 超时时间设定
     * @var integer
     */
    private $es_timeout = 3;
    private $es_connect_timeout = 1;

    private static $instance = null;

    /**
     * 索引的基础名称
     * @var string
     */
    protected $index = null;

    /**
     * 返回数据的时候，一定会返回的数据
     * @var array
     */
    protected $basic_field = [];

    /**
     * 下面是可以用于返回的扩展字段
     * @var array
     */
    protected $extra_field = [];

    public static function getInstance(){
        if(self::$instance==null){
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 重设超时时间，通常用于执行刷新ES的时候
     * @param int $timeout
     * @param int $connect_timeout
     */
    public function resetTimeout($timeout,$connect_timeout){
        $this->es_timeout = $timeout;
        $this->es_connect_timeout = $connect_timeout;
        $this->_elastic_client = null;
    }

    public function getElasticClient()
    {
        if (! $this->_elastic_client) {
            $hostStr = config('elasticsearch.host');
            $hosts = explode(';', $hostStr);
            $this->_elastic_client = ClientBuilder::create()->setHosts($hosts)
                ->setConnectionParams([
                    'client' => [
                        'timeout' => $this->es_timeout,
                        'connect_timeout' => $this->es_connect_timeout
                    ]
                ])
                ->build();
        }
        return $this->_elastic_client;
    }

    /**
     * 得到索引名称
     * @param int $index_type
     * @param string $suffix
     * @throws \Exception
     * @return string
     */
    public function getIndex($suffix=null,$index_type = self::GET_INDEX_BASE)
    {
        if (empty($this->index)) {
            throw new \Exception('index property Not Set');
        }

        $base = config('elasticsearch.prefix') . '_' . $this->index;
        if($suffix){
            $base = $base.'['.$suffix.']';
        }
        $alias = $base . '_alias';
        switch ($index_type) {
            case self::GET_INDEX_BASE:
                return $base;
                break;
            case self::GET_INDEX_REAL:
                $index = null;
                if ($version = $this->getNewVersion()) {
                    $index = $base . '_' . $version;
                }

                if (empty($index)) {
                    $index = $this->getRealIndex($alias);
                    $index = array_pop($index);
                }
                if (empty($index)) {
                    $index = $base;
                }
                return $index;
                break;
            case self::GET_INDEX_ALIAS:
                return $alias;
                break;
            default:
                throw new \Exception('index_type:' . $index_type . ' not defined');
        }
    }

    /**
     * 得到mapping配置
     * @return array
     */
    public function getMapping()
    {
        $mappingFile = __DIR__.'/'.$this->index.'.mapping.php';
        if(file_exists($mappingFile)){
            return include($mappingFile);
        }

        if(property_exists($this, 'mapping_config')){
            return $this->mapping_config;
        }

        return [];
    }

    /**
     * 更新索引
     * @param boolean $updateMapping 是否强制更新索引，如果在索引存在的情况下
     * @param string $suffix
     * @return boolean
     */
    public function updateMapping($updateMapping = false,$suffix=null)
    {
        $client = $this->getElasticClient();
        $mappings = $client->indices()->getMapping();

        // 得到索引名称，如果不是被软链的，就用基本索引名称
        $index = $this->getIndex($suffix,self::GET_INDEX_REAL);
        if (empty($index)) {
            $index = $this->getIndex($suffix,self::GET_INDEX_BASE);
        }

        // 实际的更新索引，因为ES限制，所以要先删除数据，再重建索引和数据，否则会遇到merge mapping exception
        if (isset($mappings[$index]) && $updateMapping) {
            $client->indices()->delete([
                'index' => $index
            ]);
            $mappings = false;
        }
        // 创建索引
        if (! isset($mappings[$index])) {
            $result = $client->indices()->create([
                'index' => $index,
                'body' => [
                    'settings' => [
                        'max_result_window' => self::MAX_RESULT_WINDOW
                    ]
                ]
            ]);
            $updateMapping = true;
        }

        // 创建mapping
        if ($updateMapping) {
            echo "update mapping...\n";
            $params = [];
            $params['index'] = $index;
//            $params['type'] = 'data';
            // 后期静态绑定 http://php.net/manual/zh/language.oop5.late-static-bindings.php
            $new_mapping = static::getMapping();
            //增加最后修改字段
            $new_mapping[self::LAST_TIMESTAMP_FIELD] = [
                'type' => 'date',
                'format' => 'epoch_second'
            ];
            $params['body'] = [
                'properties' => $new_mapping,
            ];
            $client->indices()->putMapping($params);
            $client->indices()->putSettings([
                'index' => $index,
                'body' => [
                    'index.requests.cache.enable' => true
                ]
            ]);
            return true;
        }
    }

    /**
     * 给数据增加最后修改时间
     * @param array $data
     * @return array
     */
    public static function wrapContent(array $data)
    {
        $data[self::LAST_TIMESTAMP_FIELD] = time();
        return $data;
    }

    /**
     * 在完成推送之后
     *  更新别名
     *  删除遗留数据
     * @param string $suffix
     * @return array
     */
    public function afterPush($suffix=null)
    {
        // 版本发生变化时 更新别名
        if ($this->getNewVersion()) {
            $this->changeAlias($suffix);
        }
        // 删除掉因为数据库硬删除造成的渣子数据
        $client = $this->getElasticClient();
        $hits = [];
        try {
            $yesterday = (new \DateTime())->modify('-3 day');
            $result = $client->search([
                'index' => $this->getIndex($suffix),
                'type' => 'data',
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                'range' => [
                                    self::LAST_TIMESTAMP_FIELD => [
                                        'lte' => $yesterday->getTimestamp()
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '_source' => [
                    '_id'
                ],
                'size' => 2000
            ]);
            $hits = $result['hits']['hits'];
        } catch (Missing404Exception $ex) {
            // DONT NEED DO ANYTHING
        }

        $ids = [];
        foreach ($hits as $hit) {
            $id = $hit['_id'];
            $ids[] = $id;
            try{
                $client->delete([
                    'index' => $this->getIndex($suffix,self::GET_INDEX_BASE),
                    'type' => 'data',
                    'id' => $id
                ]);
            }catch(Missing404Exception $ex){
                // DO NOTHING,DELETED
            }
        }
        return $ids;
    }

    public function deleteProduct(array $ids, $suffix=null)
    {

        // 得到索引名称，如果不是被软链的，就用基本索引名称
        $index = $this->getIndex($suffix,self::GET_INDEX_REAL);
        if (empty($index)) {
            $index = $this->getIndex($suffix,self::GET_INDEX_BASE);
        }

        $client = $this->getElasticClient();
        foreach ($ids as $id) {
            try{
                $client->delete([
                    'index' => $index,
                    'type' => 'data',
                    'id' => $id
                ]);
            }catch(Missing404Exception $ex){
                // DO NOTHING,DELETED
            }
        }
    }

    public function changeVersion($version)
    {
        if (empty($this->_new_version)) {
            $this->_new_version = $version;
        }
    }

    public function getNewVersion()
    {
        return $this->_new_version;
    }

    /**
     * 修改别名
     *
     * @return void
     */
    public function changeAlias($suffix)
    {
        $alias = $this->getIndex($suffix,self::GET_INDEX_ALIAS);
        $curRealIndexes = $this->getRealIndex($alias);
        $newRealIndex = $this->getIndex($suffix,self::GET_INDEX_REAL);

        $this->createAlias($newRealIndex, $alias);
        foreach ($curRealIndexes as $index) {
            $this->delAlias($index, $alias);
        }
        echo "成功修改别名 " . $alias . '-->' . $newRealIndex . "\n";
        foreach ($curRealIndexes as $index) {
            $this->getElasticClient()
                ->indices()
                ->delete([
                    'index' => $index
                ]);
            echo "成功删除旧索引 " . $index . "\n";
        }
        echo "检查多余索引\n";
        $dirtyIndexes = $this->checkDirtyIndex($suffix);
        if ($dirtyIndexes) {
            foreach ($dirtyIndexes as $index) {
                $this->getElasticClient()
                    ->indices()
                    ->delete([
                        'index' => $index
                    ]);
                echo "成功删除错误索引 " . $index . "\n";
            }
            Log::error('dirty indexes', $dirtyIndexes);
        }
    }

    public function createAlias($index, $alias)
    {
        $client = $this->getElasticClient();
        $result = $client->indices()->putAlias([
            'index' => $index,
            'name' => $alias
        ]);
    }

    public function delAlias($index, $alias)
    {
        $client = $this->getElasticClient();
        $client->indices()->deleteAlias([
            'index' => $index,
            'name' => $alias
        ]);
    }

    /**
     * 检查不用的索引
     * 别名正常使用时，删除多余索引
     *
     * @return void
     */
    public function checkDirtyIndex($suffix)
    {
        $client = $this->getElasticClient();
        $allAliases = $client->indices()->getAliases();
        $base = $this->getIndex($suffix,self::GET_INDEX_BASE);
        $usingAlias = $this->getIndex($suffix,self::GET_INDEX_ALIAS);
        $canRemove = false;
        $removeIndex = [];

        $base = preg_quote($base);
        foreach ($allAliases as $index => $aliases) {
            $pattern = '/^'.$base.'(_\d{10})?$/';
            if(preg_match($pattern, $index)){
                if(array_key_exists($usingAlias, $aliases['aliases'])){
                    $canRemove = true;
                }else{
                    $removeIndex[] = $index;
                }
            }
        }
        if ($canRemove) {
            return $removeIndex;
        }
        return [];
    }

    /**
     * 获取别名对应的inedx
     * 和最新
     *
     * @param mixed $alias
     * @return void
     */
    public function getRealIndex($alias)
    {
        $client = $this->getElasticClient();
        $indexes = [];
        try {
            $result = $client->indices()->getAlias([
                'name' => $alias
            ]);
            $indexes = array_keys($result);
        } catch (Missing404Exception $ex) {
            // do nothing，这个index没有做alias
        } catch (\Exception $e) {
            logger($e);
        }
        return $indexes;
    }

    /**
     * 结构化查询ES里面的数据
     * 1，常规查询，精确匹配，不会计算权重，包含must(肯定查询)和must_not(否定查询)两种
     * 2，相关度查询scored_query，比如模糊搜索，这是计算权重的
     * 3，聚合查询aggs，用于聚合某个属性出现的次数，通常用户展示筛选项用
     * @param int $start 起始位置
     * @param int $limit 返回数量
     * @param array $esMustFilter elasticsearch中用于and查询的数组
     * @param array $esShouldFilter elasticsearch中用于or查询的数组
     * @param array $esScoredQuery elasticsearch中用于模糊查询的数组
     * @param array $esAggsQuery elasticsearch中用于聚合查询的数组
     * @param array $sort 排序数组
     * @param array $with 设定返回字段
     * @param string|bool $cursor 是否开启游标，以及游标值
     * @param string $suffix
     * @throws \Exception
     * @return array|callable
     */
    protected function structSearch($start, $limit, array $esMustFilter, array $esShouldFilter,
                                    array $esScoredQuery, array $esAggsQuery, array $sort, array $with,$cursor, $suffix=null){

        $queryDSL = [
            'index' => $this->getIndex($suffix),
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            'bool' => [
                                'must' => $esMustFilter,
                                'should' => $esShouldFilter,
                            ]
                        ],
                        'must' => $esScoredQuery
                    ]
                ],
                'sort' => $sort
            ],
            '_source' => $this->fields($with),
            'from' => $start,
            'size' => $limit,
        ];

        if($esAggsQuery){
            $queryDSL['body']['aggs'] = $esAggsQuery;

            if ((request()->input('env', 0) == 1)) {
                dd($queryDSL);
            }
        }

        $stopWatch = StopWatch::make()->start();
        $esClient = $this->getElasticClient();

        if($cursor=='open'){
            $queryDSL['search_type'] = 'scan';
            $queryDSL['scroll'] = self::CURSOR_TIMEOUT;
            unset($queryDSL['from']);
            $queryDSL['size'] = $this->getMaxSize($suffix);
        }

//        try{
            if($cursor=='open' || $cursor===false){
                $result = $esClient->search($queryDSL);
                $cursor = array_get($result,'_scroll_id',null);
            }
//        }catch(\Exception $ex){
//            logger('exception product es query failed,query params:'.json_encode($queryDSL));
//            logger('exception product es query failed,query error:'.$ex->getMessage());
//            throw $ex;
//        }
        if($cursor){
            try{
                $result = $esClient->scroll([
                    'scroll_id' => $cursor,
                    'scroll' => self::CURSOR_TIMEOUT
                ]);
            }catch(\Exception $ex){
                logger('scroll exception');
                throw $ex;
            }
        }

        $timeUsed = $stopWatch->stop()->diff();
        if (app()->isLocal() || $timeUsed > config('elasticsearch.slow_time') || request('loges', false)) {
            logger(sprintf('elasticsearch slow query index: %s ,total-time: %sms, query-time: %sms, hit: %s, query: %s',
                $this->index,$timeUsed,$result['took'],$result['hits']['total']['value'],json_encode($queryDSL)));
        }

        return $result;
    }

    /**
     * 因为laravel自带的array_pull无法移除key中有点(.)的数据
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected static function array_pull(&$array,$key,$default=null){
        $value = array_get($array, $key, $default);
        if(isset($array[$key])){
            unset($array[$key]);
        }
        return $value;
    }

    /**
     * 得到游标最大可以获取的数量
     * @param string $suffix
     * @return mixed
     */
    protected function getMaxSize($suffix=null)
    {
        $client = $this->getElasticClient();
        $indices = explode(' ', $client->cat()->indices([
            'index' => $this->getIndex($suffix)
        ]));
        $max = 5000 / $indices['3'];
        return floor($max);
    }

    /**
     * 传入排序数组，获得ES专用的排序语句
     * @param array $sorts
     * @param array $params
     * @return array
     */
    protected function toSortQuery($sorts, $params)
    {
        if(!$sorts) return [];

        $query = [];
        foreach ($sorts as $field => $sort) {
            if ($q = $this->customSort($field, $sort, $params)) {
                $query[] = [$q['key'] => $q['value']];
            } else
                if (is_array($sort)) {
                    //如果是sorts=id(0)会出问题
                    if(empty($sort) || $sort==[0=>0]){
                        continue;
                    }
                    $ids = array_flip(array_reverse($sort));
                    foreach ($ids as &$v) {
                        $v ++;
                    }
                    $query[] = [
                        '_script' => [
                            'order' => 'desc',
                            'type' => 'number',
                            'script' => [
                                // 'source' => "doc['$field'].value in ids?ids[doc['$field'].value]:0.1",
                                'lang' => 'painless',
                                'source' => "params.ids.containsKey(doc['$field'].value+'')?params.ids[doc['$field'].value+'']:0.1",
                                'params' => [
                                    'ids' => $ids
                                ],
                            ]
                        ]
                    ];
                } else {
                    $query[] = [ $field => ($sort ? 'asc' : 'desc') ];
                }
        }
        return $query;
    }

    /**
     * 可以重写这个方法，实现复杂的自定义排序
     * @param string $field
     * @param mixed $sort
     * @param array $params
     */
    protected function customSort($field, $sort, $params)
    {}

    /**
     * 生成ES的查询参数
     * @param array $params
     * @return array[]
     */
    protected function toQuery($params){
        $query = [
            'must' => [],
            'should' => []
        ];

        foreach ($params as $type => $data) {
            foreach ($data as $item) {
                if ($q = $this->customQuery($item['field'], $item['value'], $type)) {
                    $query[$type] = array_merge($query[$type], $q[$type]);
                    continue;
                }

                if($item['value'] === '') continue;

                $_filter = static::parameterToQuery($item['field'], $item['value'], 'ES');
                if ($_filter) {
                    $query[$type][] = $_filter;
                }
            }

        }
        return $query;
    }

    /**
     * 自定义查询，客户端必须构建这样的返回数组
     * [
     *      'must' => [],
     *      'must_not' => [],
     * ]
     * @param string $field
     * @param string $value
     */
    protected function customQuery($field, $value, $type){
        return static::customQuery($field,$value, $type);
    }

    /**
     * 筛选可用返回字段
     * @param array $extra
     * @return array
     */
    protected function fields(array $extra)
    {
        if ('*' == array_get($extra, 0)) {
            $extra = $this->extra_field;
        }
        $extra = array_values(array_intersect($this->extra_field, $extra));
        $fields = array_merge($this->basic_field, $extra);

        return $fields;
    }

    protected function filterField($data, $fields)
    {
        foreach ($data as $key => $d) {
            if (! in_array($key, $fields))
                unset($data[$key]);
        }
        return $data;
    }

    /**
     * 在时间查询中使用，用于得到一个非当前具体时间的字符
     * 主要是获得一个大范围的时间，比如不会精确到秒，知识精确到分钟，这样有助于ES进行缓存
     * @param number $offset
     * @return string
     */
    protected function getDatetime($offset=0){
        $d = new \DateTime();
        $d->setTimestamp($d->getTimestamp()+$offset);
        return $d->format('Y-m-d H:i:00');
    }

    /**
     * 把传入的QueryString转成相应的查询语句
     *
     * @param string $key
     * @param string $value
     * @param string $type
     * @throws \Exception
     */
    public static function parameterToQuery($key, $value, $type = 'ES')
    {
        $method = 'resolveTo' . $type;
        if (method_exists(static::class, $method)) {
            return static::$method($key, $value);
        } elseif (method_exists(self::class, $method))
            return self::$method($key, $value);
        else {
            throw new \Exception('No resolve method:' . $type);
        }
    }

    /**
     * @param $key
     * @param $value
     * @return array
     */
    protected static function resolveToES($key, $value)
    {
        $param = [];
        if (!$value) {
            return  $param;
        }

        // 是否为排除
        $exclude = false;
        if (strpos($key, 'exclude_') === 0) {
            $key = str_replace('exclude_', '', $key);
            $exclude = true;
        }

        // 词组包含
        if (strpos($key, 'match_') === 0) {
            $key = str_replace('match_', '', $key);
            foreach (explode(':', $value) as $item) {
                if (!trim($item)) continue;

                $param['bool']['should'][]['match_phrase'][$key]= [
                    'query' => trim($item),
                    'slop' => 2
                ];
            }
//            $param['match_phrase'][$key] = [
//                'query' => $value,
//                'slop' => 2
//            ];
        }

        // 区间变量 or
        elseif (preg_match('/^(\(|\[)(.*)?,(.*)?(\)|\])$/', $value, $matches)) {

            if ($matches[2] != '') {
                $param['bool']['should'][]['range'][$key][($matches[1] == '[' ? 'gte' : 'gt')] = $matches[2];
            }

            if ($matches[3] != '') {
                $param['bool']['should'][]['range'][$key][($matches[4] == ']' ? 'lte' : 'lt')] = $matches[3];
            }
        }

        // 区间变量 and
        elseif (preg_match('/^(\(|\[)(.*)?:(.*)?(\)|\])$/', $value, $matches)) {
            if ($matches[2] != '') {
                $param['range'][$key][($matches[1] == '[' ? 'gte' : 'gt')] = $matches[2];
            }

            if ($matches[3] != '') {
                $param['range'][$key][($matches[4] == ']' ? 'lte' : 'lt')] = $matches[3];
            }
        }

        // 复合多选 or
        elseif (preg_match('/^(.+)(,(.+))+$/', $value)) {
            $data = [];
            foreach(explode(',', $value) as $d){
                if($d){
                    $data[] = trim($d);
                }
            }

            $param['terms'] = [
                $key => $data,
            ];
        }

        // 复合多选 and
        elseif (preg_match('/^(.+)(:(.+))+$/', $value)) {
            foreach(explode(':', $value) as $d){
                if($d){
                    $param['bool']['must'][] = [
                        'term' => [
                            $key => trim($d)
                        ]
                    ];
                }
            }
        }

        // 默认
        else {
            $param['term'] = [
                $key => $value
            ];
        }

        return $exclude ? ['bool' => ['must_not' => $param]]
            : $param;
    }
}
