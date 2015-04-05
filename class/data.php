<?php
	require_once($_SERVER['DOCUMENT_ROOT'].'/class/array.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/functions/json.php');

	//This holds config variables for DB etc. DO not edit if linked to remote
	require_once($_SERVER['DOCUMENT_ROOT'].'/constants/admin.php'); 

 	require_once($_SERVER['DOCUMENT_ROOT'].'/constants/modules.php');
  	require_once($_SERVER['DOCUMENT_ROOT'].'/constants/classes.php');
	class data
	{
		private $user = DB_user;
		private $pass = DB_pass;
		private $host = DB_host;
		private $port = DB_port;
		private $dbname = DB_name;
		public $date;

		public $limit = '';
		public $testQ = true;

		protected $db;

		public $time;

		function __construct()
		{

			$this->date = date('Y-m-d H:s:i');
			$this->time = time();
			$this->array = new arrayFnc();

			try
			{
				$this->db = new PDO("mysql:host={$this->host};dbname={$this->dbname};port={$this->port}", $this->user, $this->pass);
				$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			catch(PDOException $e)
			{
				echo $e->getMessage();
				die();
			}
			
		}


		function test($object,$type = "dump", $qTest = true)
		{
			$this->testQ = $qTest;

			switch ($type) {
				case 'dump':
					ob_start();
					var_dump($object);
					$output = ob_get_clean();
					break;
				case 'print':
					$output = print_r($object, true);
					break;
				default:
					# code...
					break;
			}
			$this->testQ = false;
			$this->prepareFromArray("test_logs", array("time" => $this->dateTimeStamp(), "log" => $output));
		}

		function timeStamp()
		{
			return date("h:i:s",$this->time);
		}

		function dateTimeStamp()
		{
			return date("m/d/y h:i:s",$this->time);
		}

		function date()
		{
			return date("m/d/y",$this->time);
		}

		function validate($action, $from = false)
		{
			if(!$action)
			{
				print_r($from);
				print_r($this->db->errorInfo());
				return false;
			}
			return true;
		}
		//use array keys as params and the values as values.
		function prepareFromArray($table,$param,$for = "insert",$get = 'none', $wherez = false, $orderBy = null)
		{
			$params = array();
			$values = array();
			$wheres = (!is_array($wherez)) ? array() : $wherez;

			foreach($param as $var=>$value)
			{
				if(!is_int($var))
				{
					array_push($params, $var);
					array_push($values, $value);
					if((!is_array($wherez)) && ($wherez)){
						array_push($wheres,$var);
					}
				}
			}

			return $this->prepare($table,$params,$values,$get,$for,$wheres,null,$orderBy,'fromArray',$param);
		}

		//prepare any and all mysql querys (limited to = conditions)
		function prepare($table,$params = false,$values = false,$get = "all", $on = "select", $wheres = false, $is = null, $orderBy = null,$from = 'static',$options = null)
		{
			if($params)
			{
				//create a snippet for each type of params we may need.
				$insertParams = (count($params) > 1) ? implode('`,`', $params) : false;
				$updateParams = (count($params) > 1) ? implode('` = ?, `', $params) : false;
				$whereParams = ((count($params) > 1) && ($wheres)) ? implode('` = ? AND `', $wheres) : false;
			}

			$selectParams = ((count($wheres) >= 1) && ($wheres)) ? implode('` = ? AND `', $wheres) : false;

			//if we are doing a where lets add it to a find by array
			if(($wheres) && ($on != 'select'))
			{
				$findBy = array();
				//while we are listing a where for each wheres
				foreach($wheres as $col=>$where)
				{	
					//add the col number to the findby and set the value of the passed in values to it.
					$findBy[$col] = ($from == 'static') ? $values[$where] : $options[$where];
				}
			}

			//create a specific dlimiter so we can implode the array to a string than  explode it back out to array, this will eliminate any keys we dont use (saving process)
			$values = (is_string($values)) ? explode('[--block--]', implode('[--block--]', $values)) : ($values) ? $values : false;

			//if we need to find by, merge it to the values as with pdo we will append a where clause to the q statement
			$values = ($values) ? ((isset($findBy)) ? array_merge($values,$findBy) : $values) : $is;

			//this will hold the "?" params that we need
			$secure = array();

			//we will count based off values as it also accounts for any where clauses we may merge.
			for($i = 0;$i < count($values);$i++)
			{
				$secure[$i] = "?";
			}
			//implode them to string so that we can use them with mysql.
			$secure = implode(',', $secure);
			//statements we might need to use with the query
				
			$insert = (isset($insertParams)) ? "INSERT INTO $table (`$insertParams`) VALUES ($secure)" : false;
			$update = (isset($updateParams)) ? "UPDATE $table SET `$updateParams` = ? WHERE `$whereParams` = ?" : false;
			$select = (!is_string($selectParams)) ? "SELECT * FROM $table $this->limit" : "SELECT * FROM $table WHERE `$selectParams` = ? $this->limit";
			//switch on query type
			switch($on)
			{
				case "insert":
					$q = $insert;
					break;
				case "update":
					$q = $update;
					break;
				default:
					$q = $select;
					break;
			}

			$values = (is_null($values)) ? null : array_values($values);

			$q = ($orderBy) ? "$q ORDER BY $orderBy" : $q;

			$query = array("sql" => $q,
							"values" => $values,
							"get" => $get);

			// print_r($query);

			return $this->doThis($query);
		}

		function doThis($query)
		{
			if($this->testQ == true)
			{
				$this->test($query,'print',false);
			}

			$sql = $this->db->prepare($query['sql']);

			if($this->validate($sql))
			{

				if($this->validate($sql->execute($query['values'])))
				{
					switch($query['get'])
					{
						case 'all':
							$result = $sql->fetchAll(PDO::FETCH_ASSOC);
							if(count($result) == 0)
							{
								$result = false;
							}
							break;
						case 'none':
							$result = "Finished";
							break;
						default:
							$result = $sql->fetch(PDO::FETCH_ASSOC);
							if(count($result) == 0)
							{
								$result = false;
							}
							break;
					}

					return $result;
				}
				else
				{
					return false;
				}
			}
		}
	}

?>
