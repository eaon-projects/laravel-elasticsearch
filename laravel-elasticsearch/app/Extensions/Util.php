<?php
/**
 * Created by PhpStorm.
 * User: eaon
 * Date: 2020-05-08
 * Time: 13:55
 */

namespace App\Extensions;


class Util
{
    /**
     * 合并filter 项
     * @param $filters
     * @param $collection_filters
     * @return array
     */
    public static function mergeFilters($filters, $collection_filters = '')
    {
//        $params = [];
        if ($collection_filters) {
            $collection_filters = explode('&', $collection_filters);
            foreach ($collection_filters as $collection_filter) {
                if (preg_match('/^filters\[(must|should)\]=([^&]+)$/', $collection_filter, $matches)) {
//                    $params[$matches[1]] = $matches[2];
                    if (isset($filters[$matches[1]])) {
                        $filters[$matches[1]] .= '|' . $matches[2];
                    } else {
                        $filters[$matches[1]] = $matches[2];
                    }
                }
            }
        }
//        if (empty($filters)) {
        //            $filters = $params;
        //            unset($params);
        //        }

        // cid=(123,124)|bid=23
        $query = [];
        foreach ($filters as $type => $filter) {
//            $filter = isset($params[$type]) ? $filter . '|' . $params[$type] : $filter;
            $source = explode('|', $filter);
            foreach ($source as $s) {
                if (preg_match('/([^=]+)=([^=]+)/', urldecode($s), $matches)) {
                    $query[$type][] = [
                        'field' => $matches[1],
                        'value' => $matches[2],
                    ];
                }
            }
        }
        return $query;
    }

    /**
     * 合并sorts
     * @param $sorts
     * @param $collection_sorts
     * @return array
     */
    public static function mergeSorts($sorts, $collection_sorts = '')
    {
        $text = $collection_sorts ? $sorts . "|" . $collection_sorts
            : $sorts;
        if (empty($text)) {
            return [];
        }
        $source = explode('|', $text);
        $query = [];
        foreach ($source as $s) {
            // 常规判断 eg:+point -sale.price
            if (preg_match('/^([\+\- ]?)([A-Za-z0-9\._]+)$/', $s, $matches)) {
                $query[$matches[2]] = $matches[1] === '-' ? false : true;

                // 给定排序规则 eg:product_id(1210,3349) category_id(223,59)
            } elseif (preg_match('/^([A-Za-z0-9\._]+)\((.*)\)$/', $s, $matches)) {
                $ids = explode(',', $matches[2]);
                $query[$matches[1]] = $ids;
            }
        }
        return $query;
    }
}