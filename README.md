# Ksearch
thinkphp分词全文检索全文搜素，利用PullWord分词接口。借鉴TNTSearch数据库思路。精确匹配结果。


简单调用 建议分页查询，每篇长文章，几千词汇不是闹着玩的。
# **[Ksearch](https://github.com/scupte/Ksearch)**

安装 htmlpurifier
 **[htmlpurifier](https://github.com/ezyang/htmlpurifier)**

$ composer require ezyang/htmlpurifier
```
<?php
namespace app\search\controller;
use think\Controller;
use think\Request;
use think\Db;
use Ksearch\Ksearchmysql;
use think\facade\Cache;
use think\facade\Debug;
ini_set('max_execution_time',0);
class Index extends Controller
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {

        $config = [ 
        'storage' => '../Ksearch.db',
        ];
       //$Ksearch=new Ksearch($config);
/*
查询还没建立索引的文章，简单的分页可能有遗漏
*/
     $res=$db->query("SELECT distinct doc_id as id FROM doclist;");
     $as=[];
     foreach ($res as $key => $value) {
        $as[]=$value['id'];
    }
    $kk=Db::table('ks_weibo')->field('id')->select();
    $asb=[];
    foreach ($kk as $key => $value) {
        $asb[]=$value['id'];
    }
    $sas=array_diff($asb, $as);
    $start=0;
    /*分页13条*/
    $article = array_slice($sas,$start,13);
    $s = implode(',', $article);
    $Ksearch=new Ksearchsql();
    $Ksearch->kquery('SELECT A.id as id,A.title as title,A.tag as tag,UNCOMPRESS(B.content) as content  from ceshi A left join content B  on A.id= B.wid WHERE A.id IN ('.$s.');');       
        $res=$Ksearch->kinsert();
        Cache::inc('name');
        return json($s);
    }
    public function addcahe()
    {
     Cache::set('name',1,3600);
    }
/**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read()
    {
       $config = [ 
       'storage' => '../Ksearch.db',
       ];

         $Ksearch=new Ksearchsql();
       $text='每篇几千词汇的文章，每次都要查询数据库';
       Debug::remark('begin');
       $res=$Ksearch->search($text);
       Debug::remark('end');
       if (!$res) {
           return "0";
       }
       echo Debug::getRangeTime('begin','end').'s';
       echo "<pre>";
       print_r($res);
       echo "</pre>";


   }
}
 ```
每篇几千词汇的文章，每次都要查询数据库，耗时。
简单处理思路。
## 1 索引词汇表 wordlist 文件缓存  Cache::set('gjc',1);
```
//键值对互转
if (Cache::get('gjc')) {
	$gjc=[];
	 $res=$db->query("SELECT  id,term FROM wordlist ;");
	 if ($res) {
	 	foreach ($res as $key => $value) {
	 		$gjc[$value['term']]=$key;
	 	}
	 }
	 Cache::set('gjc',$gjc);
}
//缓存关键词表
if (Cache::get('wordlist')) {

	 $res=$db->query("SELECT  id,term, num_hits, num_docs FROM wordlist ;");

	 Cache::set('wordlist',$res);
}

```
## 2 自行修改 [Ksearchmysql.php](https://github.com/scupte/Ksearch/blob/master/Ksearchmysql.php "Ksearchmysql.php") 文件中 pl() 批量操作 3 次写入函数
			$winstdata=[];
			$wupdata=[];
			$dinstdata=[];
上3 变量最好放到
```
 foreach ($this->kdbinfo  as $key => $value)
```
   上面
```
foreach ($this->kdbinfo as $key => $value) {} ////里面逻辑自行修改
```
```
  /*pullword分词*/
  function pullword($postData)
{
  $ch = curl_init();
  curl_setopt($ch,CURLOPT_URL,'http://120.26.6.172/get.php?'.$postData);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
  curl_setopt($ch,CURLOPT_HEADER, false);
  //curl_setopt($ch, CURLOPT_POST, count($postData));
  //curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  $output=curl_exec($ch);
  curl_close($ch);
  return $output;
}

  function htmla($value) {

    $config = HTMLPurifier_Config::createDefault();

    $config->set('HTML.Allowed', '');

    $purifier = new HTMLPurifier($config);

    $clean_html = $purifier->purify($value);

    return ($clean_html);

  }
//去标点等无用符号
  function filter_mark($text){
    if(trim($text)=='')return '';
    $text=preg_replace("/[[:punct:]\s]/",' ',$text);
    $text=urlencode($text);
    $text=preg_replace("/(%7E|%60|%21|%40|%23|%24|%25|%5E|%26|%27|%2A|%28|%29|%2B|%7C|%5C|%3D|\-|_|%5B|%5D|%7D|%7B|%3B|%22|%3A|%3F|%3E|%3C|%2C|\.|%2F|%A3%BF|%A1%B7|%A1%B6|%A1%A2|%A1%A3|%A3%AC|%7D|%A1%B0|%A3%BA|%A3%BB|%A1%AE|%A1%AF|%A1%B1|%A3%FC|%A3%BD|%A1%AA|%A3%A9|%A3%A8|%A1%AD|%A3%A4|%A1%A4|%A3%A1|%E3%80%82|%EF%BC%81|%EF%BC%8C|%EF%BC%9B|%EF%BC%9F|%EF%BC%9A|%E3%80%81|%E2%80%A6%E2%80%A6|%E2%80%9D|%E2%80%9C|%E2%80%98|%E2%80%99|%EF%BD%9E|%EF%BC%8E|%EF%BC%88)+/",' ',$text);
    $text=urldecode($text);
    return trim($text);
  } 
```
