<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductIndexController extends BaseController
{
    // productindex 接口参数说明, 字读参考上述结构体
    //
    // 排序参数: sorts
    // 示例: sorts=-statistic.sold|+price
    // 说明: 字段按竖线分割, 按销量倒序后 再按价格正序
    //
    // 筛选参数: filters[must]
    // 示例: filters[must]=id=1,2,3|categories.id=1:2:3|exclude_price=12|price=(10,59]|match_name=Dresses|specification_Style=Cool
    // 说明:
    // 1、filters[must] 代表字段间关系为且,  还支持filters[should] 代表字段间关系为或。
    // 2、id=1,2,3 筛选id字段, 逗号代表关系为或(通用规则)。 即匹配 id=1 或者 id=2 或者 id=3。
    // 3、categories.id=1:2:3  筛选分类id字段, 冒号代表关系且(通用规则)。即匹配 categories.id=1 且 categories.id=2 且 categories.id=3。
    // 4、exclude_price=12  筛选价格字段, exclude_为否定关键字(通用规则)。 即匹配 价格不为12的。
    // 5、price=(10:59]  价格区间筛选, 左括号为大于(通用规则), 右中括号为小于等于((通用规则)。 即匹配价格大于10 且 小于等于59。
    // 6、match_name=Dresses  名称包含筛选, match_为包含关键字(通用规则), 同理不包含为exclude_match_ 。 即匹配名称包含Dresses。
    // 7、specification_Style=Cool  属性筛选, 匹配specification.title 且 specification.value=Cool。 options 和 specification 支持此类筛选规则
    //
    // 全文检索参数: keywords
    // 示例: keywords=Dresses
    // 说明: 从id、name、spu 字段中做匹配 返回相识度最低0.8
    //
    //
    // 备注: 使用exclude_关键字时 逗号和冒号规则相反

    /**
     * @api {get} /productindex 获取商品列表
     * @apiGroup Product
     *
     * @apiParam {String} with    指定返回内容包含项，多个用逗号隔开 例:categories,specification,options,images,skus,collection_sort
     * @apiParam {String} sorts    排序规则，多个用竖线隔开 例: -statistic.sold|+price 按销量倒序后 再按价格正序
     * @apiParam {String} filters[must]  且关系筛选，多个用竖线隔开 例: filters[must]=id=1,2,3|categories.id=1:2:3
     * @apiParam {String} filters[should]  或关系筛选，多个用竖线隔开 例: filters[should]=id=1,2,3|categories.id=1:2:3
     * @apiParam {String} keywords  全文检索，从id、name、spu 字段中做匹配 返回相识度最低0.8
     * @apiParam {Number} collection_id  对应商品集合id
     * @apiParam {Number[1-200]} limit=10  每页数量
     * @apiParam {Number} page=1   页码
     * @apiParamExample {json} Request-Example:
     *  {
     *    "with": "categories,specification,options,images,skus,automatic_discount,collection_sort",
     *    "sorts": "-statistic.sold|+price",
     *    "filters[must]": "id=1,2,3|categories.id=1:2:3",
     *    "filters[should]": "id=1,2,3|categories.id=1:2:3",
     *    "keywords": "Dresses",
     *    "collection_id": 1,
     *    "limit": 10,
     *    "page": 1
     *  }
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 Ok
     */
    public function index(Request $request, ProductService $service)
    {
        $collection = $extend = [];
        $query = $this->getOriginQueryParams();
//      供给查询集合使用
//        if (isset($query['collection_handle'])) {
//            $collection = Collection::findWithImage($query['collection_handle']);
//            if (empty($collection)) {
//                $this->response->errorNotFound();
//            }
//            $extend['collection'] = $collection;
//        }
        // xxx 感觉放到中间件里更好
        $params = $request->all();
        $query['sorts'] = array_get($params, 'sorts', '');
        $query['filters'] = array_get($params, 'filters', []);
        $query['collection_sorts'] = data_get($collection, 'sort_value', '');
        $query['collection_filters'] = data_get($collection, 'filter_value', '');
//        $query['sorts'] = $service->sortValue( $query['sorts'] , array_merge( $collection, $query ) );
//        if(!empty($query['sorts'])) $query['collection_sorts'] = '';
        $query = array_merge($query, $this->getOffset(true));
        $query['with'] = $this->getWith();

        $result = $service->get($query);
        $list = $this->returnList($result['products'], $result['total']);
        $extend['filter'] = $result['filter'];

        return response()->json(array_merge($list, $extend));
    }
}
