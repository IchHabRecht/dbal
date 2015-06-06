<?php
namespace TYPO3\CMS\Dbal\Database\Specifics;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * This class handles the specifics of the active DBMS. Inheriting classes
 * are intended to define their own specifics.
 */
abstract class AbstractSpecifics {
	/**
	 * Constants used as identifiers in $specificProperties.
	 */
	const TABLE_MAXLENGTH = 'table_maxlength';
	const FIELD_MAXLENGTH = 'field_maxlength';
	const LIST_MAXEXPRESSIONS = 'list_maxexpressions';

	/**
	 * Contains the specifics of a DBMS.
	 * This is intended to be overridden by inheriting classes.
	 *
	 * @var array
	 */
	protected $specificProperties = array();

	/**
	 * Contains the DBMS specific mapping information for native MySQL to ADOdb meta field types
	 *
	 * @var array
	 */
	protected $nativeToMetaFieldTypeMap = array(
		'STRING' => 'C',
		'CHAR' => 'C',
		'VARCHAR' => 'C',
		'TINYBLOB' => 'C',
		'TINYTEXT' => 'C',
		'ENUM' => 'C',
		'SET' => 'C',
		'TEXT' => 'XL',
		'LONGTEXT' => 'XL',
		'MEDIUMTEXT' => 'XL',
		'IMAGE' => 'B',
		'LONGBLOB' => 'B',
		'BLOB' => 'B',
		'MEDIUMBLOB' => 'B',
		'YEAR' => 'D',
		'DATE' => 'D',
		'TIME' => 'T',
		'DATETIME' => 'T',
		'TIMESTAMP' => 'T',
		'FLOAT' => 'F',
		'DOUBLE' => 'F',
		'INT' => 'I8',
		'INTEGER' => 'I8',
		'TINYINT' => 'I8',
		'SMALLINT' => 'I8',
		'MEDIUMINT' => 'I8',
		'BIGINT' => 'I8',
	);

	/**
	 * Contains the DBMS specific mapping overrides for native MySQL to ADOdb meta field types
	 */
	protected $nativeToMetaFieldTypeOverrides = array();

	/**
	 * Contains the default mapping information for ADOdb meta to MySQL native field types
	 *
	 * @var array
	 */
	protected $metaToNativeFieldTypeMap = array(
		'C' => 'VARCHAR',
		'C2' => 'VARCHAR',
		'X' => 'LONGTEXT',
		'XL' => 'LONGTEXT',
		'X2' => 'LONGTEXT',
		'B' => 'LONGBLOB',
		'D' => 'DATE',
		'T' => 'DATETIME',
		'L' => 'TINYINT',
		'I' => 'BIGINT',
		'I1' => 'BIGINT',
		'I2' => 'BIGINT',
		'I4' => 'BIGINT',
		'I8' => 'BIGINT',
		'F' => 'DOUBLE',
		'N' => 'NUMERIC'
	);

	/**
	 * Contains the DBMS specific mapping information for ADOdb meta field types to MySQL native field types
	 *
	 * @var array
	 */
	protected $metaToNativeFieldTypeOverrides = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->nativeToMetaFieldTypeMap = array_merge($this->nativeToMetaFieldTypeMap, $this->nativeToMetaFieldTypeOverrides);
		$this->metaToNativeFieldTypeMap = array_merge($this->metaToNativeFieldTypeMap, $this->metaToNativeFieldTypeOverrides);
	}

	/**
	 * Checks if a specific is defined for the used DBMS.
	 *
	 * @param string $specific
	 * @return bool
	 */
	public function specificExists($specific) {
		return isset($this->specificProperties[$specific]);
	}

	/**
	 * Gets the specific value.
	 *
	 * @param string $specific
	 * @return mixed
	 */
	public function getSpecific($specific) {
		return $this->specificProperties[$specific];
	}

	/**
	 * Splits $expressionList into multiple chunks.
	 *
	 * @param array $expressionList
	 * @param bool $preserveArrayKeys If TRUE, array keys are preserved in array_chunk()
	 * @return array
	 */
	public function splitMaxExpressions($expressionList, $preserveArrayKeys = FALSE) {
		if (!$this->specificExists(self::LIST_MAXEXPRESSIONS)) {
			return array($expressionList);
		}

		return array_chunk($expressionList, $this->getSpecific(self::LIST_MAXEXPRESSIONS), $preserveArrayKeys);
	}

	/**
	 * Transforms a database specific representation of field information and translates it
	 * as close as possible to the MySQL standard.
	 *
	 * @param array $fieldRow
	 * @param string $metaType
	 * @return array
	 */
	public function transformFieldRowToMySQL($fieldRow, $metaType) {
		$mysqlType = $this->getNativeFieldType($metaType);
		$mysqlType .= $this->getNativeFieldLength($mysqlType, $fieldRow['max_length']);

		$fieldRow['Field'] = $fieldRow['name'];
		$fieldRow['Type'] = strtolower($mysqlType);
		$fieldRow['Null'] = $this->getNativeNotNull($fieldRow['not_null']);
		$fieldRow['Key'] = '';
		$fieldRow['Default'] = $fieldRow['default_value'];
		$fieldRow['Extra'] = '';

		return $fieldRow;
	}

	/**
	 * Return actual MySQL type for meta field type
	 *
	 * @param string $metaType Meta type (currenly ADOdb syntax only, http://phplens.com/lens/adodb/docs-adodb.htm#metatype)
	 * @return string Native type as reported as in mysqldump files, uppercase
	 */
	public function getNativeFieldType($metaType) {
		$metaType = strtoupper($metaType);
		return empty($this->metaToNativeFieldTypeMap[$metaType]) ? $metaType : $this->metaToNativeFieldTypeMap[$metaType];
	}

	/**
	 * Return MetaType for native MySQL field type
	 *
	 * @param string $nativeType native type as reported as in mysqldump files
	 * @return string Meta type (currently ADOdb syntax only, http://phplens.com/lens/adodb/docs-adodb.htm#metatype)
	 */
	public function getMetaFieldType($nativeType) {
		$nativeType = strtoupper($nativeType);
		return empty($this->nativeToMetaFieldTypeMap[$nativeType]) ? 'N' : $this->nativeToMetaFieldTypeMap[$nativeType];
	}

	/**
	 * Determine the native field length information for a table field.
	 *
	 * @param string  $mysqlType
	 * @param int $maxLength
	 * @return string
	 */
	public function getNativeFieldLength($mysqlType, $maxLength) {
		if ($maxLength === -1) {
			return '';
		}
		switch ($mysqlType) {
			case 'INT':
				return '(11)';
			default:
				return '(' . $maxLength . ')';
		}
	}

	/**
	 * Return the MySQL native representation of the NOT NULL setting
	 *
	 * @param mixed $notNull
	 * @return string
	 */
	protected function getNativeNotNull($notNull) {
		return (bool)$notNull ? 'NO' : 'YES';
	}
}
