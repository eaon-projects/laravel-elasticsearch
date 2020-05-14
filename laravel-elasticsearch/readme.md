#### 本项目是基于 laravel 5.5 + elasticsearch 7.6.2 的一个搜索项目

#### 环境要求
- php7.0+
- nginx 1.8+
- mysql 5.7+
- linux

#### 导入产品数据
mysql 导入 /database/products.sql

#### 推送产品表的数据到 Es
| 命令  |  说明 | 
| ---  | ---   |
| `php artisan es:pushData --update-mapping --change-alias` ` | 推数据到es |

#### 关于Es查询使用

Es 查询通过访问 /productindex 后添加参数进行查询

##### 查询路由
使用 /productindex?参数进行查询

| 参数  |  说明 | demo |
| ---  | ---   | ---  |
| keyword | 关键字查询: 支持查询 id name spu 查询 | /productindex?keyword=123
| filters[must] | 交集查询: id name spu handle 等等的 交集查询 | /productindex?filters[must]=id=123&#124;name=red
| filters[should] | 并集查询: id name spu handle 等等的 交集查询 | /productindex?filters[must]=id=123&#124;name=red
| sorts | 排序: +/-id +/-inventory +/-price 等等排序 | /productindex?sorts=id&#124;-price
| sorts=id(2324643,2324818) | 指定 id 排序 | /productindex?sorts=id(2324643,2324818)
| with | 追加 附加参数 例如 skus,image 等参数 | /productindex?with=skus,image

