<?php
namespace Baiy;

/**
 * 数据库操作类
 * $db = new \Baiy\Mysql($config);
 * ============配置参数============
 * array(
 *	"host"=>'127.0.0.1',
 *	"user"=>'root',
 *	"dbname"=>'',
 *	"password"=>'root',
 *	"port"=>'3306',
 *	"charset"=>'utf-8',
 *	"debug"=>true
 * )
 * ===========使用方法=============
 * 查询:$db->table->field()->where()->group()->having()->order()->limit()->key()->[select|find|count]();
 * 添加:$db->table->data()->[insert|add|replace]();
 * 更新:$db->table->where()->data()->[update|save]();
 * 删除:$db->table->where()->delete();
 * 执行SQL:$db->query();
 * 事务:$db->startTrans();$db->commit();$db->rollback();
 * 其他:$db->getLastSql();$db->getError();
 */
class Mysql {

	/**
	 * 当前数据库操作对象
	 * @var mysql
	 */
	public $db = NULL;

	/**
	 * 数据库配置
	 * @var array
	 */
	public $config = array();
	/**
	 * sql语句，主要用于输出构造成的sql语句
	 * @var string
	 */
	public $sql = '';

	/**
	 * 数据信息
	 * @var array
	 */
	private $data = array();

	/**
	 * 查询表达式参数
	 * @var array
	 */
	private $options = array();

	/**
	 * 错误信息
	 * @var string
	 */
	private $error = '';

	/**
	 * 模型初始化
	 * @param array $config 数据库配置
	 * @param array $table 表名
	 */
	public function __construct($config = array()) {
		$config['host']     = !isset($config['host']) ? '127.0.0.1' : $config['host'];
		$config['user']     = !isset($config['user']) ? 'root' : $config['user'];
		$config['dbname']   = !isset($config['dbname']) ? '' : $config['dbname'];
		$config['password'] = !isset($config['password']) ? 'root' : $config['password'];
		$config['port']     = !isset($config['port']) ? '3306' : $config['port'];
		$config['charset']  = !isset($config['charset']) ? 'utf-8' : $config['charset'];
		$config['debug']    = !isset($config['debug']) ? true : $config['debug'];

		if (!isset($config['dbname'])) {
			throw new \Exception('数据库名称不能为空');
		}
		$this->config = $config;
	}

	/**
	 * 连接数据库
	 */
	private function connect() {
		if (empty($this->db)) {
			$config   = $this->config;
			$this->db = new MysqlDrive($config['host'], $config['user'], $config['password'], $config['dbname'], $config['port'], $config['charset']);
		}
	}

	/**
	 * 重载实现相关连贯操作
	 * [field,data,where,group,having,order,limit]
	 * @param  string $method 方法名
	 * @param  array $args   参数
	 */
	public function __call($method, $args) {
		$method = strtolower($method);
		if (in_array($method, array('table', 'field', 'data', 'where', 'group', 'having', 'order', 'limit', 'key'))) {
			$this->options[$method] = $args[0];
			return $this;
		} else {
			throw new \Exception($method . '方法 未定义');
		}
	}

	/**
	 * 执行SQL语句
	 * @param  string $sql 需要执行的sql语句
	 */
	public function query($sql) {
		if (empty($sql)) {
			return false;
		}

		$this->sql = $sql;
		try {
			$this->connect();
			return $this->db->query($this->sql);
		} catch (\Exception $e) {
			if ($this->config['debug'] !== false) {
				throw new \Exception($e->getMessage());
			} else {
				$this->error = $e->getMessage();
			}
		}
		return;
	}

	/**
	 * 统计
	 */
	public function count($where = '') {
		$data = $this->where($where)->field('count(*)')->find();
		return intval($data['count(*)']);
	}

	/**
	 * 单条数据
	 * 返回关联数组
	 */
	public function find() {
		$lists = $this->limit(1)->select();
		return empty($lists) ? array() : $lists[0];
	}

	/**
	 * SELECT
	 */
	public function select() {
		$select_sql = 'SELECT {FIELD} FROM {TABLE}{WHERE}{GROUP}{HAVING}{ORDER}{LIMIT}';
		$this->sql  = str_replace(
			array('{TABLE}', '{FIELD}', '{WHERE}', '{GROUP}', '{HAVING}', '{ORDER}', '{LIMIT}'),
			array(
				$this->_parseTable(),
				$this->_parseField(),
				$this->_parseWhere(),
				$this->_parseGroup(),
				$this->_parseHaving(),
				$this->_parseOrder(),
				$this->_parseLimit(),
			), $select_sql);

		$data  = array();
		$query = $this->query($this->sql);

		$key = $this->_parseKey();

		while ($row = $this->db->fetch_array($query)) {
			if ($key) {
				$data[$row[$key]] = $row;
			} else {
				$data[] = $row;
			}
		}
		return $data;
	}

	/**
	 * 插入数据 支持批量插入
	 * @param  boolean $replace 是否替换插入
	 * @return 返回插入主键值 如没有则为影响行数 出错返回false
	 */
	public function insert($replace = false) {
		$this->sql = ($replace ? 'REPLACE' : 'INSERT') . ' INTO '
		. $this->_parseTable()
		. $this->_parseData('insert');

		$query = $this->query($this->sql);

		$id = $this->db->insert_id();
		return empty($id) ? $this->db->affected_rows() : $id;
	}

	/**
	 * $this->insert() 别名
	 */
	public function add($replace = false) {
		return $this->insert($replace);
	}

	/**
	 * 替换插入
	 * 重载$this->insert() 部分功能
	 */
	public function replace() {
		return $this->insert(true);
	}

	/**
	 * 更新
	 * @return 返回受影响函数 发生错误 返回false
	 */
	public function update() {
		$this->sql = 'UPDATE '
		. $this->_parseTable()
		. $this->_parseData('update')
		. $this->_parseWhere();

		$this->query($this->sql);

		return $this->db->affected_rows();
	}

	/**
	 * 重载$this->update()
	 */
	public function save() {
		return $this->update();
	}

	/**
	 * 删除
	 * @return 返回受影响函数 发生错误 返回false
	 */
	public function delete() {
		$this->sql = 'DELETE FROM '
		. $this->_parseTable()
		. $this->_parseWhere()
		. $this->_parseOrder()
		. $this->_parseLimit();

		$this->query($this->sql);

		return $this->db->affected_rows();
	}

	/**
	 * 启动事务
	 */
	public function startTrans() {
		$this->commit();
		$this->db->start_trans();
		return;
	}

	/**
	 * 事务提交
	 */
	public function commit() {
		return $this->db->commit();
	}

	/**
	 * 事务回滚
	 */
	public function rollback() {
		return $this->db->rollback();
	}

	/**
	 * 返回最后一条执行的SQL语句
	 */
	public function getLastSql() {
		return $this->sql;
	}

	/**
	 * 获取数据库错误信息
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * table分析
	 */
	private function _parseTable() {
		$table = $this->options['table'];
		unset($this->options['field']);
		return $this->_filterFieldName($table);
	}

	/**
	 * field分析
	 */
	private function _parseField() {
		$field = $this->options['field'];
		unset($this->options['field']);
		return empty($field) ? '*' : $field;
	}

	/**
	 * group分析
	 */
	private function _parseGroup() {
		$group = $this->options['group'];
		unset($this->options['group']);
		return !empty($group) ? ' GROUP BY ' . $group : '';
	}

	/**
	 * where分析
	 */
	private function _parseWhere() {
		$where = $this->options['where'];
		unset($this->options['where']);
		$condition = "";
		if (!empty($where)) {
			$condition = " WHERE ";
			if (is_string($where)) {
				$condition .= $where;
			} else if (is_array($where)) {
				foreach ($where as $key => $value) {
					$condition .= " " . $this->_filterFieldName($key) . "=" . $this->_filterFieldValue($value) . " AND ";
				}
				$condition = substr($condition, 0, -4);
			} else {
				$condition = "";
			}
		}
		if (empty($condition)) {
			return "";
		}

		return $condition;
	}

	/**
	 * having分析
	 */
	private function _parseHaving() {
		$having = $this->options['having'];
		unset($this->options['having']);
		return !empty($having) ? ' HAVING ' . $having : '';
	}

	/**
	 * order分析
	 * @param string $order
	 */
	private function _parseOrder() {
		$order = $this->options['order'];
		unset($this->options['order']);
		return !empty($order) ? ' ORDER BY ' . $order : '';
	}

	/**
	 * key分析
	 * select 返回数组所以字段名
	 * @param string $order
	 */
	private function _parseKey() {
		$key = $this->options['key'];
		unset($this->options['key']);
		return trim($key);
	}

	/**
	 * data分析
	 * @param array $data
	 */
	private function _parseData($type = 'insert') {
		$data = $this->options['data'];
		unset($this->options['data']);
		//插入
		if ($type == 'insert') {
			$fields = $values = array();
			//批量
			if (isset($data[0]) && is_array($data[0])) {
				$fields = array_map(array($this, '_filterFieldName'), array_keys($data[0]));
				foreach ($data as $key => $var) {
					$values[] = '(' . implode(',', array_map(array($this, '_filterFieldValue'), array_values($var))) . ')';
				}
				return ' (' . implode(',', $fields) . ') VALUES ' . implode(',', $values);
			}
			//单条
			else {
				$fields = array_map(array($this, '_filterFieldName'), array_keys($data));
				$values = array_map(array($this, '_filterFieldValue'), array_values($data));
				return ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
			}
		}
		//更新
		else {
			$set = array();
			foreach ($data as $key => $val) {
				$set[] = $this->_filterFieldName($key) . '=' . $this->_filterFieldValue($val);
			}
			return ' SET ' . implode(',', $set);
		}
	}

	/**
	 * limit分析
	 * @param mixed $limit
	 */
	private function _parseLimit() {
		$limit = $this->options['limit'];
		unset($this->options['limit']);
		return !empty($limit) ? ' LIMIT ' . $limit . ' ' : '';
	}

	/**
	 * 字段和表名处理添加`和过滤
	 * @param string $key
	 */
	private function _filterFieldName($key) {
		$key = trim($key);
		if (!is_numeric($key) && !preg_match('/[,\'\"\*\(\)`.\s]/', $key)) {
			$key = '`' . $key . '`';
		}
		return $key;
	}

	private function _filterFieldValue($value) {
		return '\'' . addslashes($value) . '\'';
	}
}

/**
 * mysql驱动
 */
class MysqlDrive {
	//数据库连接实例
	public $link;
	//数据库主机
	public $dbhost;
	//数据库用户名
	public $dbuser;
	//数据库密码
	public $dbpw;
	//数据库编码
	public $dbcharset;

	//事务开启标记
	private $trans_tag = 0; //0 关闭 >0 开启

	/**
	 * 连接数据库
	 * @param  string  $dbhost    数据库地址
	 * @param  string  $dbuser    数据库用户
	 * @param  string  $dbpw      数据库密码
	 * @param  string  $dbname    数据库名
	 * @param  int  $dbport    数据库端口
	 * @param  string  $dbcharset 数据库编码
	 */
	public function __construct($dbhost, $dbuser, $dbpw, $dbname, $dbport, $dbcharset) {
		$this->dbhost    = $dbhost;
		$this->dbuser    = $dbuser;
		$this->dbpw      = $dbpw;
		$this->dbname    = $dbname;
		$this->dbport    = $dbport;
		$this->dbcharset = $dbcharset;

		$this->link = new \mysqli($this->dbhost, $this->dbuser, $this->dbpw, $this->dbname, $this->dbport);
		if ($this->link->connect_error) {
			$this->halt('数据库连接错误 (' . $this->link->connect_errno . ')' . $this->link->connect_error);
		}

		//设置数据库编码 防止中文乱码
		$this->link->query("SET NAMES '" . $this->dbcharset . "'");
		//设置运行模型 防止mysql报错
		$this->link->query("SET sql_mode=''");
	}

	/**
	 * 选择数据库
	 * @param  string $dbname 数据库名
	 */
	public function select_db($dbname) {
		return $this->link->select_db($dbname);
	}

	/**
	 * 查询sql语句
	 * @param  string $sql SQL语句
	 */
	public function query($sql) {
		$result = $this->link->query($sql);
		if ($result === false) {
			$this->halt($sql . '执行错误 [' . $this->errno . ']' . $this->error);
		}
		return $result;
	}

	/**
	 * 从结果集中取得一行作为关联数组，或数字数组，或二者兼有
	 * @param  object $result      结果集对象
	 * @param  int $result_type 返回类型
	 */
	public function fetch_array($result, $result_type = MYSQLI_ASSOC) {
		return empty($result) ? '' : $result->fetch_array($result_type);
	}

	/**
	 * 获取上一次插入的id
	 */
	public function insert_id() {
		return $this->link->insert_id;
	}

	/**
	 * 取得前一次 MySQL 操作所影响的记录行数
	 */
	public function affected_rows() {
		return $this->link->affected_rows;
	}

	/**
	 * 取得结果集中行的数目
	 * @param  object $result 结果集对象
	 */
	public function num_rows($result) {
		return empty($result) ? '' : $result->num_rows;
	}

	/**
	 * 取得结果集中字段的数目
	 * @param  object $result 结果集对象
	 */
	public function num_fields($result) {
		return empty($result) ? '' : $result->field_count;
	}
	/**
	 * 从结果集中取得列信息并作为对象返回
	 * @param  object $result 结果集对象
	 */
	public function fetch_fields($result) {
		return empty($result) ? '' : $result->fetch_field();
	}
	/**
	 * 释放结果内存
	 * @param  object $result 结果集对象
	 */
	public function free_result($result) {
		return empty($result) ? '' : $result->free();
	}

	/**
	 * 启动事务
	 */
	public function start_trans() {
		if ($this->trans_tag == 0) {
			//关闭自动提交
			$this->link->autocommit(FALSE);
		}
		$this->trans_tag++;
		return TRUE;
	}

	/**
	 * 事务提交
	 * 用于非自动提交状态下面的查询提交
	 */
	public function commit() {
		if ($this->trans_tag > 0) {
			//事务提交
			$result = $this->link->commit();
			$this->link->autocommit(TRUE);
			$this->trans_tag = 0;
			if (!$result) {
				$this->halt($this->errno(), $this->error());
				return false;
			}
		}
		return TRUE;
	}

	/**
	 * 事务回滚
	 */
	public function rollback() {
		if ($this->trans_tag > 0) {
			$result = $this->link->rollback();
			$this->link->autocommit(TRUE);
			$this->trans_tag = 0;
			if (!$result) {
				$this->halt($this->errno(), $this->error());
				return false;
			}
		}
		return true;
	}

	/**
	 * 获取错误信息详情
	 */
	public function error() {
		return empty($this->link) ? '' : $this->link->error;
	}
	/**
	 * 获取错误代码
	 */
	public function errno() {
		return empty($this->link) ? '' : $this->link->errno;
	}

	/**
	 * 获取版本号
	 */
	public function version() {
		return $this->link->server_info;
	}

	/**
	 * 关闭数据库连接
	 */
	public function close() {
		return !empty($this->link) ? $this->link->close() : true;

	}

	/**
	 * 输出错误信息
	 * @param  string $message 错误信息
	 * @param  string $sql     sql语句
	 */
	public function halt($message = '', $sql = '') {
		throw new \Exception($message . $sql);
	}

	/**
	 * 析构函数
	 */
	public function __destruct() {
		$this->close();
	}
}