<?php

/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

class SQLDumper
{	
	public function dumpTablesToFile($dbTables, $filePath)
	{
		if($this->canNative('mysqldump'))
		{
			return $this->nativeDumpToFile($dbTables, $filePath);
		}
		else
		{
			return $this->softwareDumpToFile($dbTables, $filePath);
		}
	}
	
	private function setTimelimit()
	{
		@set_time_limit(600); // 10 Minutes
	}
		
	private function canNative($command)
	{
		return function_exists('shell_exec') && function_exists('escapeshellarg') && !empty(shell_exec("which " . $command));
	}
	
	private function nativeDumpToFile($dbTables, $filePath)
	{
		$database	= array();
		require 'includes/config.php';

        $dbVersion	= Database::get()->selectSingle('SELECT @@version', array(), '@@version');
        if(version_compare($dbVersion, '5.5') >= 0) {
            putenv('MYSQL_PWD='.$database['userpw']);
            $passwordArgument = '';
        } else {
            $passwordArgument = "--password='".escapeshellarg($database['userpw'])."'";
        }

		$dbTables	= array_map('escapeshellarg', $dbTables);
		$sqlDump	= shell_exec("mysqldump --host=".escapeshellarg($database['host'])." --port=".((int) $database['port'])." --user=".escapeshellarg($database['user'])." ".$passwordArgument." --no-create-db --order-by-primary --add-drop-table --comments --complete-insert --hex-blob ".escapeshellarg($database['databasename'])." ".implode(' ', $dbTables)." 2>&1 1> ".$filePath);
		if(strlen($sqlDump) !== 0) #mysqldump error
		{
			throw new Exception($sqlDump);
		}
		return $sqlDump;
	}
	
	private function softwareDumpToFile($dbTables, $filePath)
	{
		$this->setTimelimit();

		$db	= Database::get();
		$database	= array();
		require 'includes/config.php';
		$integerTypes	= array('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double', 'real');
		$gameVersion	= Config::get()->VERSION;
		$fp	= fopen($filePath, 'w');
		fwrite($fp, "-- MySQL dump | 2Moons dumper v{$gameVersion}
--
-- Host: {$database['host']}    Database: {$database['databasename']}
-- ------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

");

		foreach($dbTables as $dbTable)
		{
			$numColumns	= array();
			$firstRow	= true;

			fwrite($fp, "--\n-- Table structure for table `{$dbTable}`\n--\n\nDROP TABLE IF EXISTS `{$dbTable}`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;\n\n");

			$createTable	= $db->nativeQuery("SHOW CREATE TABLE ".$dbTable);
            $createTableSql = isset($createTable['Create Table']) ?
                $createTable['Create Table'] : (
                #old mysql clients
                isset($createTable[0]['Create Table']) ?
                    $createTable[0]['Create Table'] :
                    false
            );

            if($createTableSql === false) {
                throw new Exception("Error after executing SHOW CREATE TABLE ".$dbTable."! Can't find key 'Create Table' in the results. Available data: \n\n".print_r($createTable, true));
            }

			fwrite($fp, $createTableSql.';');
			fwrite($fp, "\n\n/*!40101 SET character_set_client = @saved_cs_client */;");

			$sql = "SELECT COUNT(*) as state FROM ".$dbTable.";";

			$count	= $db->nativeQuery($sql);
			if($count[0]['state'] == 0)
			{
				fwrite($fp, "\n\n--\n-- No data for table `{$dbTable}`\n--\n\n");
				continue;
			}

			fwrite($fp, "
			
--
-- Dumping data for table `{$dbTable}`
--

LOCK TABLES `{$dbTable}` WRITE;
/*!40000 ALTER TABLE `{$dbTable}` DISABLE KEYS */;

");
			$columnsData	= $db->nativeQuery("SHOW COLUMNS FROM `".$dbTable."`");
			$columnNames	= array();
			foreach($columnsData as $columnData)
			{
				$columnNames[]	= $columnData['Field'];
				foreach($integerTypes as $type)
				{
					if(strpos($columnData['Type'], $type.'(') !== false)
					{
						$numColumns[]	= $columnData['Field'];
						break;
					}
				}
			}
			
			$insertInto	= "INSERT INTO `{$dbTable}` (`".implode("`, `", $columnNames)."`) VALUES\r\n";
			
			fwrite($fp, $insertInto);
			$i = 0;
			$tableData	= $db->select("SELECT * FROM ".$dbTable);
			foreach($tableData as $tableRow)
			{
				$rowData = array();
				$i++;
				if(($i % 50) === 0)
				{
					$firstRow	= true;
					fwrite($fp, ";\r\n");
					fwrite($fp, $insertInto);
				}
				
				if(!$firstRow)
				{
					fwrite($fp, ",\r\n");
				}
				else
				{
					$firstRow = false;
				}
				
				foreach($tableRow as $colum => $value)
				{
					if(in_array($colum, $numColumns))
					{
						// var_export() keeps all digits of a float, a string cast rounds to 14
						$rowData[]	= $value === NULL ? 'NULL' : (is_float($value) ? var_export($value, true) : $value);
					}
					else
					{
						$rowData[]	= $value === NULL ? 'NULL' : $db->quote($value);
					}
				}
				fwrite($fp, "(".implode(", ",$rowData).")");
			}
			fwrite($fp, ";
			
/*!40000 ALTER TABLE `{$dbTable}` ENABLE KEYS */;
UNLOCK TABLES;

");
		}
		fwrite($fp, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on ".date("Y-d-m H:i:s"));
		fclose($fp);

		return filesize($filePath) !== 0;
	}
	
	public function restoreDatabase($filePath)
	{
		// Ugly.
		$this->setTimelimit();
		
		if($this->canNative('mysql'))
		{
			$database	= array();
			require 'includes/config.php';
			// The password goes through the environment: on the command line it is visible
			// to other users in the process list. Strict mode off: see the PHP restore below.
			putenv('MYSQL_PWD='.$database['userpw']);
			$sqlDump	= shell_exec("mysql --host=".escapeshellarg($database['host'])." --port=".((int) $database['port'])." --user=".escapeshellarg($database['user'])." --init-command=".escapeshellarg('SET SESSION innodb_strict_mode = 0')." ".escapeshellarg($database['databasename'])." < ".escapeshellarg($filePath)." 2>&1 1> /dev/null");
			putenv('MYSQL_PWD');
			if(strlen($sqlDump) !== 0) #mysql error
			{
				throw new Exception($sqlDump);
			}
		}
		else
		{
			// Tables converted from MyISAM can still carry ROW_FORMAT=FIXED, SHOW CREATE TABLE
			// keeps it in the dump, and InnoDB in strict mode refuses to create such a table;
			// outside strict mode it falls back to the default row format.
			$db			= Database::get();
			$strictMode	= $db->nativeQuery('SELECT @@SESSION.innodb_strict_mode AS strictMode;');
			$db->nativeQuery('SET SESSION innodb_strict_mode = 0;');
			try {
				foreach(self::splitStatements(file_get_contents($filePath)) as $query)
				{
					$db->nativeQuery($query);
				}
			} finally {
				$db->nativeQuery('SET SESSION innodb_strict_mode = '.(int) $strictMode[0]['strictMode'].';');
			}
		}
	}

	/**
	 * Splits an SQL script into single statements.
	 *
	 * Splits on ";" outside of quoted strings, identifiers and comments, so neither
	 * line endings nor a ";" inside the data matter. Comments are dropped, except
	 * executable ones (/*! ... *\/), which MySQL runs as part of the statement.
	 *
	 * @param string $sql
	 * @return string[]
	 */
	public static function splitStatements($sql)
	{
		$statements	= array();
		$statement	= '';
		$length		= strlen($sql);
		$copied		= 0;
		$i			= 0;

		while (true)
		{
			$i += strcspn($sql, "'\"`#-/;", $i);
			if ($i >= $length)
			{
				break;
			}

			$char	= $sql[$i];
			$next	= ($i + 1 < $length) ? $sql[$i + 1] : '';

			if ($char === "'" || $char === '"' || $char === '`')
			{
				$i = self::skipQuoted($sql, $i, $length);
			}
			elseif ($char === '#' || ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))))
			{
				$statement	.= substr($sql, $copied, $i - $copied);
				$end		= strpos($sql, "\n", $i);
				$i			= $copied = ($end === false) ? $length : $end;
			}
			elseif ($char === '/' && $next === '*')
			{
				$end	= strpos($sql, '*/', $i + 2);
				$end	= ($end === false) ? $length : $end + 2;
				if ($i + 2 >= $length || $sql[$i + 2] !== '!')
				{
					$statement	.= substr($sql, $copied, $i - $copied).' ';
					$copied		= $end;
				}
				$i = $end;
			}
			elseif ($char === ';')
			{
				$statement	.= substr($sql, $copied, $i - $copied);
				$statements[]	= $statement;
				$statement	= '';
				$i			= $copied = $i + 1;
			}
			else
			{
				$i++;
			}
		}

		$statements[]	= $statement.substr($sql, $copied);

		return array_values(array_filter(array_map('trim', $statements), 'strlen'));
	}

	/**
	 * Returns the position right after the closing quote of the string or identifier
	 * that starts at $start. Strings know backslash escapes and doubled quotes,
	 * identifiers (`...`) only doubled backticks.
	 */
	private static function skipQuoted($sql, $start, $length)
	{
		$quote	= $sql[$start];
		$stops	= ($quote === '`') ? '`' : $quote.'\\';
		$i		= $start + 1;

		while (true)
		{
			$i += strcspn($sql, $stops, $i);
			if ($i >= $length)
			{
				return $length;
			}

			if ($sql[$i] === '\\')
			{
				$i += 2;
			}
			elseif ($i + 1 < $length && $sql[$i + 1] === $quote)
			{
				$i += 2;
			}
			else
			{
				return $i + 1;
			}
		}
	}
}