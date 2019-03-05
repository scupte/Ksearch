<?php
namespace Ksearch;
use think\Db;
use think\Cache;
class Ksearchsql
{
	protected $gjc =[];
	protected $gjc1=[];
	protected $max =1;
	protected $index              = null;
	protected $dbh                = null;
	protected $primaryKey         = null;
	protected $excludePrimaryKey  = true;
	public $stemmer               = null;
	public $tokenizer             = null;
	public $stopWords             = [];
	public $filereader            = null;
	public $config                = [];
	protected $query              = "";
	protected $wordlist           = [];
	protected $inMemoryTerms      = [];
	protected $decodeHTMLEntities = false;
	public $disableOutput         = false;
	public $inMemory              = true;
	public $steps                 = 1000;
	public $indexName             = "";
	public $statementsPrepared    = false;
	public $kdbinfo=[];
	function __construct($config='')
	{
		$this->config            = $config;
		$this->index = Db::connect([
			 // 数据库类型
			'type'            => 'mysql',

			'hostname'        => '127.0.0.1',

			'database'        => 'ksearch',

			'username'        => 'root',

			'password'        => 'root',

			'hostport'        => '3306',

			]);

	}
 	/*载入配置
 	*/
 	/**
     * @param array $config
     */
 	public function loadConfig(array $config)
 	{
 		

 	}
 	public function se(){
 		$db=Db::connect([
 			'type'           => 'sqlite',
 			'database'       => 'jblog.db', 
 			'prefix'         => 'prefix_',
 			'debug'          => true,
 			'charset'     => 'utf8',
 			]);

 		$res=$db->table('wordlist')->find();
 		return $res;

 	}

 	/*新建数据库*/
 	public function createIndex($indexName)
 	{
 		$this->indexName = $indexName;

	/*if (file_exists($this->config['storage'])) {
		unlink($this->config['storage']);
	}*/

	
	$this->index->execute("CREATE TABLE IF NOT EXISTS wordlist (
		id INTEGER PRIMARY KEY,
		term TEXT UNIQUE COLLATE nocase,
		num_hits INTEGER,
		num_docs INTEGER)");

	$this->index->execute("CREATE UNIQUE INDEX 'main'.'index' ON wordlist ('term');");

	$this->index->execute("CREATE TABLE IF NOT EXISTS doclist (
		term_id INTEGER,
		doc_id INTEGER,
		hit_count INTEGER)");

	$this->index->execute("CREATE TABLE IF NOT EXISTS fields (
		id INTEGER PRIMARY KEY,
		name TEXT)");

	$this->index->execute("CREATE TABLE IF NOT EXISTS hitlist (
		term_id INTEGER,
		doc_id INTEGER,
		field_id INTEGER,
		position INTEGER,
		hit_count INTEGER)");

	$this->index->execute("CREATE TABLE IF NOT EXISTS info (
		key TEXT,
		value INTEGER)");

	$this->index->execute("INSERT INTO info ( 'key', 'value') values ( 'total_documents', 0)");

	$this->index->execute("CREATE INDEX IF NOT EXISTS 'main'.'term_id_index' ON doclist ('term_id' COLLATE BINARY);");
	
	return $this;

}
public function kquery($sql=''){
	
	$gjc=$this->index->query("SELECT id,term,num_docs,num_hits FROM wordlist LIMIT 10");
	$gjc1=$this->index->query("SELECT term FROM wordlist LIMIT 10");
	
	$max=$this->index->query("SELECT id FROM wordlist  ORDER BY id desc LIMIT 1 ");
	if ($max) {
		$this->max=$max[0]['id']+1;
	}
	//Cache::set('ksy_gjc',$gjc,3600);
	$this->gjc=	$gjc;
	$this->gjc1=$gjc1;
	if (!$sql) {
		return false;
	}else{
		$this->kdbinfo=Db::query($sql);
		return $this->kdbinfo;
	}

}
/*插入索引*/
public function kinsert(){

	if (!$this->kdbinfo) {
		return false;
	}
	$this->index->transaction(function () {
		foreach ($this->kdbinfo as $key => $value) {
			$id=$value['id'];
			$text=$value['title'].$value['content'];
			/*数据分词*/
			$data=$this->tokenize($text);
			$data=json_decode($data,true);
			$words=[];
			if (!$data) {
				$words=['图片'];
			}else{
				foreach ($data as $ke => $valu) {
					$words[]=$valu['t'];
				}
			}

			$words=array_count_values($words);

			/*插入索引*/
			foreach ($words as $k => $val) {
 				//dump($this->kdbinfo);
				$termid='';
				$term_id=$this->index->query("SELECT * FROM wordlist WHERE term ='".$k."' LIMIT 1");
				if ($term_id) {
					$term_id=$term_id[0]?$term_id[0]:'';
				}
				if (!$term_id) {
					$this->index->execute("INSERT INTO wordlist (term, num_hits, num_docs) VALUES ('".$k."', $val, 1);");
					$term_id=$this->index->query("SELECT * FROM wordlist WHERE term ='".$k."' LIMIT 1");
					$termid=$term_id[0]['id'];;
				}else{

					$termid=$term_id['id'];
					$num_docs=$term_id['num_docs'] + 1;
					$num_hits=$term_id['num_hits'] + $val;
					$this->index->execute("UPDATE wordlist SET num_docs = $num_docs, num_hits = $num_hits WHERE id= $termid;");

				}
				/*$termid 关键词 id*/
				$termid=$this->index->execute("INSERT INTO doclist (term_id, doc_id, hit_count) VALUES ($termid, $id, $val);");

			}

		}

	});


}
/*拆分中文字符串*/
public function tokenize($text, $stopwords = []) 
{ 
	$text  = mb_strtolower(filter_mark(htmla($text)));

	$rr=pullword("source=".$text."&param1=0&param2=1&json=1");
	return $rr;

}


public function pl(){


	if (!$this->kdbinfo) {
		return false;
	}
	$this->index->transaction(function () {
	foreach ($this->kdbinfo as $key => $value) {
		$id=$value['id'];
		$text=$value['tag'].$value['title'].$value['content'];
		/*数据分词*/
		$data=$this->tokenize($text);
		$data=json_decode($data,true);
		$words=[];
		if (!$data) {
			$words=['图片'];
		}else{
			foreach ($data as $ke => $valu) {
				$words[]=$valu['t'];
			}
		}
			$winstdata=[];
			$wupdata=[];
			$dinstdata=[];
		$words=array_count_values($words);

		foreach ($words as $k => $val) {

			$termid='';
			$term_id=$this->index->query("SELECT * FROM wordlist WHERE term ='".$k."' LIMIT 1");
			if ($term_id) {
				$term_id=$term_id[0]?$term_id[0]:'';
			}
			if ($term_id) {

				$num_docs=$term_id['num_docs'] + 1;
				$num_hits=$term_id['num_hits'] + $val;
				$termid=$term_id['id'];;
				$wupdata['num_docs'][]="when id = ".$termid."  then ".$num_docs."  

				";
				$wupdata['num_hits'][]="when id = ".$termid."  then ".$num_hits."  

				";
				$wupdata['id'][]=$termid;
			}else{

				$winstdata[]="(".$this->max." ,'".$k."',".$val.",1)";
				$termid=$this->max;
				$this->max=$this->max +1;
			}
			$dinstdata[]="(".$termid." ,".$id.",".$val.")";


		}

	if ($winstdata) {
		$sqp=implode(',', $winstdata);
		$sql="INSERT INTO wordlist VALUES ".$sqp.";";
		$this->index->execute($sql);
	}
	
	if ($wupdata) {
		$sqp=implode('
			', $wupdata['num_docs']);
		$sql="UPDATE  wordlist  SET num_docs =
		case
		".$sqp."
		end   
		WHERE id IN (".implode(',', $wupdata['id']).");";
		$this->index->execute($sql);

		$sqp=implode('
			', $wupdata['num_hits']);
		$sql="UPDATE  wordlist  SET num_hits =
		case
		".$sqp."
		end   
		WHERE id IN (".implode(',', $wupdata['id']).");";

		$this->index->execute($sql);

	}

	if ($dinstdata) {
		$sqp=implode(',',$dinstdata);

		$sql="INSERT INTO doclist (term_id, doc_id, hit_count) VALUES ".$sqp.";";
	
		$this->index->execute($sql);
	}

	}
});

	return 8;
}

}
