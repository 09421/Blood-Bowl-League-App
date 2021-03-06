<?php
/**
 * Team Model for BBQL Component
 * @license    GNU/GPL
 */

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.application.component.model' );

/**
 * BBQL Model
 */
class BbqlModelTeam extends JModel {
	
    /**
    * Gets information for a team
    * @return result object with team info
    */
	var $dbHandle;
	var $teamId;
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		
		global $systemPathToComponent, $httpPathToComponent, $bbqlDb;
		
		$this->teamId = JRequest::getVar('teamId');
		
		$this->dbHandle = $bbqlDb;
		
		// include the utilities class
		include_once($httpPathToComponent.DS.'models'.DS.'utilities.php');
		// include the player class
		include_once($httpPathToComponent.DS.'models'.DS.'player.php');
	
		$this->utils = new BbqlModelUtilities;
		$this->player = new BbqlModelPlayer;
		
		$this->stringsLocalized = $this->utils->getStringsLocalized();
		$this->user =& JFactory::getUser();
		
		$this->positionCount = array();
	}
	
	function __destruct() {
		unset($this->dbHandle);
	}
	
	function getLeagueInfo() {
		$sql = "SELECT TL.leagueId, L.name AS leagueName, FullControl FROM Team_Listing TL INNER JOIN League L ON TL.leagueId = L.ID
			WHERE teamHash = '".$this->teamId."'";
		
		return $this->dbHandle->query($sql)->fetch();
	}
	
	function getTeamDetails() {
		// get team information from database
		$sql = "SELECT TL.*, R.iRerollPrice
				FROM Team_Listing TL INNER JOIN Races R ON TL.idRaces = R.ID
				WHERE teamHash = '" . $this->teamId . "'";
		$teamInfo = $this->dbHandle->query($sql)->fetch();

		return $teamInfo;
	}
	
	function getRoster() {
		// get roster information from database
		$sql = "SELECT PL.playerHash, PL.teamHash, PL.strName, PL.idPlayer_Types, PL.iNumber, PL.Characteristics_fMovementAllowance MA,
				PL.Characteristics_fStrength ST, PL.Characteristics_fAgility AG, PL.Characteristics_fArmourValue AV, 
				PL.idPlayer_Levels, PL.iExperience, PL.iValue, PL.iMatchSuspended, PL.iNbLevelsUp, bDead, bRetired, iSkinTextureVariant,
				PL.LevelUp_iRollResult, PL.LevelUp_iRollResult2, PL.journeyman,
				PT.idStrings_Localized AS positionId, SL.English AS position
				FROM Player_Listing PL INNER JOIN Player_Types PT ON PL.idPlayer_Types = PT.ID
				INNER JOIN Strings_Localized SL ON PT.idStrings_Localized = SL.ID
				WHERE PL.teamHash='".$this->teamId."'  AND (pl.journeyman = 0 OR (pl.journeyman = 1 AND bRetired = 0)) ORDER BY bRetired, iNumber";
		$rosterInfo = $this->dbHandle->query($sql)->fetchAll();
		
		
		foreach ($rosterInfo as &$value) {
			$value = $this->player->processPlayer($value);
			if ($value['bRetired']==0) {
				if (!array_key_exists($value['position'], $this->positionCount)) {
					$this->positionCount[$value['position']] = 1;
				} else {
					$this->positionCount[$value['position']]++;
				}
			}
		}
		
		return $rosterInfo;
	}
	
	function getRosterAllFields() {
		// get roster with all fields
		$sql = "SELECT * FROM Player_Listing WHERE teamHash = '".$this->teamId."' ORDER BY bRetired, iNumber";
		$rosterAllFields = $this->dbHandle->query($sql)->fetchAll();
		return $rosterAllFields;
	}
	
	function getMatchHistory() {
		$leagueInfo = $this->getLeagueInfo();
		$leagueId = $leagueInfo['leagueId'];
		// get match history from database
		$sql = "SELECT *,
			(SELECT strName FROM Team_Listing TL WHERE TL.teamHash = C.teamHash_Away) AS AwayTeam,
			(SELECT strName FROM Team_Listing TL WHERE TL.teamHash = C.teamHash_Home) AS HomeTeam
			FROM Calendar C
			WHERE (teamHash_Away = '" . $this->teamId ."'". 
			" OR teamHash_Home = '" . $this->teamId ."') " .
			" AND leagueId = ". $leagueId.
			" ORDER BY Championship_iDay";
		$matchInfo = $this->dbHandle->query($sql)->fetchAll();
		return $matchInfo;
	}
	
	function getTeamInfo() {
		$fitPlayers = $this->determineFitPlayers();
		$this->determineJourneymenStatus();
		$teamInfo = $this->getTeamDetails();
		$coach = $this->getCoach($this->teamId);
		$teamInfo['coachId'] = $coach['coachId'];
		$rosterInfo = $this->getRoster();
		$matchInfo = $this->getMatchHistory();
		
		$struct = array();
		$struct['team'] = $teamInfo;
		$struct['team']['fitPlayers'] = $fitPlayers;
		$struct['roster'] = $rosterInfo;
		$struct['matches'] = $matchInfo;
		$struct['positionCount'] = $this->positionCount;

		return $struct;
	}
	
	function getCoach() {
		global $bbqlDb;
		
		$dbHandle = $bbqlDb;

		// get team information from database
		$sql = "SELECT coachId FROM Team_Listing WHERE teamHash = '".$this->teamId."'";
		$coachQry = $dbHandle->query($sql);
		
		$coach = $coachQry->fetch();
		
		return $coach;
		
	}
	
	function getPlayerStats() {
		// get roster information from database
		$sql = "SELECT pl.strName, pl.iNumber, sp.* FROM Statistics_Season_Players sp INNER JOIN Player_Listing pl 
				ON sp.playerHash = pl.playerHash WHERE pl.teamHash = '".$this->teamId."' AND (pl.journeyman = 0 OR (pl.journeyman = 1 AND bRetired = 0)) ORDER BY pl.iNumber";
		$playerQry = $this->dbHandle->query($sql);
		
		// convert query to associative array
		$playerStats = $playerQry->fetchAll();

		return $playerStats;
	}
	
	function getTeamStats() {
		// get roster information from database
		$sql = "SELECT * FROM Statistics_Season_Teams WHERE teamHash = '".$this->teamId."'";
		$teamQry = $this->dbHandle->query($sql);
		
		// convert query to associative array
		$teamStats = $teamQry->fetchAll();
		//var_dump($playerStats);
		return $teamStats;
	}
	
	function determineFitPlayers() {
		$sql = "SELECT COUNT(*) as fitPlayers FROM Player_Listing WHERE iMatchSuspended = 0 AND bRetired = 0 AND teamHash = '".$this->teamId."'";
		$fit = $this->dbHandle->query($sql)->fetch();
		
		if ($fit['fitPlayers'] < 11) {
			$sql = "UPDATE Team_Listing SET playersNeeded = 1 WHERE teamHash = '".$this->teamId."'";
		} else {
			$sql = "UPDATE Team_Listing SET playersNeeded = 0 WHERE teamHash = '".$this->teamId."'";
		}
		
		if ($fit['fitPlayers'] >= 12) {
			$sql2 = "SELECT COUNT(*) as tooManyPlayers FROM Player_Listing WHERE journeyman = 1 AND bRetired = 0 AND teamHash = '".$this->teamId."'";
			$qry = $this->dbHandle->query($sql2)->fetch();
			if ($qry['tooManyPlayers'] > 0) {
				$sql = "UPDATE Team_Listing SET playersNeeded = 2 WHERE teamHash = '".$this->teamId."'";
			}
		}
		
		$this->dbHandle->query($sql);
		
		return $fit['fitPlayers'];
	}
	
	function determineJourneymenStatus() {
		$sql = "SELECT PL.* FROM Player_Listing PL INNER JOIN Statistics_Season_Players S ON PL.playerHash = S.PlayerHash" .
			" WHERE iMatchPlayed > 0 AND journeyman = 1 AND bRetired = 0 AND teamHash = '".$this->teamId."'";
		$journeymen = $this->dbHandle->query($sql)->fetchAll();
		
		//if a journeymen has played a match, he'll need to be either hired 
		//or fired, and we have to set the appropriate message.
		if (count($journeymen) > 0) {
			$sql = "UPDATE Team_Listing SET journeymenHireFire = 1";
		} else {
			$sql = "UPDATE Team_Listing SET journeymenHireFire = 0";
		}
		
		$this->dbHandle->query($sql);
	}
	
	function getPlayersForPurchase() {
		$teamDetails = $this->getTeamDetails();
		$sql = "SELECT PT.*, PT.Characteristics_fMovementAllowance MA,
				PT.Characteristics_fStrength ST, PT.Characteristics_fAgility AG, PT.Characteristics_fArmourValue AV,
				PT.idStrings_Localized AS positionId, SL.English AS position
				FROM Player_Types PT INNER JOIN Strings_Localized SL ON PT.idStrings_Localized = SL.ID
				WHERE idRaces = ".$teamDetails['idRaces']." AND idPlayer_Name_Types <> ''";
		
		$playerPurchase = $this->dbHandle->query($sql)->fetchAll();
		
		for ($i=0; $i<count($playerPurchase); $i++) {
			$playerPurchase[$i]['MA'] = $this->utils->convertMA($playerPurchase[$i]['MA']);
			$playerPurchase[$i]['ST'] = $this->utils->convertST($playerPurchase[$i]['ST']);
			$playerPurchase[$i]['AG'] = $this->utils->convertAG($playerPurchase[$i]['AG']);
			$playerPurchase[$i]['AV'] = $this->utils->convertAV($playerPurchase[$i]['AV']);
			
			$getSkills = $this->player->getPlayerSkillsInjuries('0', $playerPurchase[$i]['ID']);
			
			$getSkillCat = $this->player->getPlayerSkillCategories($playerPurchase[$i]['ID']);
			
			$playerPurchase[$i]['DefaultSkills'] = $getSkills['default'];
			$playerPurchase[$i]['SkillCategories'] = $getSkillCat;
			//$this->utils->dump($playerPurchase[$i]);
			//die();
		}
		return $playerPurchase;
	}
	
	function purchaseTeamItems() {
		$teamInfo = $this->getTeamInfo();
		$coachId = $teamInfo['team']['coachId'];
		$reRollPrice = $teamInfo['team']['iRerollPrice'];
		$gold = $teamInfo['team']['iCash'];
		
		//if userID and CoachId don't match
		if ($this->user->id != $coachId) {
			$msg[] = "You are not the coach.  You cannot hire or fire for this team.";
			return array("result" => "error", "teamId" => $this->teamId, "msg" => $msg);
		} else {
			
			$fireMsg = array();
			$hireMsg = array();
			$errorMsg = array();
			$cost = 0;
			$cheerSql = "";
			$assistantSql = "";
			$apothSql = "";
			$rerollSql = "";
			//cheerleaders
			switch ($_POST['cheerleaders']) {
				case "":
					break; //do nothing
				case -1:
					$sql = "UPDATE Team_Listing SET iCheerleaders = iCheerleaders - 1 WHERE teamHash = '".$this->teamId."'";
					$this->dbHandle->query($sql);
					$fireMsg[] = "1 cheerleader was fired.";
					break;
				default:
					$cheerSql = "UPDATE Team_Listing SET iCheerleaders = iCheerleaders + ".$_POST['cheerleaders']." WHERE teamHash = '".$this->teamId."'";
					$hireMsg[] = $_POST['cheerleaders']." cheerleader(s) hired.";
					$cost = $cost + ($_POST['cheerleaders']*10000);
					break;
			}
			//assistant coaches
			switch ($_POST['assistantCoaches']) {
				case "":
					break; //do nothing
				case -1:
					$sql = "UPDATE Team_Listing SET iAssistantCoaches = iAssistantCoaches - 1 WHERE teamHash = '".$this->teamId."'";
					$this->dbHandle->query($sql);
					$fireMsg[] = "1 assistant coach was fired.";
					break;
				default:
					$assistantSql = "UPDATE Team_Listing SET iAssistantCoaches = iAssistantCoaches + ".$_POST['assistantCoaches']." WHERE teamHash = '".$this->teamId."'";
					$hireMsg[] = $_POST['assistantCoaches']." assistant coach(es) hired.";
					$cost = $cost + ($_POST['assistantCoaches']*10000);
					break;
			}
			//apothecary
			switch ($_POST['apothecary']) {
				case "":
					break; //do nothing
				case -1:
					$sql = "UPDATE Team_Listing SET bApothecary = 0 WHERE teamHash = '".$this->teamId."'";
					$this->dbHandle->query($sql);
					$fireMsg[] = "Apothecary was fired.";
					break;
				default:
					$apothSql = "UPDATE Team_Listing SET bApothecary = 1 WHERE teamHash = '".$this->teamId."'";
					$hireMsg[] = "Apothecary hired.";
					$cost = $cost + 50000;
					break;
			}
			//rerolls
			switch ($_POST['rerolls']) {
				case "":
					break; //do nothing
				case -1:
					$sql = "UPDATE Team_Listing SET iRerolls = iRerolls - 1 WHERE teamHash = '".$this->teamId."'";
					$this->dbHandle->query($sql);
					$fireMsg[] = "1 Re-roll was removed from your team.";
					break;
				default:
					$rerollSql = "UPDATE Team_Listing SET iRerolls = iRerolls + 1 WHERE teamHash = '".$this->teamId."'";
					$hireMsg[] = $_POST['rerolls']." re-roll(s) purchased.";
					$cost = $cost + ($_POST['rerolls']*$reRollPrice*2);
					break;
			}
			if ($cost > 0) {
				//if they tried to buy too much
				if ($cost > $gold) {
					$errorMsg[] = "<br/>";
					$errorMsg[] = "Your purchases (".number_format($cost).") exceed the gold in your treasury (".number_format($gold).")";
					$msg = array_merge($fireMsg, $errorMsg);
					
					//must update team value in case there were firings
					$this->utils->updateTeamValue($this->teamId);
					
					return array("result" => "error", "teamId" => $this->teamId, "msg" => $msg);
				//else deduct gold and run SQL statements
				} else {
					$this->dbHandle->query($cheerSql);
					$this->dbHandle->query($assistantSql);
					$this->dbHandle->query($apothSql);
					$this->dbHandle->query($rerollSql);
					
					$sql="UPDATE Team_Listing SET iCash = iCash - ".$cost." WHERE teamHash = '".$this->teamId."'";
					$this->dbHandle->query($sql);
					
					$hireMsg[] = "<br/>";
					$hireMsg[] = number_format($cost)." gold was deducted from your treasury.";
					$msg = array_merge($fireMsg, $hireMsg);
				}
			} else {
				$msg = $fireMsg;
			}
			//finally, update team values based on purchases
			$this->utils->updateTeamValue($this->teamId);
			
			return array("result" => "success", "teamId" => $this->teamId, "msg" => $msg);
		}
	}
	
	function purchasePlayers() {
		$teamInfo = $this->getTeamInfo();
		$coachId = $teamInfo['team']['coachId'];
		$gold = $teamInfo['team']['iCash'];
		$msg = array();
		
		//if userID and CoachId don't match
		if ($this->user->id != $coachId) {
			$msg[] = "You are not the coach.  You cannot hire players for this team.";
			return array("result" => "error", "teamId" => $this->teamId, "msg" => $msg);
		} else {
			$cost = 0;
			$positionCosts = array();
			$hireMsg = array();
			$numPlayersPurchased = 0;
			//loop through form fields
			foreach (array_keys($_POST) as $formFields) {
				//look for playerTypeId_
				if (strpos($formFields, 'playerTypeId_') !== false) {
					//if it's not empty, look up the player type and price
					if ($_POST[$formFields] != "") {
						//$this->player->getPlayerTypeDetails(substr($formFields, 13));
						
						$sql = "SELECT PT.*, SL.English AS position FROM Player_Types PT 
							INNER JOIN Strings_Localized SL ON PT.idStrings_Localized = SL.ID
							WHERE PT.ID = ".substr($formFields, 13);
						$qry = $this->dbHandle->query($sql)->fetch();
						
						$sql = "SELECT ID, idEquipment_Types FROM Equipment_Listing
							WHERE idPlayer_Levels = 1 AND idPlayer_Types = ".substr($formFields, 13).
							" ORDER BY idEquipment_Types";
						$equipment = $this->dbHandle->query($sql)->fetchAll();
						
						$positionCosts[$qry['position']] = array();
						$positionCosts[$qry['position']]['cost'] = $qry['iPrice'];
						$positionCosts[$qry['position']]['quantity'] = $_POST[$formFields];	
						$positionCosts[$qry['position']]['playerAttributes'] = $qry;
						$positionCosts[$qry['position']]['playerAttributes']['equipment'] = $equipment;
						
						$numPlayersPurchased = $numPlayersPurchased + $_POST[$formFields];
						$cost = $cost + $qry['iPrice']*$_POST[$formFields];
						$hireMsg[] = $qry['position'].": ".$_POST[$formFields]." hired.";
					}
				}
			}
			
			if ($cost > 0) {
				//if they tried to buy too much
				if ($cost > $gold) {
					$errorMsg[] = "Your purchases (".number_format($cost).") exceed the gold in your treasury (".number_format($gold).")";
					$msg = $errorMsg;
					return array("result" => "error", "teamId" => $this->teamId, "msg" => $msg);
				//else deduct gold and run SQL statements
				} else {
					//get highest player ID
					$sql = "SELECT playerId FROM Player_Listing WHERE teamHash = '".$this->teamId."' ORDER BY playerId DESC LIMIT 1";
					$playerId = $this->dbHandle->query($sql)->fetch();
					$nextPlayerId = $playerId['playerId'] + 1;
					
					//retrieve player numbers
					$sql = "SELECT iNumber FROM Player_Listing WHERE teamHash = '".$this->teamId."' AND bRetired <> 1 ORDER BY iNumber";
					$numbers = $this->dbHandle->query($sql)->fetchAll();

					//if attempted hirings bring roster over 16, return with error
					if (count($numbers) + $numPlayersPurchased > 16) {
						$errorMsg[] = "Your purchases would bring your rostered players to ".(count($numbers) + $numPlayersPurchased).".  Only 16 are allowed.";
						$msg = $errorMsg;
						return array("result" => "error", "teamId" => $this->teamId, "msg" => $msg);
					}
					
					//define player fields
					$playerFields = $this->utils->getPlayer_ListingFields();
					
					$numberCheck = 1;  //player numbers start at 1
					$iterator = 0;  //arrays start at 0
					//loop through purchases
					foreach($positionCosts as $position => $row) {
						for ($i=1; $i<=$row['quantity']; $i++) {
							//find an available player number
							while ($numberCheck == $numbers[$iterator]['iNumber']) {
								$numberCheck++;
								$iterator++;
							}
							
							$pa = $row['playerAttributes'];
							
							//set specific values for insertion, all others will = 0
							$pa['strName'] = $position." #".$numberCheck;
							$pa['idPlayer_Types'] = $pa['ID'];
							$pa['playerHash'] = $this->teamId."-".$nextPlayerId;
							$pa['teamHash'] = $this->teamId;
							$pa['fAgeing'] = 100;
							$pa['iNumber'] = $numberCheck;
							$pa['idPlayer_Levels'] = 1;
							$pa['idEquipment_Listing_Helmet'] = $pa['equipment'][0]['ID'];
							$pa['idEquipment_Listing_Pauldron'] = $pa['equipment'][1]['ID'];
							$pa['idEquipment_Listing_Gauntlet'] = $pa['equipment'][2]['ID'];
							$pa['idEquipment_Listing_Boot'] = $pa['equipment'][3]['ID'];
							$pa['iSalary'] = $pa['iPrice'];
							$pa['iValue'] = $pa['iPrice']/1000;
							$pa['playerId'] = $nextPlayerId;
							
							$sql = "INSERT INTO Player_Listing (";
							foreach($playerFields as $value) {
								$sql = $sql . $value . ",";
							}
							$sql = substr($sql, 0, -1).") VALUES (";
							
							foreach($playerFields as $value) {
								//if specified value, insert it
								if ($pa[$value] != null) {
									$sql = $sql . $this->dbHandle->quote($pa[$value]) . ",";
								//else insert 0
								} else {
									$sql = $sql . "0,";
								}
							}
							$sql = substr($sql, 0, -1).")";
							
							$this->dbHandle->query($sql);
							
							$sql = "INSERT INTO Statistics_Season_Players (iSeason,iMatchPlayed,iMVP,Inflicted_iPasses,Inflicted_iCatches,Inflicted_iInterceptions,Inflicted_iTouchdowns,Inflicted_iCasualties,Inflicted_iTackles,Inflicted_iKO,Inflicted_iStuns,Inflicted_iInjuries,Inflicted_iDead,Inflicted_iMetersRunning,Inflicted_iMetersPassing,Sustained_iInterceptions,Sustained_iCasualties,Sustained_iTackles,Sustained_iKO,Sustained_iStuns,Sustained_iInjuries,Sustained_iDead,playerHash)" .
								" VALUES (1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,'".$pa['playerHash']."')";
							$this->dbHandle->query($sql);
							
							$nextPlayerId++;
							$numberCheck++;
						}
					}
					//deduct gold from treasury
					$sql="UPDATE Team_Listing SET iCash = iCash - ".$cost." WHERE teamHash = '".$this->teamId."'";
					$this->dbHandle->query($sql);
					
					
					$hireMsg[] = "<br/>";
					$hireMsg[] = number_format($cost)." gold was deducted from your treasury.";
					$msg = $hireMsg;
				}
			}
		}
		//finally, update team values based on purchases
		$this->utils->updateTeamValue($this->teamId);
		return array("result" => "success", "teamId" => $this->teamId, "msg" => $msg);
	}
	
	function receiveJourneymen() {
		$teamInfo = $this->getTeamInfo();
		$coachId = $teamInfo['team']['coachId'];
		$msg = array();

		//if userID and CoachId don't match
		if ($this->user->id != $coachId) {
			$msg[] = "You are not the coach.  You cannot acquire journeymen for this team.";
			return array("result" => "error", "teamId" => $this->teamId, "msg" => $msg);
		} else {
			foreach (array_keys($_POST) as $formFields) {
				//look for playerTypeId_
				if (strpos($formFields, 'playerTypeId_') !== false) {
					//if it's not empty, look up the player type and price
					if ($_POST[$formFields] != "") {
						//$this->player->getPlayerTypeDetails(substr($formFields, 13));
						
						$sql = "SELECT PT.*, SL.English AS position FROM Player_Types PT 
							INNER JOIN Strings_Localized SL ON PT.idStrings_Localized = SL.ID
							WHERE PT.ID = ".substr($formFields, 13);
						$qry = $this->dbHandle->query($sql)->fetch();
						
						$sql = "SELECT ID, idEquipment_Types FROM Equipment_Listing
							WHERE idPlayer_Levels = 1 AND idPlayer_Types = ".substr($formFields, 13).
							" ORDER BY idEquipment_Types";
						$equipment = $this->dbHandle->query($sql)->fetchAll();
						
						$positionCosts[$qry['position']] = array();
						$positionCosts[$qry['position']]['quantity'] = $_POST[$formFields];	
						$positionCosts[$qry['position']]['playerAttributes'] = $qry;
						$positionCosts[$qry['position']]['playerAttributes']['equipment'] = $equipment;
						
						$msg[] = "Journeyman ".$qry['position'].": ".$_POST[$formFields]." added to your team.";
					}
				}
			}
			//get highest player ID
			$sql = "SELECT playerId FROM Player_Listing WHERE teamHash = '".$this->teamId."' ORDER BY playerId DESC LIMIT 1";
			$playerId = $this->dbHandle->query($sql)->fetch();
			$nextPlayerId = $playerId['playerId'] + 1;
			
			//retrieve player numbers
			$sql = "SELECT iNumber FROM Player_Listing WHERE teamHash = '".$this->teamId."' AND bRetired <> 1 ORDER BY iNumber";
			$numbers = $this->dbHandle->query($sql)->fetchAll();
			
			//define player fields
			$playerFields = $this->utils->getPlayer_ListingFields();
			
			$numberCheck = 1;  //player numbers start at 1
			$iterator = 0;  //arrays start at 0
			//loop through purchases
			foreach($positionCosts as $position => $row) {
				for ($i=1; $i<=$row['quantity']; $i++) {
					//find an available player number
					while ($numberCheck == $numbers[$iterator]['iNumber']) {
						$numberCheck++;
						$iterator++;
					}
					
					$pa = $row['playerAttributes'];
					
					//set specific values for insertion, all others will = 0
					$pa['strName'] = "Journeyman ".$position." #".$numberCheck;
					$pa['idPlayer_Types'] = $pa['ID'];
					$pa['playerHash'] = $this->teamId."-".$nextPlayerId;
					$pa['teamHash'] = $this->teamId;
					$pa['fAgeing'] = 100;
					$pa['iNumber'] = $numberCheck;
					$pa['idPlayer_Levels'] = 1;
					$pa['idEquipment_Listing_Helmet'] = $pa['equipment'][0]['ID'];
					$pa['idEquipment_Listing_Pauldron'] = $pa['equipment'][1]['ID'];
					$pa['idEquipment_Listing_Gauntlet'] = $pa['equipment'][2]['ID'];
					$pa['idEquipment_Listing_Boot'] = $pa['equipment'][3]['ID'];
					$pa['iSalary'] = $pa['iPrice'];
					$pa['iValue'] = $pa['iPrice']/1000;
					$pa['playerId'] = $nextPlayerId;
					$pa['journeyman'] = 1;
					
					$sql = "INSERT INTO Player_Listing (";
					foreach($playerFields as $value) {
						$sql = $sql . $value . ",";
					}
					$sql = $sql."journeyman) VALUES (";
					
					foreach($playerFields as $value) {
						//if specified value, insert it
						if ($pa[$value] != null) {
							$sql = $sql . $this->dbHandle->quote($pa[$value]) . ",";
						//else insert 0
						} else {
							$sql = $sql . "0,";
						}
					}
					$sql = $sql."1)";
					
					$this->dbHandle->query($sql);
					
					//add loner skill for player
					$sql = "INSERT INTO Player_Skills(idSkill_Listing,playerHash) VALUES(44,'".$pa['playerHash']."')";
					$this->dbHandle->query($sql);
					
					$sql = "INSERT INTO Statistics_Season_Players (iSeason,iMatchPlayed,iMVP,Inflicted_iPasses,Inflicted_iCatches,Inflicted_iInterceptions,Inflicted_iTouchdowns,Inflicted_iCasualties,Inflicted_iTackles,Inflicted_iKO,Inflicted_iStuns,Inflicted_iInjuries,Inflicted_iDead,Inflicted_iMetersRunning,Inflicted_iMetersPassing,Sustained_iInterceptions,Sustained_iCasualties,Sustained_iTackles,Sustained_iKO,Sustained_iStuns,Sustained_iInjuries,Sustained_iDead,playerHash)" .
						" VALUES (1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,'".$pa['playerHash']."')";
					$this->dbHandle->query($sql);
					
					$nextPlayerId++;
					$numberCheck++;
				}
			}
		}
		//finally, update team values based on journeymen added
		$this->utils->updateTeamValue($this->teamId);
		return array("result" => "success", "teamId" => $this->teamId, "msg" => $msg);
	}
        
        function recalculateTeamValue() {
            $msg = array();
            $msg[] = "Team Value recalculated successfully";
            
            $this->utils->updateTeamValue($this->teamId);
            return array("result" => "success", "teamId" => $this->teamId, "msg" => $msg);
            
        }
	
	function downloadteam() {
		global $systemPathToComponent;
		
		$teamInfo = $this->getTeamDetails();
		$rosterAllFields = $this->getRosterAllFields();
		$roster = $this->getRoster();
		
		$filePath = $systemPathToComponent.DS.'uploads'.DS.$teamInfo['strName'].'.db';
		
		//now access the DB to determine the teamID
		$db = 'sqlite:'.$filePath;
		
		// create a connection to SQLite3 database file with PDO and return a database handle
		try{
			$dbHandle = new PDO($db);
		}catch( PDOException $exception ){
			die($exception->getMessage());
		}

		$sql = "SELECT ID from Team_Listing LIMIT 1";
		$teamIdQry = $dbHandle->query($sql)->fetch();
		$teamId = $teamIdQry[0];
		
		//update team info
		$sql = "UPDATE Team_Listing SET 
			iValue = ".$teamInfo['iValue'].",
			iPopularity = ".$teamInfo['iPopularity'].",
			iCash = ".$teamInfo['iCash'].",
			iCheerleaders = ".$teamInfo['iCheerleaders'].",
			bApothecary = ".$teamInfo['bApothecary'].",
			iRerolls = ".$teamInfo['iRerolls'].",
			iAssistantCoaches = ".$teamInfo['iAssistantCoaches'].
			" WHERE ID = $teamId";
		$dbHandle->query($sql);
		
		/*
		$sql = "UPDATE SavedGameInfo SET Championship_iTeamValue = ".$teamInfo['team']['iValue'].",
			Championship_iTeamPopularity = ".$teamInfo['team']['iPopularity'].",
			" Championship_iTeamCash = ".$teamInfo['team']['iCash'].",
			" WHERE ID = 1";
		*/
		
		//remove players, as we'll reinsert the entire roster
		$sql = "DELETE FROM Player_Listing";
		$dbHandle->query($sql);
		
		//remove skills
		$sql = "DELETE FROM Player_Skills";
		$dbHandle->query($sql);
		
		//remove casualties
		$sql = "DELETE FROM Player_Casualties";
		$dbHandle->query($sql);
		
		//define player fields
		$playerFields = array('idPlayer_Names','strName','idPlayer_Types','idTeam_Listing_Previous',
				'idRaces','iPlayerColor','iSkinScalePercent','iSkinMeshVariant','iSkinTextureVariant','fAgeing','iNumber',
				'Characteristics_fMovementAllowance','Characteristics_fStrength','Characteristics_fAgility',
				'Characteristics_fArmourValue','idPlayer_Levels','iExperience','idEquipment_Listing_Helmet',
				'idEquipment_Listing_Pauldron','idEquipment_Listing_Gauntlet','idEquipment_Listing_Boot',
				'Durability_iHelmet','Durability_iPauldron','Durability_iGauntlet','Durability_iBoot','iSalary',
				'Contract_iDuration','Contract_iSeasonRemaining','idNegotiation_Condition_Types',
				'Negotiation_iRemainingTries','Negotiation_iConditionDemand','iValue','iMatchSuspended','iNbLevelsUp',
				'LevelUp_iRollResult','LevelUp_iRollResult2','LevelUp_bDouble','bGenerated','bStar','bEdited','bDead','strLevelUp');
		
				
		//insert player info, casualties, and skills
		foreach($rosterAllFields as $i => $row) {
			if ($row['bRetired'] != 1 && $row['iMatchSuspended'] != 1) {
				$sql = "INSERT INTO Player_Listing (";
				foreach($playerFields as $value) {
					$sql = $sql . $value . ",";
				}
				$sql = $sql."ID,idTeam_Listing) VALUES (";
				foreach($playerFields as $value) {
					$sql = $sql . $dbHandle->quote($row[$value]) . ",";
				}
				$sql = $sql .substr($row['playerHash'],33,20).",$teamId)";
				$dbHandle->query($sql);
				
				echo $sql."<br><br>";
				foreach ($roster[$i]['AcquiredSkills'] as $skill) {
					$sql = "INSERT INTO Player_Skills (idPlayer_Listing,idSkill_Listing) VALUES("
					.substr($row['playerHash'],33).",".$skill['ID'].")";
					$dbHandle->query($sql);
					echo $sql."<br><br>";
				}
				
				foreach ($roster[$i]['Injuries'] as $injury) {
					$sql = "INSERT INTO Player_Casualties (idPlayer_Listing,idPlayer_Casualty_Types) VALUES("
					.substr($row['playerHash'],33,20).",".$injury['idPlayer_Casualty_Types'].")";
					$dbHandle->query($sql);
					echo $sql."<br><br>";
				}
				echo "<br>";
			}
		}

		return $filePath;
	}
}

