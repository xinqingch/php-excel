<?php

namespace xinqingch\excel;

use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * 导出导入Excel
 *
 * Class Excel
 * @package xinqingch\excel
 * @author morven<8818190@qq.com>
 */
class Excel
{
    /**
     * 导出Excel
     *
     * @param array $list 数据
     * @param array $header 数据处理格式
     * @param string $filename 导出的文件名
     * @param string $suffix 导出的格式
     * @param string $path 导出的存放地址 无则不在服务器存放
     * @param string $image 导出的格式 可以用 大写字母 或者 数字 标识 哪一列
     * @return bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public static function exportData(
        $list = [],
        $header = [],
        $filename = '',
        $suffix = 'xlsx',
        $path = '',
        $image = [],
        $title ='工作文档'
    ) {
        if (!is_array($list) || !is_array($header)) {
            return false;
        }

        // 清除之前的错误输出
        ob_end_clean();
        ob_start();

        !$filename && $filename = time();

        // 初始化
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 设置表格名称
        $sheet->setTitle($title);
        // 写入头部
        $hk = 1;
        foreach ($header as $k => $v) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($hk) . '1', $v[0]);
            $hk += 1;
        }

        // 开始写入内容
        $column = 2;
        $size = ceil(count($list) / 500);
        for ($i = 0; $i < $size; $i++) {
            $buffer = array_slice($list, $i * 500, 500);

            foreach ($buffer as $k => $row) {
                $span = 1;

                foreach ($header as $key => $value) {
                    //dump($value);exit;
                    // 解析字段
                    $realData = self::formatting($header[$key], trim(self::formattingField($row, $value[1])), $row);
                    // 写入excel
                    $rowR = Coordinate::stringFromColumnIndex($span);
                    $sheet->getColumnDimension($rowR)->setWidth(isset($value[3])?$value[3]:20);
                    $sheet->getDefaultRowDimension()->setRowHeight(isset($value[4])?$value[4]:20);
                    $sheet->getStyle($rowR)->getAlignment()->setWrapText(true);
                    if (in_array($span, $image) || in_array($rowR, $image)) { // 如果这一列应该是图片
                        if (file_exists($realData)) { // 本地文件
                            $drawing = new Drawing();
                            $drawing->setName('image');
                            $drawing->setDescription('image');
                            try {
                                $drawing->setPath($realData);
                            } catch (\Exception $e) {
                                echo $e->getMessage();
                                echo '<br>可能是图片丢失了或者无权限';
                                die;
                            }

                            $drawing->setWidth(80);
                            $drawing->setHeight(80);
                            $drawing->setCoordinates($rowR . $column);//A1
                            $drawing->setOffsetX(12);
                            $drawing->setOffsetY(12);
                            $drawing->setWorksheet($spreadsheet->getActiveSheet());
                        } else { // 可能是 网络文件
                            $img = self::curlGet($realData);
                            $file_info = pathinfo($realData);
                            $extension = $file_info['extension'];// 文件后缀
                            $dir = '.' . DIRECTORY_SEPARATOR . 'execlImg' . DIRECTORY_SEPARATOR . \date('Y-m-d') . DIRECTORY_SEPARATOR;// 文件夹名
                            $basename = time() . mt_rand(1000, 9999) . '.' . $extension;// 文件名
                            is_dir($dir) or mkdir($dir, 0777, true); //进行检测文件夹是否存在
                            file_put_contents($dir . $basename, $img);
                            $drawing = new Drawing();
                            $drawing->setName('image');
                            $drawing->setDescription('image');
                            try {
                                $drawing->setPath($dir . $basename);
                            } catch (\Exception $e) {
                                echo $e->getMessage();
                                echo '<br>可能是图片丢失了或者无权限';
                                die;
                            }

                            $drawing->setWidth(80);
                            $drawing->setHeight(80);
                            $drawing->setCoordinates($rowR . $column);//A1
                            $drawing->setOffsetX(12);
                            $drawing->setOffsetY(12);
                            $drawing->setWorksheet($spreadsheet->getActiveSheet());
                        }
                    } else {
                        // $sheet->setCellValue($rowR . $column, $realData);
                        // 写入excel
                        $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($span) . $column, $realData, DataType::TYPE_STRING);
                    }


                    $span++;
                }

                $column++;
                unset($buffer[$k]);
            }
        }

        // 直接输出下载
        switch ($suffix) {
            case 'xlsx' :
                $writer = new Xlsx($spreadsheet);
                if (!empty($path)) {
                    $writer->save($path);
                } else {
                    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=utf-8;");
                    header("Content-Disposition: inline;filename=\"{$filename}.xlsx\"");
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                    exit();
                }


                break;
            case 'xls' :
                $writer = new Xls($spreadsheet);
                if (!empty($path)) {
                    $writer->save($path);
                } else {
                    header("Content-Type:application/vnd.ms-excel;charset=utf-8;");
                    header("Content-Disposition:inline;filename=\"{$filename}.xls\"");
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                    exit();
                }


                break;
            case 'csv' :
                $writer = new Csv($spreadsheet);
                if (!empty($path)) {
                    $writer->save($path);
                } else {
                    header("Content-type:text/csv;charset=utf-8;");
                    header("Content-Disposition:attachment; filename={$filename}.csv");
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                    exit();
                }


                break;
            case 'html' :
                $writer = new Html($spreadsheet);
                if (!empty($path)) {
                    $writer->save($path);
                } else {
                    header("Content-Type:text/html;charset=utf-8;");
                    header("Content-Disposition:attachment;filename=\"{$filename}.{$suffix}\"");
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                    exit();
                }


                break;
        }

        return true;
    }

    /**
     * 导出的另外一种形式(不建议使用)
     *
     * @param array $list
     * @param array $header
     * @param string $filename
     * @return bool
     */
    public static function exportCsvData($list = [], $header = [], $filename = '')
    {
        if (!is_array($list) || !is_array($header)) {
            return false;
        }

        // 清除之前的错误输出
        ob_end_clean();
        ob_start();

        !$filename && $filename = time();

        $html = "\xEF\xBB\xBF";
        foreach ($header as $k => $v) {
            $html .= $v[0] . "\t ,";
        }

        $html .= "\n";

        if (!empty($list)) {
            $info = [];
            $size = ceil(count($list) / 500);

            for ($i = 0; $i < $size; $i++) {
                $buffer = array_slice($list, $i * 500, 500);

                foreach ($buffer as $k => $row) {
                    $data = [];

                    foreach ($header as $key => $value) {
                        // 解析字段
                        $realData = self::formatting($header[$key], trim(self::formattingField($row, $value[1])), $row);
                        $data[] = str_replace(PHP_EOL, '', $realData);
                    }

                    $info[] = implode("\t ,", $data) . "\t ,";
                    unset($data, $buffer[$k]);
                }
            }

            $html .= implode("\n", $info);
        }

        header("Content-type:text/csv");
        header("Content-Disposition:attachment; filename={$filename}.csv");
        echo $html;
        exit();
    }

    /**
     * 多sheet的导出
     * @author chaihao <158533752@qq.com>
     * @param [array] $data_array
     */
    public  function xtExport(array $dataArray,$path='',$filename,$suffix='xlsx'): string
    {
       // $startTime = microtime(true);
        $spreadsheet = new Spreadsheet();
        foreach ($dataArray as $key => $data) {
            $this->opSheet($spreadsheet, $key, $data);
        }

        !$filename && $filename = time();
        //header('Content-Type: application/vnd.ms-excel');
        //header('Content-Disposition: attachment;filename="' . $name . '.xlsx"');
        //header('Cache-Control: max-age=0');
        // $writer = new Xlsx($spreadsheet);
        // $writer->save('php://output');
        // //删除清空：
        // $spreadsheet->disconnectWorksheets();
        // unset($spreadsheet);
        // exit;
        //$md5 = md5(serialize($dataArray));
        // if(!empty($path)){
        //     if (!file_exists($path)) {
        //         mkdir($path, 0777, true);
        //     }
        // }

        //$path = $path .$md5 . '.'.$suffix;
        //$path=
        // 导出Excel文件
// 直接输出下载

        switch ($suffix) {
            case 'xlsx' :
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                //$writer = new Xlsx($spreadsheet);
                if (!empty($path)) {
                    $writer->save($path);
                } else {
                    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=utf-8;");
                    header("Content-Disposition: inline;filename=\"{$filename}.xlsx\"");
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                    exit();
                }


                break;
            case 'xls' :
                $writer = IOFactory::createWriter($spreadsheet, 'Xls');
                //$writer = new Xls($spreadsheet);
                if (!empty($path)) {
                    $writer->save($path);
                } else {
                    header("Content-Type:application/vnd.ms-excel;charset=utf-8;");
                    header("Content-Disposition:inline;filename=\"{$filename}.xls\"");
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                    exit();
                }


                break;
            case 'csv' :
                $writer = IOFactory::createWriter($spreadsheet, 'Csv');
                //$writer = new Csv($spreadsheet);
                if (!empty($path)) {
                    $writer->save($path);
                } else {
                    header("Content-type:text/csv;charset=utf-8;");
                    header("Content-Disposition:attachment; filename={$filename}.csv");
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                    exit();
                }


                break;
            case 'html' :
                $writer = IOFactory::createWriter($spreadsheet, 'Html');
                //$writer = new Html($spreadsheet);
                if (!empty($path)) {
                    $writer->save($path);
                } else {
                    header("Content-Type:text/html;charset=utf-8;");
                    header("Content-Disposition:attachment;filename=\"{$filename}.{$suffix}\"");
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                    exit();
                }


                break;
        }

        if (!empty($path)){
            return $path;
        }else{
            return true;
        }
        //$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        //$writer->save($filename);

    }


    /**
     *  设定EXCEL表头
     * @param integer $num
     * @return array
     * @author: chaihao <158533752@qq.com>
     */
    public function getExcelHeader($num = 100)
    {
        $col = range('A', 'Z');
        $newCol = [];

        for ($j = 0; $j < ceil($num / 26); $j++) {
            for ($i = 0; $i < 26; $i++) {
                if (count($newCol) >= $num) {
                    break 2;
                }
                $newCol[] = ($j == 0 ? '' : $col[$j - 1]) . $col[$i];
            }
        }
        return $newCol;
    }


   /**
     * 处理多sheet
     * @param [type] $spreadsheet
     * @param [type] $n
     * @param [type] $data
     */
    public function opSheet($spreadsheet, $n, $data)
    {
        //$start = microtime(true);
        $spreadsheet->createSheet(); //创建sheet
        $sheet = $spreadsheet->setActiveSheetIndex($n)->setTitle($data['title']); //设置当前的活动sheet,设置sheet的名称
        // $keys = $data['rows'][0]; //这是你的数据键名
        // $count = count($keys); //计算你所占的列数
        $count = count($data['rows'][0]); //计算你所占的列数
        $infoNum = count($data['info'] ?? []); //求k-v值的所占行数, 多表头占用行
        $total = count($data['rows']);//总行数
        //dump($total);
        $infoStart = $infoNum + 1; //下面的详细信息的开始行数
        // 假设 getExcelHeader 返回一个列名的数组（如 ['A', 'B', 'C', ...]）
        $cellNames = $this->getExcelHeader($count);
        // 设置列宽和标题样式（一次性）
        foreach ($cellNames as $index => $columnName) {
            //dump($columnName);
            $sheet->getColumnDimension($columnName)->setWidth($data['width'][$index]);
            $cellName1 = $columnName.'1:'.$columnName.$total;
            //$sheet->getStyle($cellName1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);//数字格式

            if($data['color'][$index]!='FFFFFF') {

                //dump($cellName1);exit;
                $sheet->getStyle($cellName1)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($data['color'][$index]);//单元格背景色
            }
            //$sheet->getColumnDimension($columnName)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);//数字格式
            // 可以在这里设置标题行的样式（如果需要）
        }
        //dump($data['color']);exit;

        //
        //$sheet = $spreadsheet->getActiveSheet($n)->setTitle($data['title']); //
        $spreadsheet->getActiveSheet($n)->getStyle($infoStart)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);//首行垂直顶部
        $spreadsheet->getActiveSheet($n)->getStyle($infoStart)->getAlignment()->setWrapText(true);
        // $spreadsheet->getActiveSheet($n)->mergeCells('A1:' . $cellName[$count - 1] . '1'); //合并单元格
        // $spreadsheet->getActiveSheet($n)->getStyle('A1')->getFont()->setSize(20); //设置title的字体大小
        $spreadsheet->getActiveSheet($n)->getStyle($infoStart)->getFont()->setBold(true); //标题栏加粗
        // $spreadsheet->setCellValue('A1', $data['title']); //设置每个sheet中的名称title

        $size = ceil($total / 500);
        for ($i = 0; $i < $size; $i++) {
            $buffer = array_slice($data['rows'], $i * 500, 500);

            foreach ($buffer as $key => $item) {
                $num = (($i+1) * 500-500)+$key;
                //dump($num);
                for ($j = 0; $j < $count; $j++) {
                    $column = $cellNames[$j] . ( $num + $infoStart);
                    $sheet->setCellValue($column, $item[$j]);
                    //$sheet->getStyle($column)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);//数字格式
                    //$sheet->getStyle($column)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // 单元格水平居中
                    //$spreadsheet->getActiveSheet($n)->getColumnDimension($cellName[$j])->setWidth($data['width'][$j]); //固定列宽
                    //if($data['color'][$j]!='FFFFFF') {
                        //
                    //}
                }
                unset($buffer[$key]);
            }
            //$end = microtime(true); // 获取结束时间
            //$executionTime = $end - $start; // 计算执行时间
            //echo "执行时间：".$executionTime." 秒</br>";

        }
        //dump($executionTime);exit;

        $spreadsheet->setActiveSheetIndex(0);//最后设置活动sheet为第一个
    }

    /**
     * 导入
     *
     * @param $filePath     excel的服务器存放地址 可以取临时地址
     * @param int $startRow 开始和行数
     * @param bool $hasImg 导出的时候是否有图片
     * @param string $suffix 格式
     * @param string $imageFilePath 作为临时使用的 图片存放的地址
     * @return array|mixed
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public static function import($filePath, $startRow = 1, $hasImg = false, $suffix = 'Xlsx', $imageFilePath = null)
    {
        if ($hasImg) {
            if ($imageFilePath == null) {
                $imageFilePath = '.' . DIRECTORY_SEPARATOR . 'execlImg' . DIRECTORY_SEPARATOR . \date('Y-m-d') . DIRECTORY_SEPARATOR;
            }
            if (!file_exists($imageFilePath)) {
                //如果目录不存在则递归创建
                mkdir($imageFilePath, 0777, true);
            }
        }
        $reader = IOFactory::createReader($suffix);
        if (!$reader->canRead($filePath)) {
            throw new Exception('不能读取Excel');
        }

        $spreadsheet = $reader->load($filePath);
        $sheetCount = $spreadsheet->getSheetCount();// 获取sheet(工作表)的数量

        // 获取所有的sheet表格数据
        $excleDatas = [];
        $emptyRowNum = 0;
        for ($i = 0; $i < $sheetCount; $i++) {
            $objWorksheet = $spreadsheet->getSheet($i); // 读取excel文件中的第一个工作表
            $data = $objWorksheet->toArray();
            if ($hasImg) {
                foreach ($objWorksheet->getDrawingCollection() as $drawing) {
                    list($startColumn, $startRow) = Coordinate::coordinateFromString($drawing->getCoordinates());
                    $imageFileName = $drawing->getCoordinates() . mt_rand(1000, 9999);
                    $imageFileName .= '.' . $drawing->getExtension();
                    $source = imagecreatefromjpeg($drawing->getPath());
                    imagejpeg($source, $imageFilePath . $imageFileName);

                    $startColumn = self::ABC2decimal($startColumn);
                    $data[$startRow - 1][$startColumn] = $imageFilePath . $imageFileName;
                }
            }
            $excleDatas[$i] = $data; // 多个sheet的数组的集合
        }

        // 这里我只需要用到第一个sheet的数据，所以只返回了第一个sheet的数据
        $returnData = $excleDatas ? array_shift($excleDatas) : [];

        // 第一行数据就是空的，为了保留其原始数据，第一行数据就不做array_fiter操作；
        $returnData = $returnData && isset($returnData[$startRow]) && !empty($returnData[$startRow]) ? array_filter($returnData) : $returnData;

        return $returnData;
    }

    private static function ABC2decimal($abc)
    {
        $ten = 0;
        $len = strlen($abc);
        for ($i = 1; $i <= $len; $i++) {
            $char = substr($abc, 0 - $i, 1);//反向获取单个字符

            $int = ord($char);
            $ten += ($int - 65) * pow(26, $i - 1);
        }

        return $ten;
    }

    /**
     * 格式化内容
     *
     * @param array $array 头部规则
     * @return false|mixed|null|string 内容值
     */
    protected static function formatting(array $array, $value, $row)
    {
        !isset($array[2]) && $array[2] = 'text';

        switch ($array[2]) {
            // 文本
            case 'text' :
                return $value;
                break;
            // 日期
            case  'date' :
                return !empty($value) ? date($array[3], $value) : null;
                break;
            // 选择框
            case  'selectd' :
                return $array[3][$value] ?? null;
                break;
            // 匿名函数
            case  'function' :
                return isset($array[3]) ? call_user_func($array[3], $row) : null;
                break;
            // 默认
            default :

                break;
        }

        return null;
    }

    /**
     * 解析字段
     *
     * @param $row
     * @param $field
     * @return mixed
     */
    protected static function formattingField($row, $field)
    {
        $newField = explode('.', $field);
        if (count($newField) == 1) {
            if (isset($row[$field])) {
                return $row[$field];
            } else {
                return false;
            }
        }

        foreach ($newField as $item) {
            if (isset($row[$item])) {
                $row = $row[$item];
            } else {
                break;
            }
        }

        return is_array($row) ? false : $row;
    }

    public static function curlGet($url)
    {
        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_HEADER, 0);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 这个是重点 请求https。
        $data = \curl_exec($ch);
        \curl_close($ch);

        return $data;
    }

    /**
     * 转换数据格式
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function exportLink($title,$header,$data = [])
    {
        if ($data) {
            //  设置 sheet 名
            $export['title'] = $title;
            $export['rows'] = [];
            $export['width']=[];
            foreach ($data as $key=>$item) {
                foreach ($header as $k=>$v){
                    $width =20;
                    $color = 'FFFFFF';
                    if(is_array($v)){
                        $result[$k]=$item[$v[0]];
                        if(isset($v[1])) {
                            $width = $v[1];
                        }
                        if(isset($v[2])){
                            $color = $v[2];
                        }
                    }else{
                        $result[$k]=$item[$v];
                    }
                    if($key==0){
                    $export['width'][] =$width;
                    $export['color'][] =$color;
                    }
                }

                if (empty($export['rows'])) {
                    // 设置表头
                    $export['rows'][] = array_keys($result);
                }
                // 内容
                $export['rows'][] = array_values($result);

            }
            //dump($export['width']);exit;
            return $export;
        } else {
            return false;
        }
    }
}