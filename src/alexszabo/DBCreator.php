<?php
namespace alexszabo;

use \Exception;

class DBCreator
{
	private $tables = [];
	private $requestedTable = NULL;

	const SMALLINT = 1;
	const INT = 2;
	const BIGINT = 3;
	const VARCHAR = 4;
	const LONGTEXT = 5;

	/**
	 * @param Table $table
	 */
	public function addTable(Table $table)
	{
		$this->tables[] = $table;
		$table->setParentCreator($this);
	}

	/**
	 * @return bool|string
	 */
	public function getCreateSQL()
	{
		$sql = "";

		foreach ($this->getTables() as $table) {
			/* @var $table Table */
			$table_name = strtolower($table->getName());
			$sql .= "\tDROP TABLE IF EXISTS `".$table_name."`;"."\n"
				."\tCREATE TABLE IF NOT EXISTS `".$table_name."` ("."\n"."\t\t"."\n";

			foreach ($table->getColumns() as $column) {
				/* @var $column Column */
				$column_name = strtolower($column->getName());
				$column_type = $column->getType();
				$column_type_length = $column->getTypeLength();
				$column_default = $column->getDefault();

				$column_default !== NULL ? $default = "default '".$column_default."'" : $default = "default NULL";
				if ($column->getPrimary() === true) {
					$default = "NOT NULL auto_increment ";
				}

				if ($column->getUnique() === true) {
					$default = "NOT NULL";
				}

				//strings are all utf-8
				$utf = ($column->isTypeStr($column_type)) ? " character set utf8 " : "";

				//if a length is assigned - use it
				$length = (!$column->isTypeStr($column_type) || ($column_type_length !== NULL))
					? "(".$column_type_length.")"
					: "";
				if (!$column->isTypeStr($column_type)) $length .= " "; //small spacing fix for compatibility

				$sql .= "\t`" . $column_name . "` " . $column->getTypeAsStr() . $length . $utf . $default . ","."\n";
			}

			foreach ($table->getColumns() as $column) {
				/* @var $column Column */

				if ($column->getPrimary() === true) {
					$sql = substr($sql, 0, -2);
					$sql .= "\n"."\t, PRIMARY KEY (`".strtolower($column->getName())."`)"."\n";
				}
			}

			foreach ($table->getColumns() as $column) {
				/* @var $column Column */

				if ($column->getUnique() === true) {
					$sql .= "  , UNIQUE KEY `".strtolower($column->getName()).
						"` (`".strtolower($column->getName())."`)"."\n";
				}
			}

			$sql = substr($sql, 0, -1);
			$sql .= "\n"."\t) ENGINE=MyISAM DEFAULT CHARSET=utf8  AUTO_INCREMENT=1;\t"."\n"."\t"."\n"."\n";
		}

		return $sql = substr($sql, 0, -1);
	}

	/**
	 * @return string
	 */
	public function getAsJsonConfiguration()
	{
		$tables = $this->getTables();
		$jsonArr = [];
		$tablesArr = [];

		foreach ($tables as $table) {
			/* @var $table Table */
			$table_name = $table->getName();
			$tablesArr["name"] = $table_name;
			$tablesArr["columns"] = [];

			$columns = $table->getColumns();

			foreach ($columns as $column) {
				/* @var $column Column */
				$columns_array = [];
				$columns_array["name"]   = strtolower($column->getName());
				$columns_array["type"]   = strtolower($column->getTypeAsStr());

				if ($column->isTypeStr($columns_array["type"]) === true) {
					$columns_array["length"] = $column->getTypeLength();
				} else {
					if ($column->getTrueTypeLength() !== NULL) {
						$columns_array["length"] = $column->getTrueTypeLength();
					}
				}

				if ($column->getPrimary() === true) {
					$columns_array["primary"] = $column->getPrimary();
				}
				if ($column->getUnique()  === true) {
					$columns_array["unique"]  = $column->getUnique();
				}
				if ($column->getDefault() !== NULL) {
					$columns_array["default"] = $column->getDefault();
				}
				if ($column->getForeignKeyAsString() !== NULL) {
					$columns_array["foreignkey"] = $column->getForeignKeyAsString();
				}

				$tablesArr["columns"][] = $columns_array;
			}

			$jsonArr["tables"][] = $tablesArr;
		}

		return json_encode($jsonArr, JSON_PRETTY_PRINT);
	}

	/**
	 * @param $json
	 * @throws Exception if table.column format is broken
	 */
	public function loadFromJsonConfiguration($json)
	{
		if (!is_object(json_decode($json))) {
			$json = file_get_contents($json);
		}

		$dbConfig = get_object_vars(json_decode($json));
		$tablesConfig = $dbConfig["tables"];

		for($i = 0; $i < count($tablesConfig); $i++) {
			$table = get_object_vars($tablesConfig[$i]);
			$table_name = $table["name"];
			$columns = $table["columns"];

			$t = new Table($table_name);
			$this->addTable($t);

			for($j = 0; $j < count($columns); $j++) {
				$column = get_object_vars($columns[$j]);
				$column_name = $column["name"];
				$column_type = $column["type"];

				if (!empty($column["length"])) {
					$column_type_length = $column["length"];
					$c = new Column($column_name, $this->getConstantForColumnType($column_type), intval($column_type_length));
				} else {
					$c = new Column($column_name, $this->getConstantForColumnType($column_type));
				}
				/* @var $c Column */

				$t->addColumn($c);

				if (!empty($column["primary"])) $c->setPrimary();
				if (!empty($column["unique"])) $c->setUnique();
				if (!empty($column["foreignkey"])) {
					$c->addForeignKeyConstraintsTo(
						$this->getColumn( $column["foreignkey"] )
					);
				}

				if (array_key_exists("default", $column)) $c->setDefault($column["default"]);

			}

		}
	}

	/**
	 * @return string
	 */
	public function createDBObjectsFile()
	{
		$tables = $this->getTables();

		$php = "<?php\n".
			"/*************************************************************************\n".
			"* remove, replace and extend these classes in own files\n".
			"* feel free to redistribute these classes to other files\n".
			"*************************************************************************/";


		for ($i = 0; $i < count($tables); $i++) {
			$table = $tables[$i];
			$table_name = $table->getName();
			$php .= "\n"."class ".$table_name." extends ".$table_name."DBObject {"."\n"."}"."\n";
		}
		for ($i = 0; $i < count($tables); $i++) {
			$table = $tables[$i];
			$table_name = $table->getName();
			$php .= "\n"."class ".$table_name."DBAccess extends ".$table_name."DBAccessObject {"."\n"."}"."\n";
		}

		for ($i = 0; $i < count($tables); $i++) {
			$table = $tables[$i];
			$table_name = $table->getName();
			$php .= "\n"."\n".
					"/*************************************************************************\n".
					"* basic ".$table_name." object containing DB columns\n".
					"*************************************************************************/";
			$php .= "\n"."class ".$table_name."DBObject"."\n"."{"."\n";
			$columns = $tables[$i]->getColumns();

			for ($j = 0; $j < count($columns); $j++) {
				/** @var $column Column */
				$column = $columns[$j];
				if ($column->isTypeStr($column->getType())) {
					$php .= "\t"."public $".$column->getName().' = "";'."\n";
				} else {
					if ($column->getDefault() !== NULL) {
						$php .= "\t"."public $".$column->getName()." = ".$column->getDefault().";"."\n";
					} else {
						$php .= "\t"."public $".$column->getName()." = -1;"."\n";
					}
				}

			}

			$php .= "}"."\n";

		}

		foreach ($tables as $table) {
			/** @var $table Table */
			$table_name = $table->getName();
			$columns = $table->getColumns();
			$php .= "\n"."\n".
				"/*************************************************************************\n".
				"* basic access functions to store/load ".$table_name." to/from database\n".
				"*************************************************************************/";
			$php .= "\n"."class ".$table_name."DBAccessObject {"."\n"."\n"."\n";

			$php .=
				"\t/**\n".
				"\t * Save the ".$table_name." object\n".
				"\t *\n".
				"\t * @param PDO $"."db"." the database connection\n".
				"\t * @param ".$table_name." $".strtolower($table_name)." a new or already saved ".$table_name." object\n".
				"\t * @return bool true, if succesfull, else throws PDOException\n".
				"\t */	"."\n\t";

			$php .= "public static function Save(PDO $"."db, ".$table_name." $".strtolower($table_name).") {"."\n";
			$php .= "\t"."\t"."try {"."\n";
			$php .= "\t"."\t"."\t"."$"."query = ($".strtolower($table_name)."->id <= 0)"."\n";
			$php .= "\t"."\t"."\t"."\t"."?"."\t"."'INSERT INTO ".strtolower($table_name)." (";
			for ($j = 1; $j < count($columns);  $j++) {
				$column = $columns[$j];
				$column_name = $column->getName();

				$php .= "".$column_name.", ";
			}
			$php = substr($php, 0, -2);
			$php .= ") '."."\n";
			$php .= "\t"."\t"."\t"."\t"."\t"."'VALUES (";
			for ($j = 1; $j < count($columns);  $j++) {
				$column = $columns[$j];
				$column_name = $column->getName();

				$php .= ":".$column_name.", ";
			}
			$php = substr($php, 0, -2);
			$php .= ")'"."\n";
			$php .= "\t"."\t"."\t"."\t".": 'UPDATE ".strtolower($table_name)." SET ";
			for ($j = 1; $j < count($columns);  $j++) {
				$column = $columns[$j];
				$column_name = $column->getName();

				$php .= "".$column_name."=:".$column_name.", ";
			}
			$php = substr($php, 0, -2);
			$php .= " WHERE id=:id';"."\n"."\t"."\t"."\t"."\n";

			$php .= "\t"."\t"."\t"."$"."statement = $"."db->prepare($"."query);"."\n";
			$php .= "\t"."\t"."\t"."if ($".strtolower($table_name)."->id > 0) "."\n";
			$php .= "\t"."\t"."\t"."\t"."$"."statement->bindParam(";
			$php .= "':id', $".strtolower($table_name)."->id, PDO::PARAM_INT);"."\n"."\n";
			for ($j = 1; $j < count($columns);  $j++) {
				$column = $columns[$j];
				$column_name = $column->getName();

				$php .= "\t"."\t"."\t"."$"."statement->bindParam(':".$column_name."', ";
				$php .= "$".strtolower($table_name)."->".$column_name.",";
				if ($column->isTypeStr($column->getType())) {
					$php .= " PDO::PARAM_STR, ".$column->getTypeLength().");"."\n";
				} else {
					$php .= " PDO::PARAM_INT);"."\n";
				}
			}
			$php .= "\t"."\t"."\t"."$"."statement->execute();"."\n";
			$php .= "\t"."\t"."\t"."if ($".strtolower($table_name)."->id <= 0)"."\n";
			$php .= "\t"."\t"."\t"."\t"."$".strtolower($table_name)."->id = $"."db->lastInsertId();"."\n";
			$php .= "\t"."\t"."} catch (PDOException $"."e) {"."\n";
			$php .= "\t"."\t"."\t".'throw new Exception("unable to save '.$table_name.' to Database: "';
			$php .= ".$"."e->getMessage().$"."e->getTraceAsString());"."\n";
			$php .= "\t"."\t"."}"."\t"."\t"."\t"."\n";
			$php .= "\t"."\t"."return true;"."\n";
			$php .= "\t"."}"."\n"."\n"."\n";

			$php .= "	/**\n".
				"\t * Loads a single ".$table_name." by id\n".
				"\t *\n".
				"\t * @param PDO $"."db the database connection\n".
				"\t * @param int $"."id\n".
				"\t * @return ".$table_name." the desired ".$table_name." object or null\n".
				"\t */\n";
			$php .= "\t"." public static function LoadBy_id(PDO $"."db, $"."id) {"."\n";
			$php .= "\t"."\t"."$".strtolower($table_name)." = null;"."\n";
			$php .= "\t"."\t"."try {"."\n";
			$php .= "\t"."\t"."\t"."$"."statement = $"."db->prepare('SELECT id, ";
			for ($j = 1; $j < count($columns);  $j++) {
				$column = $columns[$j];
				$column_name = $column->getName();

				$php .= "".$column_name.", ";
			}
			$php = substr($php, 0, -2);
			$php .= " FROM ".strtolower($table_name)." WHERE id=:id');"."\n"."\n";
			$php .= "\t"."\t"."\t"."$"."statement->bindParam(':id', $"."id, PDO::PARAM_INT);"."\n";
			$php .= "\t"."\t"."\t"."if ($"."statement->execute()) {"."\n";
			$php .= "\t"."\t"."\t"."\t"."$"."statement->setFetchMode(PDO::FETCH_CLASS, '".$table_name."');"."\n";
			$php .= "\t"."\t"."\t"."    if ($"."row = $"."statement->fetch(PDO::FETCH_CLASS)) {"."\n";
			$php .= "\t"."\t"."\t"."    "."\t"."$".strtolower($table_name)."  = $"."row;"."\n";
			$php .= "\t"."\t"."\t"."    }"."\n";
			$php .= "\t"."\t"."\t"."} else {"."\n";
			$php .= "\t"."\t"."\t"."\t".'throw new Exception(';
			$php .= '"unable to load '.$table_name.' object from Database");'."\n";
			$php .= "\t"."\t"."\t"."}"."\n";
			$php .= "\t"."\t"."} catch (PDOException $"."e) {"."\n";
			$php .= "\t"."\t"."\t"."throw new Exception(";
			$php .= '"unable to load '.$table_name.' object from Database: ".$'.'e->getMessage().$';
			$php .= "e->getTraceAsString());"."\n";
			$php .= "\t"."\t"."}"."\t"."\t"."\t"."\t"."\t"."\n";
			$php .= "\t"."\t"."return $".strtolower($table_name)." ;"."\n";
			$php .= "\t"."}"."\n"."\n";

			foreach ($columns as $column) {
				/* @var $column Column */
				$column_name = $column->getName();

				if ($column->getForeignKeyAsString() === NULL) {
					continue;
				}
				$column_foreignKeyParts  = explode(".", $column->getForeignKeyAsString());
				$column_foreignKeyTable  = $column_foreignKeyParts[0];
				$column_foreignKeyColumn = $column_foreignKeyParts[1];

				if ($column->isTypeStr( $column->getForeignKey()->getType() ) === false) {
					$type = "int";
				} else {
					$type = "string";
				}

				$php .= "\n\t/**\n".
					"\t * Loads a list of ".$table_name." objects according to the ".$column_name."\n".
					"\t *\n".
					"\t * @param PDO $"."db the database connection\n".
					"\t * @param ".$type." $".strtolower($column_foreignKeyTable)."_".$column_foreignKeyColumn."\n".
					"\t * @return array of ".$table_name." objects or empty array\n".
					"\t */\n\t \n";

				$php .= "\tpublic static function LoadListBy_".$column_name."(PDO $"."db, ";
				$php .= "$".strtolower($column_foreignKeyTable)."_".$column_foreignKeyColumn.") {\n\t\t\n";
				$php .= "\t\t$".strtolower($table_name)."s = array();\n\t\t\n";
				$php .= "\t\ttry {\n";
				$php .= "\t\t\t$"."statement = $"."db->prepare('SELECT id, ";
				for ($j = 1; $j < count($columns);  $j++) {
					/* @var $column2 Column */
					$column2 = $columns[$j];
					$column_name2 = $column2->getName();

					$php .= "".$column_name2.", ";
				};
				$php = substr($php, 0, -2);
				$php .= " FROM ".strtolower($table_name)." WHERE ".$column_name."=:".$column_name."');"."\n"."\n";
				$php .= "\t\t\t$"."statement->bindParam(':".$column_name."', ";
				$php .= "$".strtolower($column_foreignKeyTable)."_".$column_foreignKeyColumn;
				if ($column->isTypeStr( $column->getForeignKey()->getType() ) === false) {
					if ($column->getTrueTypeLength() === NULL) {
						$php .= ", PDO::PARAM_INT);"."\n";
					} else {
						$php .= ", PDO::PARAM_INT, ".$column->getTrueTypeLength().");"."\n";
					}
				} else {
					$php .= ", PDO::PARAM_STR, ".$column->getTypeLength().");"."\n";
				}
				$php .= "\t\t\tif ($"."statement->execute()) {\n";
				$php .= "\t\t\t\t$"."statement->setFetchMode(PDO::FETCH_CLASS, '".$table_name."');\n";
				$php .= "\t\t\t    if ($"."rows = $"."statement->fetchAll(PDO::FETCH_CLASS, '".$table_name."')) {\n";
				$php .= "\t\t\t    \t$"."".strtolower($table_name)."s  = $"."rows;\n";
				$php .= "\t\t\t    }\n";
				$php .= "\t\t\t} else {\n";
				$php .= "\t\t\t\tthrow new Exception(\"unable to load ".$table_name." object from Database\");\n";
				$php .= "\t\t\t}\n";
				$php .= "\t\t} catch (PDOException $"."e) {\n";
				$php .= "\t\t\tthrow new Exception(\"unable to load ".$table_name." object from Database: ";
				$php .= "\".$"."e->getMessage().$"."e->getTraceAsString());\n";
				$php .= "\t\t}\t\t\t\t\t\n";
				$php .= "\t\treturn $"."".strtolower($table_name)."s ;	\n";
				$php .= "\t}\n\n\n";
			}

			$php .= "\n	/**\n".
				"\t * Load all ".$table_name." from the database\n".
				"\t *\n".
				"\t * @param PDO $"."db the database connection\n".
				"\t * @return array of ".$table_name." objects or empty array \n".
				"\t */\n";
			$php .= "\t"."public static function LoadAll".$table_name."s(PDO $"."db) {"."\n";
			$php .= "\t"."\t"."$".strtolower($table_name)."s = array();"."\n";
			$php .= "\t"."\t"."try {"."\n";
			$php .= "\t"."\t"."\t"."$"."statement = $"."db->prepare('SELECT id, ";
			for ($j = 1; $j < count($columns);  $j++) {
				$column = $columns[$j];
				$column_name = $column->getName();

				$php .= "".$column_name.", ";
			}
			$php = substr($php, 0, -2);
			$php .= " FROM ".strtolower($table_name)."');"."\n"."\n";
			$php .= "\t"."\t"."\t"."if ($"."statement->execute()) {"."\n";
			$php .= "\t"."\t"."\t"."\t"."$"."statement->setFetchMode(PDO::FETCH_CLASS, '".$table_name."');"."\n";
			$php .= "\t"."\t"."\t"."    if ($"."rows = $"."statement->fetchAll(PDO::FETCH_CLASS, ";
			$php .= "'".$table_name."')) {"."\n";
			$php .= "\t"."\t"."\t"."    "."\t"."$".strtolower($table_name)."s  = $"."rows;"."\n";
			$php .= "\t"."\t"."\t"."    }"."\n";
			$php .= "\t"."\t"."\t"."} else {"."\n";
			$php .= "\t"."\t"."\t"."\t"."throw new Exception(";
			$php .= '"unable to load all '.$table_name.' objects from Database");'."\n";
			$php .= "\t"."\t"."\t"."}"."\n";
			$php .= "\t"."\t"."} catch (PDOException $"."e) {"."\n";
			$php .= "\t"."\t"."\t"."throw new Exception(";
			$php .= '"unable to load all '.$table_name.' objects from Database: ".$';
			$php .= "e->getMessage().$"."e->getTraceAsString());"."\n";
			$php .= "\t"."\t"."}"."\t"."\t"."\t"."\t"."\t"."\n";
			$php .= "\t"."\t"."return $".strtolower($table_name)."s ;"."\t"."\n";
			$php .= "\t"."}"."\n"."\n"."\n";

			for ($j = 0; $j < count($columns);  $j++) {
				$column = $columns[$j];
				$column_name = $column->getName();

				if ($column->getUnique() !== true) {
					continue;
				}

				if ($column->isTypeStr( $column->getType() ) === false) {
					$type = "int";
				} else {
					$type = "string";
				}

				$php .= "\n	/**\n".
					"\t * Loads a ".$table_name." object according to the unique ".$column_name."\n".
					"\t *\n".
					"\t * @param PDO $"."db the database connection\n".
					"\t * @param ".$type." $".$column_name."\n".
					"\t * @return ".$table_name." the desired ".$table_name." object or null\n".
					"\t */\n\t \n";
				$php .= "\t"."public static function LoadBy_".$column_name."(PDO $"."db, $".$column_name.") {"."\n";
				$php .= "\t"."\t"."\n"."\t"."\t"."$".strtolower($table_name)."s = null;"."\n"."\t"."\t"."\n";
				$php .= "\t"."\t"."try {"."\n";
				$php .= "\t"."\t"."\t"."$"."statement = $"."db->prepare('SELECT id, ";
				for ($k = 1; $k < count($columns);  $k++) {
					/* @var $column2 Column */
					$column2 = $columns[$k];
					$column_name2 = $column2->getName();

					$php .= "".$column_name2.", ";
				}
				$php = substr($php, 0, -2);
				$php .= " FROM ".strtolower($table_name)." WHERE ".$column_name."=:".$column_name."');"."\n"."\n";
				$php .= "\t"."\t"."\t"."$"."statement->bindParam(':".$column_name."', $".$column_name;

				if ($column->isTypeStr( $column->getType() )) {
					$php .= ", PDO::PARAM_STR, ".$column->getTypeLength().");"."\n";
				} else {
					if ($column->getTrueTypeLength() === NULL) {
						$php .= ", PDO::PARAM_INT);"."\n";
					} else {
						$php .= ", PDO::PARAM_INT, ".$column->getTypeLength().");"."\n";
					}
				}

				$php .= "\t"."\t"."\t"."if ($"."statement->execute()) {"."\n";
				$php .= "\t"."\t"."\t"."\t"."$"."statement->setFetchMode(PDO::FETCH_CLASS, '".$table_name."');"."\n";
				$php .= "\t"."\t"."\t"."    if ($"."rows = $"."statement->fetch(PDO::FETCH_CLASS)) {"."\n";
				$php .= "\t"."\t"."\t"."    "."\t"."$".strtolower($table_name)."s  = $"."rows;"."\n";
				$php .= "\t"."\t"."\t"."    }"."\n";
				$php .= "\t"."\t"."\t"."} else {"."\n";
				$php .= "\t"."\t"."\t"."\t"."throw new Exception(";
				$php .= '"unable to load '.$table_name.' object from Database");'."\n";
				$php .= "\t"."\t"."\t"."}"."\n";
				$php .= "\t"."\t"."} catch (PDOException $"."e) {"."\n";
				$php .= "\t"."\t"."\t"."throw new Exception(";
				$php .= '"unable to load '.$table_name.' object from Database: ".$';
				$php .= "e->getMessage().$"."e->getTraceAsString());"."\n";
				$php .= "\t"."\t"."}"."\t"."\t"."\t"."\t"."\t"."\n";
				$php .= "\t"."\t"."return $".strtolower($table_name)."s ;"."\t"."\n";
				$php .= "\t"."}"."\n"."\n"."\n";
			}

			$php .= "\n"."\n".
				"\t//-------------------------------------------------------------\n".
				"\t//-------------------------------------------------------------"."\n";

			$php .= "}"."\n";
		}

		$php .= "\n"."\n"."\n"."\n"."?>";

		return $php;
	}

	/**
	 * @return array
	 */
	public function getTables()
	{
		return $this->tables;
	}

	/**
	 * @param $tableName
	 * @return null|Table
	 */
	public function getTable($tableName)
	{
		$tables = $this->tables;

		for ($i = 0; $i < count($tables); $i++) {
			$table = $tables[$i];
			$currentTable_name = $table->getName();

			if ($currentTable_name === $tableName) $this->requestedTable = $table;
		}

		return $this->requestedTable;
	}

	/**
	 * Returns the Column object from a givens string naming the location
	 * @param string $tableAndColumnName location of column (e.g. 'mytable.somecolumn')
	 * @return Column|null
	 * @throws Exception if table.column format is broken
	 */
	public function getColumn($tableAndColumnName) {
		$parts = explode(".", $tableAndColumnName);
		if (count($parts) !== 2) {
			throw new Exception("use getColumn() on ".DBCreator::class." only with syntax: 'tablename.colname'");
		}
		$table = $this->getTable($parts[0]);
		if ($table === NULL) {
			return NULL;
		}
		return $table->getColumn($parts[1]);
	}

	/**
	 * @param $type
	 * @return bool|int
	 */
	public function getConstantForColumnType($type)
	{
		switch (strtolower($type)) {
			case "smallint":
				return DBCreator::SMALLINT;
			case "int":
				return DBCreator::INT;
			case "bigint":
				return DBCreator::BIGINT;
			case "varchar":
				return DBCreator::VARCHAR;
			case "longtext":
				return DBCreator::LONGTEXT;
			default:
				return false;
		}
	}

	/**
	 * @param $oldCreator
	 * @return bool|string
	 * @throws Exception
	 */
	public function getPatchSQLFrom($oldCreator)
	{
		$patchSQL = "";

		foreach ($oldCreator->tables as $oldCreatorTable) {
			/* @var $oldCreatorTable Table */

			if (!$this->checkIfTableExists( $this, $oldCreatorTable )) {
				$patchSQL .= "\tDROP TABLE IF EXISTS `".strtolower( $oldCreatorTable->getName() )."`;\n\t\n";
			}
		}

		foreach ($this->tables as $newCreatorTable) {
			/* @var $newCreatorTable Table */

			if (!$this->checkIfTableExists( $oldCreator, $newCreatorTable )) {
				$patchSQL .= "\tCREATE TABLE IF NOT EXISTS `".strtolower( $newCreatorTable->getName() )."` (\n";

				foreach ($newCreatorTable->getColumns() as $column) {
					/* @var $column Column */
					$column_type = $column->getType();
					$column_type_length = $column->getTypeLength();
					$column_default = $column->getDefault();

					$column_default !== NULL ? $default = "default '".$column_default."'" : $default = "default NULL";
					if ($column->getPrimary() === true) {
						$default = "NOT NULL auto_increment ";
					}

					if ($column->getUnique() === true) {
						$default = "NOT NULL";
					}

					//strings are all utf-8
					$utf = ($column->isTypeStr($column_type)) ? " character set utf8 " : "";

					//if a length is assigned - use it
					$length = (!$column->isTypeStr($column_type) || ($column_type_length !== NULL))
						? "(".$column_type_length.")"
						: "";
					if (!$column->isTypeStr($column_type)) $length .= " "; //small spacing fix for compatibility

					$patchSQL .= "\t\t`" . strtolower($column->getName()) . "` ";
					$patchSQL .= $column->getTypeAsStr() . $length . $utf . $default . ","."\n";
				}

				foreach ($newCreatorTable->getColumns() as $column) {
					/* @var $column Column */

					if ($column->getPrimary() === true) {
						$patchSQL = substr($patchSQL, 0, -2);
						$patchSQL .= "\n"."\t\t, PRIMARY KEY (`".strtolower($column->getName())."`)"."\n";
					}
				}

				foreach ($newCreatorTable->getColumns() as $column) {
					/* @var $column Column */

					if ($column->getUnique() === true) {
						$patchSQL .= "  , UNIQUE KEY `".strtolower($column->getName()).
							"` (`".strtolower($column->getName())."`)"."\n";
					}
				}

				$patchSQL = substr($patchSQL, 0, -1);
				$patchSQL .= "\n"."\t) ENGINE=MyISAM DEFAULT CHARSET=utf8  AUTO_INCREMENT=1;\t"."\n"."\t"."\n";
			}
		}

		foreach ($oldCreator->tables as $oldCreatorTable) {
			/* @var $oldCreatorTable Table */

			if ($this->checkIfTableExists($this, $oldCreatorTable)) {
				foreach ($oldCreatorTable->getColumns() as $oldCreatorColumn) {
					/* @var $oldCreatorColumn Column */

					if (!$this->checkIfColumnExists( $this, $oldCreatorColumn )) {
						$patchSQL .= "\tALTER"." TABLE `".strtolower($oldCreatorTable->getName());
						$patchSQL .= "` DROP COLUMN `";
						$patchSQL .= strtolower($oldCreatorColumn->getName()) ."`;"."\n\t\n";
					}
				}
			}
		}

		foreach ($this->tables as $newCreatorTable) {
			/* @var $newCreatorTable Table */

			if ($this->checkIfTableExists( $oldCreator, $newCreatorTable )) {
				foreach ($newCreatorTable->getColumns() as $newCreatorColumn) {
					/* @var $newCreatorColumn Column */

					if (!$this->checkIfColumnExists( $oldCreator, $newCreatorColumn )) {
						$column_type = $newCreatorColumn->getType();
						$column_type_length = $newCreatorColumn->getTypeLength();
						$column_default = $newCreatorColumn->getDefault();

						$patchSQL .= "\tALTER"." TABLE `".strtolower($newCreatorTable->getName());
						$patchSQL .= "` ADD ";

						$column_default !== NULL ? $default = "default '".$column_default."'" : $default = "default NULL";
						if ($newCreatorColumn->getPrimary() === true) {
							$default = "NOT NULL auto_increment ";
						}

						if ($newCreatorColumn->getUnique() === true) {
							$default = "NOT NULL";
						}

						//strings are all utf-8
						$utf = ($newCreatorColumn->isTypeStr($column_type)) ? " character set utf8 " : "";

						//if a length is assigned - use it
						$length = (!$newCreatorColumn->isTypeStr($column_type) || ($column_type_length !== NULL))
							? "(".$column_type_length.")"
							: "";
						if (!$newCreatorColumn->isTypeStr($column_type)) $length .= " "; //small spacing fix for compatibility

						$patchSQL .= "`" . strtolower($newCreatorColumn->getName()) . "` ";
						$patchSQL .= $newCreatorColumn->getTypeAsStr() . $length . $utf . $default . ";"."\n\t";
					}
				}
			}
		}
		return $patchSQL;
	}

	/**
	 * @param DBCreator $creator
	 * @param Table $searchTable
	 * @return bool
	 */
	public function checkIfTableExists(DBCreator $creator, Table $searchTable)
	{
		$tableExistsInOldCreator = false;

		foreach ($creator->tables as $creatorTable) {
			/* @var $creatorTable Table */

			if ($creatorTable->getName() === $searchTable->getName()) {
				$tableExistsInOldCreator = true;
				break;
			}
		}

		return $tableExistsInOldCreator;
	}

	/**
	 * @param DBCreator $creator
	 * @param Column $searchColumn
	 * @return bool
	 */
	public function checkIfColumnExists(DBCreator $creator, Column $searchColumn)
	{
		$columnExistsInOldCreator = false;

		foreach ($creator->tables as $creatorTable) {
			/* @var $creatorTable Table */

			foreach ($creatorTable->getColumns() as $column) {
				/* @var $column Column */

				if ($column->getName() === $searchColumn->getName()) {
					$columnExistsInOldCreator = true;
					break;
				}
			}
		}

		return $columnExistsInOldCreator;
	}
}
