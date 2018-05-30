<?php
namespace alexszabo;

use \Exception;

class Table
{
	private $name;
	private $columns = [];

	/**
	 * @var null|DBCreator
	 */
	private $dbcreator = NULL;

	function __construct($name)
	{
		$this->name = $name;
	}

	/**
	 * @param Column $column
	 * @throws Exception
	 */
	public function addColumn(Column $column)
	{
		if ($this->isColumnNameFree($column->getName())) {
			$this->columns[] = $column;
			$column->setParentTable($this);
		} else {
			throw new Exception("There already exists a Column with this name:".$column->getName());
		}
	}

	/**
	 * @param $columnName
	 * @return bool
	 */
	private function isColumnNameFree($columnName)
	{
		$nameExists = true;

		foreach ($this->getColumns() as $column) {
			/* @var $column Column */
			if ($column->getName() === $columnName) {
				return $nameExists;
			} else {
				return $nameExists = true;
			}
		}

		return $nameExists;
	}

	/**
	 * @return mixed
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return array
	 */
	public function getColumns()
	{
		return $this->columns;
	}

	/**
	 * @param $columnName
	 * @return Column|mixed|null
	 */
	public function getColumn($columnName)
	{
		foreach($this->getColumns() as $column) {
			/* @var $column Column */

			if ($column->getName() === $columnName) {
				return $column;
			}
		}

		return NULL;
	}

	/**
	 * @param DBCreator $creator
	 */
	public function setParentCreator(DBCreator $creator) {
		$this->dbcreator = $creator;
	}

	public function getParentCreator() {
		return $this->dbcreator;
	}
}
