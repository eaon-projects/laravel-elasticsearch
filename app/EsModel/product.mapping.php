<?php
/**
 * Created by PhpStorm.
 * User: chicv
 * Date: 19/4/20
 * Time: 下午6:25
 */

return [
    'id' => [
        'type' => 'integer'
    ],
    'feed_id' => [
        'type' => 'long'
    ],
    'status' => [
        'type' => 'integer'
    ],
    'name' => [
        'type' => 'text',
        'fields' => [
            'keyword' => [
                'type' => 'keyword',
                'ignore_above' => 256
            ]
        ]
    ],
    'handle' => [
        'type' => 'keyword'
    ],
    'spu' => [
        'type' => 'keyword',
    ],
    'item_spu' => [
        'type' => 'keyword',
    ],
    'vendor' => [
        'type' => 'keyword',
    ],
    'module' => [
        'type' => 'keyword',
    ],
    'price' => [
        'type' => 'float',
    ],
    'inventory' => [
        'type' => 'long',
    ],
    'released_at' => [
        'type' => 'date',
        'format' => self::DATETIME_FORMAT,
    ],
    'images' => [
        'properties' => [
            'id' => [
                'type' => 'integer'
            ],
            'product_id' => [
                'type' => 'integer'
            ],
        ]
    ],
//    'statistic' => [
//        'properties' => [
//            'viewed' => [
//                'type' => 'long',
//            ],
//            'season_viewed' => [
//                'type' => 'long',
//            ],
//            'month_viewed' => [
//                'type' => 'long',
//            ],
//            'week_viewed' => [
//                'type' => 'long',
//            ],
//            'sold' => [
//                'type' => 'long',
//            ],
//            'season_sold' => [
//                'type' => 'long',
//            ],
//            'month_sold' => [
//                'type' => 'long',
//            ],
//            'week_sold' => [
//                'type' => 'long',
//            ],
//            'cart_num' => [
//                'type' => 'long',
//            ],
//            'inventory' => [
//                'type' => 'long',
//            ],
//            'refund' => [
//                'type' => 'long',
//            ],
//            'season_refund' => [
//                'type' => 'long',
//            ],
//            'month_refund' => [
//                'type' => 'long',
//            ],
//            'week_refund' => [
//                'type' => 'long',
//            ],
//            'conversion_rate' => [
//                'type' => 'float',
//            ],
//            'refund_rate' => [
//                'type' => 'float',
//            ],
//        ]
//    ],
//    'categories' => [
//        'properties' => [
//            'id' => [
//                'type' => 'integer'
//            ],
//            'name' => [
//                'type' => 'keyword'
//            ]
//        ]
//    ],
//    'tags' => [
//        'properties' => [
//            'id' => [
//                'type' => 'integer'
//            ],
//            'name' => [
//                'type' => 'keyword'
//            ]
//        ]
//    ],
//    'options' => [
//        // 一对多
//        'type' => 'nested',
//        'include_in_parent' => true,
//        'properties' => [
//            'id' => [
//                'type' => 'integer'
//            ],
//            'name' => [
//                'type' => 'keyword'
//            ],
//            'value_id' => [
//                'type' => 'integer'
//            ],
//            'value_name' => [
//                'type' => 'keyword'
//            ]
//        ]
//    ],
//    'skus' => [
//        'properties' => [
//            'id' => [
//                'type' => 'integer'
//            ],
//            'feed_id' => [
//                'type' => 'long'
//            ],
//            'sku' => [
//                'type' => 'keyword'
//            ]
//        ]
//    ],
//    'specification' => [
//        'type' => 'nested',
//        'include_in_parent' => true,
//        'properties' => [
//            'id' => [
//                'type' => 'integer'
//            ],
//            'name' => [
//                'type' => 'keyword'
//            ],
//            'value_id' => [
//                'type' => 'integer'
//            ],
//            'value_name' => [
//                'type' => 'keyword'
//            ]
//        ]
//    ],
//    'collection_clear_sort' => [
//        'properties' => [
//            'product_id' => [
//                'type' => 'long',
//            ],
//            'clear_type' => [
//                'type' => 'integer',
//            ],
//            'sort_key' => [
//                'type' => 'integer',
//            ],
//            'sort_value' => [
//                'type' => 'float',
//            ],
//            'sort_value_bak' => [
//                'type' => 'float',
//            ],
//        ]
//    ],
//    'recommend' => [
//        'properties' => [
//            'id' => [
//                'type' => 'integer',
//            ],
//            'product_id' => [
//                'type' => 'long',
//            ],
//            'site_id' => [
//                'type' => 'integer',
//            ],
//            'shipping_country' => [
//                'type' => 'keyword',
//            ],
//            'priority' => [
//                'type' => 'integer',
//            ],
//            'p' => [
//                'type' => 'float',
//            ],
//        ]
//    ],
//    'custom_field_to_size' => [
//        'properties' => [
//            'Tag Size' => [
//                'type' => 'text'
//            ],
//            'US' => [
//                'type' => 'text'
//            ],
//            'UK' => [
//                'type' => 'text'
//            ],
//            'Shoulder'=> [
//                'type' => 'text'
//            ],
//            'Bust'=> [
//                'type' => 'text'
//            ],
//            'Waist'=> [
//                'type' => 'text'
//            ],
//            'Hip'=> [
//                'type' => 'text'
//            ],
//            'Sleeve Length'=> [
//                'type' => 'text'
//            ],
//            'Sleeve Circumference'=> [
//                'type' => 'text'
//            ],
//            'Length'=> [
//                'type' => 'text'
//            ],
//            'Hem Width'=> [
//                'type' => 'text'
//            ],
//            'Thigh'=> [
//                'type' => 'text'
//            ],
//            'Inseam'=> [
//                'type' => 'text'
//            ],
//            'Bottom Length'=> [
//                'type' => 'text'
//            ],
//            'Top Length'=> [
//                'type' => 'text'
//            ],
//            'Width'=> [
//                'type' => 'text'
//            ],
//            'Heel Height'=> [
//                'type' => 'text'
//            ],
//            'Inner Height Increasing'=> [
//                'type' => 'text'
//            ],
//            'Platform Height'=> [
//                'type' => 'text'
//            ],
//            'Shaft Height'=> [
//                'type' => 'text'
//            ],
//            'Shaft Circumference'=> [
//                'type' => 'text'
//            ],
//            'Feet Length'=> [
//                'type' => 'text'
//            ],
//        ]
//    ],
];