<?php
namespace Ksearch;
use think\Db;
class Ksearch
{
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
	function __construct(array $config)
	{
		$this->config            = $config;
 		//$this->config['storage'] = rtrim($this->config['storage'], '/').'/';
		if (!isset($this->config['driver'])) {
			$this->config['driver'] = "";
		}
		$this->index = Db::connect([
			'type'           => 'sqlite',
			'database'       => $this->config['storage'], 
			'prefix'         => 'prefix_',
			'debug'          => true,
			'charset'     => 'utf8',
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
 	public function search($text){

 		$data=$this->tokenize($text);

 		$data=json_decode($data,true);
 		$words=[];
 		if ($data) {
 			foreach ($data as $ke => $valu) {
 				$words[]=$valu['t'];
 			}
 		}
 		if (!$words) {
 			return false;
 		}
 		/*查询条件*/
 		$where='';
 		foreach ($words as $k => $v) {
 			if(empty($where)){
 				$where.="A.term ='".$v."'";
 			}else{
 				$where.=" OR A.term ='".$v."'";
 			}
 		}
 		/*查询排序count(PlateNumber) as bikeCounts*/
 		

 		$sql='SELECT B.doc_id as doc_id,count(B.doc_id) as doc_idCounts ,B.hit_count as hit_count from wordlist A left join doclist B
 		on A.id= B.term_id WHERE '.$where.' group by B.doc_id having count(B.doc_id)>0  ORDER BY doc_idCounts DESC  ;';
 		$res=$this->index->query($sql);
 		return $res;


 	}

 	/*新建数据库*/
 	public function createIndex($indexName)
 	{
 		$this->indexName = $indexName;

 		if (file_exists($this->config['storage'])) {
 			unlink($this->config['storage']);
 		}

 		$this->index->startTrans();
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
 		$this->index->commit();
 		return $this;

 	}
 	/*查询索引数据索引建立*/
 	public function kquery($sql=''){
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
 					$this->index->execute("INSERT INTO doclist (term_id, doc_id, hit_count) VALUES ($termid, $id, $val);");

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

}
