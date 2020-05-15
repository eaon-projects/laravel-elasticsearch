<?php

namespace App\Console\Commands;

use App\EsModel\BaseModel;
use App\Extensions\StopWatch;
use App\Model\Product;
use Illuminate\Console\Command;

class PushToElasticsearch extends Command
{

    const CHUNK_SIZE = 100;
    const MAX_INT = 999999999;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:pushData {--update-mapping} {--ids=} {--change-alias}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    private $esProduct = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->esProduct = new \App\EsModel\Product();
        $this->esProduct->resetTimeout(1200, 60);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $changeAlias = $this->option('change-alias');

        // 是否 更新索引
        if ($changeAlias) {
            $version = time();
            $this->esProduct->changeVersion($version);
        }

        // 是否更新索引结构
        $updateMapping = $this->option('update-mapping');
        $this->esProduct->updateMapping($updateMapping);

        $ids = $this->option('ids');
        $query = $this->buildQuery([
            'ids' => $ids
        ]);
        $total = $query->count();
        $times = $total / self::CHUNK_SIZE;
        for ($i = 0; $i < $times; $i++) {
            try {
                $this->pushIndex($i * self::CHUNK_SIZE, self::CHUNK_SIZE, $query);
            } catch (\Exception $e) {
                $error = sprintf("Error on line %s in %s: %s\n", $e->getLine(), $e->getFile(), $e->getMessage());
                echo $error;
                logger('es:push-product:error:' . $error);
            }
        }
        $ids = $this->esProduct->afterPush();
        if ($ids) {
            echo 'Delete lost item:' . json_encode($ids);
        }
        //指定商品同步，判断下架
        $this->offLine();
    }

    /**
     * 当前 为 sql 直接查询 准备压入es
     * @param $params
     * @return mixed
     */
    protected function buildQuery($params)
    {

        $query = Product::with('images:id,product_id,image,sort')
            ->with('skus.image')
            ->with('skus:id,product_id,sku,inventory,price,grams,compare_at_price,product_image_id,feed_id,status,sort');

        // 如果有 id 就 只用查询 并 更新es 内相关产品的 id 信息
        if ($params['ids']) {
            $query->whereIn('id', explode(',', $params['ids']));
        }

        return $query;
    }

    protected function pushIndex($start = 0, $limit = 1000, $model)
    {
        $harbor = StopWatch::make()->start();
        $model->skip($start)->take($limit);

        $products = $model->get()->toArray();
        echo "query time:" . $harbor->stop()->diff() . "ms\n";

        $productIndexes = [];
        $count = 0;
        $ids = [];

        $harbor->start();
        $index = $this->esProduct->getIndex(null, BaseModel::GET_INDEX_REAL);
        foreach ($products as &$product) {
            $body = $this->esProduct->wrapContent($product);
            $productIndexes[] = [
                'index' => [
                    '_index' => $index,
                    '_id' => $product['id']
                ]
            ];
            $productIndexes[] = $body;
            $ids[] = $body['id'];
            $count++;
        }
        unset($products);
        echo "process time:" . $harbor->stop()->diff() . "ms\n";

        $harbor->start();
        try {
            if ($count < 1) {
                //可能在筛选下架的商品的时候被筛掉的
                return;
            }
            $result = $this->esProduct->getElasticClient()->bulk([
                'body' => $productIndexes
            ]);
            if ($result['errors']) {
                foreach ($result['items'] as $item) {
                    if (!in_array($item['index']['status'], [
                        '200',
                        '201'
                    ])) {
                        print_r($item['index']);
                    }
                }
            }
            unset($result);
        } catch (\Exception $ex) {
            echo "bulk insert:" . $ex->getMessage() . "\n";
            logger($ex);
        }
        echo "push time:" . $harbor->stop()->diff() . "ms\n";
        echo "success:" . $start . "-" . ($start + $count) . " \n";
    }

    // 指定id 后 删除指定id 中 已下架 的 在中删除
    public function offLine()
    {
        if ($ids = $this->option('ids')) {
            $products = Product::whereIn('status', [Product::STATUS_OFFLINE, Product::STATUS_DELETE])
                ->whereIn('id', explode(',', $ids))
                ->pluck('id')
                ->toArray();
            if (!empty($products)) {
                $this->esProduct->deleteProduct($products);
            }
        }
    }
}
