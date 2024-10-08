# excel
Yii2 excel导入导出类
引入

```
use xinqingch\excel\Excel;
```

## Demo

```
// [名称, 字段名, 类型, 类型规则]
$header = [
    ['ID', 'id', 'text'],
    ['手机号码', 'mobile'], // 规则不填默认text
    ['openid', 'fans.openid', 'text'],
    ['昵称', 'fans.nickname', 'text'],
    ['关注/扫描', 'type', 'selectd', [1 => '关注', 2 => '扫描']],
    ['性别', 'sex', 'function', function($model){
        return $model['sex'] == 1 ? '男' : '女';
    }],
    ['创建时间', 'created_at', 'date', 'Y-m-d'],
    ['图片', 'image', 'text'],// 本地图片 ./images/765456898612.jpg
];

$list = [
    [
        'id' => 1,
        'type' => 1,
        'mobile' => '18888888888',
        'fans' => [
            'openid' => '123',
            'nickname' => '昵称',
        ],
        'sex' => 1,
        'create_at' => time(),
    ]
];
```

### 导出

```
// 简单使用
return Excel::exportData($list, $header);

// 定制 默认导出xlsx 支持 : xlsx/xls/html/csv， 支持写入绝对路径
return Excel::exportData($list, $header, '测试', 'xlsx', '/www/data/');

// 另外一种导出csv方式
return Excel::exportCsvData($list, $header);

// 带图片的 
* @param array $list   数据
* @param array $header 数据处理格式
* @param string $filename  导出的文件名
* @param string $suffix    导出的格式
* @param string $path      导出的存放地址 无则不在服务器存放
* @param string $image    导出的格式 可以用 大写字母 或者 数字 标识 哪一列
Excel::exportData($list, $header,date('Y-m-d h:i:s'),'xlsx','',['D','E']);
Excel::exportData($list, $header,date('Y-m-d h:i:s'),'xlsx','',[4,5]);

//多工作表导出
//['keyword',50,'FF0000'] 分别代表KEY，单元格宽度，单元格背景色
$header =[
            '关键词'=>['keyword',50,'FF0000'],
            '关键词数量'=>'keynum',
            '数量占比'=>'keynum_rate',
            '关键词流量总和'=>'keysearch',
            '流量占比'=>'keysearch_rate'

        ];
$list = [
    [
        'keyword' => 'keyword name',
        'keynum' => 1,
        'keynum_rate' => '3%',
        'keysearch' => '232',
        'keysearch_rate' => '1%',
    ]
];
$expdata=[];
$expdata[] = $execl->exportLink('关键词词库',$header,$list);//词库数据转换
//多个表可以设置多个$expdata[]即可
/**
 * 导入
 *
 * @param $expdata     生成数据
 * @param $path  保存地址，如空可使用流式直接输出
 * @param $filename  文件名称
 * @param string $suffix    导出的格式 默认支持xlsx,xls,csv
 * @return array|bool|mixed
 */
$execl = new Excel();
$execl->xtExport($expdata,$path,$filename,'xlsx');

```

### 导入

```
/**
 * 导入
 *
 * @param $filePath     excel的服务器存放地址 可以取临时地址
 * @param int $startRow 开始和行数 默认1
 * @param bool $hasImg  导出的时候是否有图片
 * @param string $suffix    格式
 * @param string $imageFilePath     作为临时使用的 图片存放的地址
 * @return array|bool|mixed
 */
$data = Excel::import($filePath, $startRow = 1,$hasImg = false,$suffix = 'Xlsx',$imageFilePath = null);
```

### 问题反馈

在使用中有任何问题，欢迎反馈给我，可以用以下联系方式跟我交流
QQ：8818190
