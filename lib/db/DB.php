<?php
/**
 * 查询构造器
 */
namespace app\lib\db;

use app\cls\anker\Config;

class DB
{
    protected $pdo;
    protected $dsn;
    protected $userName;
    protected $password;
    protected $_select = "SELECT *";//sql的select
    protected $_from = "";//sql的from
    protected $_limit = "";//sql的limit
    protected $_table_prefix = "";//表前缀
    protected $_last_query = "";//最后一条查询
    protected $charset = "utf8mb4";

    /**
     * DB constructor.
     * @param bool $is_master 是否是连接主库
     * @param string $resourceName
     */
    public function __construct($is_master=true, $resourceName = '')
    {
        Config::load("db.config", "db");
        $config = Config::item("db", $resourceName);
        if (empty($config["database"])) {
            throw new DbException("数据库连接database不能为空");
        }
        if (empty($config["host"])) {
            throw new DbException("数据库连接host不能为空");
        }
        $dsn = "mysql:dbname={$config['database']};host={$config['host']};port={$config['port']}";

        $this->dsn = $dsn;
        $this->userName = $config["user"];
        $this->password = $config["password"];

        $this->pdo = new \PDO($dsn, $config["user"], $config["password"]);

        if(isset($config["db_prefix"])) {
            $this->_table_prefix = $config["db_prefix"];
        }
        $this->init();
    }

    /**
     * 初始化工作
     */
    private function init()
    {
        if($this->charset) {
            $this->query("set names {$this->charset}");
        }
    }

    /**
     * @desc 构造select条件
     * @param $select
     * @return $this
     * @example $this->db->select("id, name as name1")
     */
    public function select($select)
    {
        $this->_select = "SELECT ".$select;
        return $this;
    }

    /**
     * @desc 构造from条件
     * @param $from
     * @return $this
     */
    public function from($from)
    {
        $from = trim($from);
        $from = "FROM ".$this->_table_prefix.$from;

        $this->_from = $from;
        return $this;
    }

    public function query($sql, $bindValue = [], $isReset = true)
    {
        $sql = trim($sql);
        $this->_last_query = $sql;

        $statement = $this->pdo->query($sql);
        if ($statement === FALSE) {
            $this->throwPdoError($this->pdo);
        }
        return new DB_Result($statement, $this);
    }

    public function get()
    {
        $sql = $this->getPreSQL();
        return $this->query($sql);
    }

    public function limit($limit, $offset=0)
    {
        $limit = intval($limit);
        $offset = intval($offset);

        $limit = "LIMIT $limit";
        $this->_limit = $offset ? $limit." OFFSET $offset" : $limit;
        return $this;
    }

    //得到预编译sql
    protected function getPreSQL($sel=NULL)
    {
        if($sel) {
            $select = $sel;
        } else {
            $select = empty($this->_select) ? "SELECT *" : $this->_select;
        }
        $from = $this->_from;
        if (empty($from)) {
            throw new \Exception("未指明表名");
        }
        $limit = $this->_limit;
        $sql = $select." ".$from;
        $sql .= $limit ? " $limit" : "";
        return $sql;
    }

    private function throwPdoError(\PDO $pdo)
    {
        $errorInfo = $pdo->errorInfo();
        if ($errorInfo[0] === "00000") {
            return [];
        }
        throw new DbException("执行sql语言产生错误错误码:==>{$errorInfo[0]};错误消息:==>{$errorInfo[2]};");
    }
}