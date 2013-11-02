<?php
class error{
	public static function system_error($message, $show = true, $halt = true) {
		list($showtrace, $logtrace) = error::debug_backtrace();
		if($show) {
			error::show_error('system', "<li>$message</li>", $showtrace, 0);
		}
		if($halt) {
			exit();
		} else {
			return $message;
		}
	}
	public static function debug_backtrace() {
		$skipfunc[] = 'error->debug_backtrace';
		$skipfunc[] = 'error->db_error';
		$skipfunc[] = 'error->template_error';
		$skipfunc[] = 'error->system_error';
		$skipfunc[] = 'db_mysql->halt';
		$skipfunc[] = 'db_mysql->query';
		$skipfunc[] = 'DB::_execute';
		$show = $log = '';
		$debug_backtrace = debug_backtrace();
		krsort($debug_backtrace);
		foreach ($debug_backtrace as $k => $error) {
			$file = str_replace(SYSTEM_ROOT, '', $error['file']);
			$func = isset($error['class']) ? $error['class'] : '';
			$func .= isset($error['type']) ? $error['type'] : '';
			$func .= isset($error['function']) ? $error['function'] : '';
			if(in_array($func, $skipfunc)) {
				break;
			}
			$error[line] = sprintf('%04d', $error['line']);
			$show .= "<li>[Line: $error[line]]".$file."($func)</li>";
			$log .= !empty($log) ? ' -> ' : '';$file.':'.$error['line'];
			$log .= $file.':'.$error['line'];
		}
		return array($show, $log);
	}
	public static function db_error($message, $sql) {
		global $_G;
		list($showtrace, $logtrace) = error::debug_backtrace();
		$db = &DB::object();
		$dberrno = $db->errno();
		$dberror = str_replace($db->tablepre,  '', $db->error());
		$sql = htmlspecialchars(str_replace($db->tablepre,  '', $sql));
		$msg = '<li>'.$message.'</li>';
		$msg .= $dberrno ? '<li>['.$dberrno.'] '.$dberror.'</li>' : '';
		$msg .= $sql ? '<li>[Query] '.$sql.'</li>' : '';
		error::show_error('db', $msg, $showtrace, false);
		exit();
	}
	public static function exception_error($exception) {
		if($exception instanceof DbException) {
			$type = 'db';
		} else {
			$type = 'system';
		}
		if($type == 'db') {
			$errormsg = '('.$exception->getCode().') ';
			$errormsg .= self::sql_clear($exception->getMessage());
			if($exception->getSql()) {
				$errormsg .= '<div class="sql">';
				$errormsg .= self::sql_clear($exception->getSql());
				$errormsg .= '</div>';
			}
		} else {
			$errormsg = $exception->getMessage();
		}
		$trace = $exception->getTrace();
		krsort($trace);
		$trace[] = array('file'=>$exception->getFile(), 'line'=>$exception->getLine(), 'function'=> 'ErrorHandler');
		$phpmsg = array();
		foreach ($trace as $error) {
			if(!empty($error['function'])) {
				$fun = '';
				if(!empty($error['class'])) {
					$fun .= $error['class'].$error['type'];
				}
				$fun .= $error['function'].'(';
				if(!empty($error['args'])) {
					$mark = '';
					foreach($error['args'] as $arg) {
						$fun .= $mark;
						if(is_array($arg)) {
							$fun .= 'Array';
						} elseif(is_bool($arg)) {
							$fun .= $arg ? 'true' : 'false';
						} elseif(is_int($arg)) {
							$fun .= (defined('DEBUG_FLAG') && DEBUG_FLAG) ? $arg : '%d';
						} elseif(is_float($arg)) {
							$fun .= (defined('DEBUG_FLAG') && DEBUG_FLAG) ? $arg : '%f';
						} else {
							$fun .= (defined('DEBUG_FLAG') && DEBUG_FLAG) ? '\''.htmlspecialchars(substr(self::clear($arg), 0, 10)).(strlen($arg) > 10 ? ' ...' : '').'\'' : '%s';
						}
						$mark = ', ';
					}
				}
				$fun .= ')';
				$error['function'] = $fun;
			}
			$phpmsg[] = array(
			    'file' => str_replace(array(SYSTEM_ROOT, '\\'), array('', '/'), $error['file']),
			    'line' => $error['line'],
			    'function' => $error['function'],
			);
		}
		self::show_error($type, $errormsg, $phpmsg);
		exit();
	}
	public static function show_error($type, $errormsg, $phpmsg = '', $exit = true) {
		ob_end_clean();
		ob_start();
		$host = $_SERVER['HTTP_HOST'];
		$title = $type == 'db' ? 'Database' : 'System';
		echo <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>$host - $title Error</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="ROBOTS" content="NOINDEX,NOFOLLOW,NOARCHIVE" />
	<style type="text/css">
	<!--
	body { background-color: white; color: black; font: 9pt/11pt verdana, arial, sans-serif;}
	#container { width: 1024px; }
	#message   { width: 1024px; color: black; }
	.red  {color: red;}
	a:link     { font: 9pt/11pt verdana, arial, sans-serif; color: red; }
	a:visited  { font: 9pt/11pt verdana, arial, sans-serif; color: #4e4e4e; }
	h1 { color: #FF0000; font: 18pt "Verdana"; margin-bottom: 0.5em;}
	.bg1{ background-color: #FFFFCC;}
	.bg2{ background-color: #EEEEEE;}
	.table {background: #AAAAAA; font: 11pt Menlo,Consolas,"Lucida Console"}
	.info {
	    background: none repeat scroll 0 0 #F3F3F3;
	    border: 0px solid #aaaaaa;
	    border-radius: 10px 10px 10px 10px;
	    color: #000000;
	    font-size: 11pt;
	    line-height: 160%;
	    margin-bottom: 1em;
	    padding: 1em;
	}
	.help {
	    background: #F3F3F3;
	    border-radius: 10px 10px 10px 10px;
	    font: 12px verdana, arial, sans-serif;
	    text-align: center;
	    line-height: 160%;
	    padding: 1em;
	}
	.sql {
	    background: none repeat scroll 0 0 #FFFFCC;
	    border: 1px solid #aaaaaa;
	    color: #000000;
	    font: arial, sans-serif;
	    font-size: 9pt;
	    line-height: 160%;
	    margin-top: 1em;
	    padding: 4px;
	}
	-->
	</style>
</head>
<body>
<div id="container">
<h1>Frame $title Error</h1>
<div class='info'>$errormsg</div>
EOT;
		if(is_array($phpmsg) && !empty($phpmsg)) {
			echo '<div class="info">';
			echo '<p><strong>PHP Debug</strong></p>';
			echo '<table cellpadding="5" cellspacing="1" width="100%" class="table">';
			echo '<tr class="bg2"><td>No.</td><td>File</td><td>Line</td><td>Code</td></tr>';
			foreach($phpmsg as $k => $msg) {
				$k++;
				echo '<tr class="bg1">';
				echo '<td>'.$k.'</td>';
				echo '<td>'.$msg['file'].'</td>';
				echo '<td>'.$msg['line'].'</td>';
				echo '<td>'.$msg['function'].'</td>';
				echo '</tr>';
			}
			echo '</table></div>';
		}
		echo <<<EOT
</div>
</body>
</html>
EOT;
		$exit && exit();
	}
	public static function clear($message) {
		return str_replace(array("\t", "\r", "\n"), " ", $message);
	}
	public static function sql_clear($message) {
		$message = self::clear($message);
		$message = str_replace(DB::object()->tablepre, '', $message);
		$message = htmlspecialchars($message);
		return $message;
	}
}
class db_mysql{
	var $tablepre;
	var $curlink;
	var $last_query;
	var $config = array();
	function set_config($config){
		$this->config = $config;
		$this->tablepre = $config['tablepre'];
	}
	function connect() {
		$this->curlink = $this->_dbconnect(
			$this->config['dbhost'],
			$this->config['dbuser'],
			$this->config['dbpw'],
			$this->config['dbcharset'],
			$this->config['dbname'],
			$this->config['pconnect']
		);
	}
	function _dbconnect($dbhost, $dbuser, $dbpw, $dbcharset, $dbname, $pconnect) {
		$link = null;
		$func = empty($pconnect) ? 'mysql_connect' : 'mysql_pconnect';
		if(!$link = @$func($dbhost, $dbuser, $dbpw, 1)) {
			$this->halt('notconnect');
		} else {
			$this->curlink = $link;
			if($this->version() > '4.1') {
				$dbcharset = $dbcharset ? $dbcharset : $this->config[1]['dbcharset'];
				$serverset = $dbcharset ? 'character_set_connection='.$dbcharset.', character_set_results='.$dbcharset.', character_set_client=binary' : '';
				$serverset .= $this->version() > '5.0.1' ? ((empty($serverset) ? '' : ',').'sql_mode=\'\'') : '';
				$serverset && mysql_query("SET $serverset", $link);
			}
			$dbname && @mysql_select_db($dbname, $link);
		}
		return $link;
	}
	function table_name($tablename) {
		return $this->tablepre.$tablename;
	}
	function select_db($dbname) {
		return mysql_select_db($dbname, $this->curlink);
	}
	function fetch_array($query, $result_type = MYSQL_ASSOC) {
		return mysql_fetch_array($query, $result_type);
	}
	function fetch_first($sql) {
		return $this->fetch_array($this->query($sql));
	}
	function result_first($sql) {
		return $this->result($this->query($sql), 0);
	}
	function query($sql, $type = '') {
		$func = $type == 'UNBUFFERED' && @function_exists('mysql_unbuffered_query') ?
		'mysql_unbuffered_query' : 'mysql_query';
		if(!$this->curlink) $this->connect();
		if(!($query = $func($sql, $this->curlink))) {
			if($type != 'SILENT') {
				$this->halt('MySQL Query ERROR', $sql);
			}
		}
		DEBUG::query_counter();
		return $this->last_query = $query;
	}
	function affected_rows() {
		return mysql_affected_rows($this->curlink);
	}
	function error() {
		return (($this->curlink) ? mysql_error($this->curlink) : mysql_error());
	}
	function errno() {
		return intval(($this->curlink) ? mysql_errno($this->curlink) : mysql_errno());
	}
	function result($query, $row = 0) {
		$query = @mysql_result($query, $row);
		return $query;
	}
	function num_rows($query) {
		$query = mysql_num_rows($query);
		return $query;
	}
	function num_fields($query) {
		return mysql_num_fields($query);
	}
	function free_result($query) {
		return mysql_free_result($query);
	}
	function insert_id() {
		return ($id = mysql_insert_id($this->curlink)) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}
	function fetch_row($query) {
		$query = mysql_fetch_row($query);
		return $query;
	}
	function fetch_fields($query) {
		return mysql_fetch_field($query);
	}
	function version() {
		if(empty($this->version)) {
			$this->version = mysql_get_server_info($this->curlink);
		}
		return $this->version;
	}
	function close() {
		return mysql_close($this->curlink);
	}
	function halt($message = '', $sql = '') {
		error::db_error($message, $sql);
	}
}

class DB{
	function init($config){
		DB::_execute('set_config', $config);
	}
	function table($table) {
		return DB::_execute('table_name', $table);
	}
	function delete($table, $condition, $limit = 0, $unbuffered = true) {
		if(empty($condition)) {
			$where = '1';
		} elseif(is_array($condition)) {
			$where = DB::implode_field_value($condition, ' AND ');
		} else {
			$where = $condition;
		}
		$sql = "DELETE FROM ".DB::table($table)." WHERE $where ".($limit ? "LIMIT $limit" : '');
		return DB::query($sql, ($unbuffered ? 'UNBUFFERED' : ''));
	}
	function insert($table, $data, $return_insert_id = true, $replace = false, $silent = false) {
		$sql = DB::implode_field_value($data);
		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
		$table = DB::table($table);
		$silent = $silent ? 'SILENT' : '';
		$return = DB::query("$cmd $table SET $sql", $silent);
		return $return_insert_id ? DB::insert_id() : $return;
	}
	function update($table, $data, $condition, $unbuffered = false, $low_priority = false) {
		$sql = DB::implode_field_value($data);
		$cmd = "UPDATE ".($low_priority ? 'LOW_PRIORITY' : '');
		$table = DB::table($table);
		$where = '';
		if(empty($condition)) {
			$where = '1';
		} elseif(is_array($condition)) {
			$where = DB::implode_field_value($condition, ' AND ');
		} else {
			$where = $condition;
		}
		$res = DB::query("$cmd $table SET $sql WHERE $where", $unbuffered ? 'UNBUFFERED' : '');
		return $res;
	}
	function implode_field_value($array, $glue = ',') {
		$sql = $comma = '';
		foreach ($array as $k => $v) {
			$sql .= $comma."`$k`='$v'";
			$comma = $glue;
		}
		return $sql;
	}
	function insert_id() {
		return DB::_execute('insert_id');
	}
	function fetch($resourceid, $type = MYSQL_ASSOC) {
		return DB::_execute('fetch_array', $resourceid, $type);
	}
	function fetch_first($sql) {
		return DB::_execute('fetch_first', $sql);
	}
	function fetch_all($sql) {
		$query = DB::_execute('query', $sql);
		$return = array();
		while($result = DB::fetch($query)){
			$return[] = $result;
		}
		return $return;
	}
	function result($resourceid, $row = 0) {
		return DB::_execute('result', $resourceid, $row);
	}
	function result_first($sql) {
		return DB::_execute('result_first', $sql);
	}
	function query($sql, $type = '') {
		return DB::_execute('query', $sql, $type);
	}
	function num_rows($resourceid) {
		return DB::_execute('num_rows', $resourceid);
	}
	function affected_rows() {
		return DB::_execute('affected_rows');
	}
	function free_result($query) {
		return DB::_execute('free_result', $query);
	}
	function error() {
		return DB::_execute('error');
	}
	function errno() {
		return DB::_execute('errno');
	}
	function _execute($cmd , $arg1 = '', $arg2 = '') {
		static $db;
		if(empty($db)) $db = & DB::object();
		$res = $db->$cmd($arg1, $arg2);
		return $res;
	}
	function &object() {
		static $db;
		if(empty($db)) $db = new db_mysql();
		return $db;
	}
}
class DEBUG{
	function INIT(){
		$GLOBALS['debug']['time_start'] = self::getmicrotime();
		$GLOBALS['debug']['query_num'] = 0;
	}
	function getmicrotime(){
		list($usec, $sec) = explode(' ',microtime());
		return ((float)$usec + (float)$sec);
	}
	function output(){
		$return = array('脚本运行时间 '.number_format((self::getmicrotime() - $GLOBALS['debug']['time_start']), 6).'秒');
		$return[] = 'MySQL 请求次数: '.$GLOBALS['debug']['query_num'];
		return implode('</li><li>', $return);
	}
	function query_counter(){
		$GLOBALS['debug']['query_num']++;
	}
}


//use

DB::init(array(
	'dbhost' => 'localhost',
	'dbuser' => '',
	'dbpw' => '',
	'dbcharset' => 'utf8',
	'dbname' => '',
	'tablepre' => 'pre_',
	'pconnect' => true,
));

?>