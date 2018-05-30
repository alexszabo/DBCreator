<?php
namespace alexszabo;

use \Exception;

class FreeMindLoader
{
	/**
	 * @var DBCreator
	 */
	private $creator;

	function __construct(DBCreator $creator)
	{
		$this->creator = $creator;
	}

	/**
	 * @param $xml
	 * @throws Exception
	 */
	public function loadXml($xml)
	{
		$simpleXML = simplexml_load_string($xml);
		$xmlData = get_object_vars($simpleXML->node);

		for ($i = 0; $i < count($xmlData); $i++) {
			$dbTable = $xmlData["node"][$i];
			$dbTableData = get_object_vars($dbTable);
			$dbTableName = $dbTableData["@attributes"]["TEXT"];

			$t = new Table($dbTableName);
			$this->creator->addTable($t);

			for ($j = 0; $j < count($dbTable); $j++) {
				$tableColumn = $dbTable->node[$j];
				$tableData = get_object_vars($tableColumn);
				$tableColumnName = $tableData["@attributes"]["TEXT"];
				$tableColumnTypeName = "";
				$tableColumnTypeLength = NULL;

				$tableColumnData = $tableColumn;
				$tableColumnType = $tableColumnData->node;
				$tableColumnIcon = $tableColumnData->icon;
				$tableColumnDefault = $tableColumnData->node[1];
				$tableColumnArrowLink = $tableColumnData->arrowlink;

				if (count($tableColumnType)) {
					$tableColumnTypeData = get_object_vars($tableColumnType);
					$tableColumnTypeName = $tableColumnTypeData["@attributes"]["TEXT"];
				}

				if ($tableColumnType->node->count()) {
					$tableColumnTypeLengthData = get_object_vars($tableColumnType->node);
					$tableColumnTypeLength = $tableColumnTypeLengthData["@attributes"]["TEXT"];
				}

				if ($tableColumnTypeLength !== NULL) {
					$c = new Column($tableColumnName, $this->creator->getConstantForColumnType($tableColumnTypeName), intval($tableColumnTypeLength));
				} else {
					$c = new Column($tableColumnName, $this->creator->getConstantForColumnType($tableColumnTypeName), $tableColumnTypeLength);
				}
				/* @var $c Column */

				$t->addColumn($c);

				if (count($tableColumnIcon)) {
					$tableColumnIconData = get_object_vars($tableColumnIcon);
					$tableColumnIconName = $tableColumnIconData["@attributes"]["BUILTIN"];

					if ($tableColumnIconName === "wizard") $c->setPrimary();
					if ($tableColumnIconName === "bookmark") $c->setUnique();
				}

				if (count($tableColumnArrowLink)) {
					$arrowlinkData = get_object_vars($tableColumnArrowLink);
					$arrowlinkId = $arrowlinkData["@attributes"]["ID"];
					$foreignKey = $this->searchForeignKey($xmlData, $arrowlinkId);

					$c->addForeignKeyConstraintsTo( $this->creator->getColumn($foreignKey) );
				}

				if ($tableColumnDefault !== NULL) {
					$tableColumnDataDefaultData = get_object_vars($tableColumnDefault->node);
					$tableColumnDefault = $tableColumnDataDefaultData["@attributes"]["TEXT"];

					$c->setDefault($tableColumnDefault);
				}
			}

		}
	}

	/**
	 * @param $xmlData
	 * @param $arrowLinkId
	 * @return null|string
	 */
	private function searchForeignKey($xmlData, $arrowLinkId)
	{
		$foreignKey = NULL;
		for ($k = 0; $k < count($xmlData); $k++) {
			$dbTable = $xmlData["node"][$k];
			$dbTableData = get_object_vars($dbTable);
			$dbTableName = $dbTableData["@attributes"]["TEXT"];

			for ($l = 0; $l < count($dbTable); $l++) {
				$tableColumn = $dbTable->node[$l];
				$tableColumnData = $tableColumn;
				$tableColumnLinktarget = $tableColumnData->linktarget;

				if (count($tableColumnLinktarget)) {
					$linktargetData = get_object_vars($tableColumnLinktarget);
					$linktargetId = $linktargetData["@attributes"]["ID"];

					$tableColumnData = $tableColumn;
					$tableColumnArr = get_object_vars($tableColumnData);
					$tableColumnName = $tableColumnArr["@attributes"]["TEXT"];

					if ($linktargetId === $arrowLinkId) {
						$foreignKey = $dbTableName.".".$tableColumnName;
					}
				}
			}
		}

		return $foreignKey;
	}
}
