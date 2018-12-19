<?php
include 'Baiy/Baiy.php';
// 配置慢查询日志文件路径
$handle = @fopen("mysql_slow_query_log.log", "r");
$cmd = new cmd();
$i = 0;
while (!feof($handle)) {
    $i++;
    $buffer = fgets($handle, 4096);
    $cmd->addOne($buffer);
    if($i % 2000 == 0){
        $cmd->addDb();
        echo $i++."\n";
    }
}
$cmd->addDb();
echo "ok";

class cmd{
    private $lists;
    private $db;
    private $addlists;
    public function __construct(){
        $this->db = new \Baiy\Mysql(array("dbname"=>'test',"debug"=>true));
    }
    public function addOne($str){
        if(
            strpos($str,'# Time:') !== false ||
            strpos($str,'use ') !== false
        ){
            return;
        }
        if(strpos($str,'# User@Host:') !== false){
            $this->dispose();
            $this->lists = array();
        }
        $this->lists[] = $str;
    }

    public function addDb(){
        if(empty($this->addlists)){
            return;
        }
        $this->db->table('db_analysis')->data($this->addlists)->add();
        echo $this->addlists[0]['timestamp']."\n";
        $this->addlists = [];
    }

    private function addDbList($lists){
        $this->addlists[] = $lists;
    }

    private function dispose(){
        if(empty($this->lists)){
            return;
        }
        $info = str_replace("\n", '', implode("", $this->lists));
        if(strpos($info,'# User@Host:') === false){
            return;
        }
        preg_match('/# User@Host:(?<user>.*?)@.*?\[(?<host>.*?)\].*?Id: +(?<dbid>\d+).*?ry_time:(?<query_time>.*?)Lock_time:(?<lock_time>.*?)Rows_sent:(?<rows_sent>.*?)Rows_examined:(?<rows_examined>.*?)SET timestamp=(?<timestamp>.*?);(?<sql>.*?);/i', $info,$m);
        $lists = array();
        if(!empty($m['sql'])){
            preg_match('/FROM (?<table>[0-9a-zA-Z\_\-]+)/i', $m['sql'],$t);
            $lists = array(
                "query_time"=>$m['query_time'],
                "lock_time"=>$m['lock_time'],
                "rows_sent"=>$m['rows_sent'],
                "rows_examined"=>$m['rows_examined'],
                "timestamp"=>date('Y-m-d H:i:s',$m['timestamp']),
                "sql"=>$m['sql'],
                "user"=>$m['user'],
                "host"=>$m['host'],
                "table"=>$t['table'],
                "dbid"=>$m['dbid'],
            );
            $this->addDbList(array_map('trim', $lists));
        }
        else{
            error_log($info,3,'error_log');
        }
    }
}
