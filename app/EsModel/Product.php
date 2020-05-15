<?php
/**
 * Created by PhpStorm.
 * User: chicv
 * Date: 19/4/21
 * Time: 下午1:56
 */

namespace App\EsModel;

use App\Extensions\Util;
use Illuminate\Support\Facades\Artisan;

class Product extends BaseModel
{
    protected $index = 'product';

    protected $basic_field = [
        'id',
        'feed_id',
        'name',
        'price',
        'image',
        'compare_at_price',
        'spu',
        'released_at',
        'tips',
        'status',
        'handle',
        'inventory',
        'vendor',
        'module',
    ];

    protected $extra_field = [
        'images',
        'skus',
    ];

    // 游标过期时间
    const CURSOR_TIMEOUT = '3m';

    /**
     * 开启别名使用
     * @param mixed $alias
     * @return void
     */
    public function getIndex($suffix = null, $type = self::GET_INDEX_ALIAS)
    {
        return parent::getIndex($suffix, $type);
    }

    /**
     * @param string $field
     * @param string $value
     * @param array $env
     * @return array|null
     * @throws \Exception
     */
    protected function customQuery($field, $value, $type)
    {
        // 判断是否为 options.color 或者 specification.material 这种
        if (preg_match('/(options|specification|exclude_specification)\_([0-9]+)/', $field, $matches)) {
            list($field, $sub) = [$matches[1] . '_nested', $matches[2]];
        }
        $query = [];
        switch ($field) {
            case 'released_at':
                $_filter = static::parameterToQuery($field, $value);
                if (isset($_filter['bool'])) {
                    foreach ($_filter['bool']['should'] as $k => $_v) {
                        foreach ($_v['range'][$field] as $item => $value) {
                            $value = (new \DateTime())->setTimestamp(strtotime("- $value day"))
                                ->setTimezone(new \DateTimeZone('UTC'))
                                ->format('Y-m-d H:i:s');
                            unset($_filter['bool']['should'][$k]['range'][$field][$item]);
                            $_filter['bool']['should'][$k]['range'][$field][$item == 'lt' ? 'gt' : 'lt'] = $value;
                        }
                    }
                } else {
                    //8号0点上架了一个商品 如果今天要搜索到这个商品  大于写1-2都能搜到  小于写大于3都可以搜到
                    foreach ($_filter['range'][$field] as $k => $_v) {
                        $_v = (new \DateTime())->setTimestamp(strtotime("- $_v day"))
                            ->setTimezone(new \DateTimeZone('UTC'))
                            ->format('Y-m-d H:i:s');
                        unset($_filter['range'][$field][$k]);
                        $k = $k == 'lt' ? 'gt' : 'lt';
                        $_filter['range'][$field][$k] = $_v;
                    }
                }
                $query[$type][] = $_filter;
                break;

            case 'options_nested':
                $query[$type][] = [
                    'nested' => [
                        'path' => 'options',
                        'score_mode' => 'none',
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'options.id' => $sub
                                        ]
                                    ],
                                    static::parameterToQuery('options.value_id', $value)
                                ],
                            ]
                        ],
                    ]
                ];
                break;

            case 'specification_nested':
                $query[$type][] = [
                    'nested' => [
                        'path' => 'specification',
                        'score_mode' => 'none',
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'specification.id' => $sub
                                        ]
                                    ],
                                    static::parameterToQuery('specification.value_id', $value)
                                ],
                            ]
                        ],
                    ]
                ];
                break;

            case 'exclude_specification_nested':
                $query[$type][] = [
                    'nested' => [
                        'path' => 'specification',
                        'score_mode' => 'none',
                        'query' => [
                            'bool' => [
                                'must_not' => [
                                    [
                                        'term' => [
                                            'specification.id' => $sub
                                        ]
                                    ],
                                    static::parameterToQuery('specification.value_id', $value)
                                ],
                            ]
                        ],
                    ]
                ];
                break;
        }

        return $query;
    }

    public function search($query, &$total = 0)
    {
        unset($query['_']);
        /* 分类参数，1:coomand参数 2:option可以用作聚合返回的参数 3:常规查询参数
         * 设置部分参数默认值，以及互斥作用
         */

        /*指令式参数，例如with,limit等*/
        $commandParam = [
            'start' => array_pull($query, "start", 0),
            'limit' => array_pull($query, "limit", 10),
            'cursor' => array_pull($query, 'cursor', false),
            'return-filter' => array_pull($query, 'return-filter', 0),
            'keywords' => array_pull($query, 'keywords', []),
            'with' => array_pull($query, 'with', [])
        ];

        // 获取过滤参数
        $param = [
            'filters' => array_pull($query, 'filters', []),
            'collection_filters' => array_pull($query, 'collection_filters', ''),
            'sorts' => array_pull($query, "sorts", ''),
            'collection_sorts' => array_pull($query, 'collection_sorts', '')
        ];

        $commandParam['with'] = $this->fields($commandParam['with']);

        $commandParam['filters'] = Util::mergeFilters($param['filters'], $param['collection_filters']);
        $commandParam['sorts'] = Util::mergeSorts($param['sorts'], $param['collection_sorts']);

        $esQuery = $this->toQuery($commandParam['filters']);
        $esMustFilter = $esQuery['must'];
        $esShouldFilter = $esQuery['should'];
        $esScoredQuery = $this->toScoreQuery($commandParam['keywords']);

        $esSort = $this->toSortQuery($commandParam['sorts'], $commandParam['filters']);
        /*查询*/
        $result = $this->structSearch($commandParam['start'], $commandParam['limit'],
            $esMustFilter, $esShouldFilter, $esScoredQuery, [],
            $esSort, $commandParam['with'], $commandParam['cursor']);

        $total = $result['hits']['total']['value'];

        //格式化信息
        $items = [];
        foreach ($result['hits']['hits'] as $hit) {
            $item = $hit['_source'];
            $items[] = $this->filterField($item, $commandParam['with']);
        }

        $rtnData['data'] = $items;
        if ($cursor = array_get($result, '_scroll_id')) {
            $rtnData['cursor'] = $cursor;
        }

        // 查询聚合信息
        if ($commandParam['return-filter']) {
            // 处理掉filters[must] 里options_1=5 的筛选
            $reg = '/\|?options_[\d]+=[^\|&]+/';
            $optionsFilter['must'] = $noOptionsFilter['must'] = '';
            $noOptionsFilter['must'] = preg_replace($reg, '', array_get($param['filters'], 'must', ''));
            $noOptionsEsMustFilter = $this->toQuery(Util::mergeFilters($noOptionsFilter, $param['collection_filters']))['must'];

            // 提取filters[must] 里options_1=5 的筛选
            if (preg_match_all($reg, array_get($param['filters'], 'must', ''), $matches)) {
                foreach ($matches[0] as $match) {
                    $optionsFilter['must'] .= trim($match, '|') . "|";
                }
            }
            $optionsMustFilter = array_get(Util::mergeFilters($optionsFilter), 'must', []);

            $result = $this->structSearch(0, 0, $noOptionsEsMustFilter, $esShouldFilter, $esScoredQuery, $this->getAggsQuery($optionsMustFilter), [], [], false);
            $rtnData['filter'] = $this->_formatAggsResult($result);
        }

        return $rtnData;
    }

    /**
     * @return array
     */
    protected function getAggsQuery($optionsMustFilter)
    {
        $aggs = $innerFilters = [];

        // innerFilter
        foreach ($optionsMustFilter as $filter) {
            $innerFilters[$filter['field']] = [
                'bool' => [
                    'should' => [
                        [
                            'terms' => [
                                'options.value_id' => explode(',', $filter['value'])
                            ]
                        ]
                    ]
                ]
            ];
        }

        // options
        $options = Option::getCachedAll();
        foreach ($options as $option) {
            $aggsFilter = [];
            $optionKey = 'options_' . $option['id'];
            foreach ($innerFilters as $k => $innerFilter) {
                if ($optionKey != $k) {
                    $aggsFilter[] = $innerFilter;
                }
            }

            $aggs = array_merge($aggs, [
                $optionKey => [
                    'filter' => [
                        'bool' => [
                            'must' => $aggsFilter
                        ]
                    ],
                    'aggs' => [
                        'nested_values' => [
                            'nested' => [
                                'path' => 'options'
                            ],
                            'aggs' => [
                                'values' => [
                                    'filter' => [
                                        'term' => [
                                            'options.id' => $option['id']
                                        ]
                                    ],
                                    'aggs' => [
                                        'values' => [
                                            'terms' => [
                                                'field' => 'options.value_id',
//                                                'order' => [
//                                                    '_term' => 'asc'
//                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
            ]);
        }

        // specification
        $filter_groups = FilterGroup::getCachedAll();
        foreach ($filter_groups as $filter_group) {
            $aggs = array_merge($aggs, [
                'specification_' . $filter_group['id'] => [
                    'filter' => [
                        'bool' => [
                            'must' => []
                        ]
                    ],
                    'aggs' => [
                        'nested_values' => [
                            'nested' => [
                                'path' => 'specification'
                            ],
                            'aggs' => [
                                'values' => [
                                    'filter' => [
                                        'term' => [
                                            'specification.id' => $filter_group['id']
                                        ]
                                    ],
                                    'aggs' => [
                                        'values' => [
                                            'terms' => [
                                                'field' => 'specification.value_id',
//                                                'order' => [
//                                                    '_term' => 'asc'
//                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
        }

        // categories
        $aggs = array_merge($aggs, [
            'categories' => [
                'filter' => [
                    'bool' => [
                        'must' => []
                    ]
                ],
                'aggs' => [
                    'values' => [
                        'terms' => [
                            'field' => 'categories.id'
                        ]
                    ]
                ]
            ]
        ]);

        return $aggs;
    }

    /**
     * @param array $result
     * @return array
     */
    private function _formatAggsResult(array $result)
    {
        $rtnFilters = [];

        // filter groups
        $filter_groups = array_column(FilterGroup::getCachedAll(), 'name', 'id');
        $filters = array_column(Filter::getCachedAll(), 'name', 'id');
        foreach ($filter_groups as $k => $v) {
            if (isset($result['aggregations']['specification_' . $k])) {
                $tmp = [];
                foreach ($result['aggregations']['specification_' . $k]['nested_values']['values']['values']['buckets'] as $d) {
                    if (isset($filters[$d['key']])) {
                        $tmp[] = [
                            'id' => $d['key'],
                            'name' => $filters[$d['key']],
                            'count' => $d['doc_count']
                        ];
                    }

                }
                if (!empty($tmp) && isset($filter_groups[$k])) {
                    $rtnFilters['specification'][] = [
                        'id' => $k,
                        'name' => $filter_groups[$k],
                        'list' => $tmp
                    ];
                }
            }
        }

        // options
        $options = array_column(Option::getCachedAll(), 'name', 'id');
        $option_values = array_column(OptionValue::getCachedAll(), 'name', 'id');
        foreach ($options as $k => $v) {
            if (isset($result['aggregations']['options_' . $k])) {
                $tmp = [];
                foreach ($result['aggregations']['options_' . $k]['nested_values']['values']['values']['buckets'] as $d) {
                    $tmp[] = [
                        'id' => $d['key'],
                        'name' => $option_values[$d['key']],
                        'count' => $d['doc_count']
                    ];
                }
                $rtnFilters['options'][] = [
                    'id' => $k,
                    'name' => $options[$k],
                    'list' => $tmp
                ];
            }
        }

        // $rtnFilters['options'] = [];

        // categories
        $categories = Util::arrayMapping(Category::getCachedAll(), 'id');
        $tmp = [];
        foreach ($result['aggregations']['categories']['values']['buckets'] as $d) {
            if (isset($categories[$d['key']])) {
                $tmp[] = [
                    'id' => $categories[$d['key']]['id'],
                    'name' => $categories[$d['key']]['name'],
                    'parent_id' => $categories[$d['key']]['parent_id'],
                    'count' => $d['doc_count']
                ];
            }
        }
        $rtnFilters['categories'] = Util::formatToTree($tmp);

        return $rtnFilters;
    }

    /**
     * @param $value
     * @return array
     */
    public function toScoreQuery($value)
    {
        if (!$value) return [];
        //Removes duplicate from keywords
        //替换,为空格
        $value = strtr($value,[','=>' ']);
        $keywords = array_filter(explode(' ', $value));
        $keywords = array_unique($keywords);
        $query[] = [
            'bool' => [
                'should' => [
                    [
                        'terms' => [
                            'spu' => array_values($keywords)
                        ]
                    ],
                    [
                        'multi_match' => [
                            'query' => join(' ', $keywords),
                            'minimum_should_match' => '80%',
                            'type' => 'cross_fields',
                            'fields' => [
                                'id',
                                'spu',
                                'name'
                            ]
                        ]
                    ]
                ]
            ]
        ];
//        $keywords = join(' ', $keywords);
        //Removes duplicate


        return $query;
    }

    //处理特殊的id
    protected static function resolveToES($key, $value)
    {
        if ($key == 'id') {
            $value = join(',', array_filter(explode(',', $value), function ($id) {
                return $id && is_numeric($id);
            }));
        }
        return parent::resolveToES($key, $value);
    }

    /**
     * @param $ids
     */
    public static function refresh($ids)
    {
        if (!$ids) {
            return;
        }

        // 同步更新
        ob_start();
        Artisan::call('es:push-product', [
            '--ids' => $ids
        ]);
        ob_end_clean();
    }

    /**
     * @param $id
     * @param array $with
     * @return mixed
     */
    public function find($handle, array $with = [])
    {
        $list = $this->search([
            'with' => $with,
            'filters' => ['must' => "handle=$handle"]
        ]);
        return current($list['data']);
    }

    /**
     * 通过 sku 获取产品
     * @param array $params
     * @param array $with
     * @return mixed
     */
    public function findBySku($params, array $with = [])
    {
        $search['with'] = $with;
        $search['filters']['must'] = '';
        if(array_get($params, 'sku_id', ''))
        {
            $search['filters']['must'] .= "|skus.id=". trim($params['sku_id']);
        }
        if(array_get($params, 'sku', ''))
        {
            $search['filters']['must'] .= "|skus.sku=". trim($params['sku']);
        }
        if(!$search['filters']['must']){
            return [];
        }
        $list = $this->search($search);
        return current($list['data']);
    }

}