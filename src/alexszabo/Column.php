<?php
namespace alexszabo;

use \Exception;

class Column
{
	private $name;
	private $type;
	private $type_length;
	private $isPrimary;
	private $isUnique;
	private $default = NULL;

	/**
	 * @var null|Column
	 */
	private $foreignKeyColumn = NULL;


	/**
	 * @var null|Table
	 */
	private $parentTable = NULL;

	/**
	 * Creates a new table Column object.
	 * @param string $name
	 * @param int $type
	 * @param int|null $length
	 * @throws Exception
	 */
	function __construct($name, $type, $length = NULL)
	{
		if ($this->isTypeSupported($type)) {
			$this->type = $type;
		} else {
			throw new Exception("This type: '$type' is not a supported type");
		}
		$this->name = $name;
		$this->type_length = $length;
	}

	/**
	 * @return $this
	 */
	public function setPrimary()
	{
		$this->isPrimary = true;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setUnique()
	{
		$this->isUnique = true;
		return $this;
	}

	/**
	 * @param $default
	 * @return $this
	 */
	public function setDefault($default)
	{
		$this->default = $default;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return int
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @return mixed
	 */
	public function getPrimary()
	{
		return $this->isPrimary;
	}

	/**
	 * @return mixed
	 */
	public function getUnique()
	{
		return $this->isUnique;
	}

	/**
	 * @return null
	 */
	public function getDefault()
	{
		return $this->default;
	}

	/**
	 * @return bool|string
	 */
	public function getTypeAsStr()
	{
		switch ($this->getType()) {
			case 1:
				return "smallint";
			case 2:
				return "int";
			case 3:
				return "bigint";
			case 4:
				return "varchar";
			case 5:
				return "longtext";
			default:
				return false;
		}
	}

	/**
	 * @return int|null
	 */
	public function getTypeLength()
	{
		if ($this->type_length === NULL) {
			switch ($this->getType()) {
				case DBCreator::SMALLINT:
					return $this->type_length = 6;
				case DBCreator::INT:
					return $this->type_length = 6;
				case DBCreator::BIGINT:
					return $this->type_length = 20;
				default:
					return $this->type_length;
			}
		}

		return $this->type_length;
	}

	/**
	 * @param $type
	 * @return bool
	 */
	public function isTypeStr($type)
	{
		$typeIsString = false;
		if ($type === DBCreator::VARCHAR || $type === DBCreator::LONGTEXT) {
			$typeIsString = true;
		}

		return $typeIsString;
	}

	/**
	 * @return int|null
	 */
	public function getTrueTypeLength()
	{
		return $this->type_length;
	}

	/**
	 * @param Column $column
	 */
	public function addForeignKeyConstraintsTo(Column $column)
	{
		$this->foreignKeyColumn = $column;
	}

	/**
	 * @return Column|null
	 */
	public function getForeignKey()
	{
		return $this->foreignKeyColumn;
	}

	/**
	 * @return null|string
	 */
	public function getForeignKeyAsString()
	{
		if ($this->foreignKeyColumn === NULL) {
			return NULL;
		}
		return $this->foreignKeyColumn->parentTable->getName() . "." . $this->foreignKeyColumn->getName();
	}

	/**
	 * @param Table $parentTable
	 */
	public function setParentTable(Table $parentTable)
	{
		$this->parentTable = $parentTable;
	}

	/**
	 * @param $type
	 * @return bool
	 */
	private function isTypeSupported($type)
	{
		switch ($type) {
			case DBCreator::SMALLINT:
				return true;
			case DBCreator::INT:
				return true;
			case DBCreator::BIGINT:
				return true;
			case DBCreator::VARCHAR:
				return true;
			case DBCreator::LONGTEXT:
				return true;
			default:
				return false;
		}
	}
}
