<?php
/**
 * BBQL Model for BBQL Component
 * @license    GNU/GPL
 */

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.application.component.model' );
 
/**
 * BBQL Model
 */
class BbqlModelUtilities extends JModel
{
	function getUserList() {
		global $userList;
		return $userList;
	}
	function getStringsLocalized() {
		global $bbqlDb;
		
		// get races
		$sql = "SELECT r.ID, SL.English FROM Races r INNER JOIN Strings_Localized SL ON r.idStrings_Localized = SL.ID";
		$raceNames = $bbqlDb->query($sql);
		
		// get player position names
		$sql = "SELECT ID, English FROM Strings_Localized WHERE Label LIKE 'PLAYERTYPE%'";
		$playerPositionNames = $bbqlDb->query($sql);
		
		// get skill names
		$sql = "SELECT Sk.ID, Sk.Description, SL.English FROM Skill_Listing Sk INNER JOIN Strings_Localized SL ON Sk.idStrings_Localized = SL.ID";
		$skillNames = $bbqlDb->query($sql);
		
		// get player level names
		$sql = "SELECT ID, English FROM Strings_Localized WHERE Label LIKE 'LEVEL_NAME%'";
		$playerLevelNames = $bbqlDb->query($sql);
		
		// get casualty effect names
		$sql = "SELECT pct.ID, sl.English FROM Player_Casualty_Types pct INNER JOIN Strings_Localized sl ON pct.idStrings_Localized_Effect = sl.ID";
		$casualtyEffectNames = $bbqlDb->query($sql);
		
		$struct = array();
		$struct['races'] = $raceNames;
		$struct['playerPositions'] = $playerPositionNames;
		$struct['skills'] = $skillNames;
		$struct['playerLevels'] = $playerLevelNames;
		$struct['casualtyEffects'] = $casualtyEffectNames;
		
		
		return $struct;
	}
	
	function convertMA($MA) {
		return round($MA/8.333);
	}
	function convertST($ST) {
		return round($ST/10-2);
	}
	function convertAG($AG) {
		return round($AG/16.666);
	}
	function convertAV($AV) {
		$AV = round($AV, 3);
		switch ($AV) {
			case ($AV >= 99.990):
				return 12;
			case ($AV >= 97.213):
				return 11;
			case ($AV >= 91.657):
				return 10;
			case ($AV >= 83.324):
				return 9;
			case ($AV >= 72.213):
				return 8;
			case ($AV >= 58.324):
				return 7;
			case ($AV >= 41.657):
				return 6;
			case ($AV >= 27.768):
				return 5;
			case ($AV >= 16.657):
				return 4;
			case ($AV >= 8.324):
				return 3;
			case ($AV >= 2.768):
				return 2;
			case ($AV >= 0.990):
				return 1;
			default:
				return $AV;
		}
	}
	
	function setMApercent($MA) {
		return $MA*8.333;
	}
	function setSTpercent($ST) {
		return ($ST+2)*10;
	}
	function setAGpercent($AG) {
		return $AG*16.666;
	}
	function setAVpercent($AV) {
		switch ($AV) {
			case 2:
				return 2.778;
			case 3:
				return 8.333;
			case 4:
				return 16.667;
			case 5:
				return 27.778;
			case 6:
				return 41.667;
			case 7:
				return 58.333;
			case 8:
				return 72.222;
			case 9:
				return 83.333;
			case 10:
				return 91.667;
			case 11:
				return 97.222;
			case 12: 
				return 99.000;
		}
	}
	
	function updateTeamValues() {
		global $bbqlDb;
    	$sql = "SELECT teamHash from Team_Listing where iValue = 1120";
    	$teams = $bbqlDb->query($sql)->fetchAll();
    	foreach ($teams as $val) {
    		$this->updateTeamValue($val['teamHash']);
    	}
    }
	
	function updateTeamValue($teamId) {
		global $bbqlDb;
		//had to break complex update query into three pieces due to processing time
		//get player values
		$sql = "SELECT sum(PL.iValue) AS playerValue FROM Player_Listing PL WHERE PL.teamHash = '".$teamId."' AND PL.iMatchSuspended = 0 AND PL.bRetired = 0";
		$playerValue = $bbqlDb->query($sql)->fetch();
		
		//get value of team items
		$sql = "SELECT (TL.iPopularity*10 + TL.iCheerleaders*10 + TL.iAssistantCoaches*10 + TL.bApothecary*50 + " .
			" TL.iRerolls*R.iRerollPrice/1000) AS teamValue FROM Team_Listing TL " .
			" INNER JOIN Races R ON TL.idRaces = R.ID " .
			" WHERE TL.teamHash = '".$teamId."'";
		$teamValue = $bbqlDb->query($sql)->fetch();
		
		//total the player and team items
		$fullValue = $playerValue['playerValue'] + $teamValue['teamValue'];
		
		//update the team value
		$sql = "UPDATE Team_Listing SET iValue = ".$fullValue.
			" WHERE teamHash = '".$teamId."'";
		$bbqlDb->query($sql);
	}
	
	function getDefaultPlayerAttributes() {
		global $bbqlDb;
		$sql = "SELECT ID, Characteristics_fMovementAllowance MA, Characteristics_fStrength ST,
				Characteristics_fAgility AG, Characteristics_fArmourValue AV FROM Player_Types";
		$defaults = $bbqlDb->query($sql)->fetchAll();
		
		//set up associative array to make player attribute access easy
		$defaultAttributeMap = array();
		foreach ($defaults as $row) {
			$defaultAttributeMap[$row['ID']] = $row;
		}
		
		return $defaultAttributeMap;
	}
	
	function getPlayer_ListingFields() {
		$playerFields = array('idPlayer_Names','strName','idPlayer_Types','idTeam_Listing_Previous',
			'idRaces','iPlayerColor','iSkinScalePercent','iSkinMeshVariant','iSkinTextureVariant','fAgeing','iNumber',
			'Characteristics_fMovementAllowance','Characteristics_fStrength','Characteristics_fAgility',
			'Characteristics_fArmourValue','idPlayer_Levels','iExperience','idEquipment_Listing_Helmet',
			'idEquipment_Listing_Pauldron','idEquipment_Listing_Gauntlet','idEquipment_Listing_Boot',
			'Durability_iHelmet','Durability_iPauldron','Durability_iGauntlet','Durability_iBoot','iSalary',
			'Contract_iDuration','Contract_iSeasonRemaining','idNegotiation_Condition_Types',
			'Negotiation_iRemainingTries','Negotiation_iConditionDemand','iValue','iMatchSuspended','iNbLevelsUp',
			'LevelUp_iRollResult','LevelUp_iRollResult2','LevelUp_bDouble','bGenerated','bStar','bEdited','bDead','strLevelUp',
			'playerHash','teamHash','bRetired','playerId');
		return $playerFields;
	}

	function calculateSpirallingExpenses($teamValue) {
		$spirallingExpense = 0;
		if ($teamValue >= 1750)
			$spirallingExpense = floor( ( $teamValue-1600 ) /150 )* 10000;
		
		return $spirallingExpense;
	}
	
	////////////////////////////////////////////////////////
	// Function:         dump
	// Inspired from:     PHP.net Contributions
	// Description: Helps with php debugging

	function dump(&$var, $info = FALSE)
	{
	    $scope = false;
	    $prefix = 'unique';
	    $suffix = 'value';
	 
	    if($scope) $vals = $scope;
	    else $vals = $GLOBALS;
	
	    $old = $var;
	    $var = $new = $prefix.rand().$suffix; $vname = FALSE;
	    foreach($vals as $key => $val) if($val === $new) $vname = $key;
	    $var = $old;
	
	    echo "<pre style='margin: 0px 0px 10px 0px; display: block; background: white; color: black; font-family: Verdana; border: 1px solid #cccccc; padding: 5px; font-size: 10px; line-height: 13px;'>";
	    if($info != FALSE) echo "<b style='color: red;'>$info:</b><br>";
	    $this->do_dump($var, '$'.$vname);
	    echo "</pre>";
	}

	////////////////////////////////////////////////////////
	// Function:         do_dump
	// Inspired from:     PHP.net Contributions
	// Description: Better GI than print_r or var_dump

	function do_dump(&$var, $var_name = NULL, $indent = NULL, $reference = NULL)
	{
	    $do_dump_indent = "<span style='color:#eeeeee;'>|</span> &nbsp;&nbsp; ";
	    $reference = $reference.$var_name;
	    $keyvar = 'the_do_dump_recursion_protection_scheme'; $keyname = 'referenced_object_name';
	
	    if (is_array($var) && isset($var[$keyvar]))
	    {
	        $real_var = &$var[$keyvar];
	        $real_name = &$var[$keyname];
	        $type = ucfirst(gettype($real_var));
	        echo "$indent$var_name <span style='color:#a2a2a2'>$type</span> = <span style='color:#e87800;'>&amp;$real_name</span><br>";
	    }
	    else
	    {
	        $var = array($keyvar => $var, $keyname => $reference);
	        $avar = &$var[$keyvar];
	   
	        $type = ucfirst(gettype($avar));
	        if($type == "String") $type_color = "<span style='color:green'>";
	        elseif($type == "Integer") $type_color = "<span style='color:red'>";
	        elseif($type == "Double"){ $type_color = "<span style='color:#0099c5'>"; $type = "Float"; }
	        elseif($type == "Boolean") $type_color = "<span style='color:#92008d'>";
	        elseif($type == "NULL") $type_color = "<span style='color:black'>";
	   
	        if(is_array($avar))
	        {
	            $count = count($avar);
	            echo "$indent" . ($var_name ? "$var_name => ":"") . "<span style='color:#a2a2a2'>$type ($count)</span><br>$indent(<br>";
	            $keys = array_keys($avar);
	            foreach($keys as $name)
	            {
	                $value = &$avar[$name];
	                $this->do_dump($value, "['$name']", $indent.$do_dump_indent, $reference);
	            }
	            echo "$indent)<br>";
	        }
	        elseif(is_object($avar))
	        {
	            echo "$indent$var_name <span style='color:#a2a2a2'>$type</span><br>$indent(<br>";
	            foreach($avar as $name=>$value) $this->do_dump($value, "$name", $indent.$do_dump_indent, $reference);
	            echo "$indent)<br>";
	        }
	        elseif(is_int($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color$avar</span><br>";
	        elseif(is_string($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color\"$avar\"</span><br>";
	        elseif(is_float($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color$avar</span><br>";
	        elseif(is_bool($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color".($avar == 1 ? "TRUE":"FALSE")."</span><br>";
	        elseif(is_null($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> {$type_color}NULL</span><br>";
	        else echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $avar<br>";
	
	        $var = $var[$keyvar];
	    }
	}
}