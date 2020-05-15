<?php
namespace App\Services;


use App\EsModel\Product as EsProduct;

class ProductService
{

    /**
     * @param $params
     * @return array
     */
    public function get($params)
    {
        $list = EsProduct::getInstance()->search($params, $total);
        $products = [];
        $filter = array_get($list, 'filter', []);
//        $with = array_get($params, 'with', []);
        // 获取到数据之后 对 单个 每一个单独的 product 进行 特殊处理
        foreach ($list['data'] as $product) {
//            $product = $this->addWithData($product, $with);
            $products[] = $this->format($product);
        }
        // 批量追加 产品 信息
        $this->multiDealProducts($products);
        return compact('products', 'total', 'filter');
    }

    // 处理批量追加产品信息
    public function multiDealProducts(& $products)
    {

    }

    // 格式化 product
    public function format($product){
        return $product;
    }

}