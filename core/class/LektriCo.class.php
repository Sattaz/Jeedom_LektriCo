<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class LektriCo extends eqLogic {
    /*     * *************************Attributs****************************** */

    /*     * ***********************Methode static*************************** */

   	//Fonction exécutée automatiquement toutes les minutes par Jeedom
    public static function cron() {
		foreach (self::byType('LektriCo') as $LektriCo) {//parcours tous les équipements du plugin LektriCo
			if ($LektriCo->getIsEnable() == 1) {//vérifie que l'équipement est actif
				$cmd = $LektriCo->getCmd(null, 'refresh');//retourne la commande "refresh si elle existe
				if (!is_object($cmd)) {//Si la commande n'existe pas
					continue; //continue la boucle
				}
				$cmd->execCmd(); // la commande existe on la lance
			}
		}
    }
  
  	public static function templateWidget(){
		$return = array('info' => array('string' => array()));
     	$return['action']['other']['OnOff'] = array(
			'template' => 'tmplicon',
			'replace' => array(
				'#_icon_on_#' => '<img class="img-responsive" src="plugins/LektriCo/core/img/on.jpg" width="70" style="border-radius:10px; border:2px solid grey;margin:2px 2px" title="Désactiver la charge">',
				'#_icon_off_#' => '<img class="img-responsive" src="plugins/LektriCo/core/img/off.jpg" width="70" style="border-radius:10px; border:2px solid grey;margin:2px 2px" title="Activer la charge">'
			)
		);
      	$return['action']['other']['AutoManu'] = array(
			'template' => 'tmplicon',
			'replace' => array(
				'#_icon_on_#' => '<img class="img-responsive" src="plugins/LektriCo/core/img/auto.jpg" width="70" style="border-radius:10px; border:2px solid grey;margin:2px 2px" title="Mode Auto">',
				'#_icon_off_#' => '<img class="img-responsive" src="plugins/LektriCo/core/img/manu.jpg" width="70" style="border-radius:10px; border:2px solid grey;margin:2px 2px" title="Mode Manuel">'
			)
		);
      	$return['action']['slider']['setpoint'] = array(
            'template' => 'nooSliderLektriCo' //'SliderButton'
        );
		return $return;
	}
	
	public function SetSliderSetPoint($valueSlider) {
		try {
        
          	if ($valueSlider==0) {return;}
          
			$LektriCo_IP = $this->getConfiguration("IP");
          	$LektriCo_User = $this->getConfiguration("User");
          	$LektriCo_Password = $this->getConfiguration("Password");
			$ch = curl_init();
          
          	if ($LektriCo_User!='' && $LektriCo_Password!='') {
            	curl_setopt($ch, CURLOPT_USERPWD, $LektriCo_User.':'.$LektriCo_Password);
              	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }
              
            //Set State $ setpoint
            curl_setopt_array($ch, [
              	CURLOPT_URL => 'http://'.$LektriCo_IP.'/rpc/app_config.Set',
  				CURLOPT_RETURNTRANSFER => true,
  				CURLOPT_ENCODING => "",
  				CURLOPT_MAXREDIRS => 10,
  				CURLOPT_TIMEOUT => 10,
  				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  				CURLOPT_CUSTOMREQUEST => 'POST',
               			CURLOPT_POSTFIELDS => '{config_key:"user_current", config_value:'.$valueSlider.'}',
  				CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
			$response = curl_exec($ch);
			$err = curl_error($ch);
			curl_close($ch);
			if ($err) {
               	log::add('LektriCo', 'debug','Fonction SetSliderSetPoint : Erreur CURL -> ').$err;
            } else {
               	log::add('LektriCo', 'debug','Fonction SetSliderSetPoint : Changement référence intensité à '.$valueSlider.' ampères -> ' .$response);
               	return $valueSlider;
			}
		} catch (Exception $e) {
			log::add('LektriCo', 'error', __('Erreur lors de l\'éxecution de SetSliderSetPoint ' . ' ' . $e->getMessage()));
       	}
	}
	
	public function SetStartStop($StartStop) {
		try {
			$LektriCo_IP = $this->getConfiguration("IP");
          	$LektriCo_User = $this->getConfiguration("User");
          	$LektriCo_Password = $this->getConfiguration("Password");
			$ch = curl_init();
          
          	if ($LektriCo_User!='' && $LektriCo_Password!='') {
            	curl_setopt($ch, CURLOPT_USERPWD, $LektriCo_User.':'.$LektriCo_Password);
              	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }
          
          	//Get all other data
            curl_setopt_array($ch, [
  				CURLOPT_URL => 'http://'.$LektriCo_IP.'/rpc/Charger_info.Get',
  				CURLOPT_RETURNTRANSFER => true,
  				CURLOPT_ENCODING => "",
  				CURLOPT_MAXREDIRS => 10,
  				CURLOPT_TIMEOUT => 10,
  				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  				CURLOPT_CUSTOMREQUEST => 'GET',
  				CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
           	]);
			$response = curl_exec($ch);
              
            if ($response=='') {
               	log::add('LektriCo', 'debug','Fonction GetData : Erreur de connexion / authentification');
               	curl_close($ch);
               	return;
            }
              
			$err = curl_error($ch);

			if ($err) {
               	log::add('LektriCo', 'debug','Fonction SetStartStop : Charger_info - Erreur CURL -> ').$err;
               	return;
			}
            $json = json_decode($response, true);
              
            // Get LektriCo State
          	$plug = 0;
  			$state = $json['extended_charger_state'];
            switch (true) {
		case ($state == 'B'):
                	$plug = 1;
                   	break;
               	case ($state == 'B_AUTH'):
			$plug = 1;
                   	break;
             	case ($state == 'C'):
			$plug = 1;
			break;
             	case ($state == 'D'):
			$plug = 1;
			break;
          	case ($state == 'B_PAUSE'):
			$plug = 1;
			break;
        	case ($state == 'B_SCHEDULER'):
			$plug = 1;
			break;
		}
          
         	if ($plug==0 && $StartStop=='Start') {
               	log::add('LektriCo', 'debug','Fonction SetStartStop : Prise non connectée, la charge ne peut pas démarrer');
               	return;
		}
          
		$state = 'Charge.Stop';
            switch ($StartStop) {
				case ('Start'):
					$state = 'Charge.Start';
					break;
				case ('Stop'):
					$state = 'Charge.Stop';
					break;              
			}
              
            curl_setopt_array($ch, [
               	CURLOPT_URL => 'http://'.$LektriCo_IP.'/rpc/'.$state,
  				CURLOPT_RETURNTRANSFER => true,
  				CURLOPT_ENCODING => "",
  				CURLOPT_MAXREDIRS => 10,
  				CURLOPT_TIMEOUT => 10,
  				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  				CURLOPT_CUSTOMREQUEST => 'GET',
  				CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
			$response = curl_exec($ch);
			$err = curl_error($ch);
          
			if ($err) {
               	log::add('LektriCo', 'debug','Fonction SetStartStop : State - Erreur CURL -> ').$err;
               	curl_close($ch);
               	return;
			}
            
            curl_close($ch);
            if (curl_errno($ch)) {
				log::add('LektriCo', 'debug','Fonction SetStartStop : Erreur CURL -> ').$err;
			} else {
				log::add('LektriCo', 'debug','Fonction SetStartStop : Changement valeur à '.$StartStop.' -> ' .$response);
			}
		} catch (Exception $e) {
			log::add('LektriCo', 'error', __('Erreur lors de l\'éxecution de SetStop ' . ' ' . $e->getMessage()));
		}
              
	}
	
	public function SetMode($SelMode) {
		try {
			switch ($SelMode) {
				case 'Man':
					$this->checkAndUpdateCmd('EVSE_Mode', 'Manuel');
                	$this->checkAndUpdateCmd('EVSE_ModeBin', 0);
					break;
				case 'Auto':
					$this->checkAndUpdateCmd('EVSE_Mode', 'Automatique');
                	$this->checkAndUpdateCmd('EVSE_ModeBin', 1);
					break;
			}
			log::add('LektriCo', 'debug','Fonction SetMode : Changement valeur à '.$SelMode);
			return;
		} catch (Exception $e) {
			log::add('LektriCo', 'error', __('Erreur lors de l\'éxecution de SetMode ' . ' ' . $e->getMessage()));
		}
	}

	public function get_string_between($string, $start, $end){
		$string = ' ' . $string;
		$ini = strpos($string, $start);
		if ($ini == 0) return '';
		$ini += strlen($start);
		$len = strpos($string, $end, $ini) - $ini;
		return substr($string, $ini, $len);
	}
	
	public function CheckHPHC() {
    	$sendHPHCCmd = $this->getConfiguration('sendHPHCCmd', '');
		if (strlen($sendHPHCCmd)>0) {
        	$cmdHPHC = cmd::byId(str_replace('#', '', $sendHPHCCmd));
          	if (!is_object($cmdHPHC)) {
				log::add('LektriCo', 'debug', "Fonction CheckHC : Commande '{$cmdHPHC->getName()}' non trouvée -> vérifiez la configuration pour  {$this->getHumanName()}.");
			}else{
				$valCmdHPHC = $cmdHPHC->execCmd();
              	$valCmdHPHC = strtoupper($valCmdHPHC);
              	$valCmdIndexHC = $this->getConfiguration('indexHCCmd', '');
              	$valCmdIndexHC = strtoupper($valCmdIndexHC);
              	$cmd = $this->getCmd(null, 'EVSE_IndexHC');
      			$valIndexHC = $cmd->execCmd();
              	$valIndexHC = strtoupper($valIndexHC);
              	$cmd = $this->getCmd(null, 'EVSE_ModeBin');
      			$valModeBin = $cmd->execCmd();
              	if ($valCmdHPHC!=$valIndexHC) {
                  	$cmdName=$cmdHPHC->getName();
               		$this->checkAndUpdateCmd('EVSE_IndexHC', $valCmdHPHC);
                  	log::add('LektriCo', 'debug',"Fonction CheckHC : La commande '{$cmdName}' retourne une nouvelle valeur -> ".$valCmdHPHC);
                  	if ($valModeBin==1) { 
                  		if ($valCmdHPHC==$valCmdIndexHC) {
                          	log::add('LektriCo', 'debug',"Fonction CheckHC : Mode Start/Stop automatique actif -> ".$valCmdHPHC." -> activation charge"); 
                   			$this->SetStartStop('Start');
                    	} else {
                          	log::add('LektriCo', 'debug',"Fonction CheckHC : Mode Start/Stop automatique actif -> ".$valCmdHPHC." -> désactivation charge");
                  			$this->SetStartStop('Stop');
                    	}
                  		sleep(2);
                    }
                } else {
                	if($valModeBin==1) {
                    	$cmd = $this->getCmd(null, 'EVSE_Status');
      					$valEVSEStatus = $cmd->execCmd();
                      	if ($valCmdHPHC==$valCmdIndexHC && $valEVSEStatus!=1) {
                          	log::add('LektriCo', 'debug',"Fonction CheckHC : Mode Start/Stop automatique actif -> ".$valCmdHPHC." -> activation charge");
                        	$this->SetStartStop('Start');
                          	sleep(2);
                        }
                    	if ($valCmdHPHC!=$valCmdIndexHC && $valEVSEStatus!=0) {
                          	log::add('LektriCo', 'debug',"Fonction CheckHC : Mode Start/Stop automatique actif -> ".$valCmdHPHC." -> désactivation charge");
                        	$this->SetStartStop('Stop');
                          	sleep(2);
                        }
                    }
                }
			}
        }
    }
	
	public function GetData() {
		
		try {
          
           	$this->CheckHPHC();
          
			$LektriCo_IP = $this->getConfiguration("IP");
          	$LektriCo_User = $this->getConfiguration("User");
          	$LektriCo_Password = $this->getConfiguration("Password");
			$ch = curl_init();
			          
          	if ($LektriCo_User!='' && $LektriCo_Password!='') {
            	curl_setopt($ch, CURLOPT_USERPWD, $LektriCo_User.':'.$LektriCo_Password);
              	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }
          
         	//Get all other data
            curl_setopt_array($ch, [
  				CURLOPT_URL => 'http://'.$LektriCo_IP.'/rpc/System_currents.Get',
  				CURLOPT_RETURNTRANSFER => true,
  				CURLOPT_ENCODING => "",
  				CURLOPT_MAXREDIRS => 10,
  				CURLOPT_TIMEOUT => 10,
  				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  				CURLOPT_CUSTOMREQUEST => 'GET',
  				CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
           	]);
			$response = curl_exec($ch);
              
            if ($response=='') {
               	log::add('LektriCo', 'debug','Fonction GetData : Erreur de connexion / authentification');
               	curl_close($ch);
               	return;
            }
              
			$err = curl_error($ch);
			//curl_close($ch);
			if ($err) {
               	log::add('LektriCo', 'debug','Fonction GetData : State - Erreur CURL -> ').$err;
               	return;
			}
            $json = json_decode($response, true);
          
          	// Get LektriCo Amperes Set Point
			$setPointEVSE = $json['user_current'];
			$cmd = $this->getCmd(null, 'EVSE_AmpSetPointReadBack');
			$setPointCMD = $cmd->execCmd();
			if ($setPointEVSE != $setPointCMD) {
				// Set AmpSetPointReadBack value
				$this->checkAndUpdateCmd('EVSE_AmpSetPointReadBack', $setPointEVSE);
				log::add('LektriCo', 'debug','Fonction GetData : Amperes Set Point -> Rafraîchissement valeur set point intensité à '.$setPointEVSE. ' ampères');
			} else {
				log::add('LektriCo', 'debug','Fonction GetData : Amperes Set Point -> Check valeur set point EVSE vs Plugin OK ('.$setPointEVSE. ' ampères)');
			}
          
           	//Get last charge session data
            curl_setopt_array($ch, [
  				CURLOPT_URL => 'http://'.$LektriCo_IP.'/rpc/Counters_config.Get',
  				CURLOPT_RETURNTRANSFER => true,
  				CURLOPT_ENCODING => "",
  				CURLOPT_MAXREDIRS => 10,
  				CURLOPT_TIMEOUT => 10,
  				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  				CURLOPT_CUSTOMREQUEST => 'GET',
  				CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
           	]);
			$response = curl_exec($ch);
              
            if ($response=='') {
               	log::add('LektriCo', 'debug','Fonction GetData : Erreur de connexion / authentification');
               	curl_close($ch);
               	return;
            }
              
			$err = curl_error($ch);

			if ($err) {
               	log::add('LektriCo', 'debug','Fonction GetData : State - Erreur CURL -> ').$err;
               	return;
			}
            $json = json_decode($response, true);

			// Get LektriCo last session energy
			$LastSessionEnergy = $json['last_session_energy'];
			$this->checkAndUpdateCmd('EVSE_LastSession', round($LastSessionEnergy/1000,2));
 
           	//Get all other data
            curl_setopt_array($ch, [
  				CURLOPT_URL => 'http://'.$LektriCo_IP.'/rpc/Charger_info.Get',
  				CURLOPT_RETURNTRANSFER => true,
  				CURLOPT_ENCODING => "",
  				CURLOPT_MAXREDIRS => 10,
  				CURLOPT_TIMEOUT => 10,
  				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  				CURLOPT_CUSTOMREQUEST => 'GET',
  				CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
           	]);
			$response = curl_exec($ch);
              
            if ($response=='') {
               	log::add('LektriCo', 'debug','Fonction GetData : Erreur de connexion / authentification');
               	curl_close($ch);
               	return;
            }
              
			$err = curl_error($ch);
			curl_close($ch);
			if ($err) {
               	log::add('LektriCo', 'debug','Fonction GetData : State - Erreur CURL -> ').$err;
               	return;
			}
            $json = json_decode($response, true);
              
            // Get LektriCo State
  			$state = $json['extended_charger_state'];
            switch (true) {
            	case ($state == 'A'):
             		$this->checkAndUpdateCmd('EVSE_State', 'OFF');
                	$this->checkAndUpdateCmd('EVSE_Status', 0);
                	$this->checkAndUpdateCmd('EVSE_Plug', 'Déconnectée');
					break;
				case ($state == 'B'):
					$this->checkAndUpdateCmd('EVSE_State', 'OFF');
                 	$this->checkAndUpdateCmd('EVSE_Status', 0);
                	$this->checkAndUpdateCmd('EVSE_Plug', 'Connectée');
                   	break;
               	case ($state == 'B_AUTH'):
					$this->checkAndUpdateCmd('EVSE_State', 'OFF');
                 	$this->checkAndUpdateCmd('EVSE_Status', 0);
                	$this->checkAndUpdateCmd('EVSE_Plug', 'Connectée');
                   	break;
             	case ($state == 'C'):
					$this->checkAndUpdateCmd('EVSE_State', 'En Charge');
                 	$this->checkAndUpdateCmd('EVSE_Status', 1);
                	$this->checkAndUpdateCmd('EVSE_Plug', 'Connectée');
					break;
             	case ($state == 'D'):
					$this->checkAndUpdateCmd('EVSE_State', 'En Charge');
                 	$this->checkAndUpdateCmd('EVSE_Status', 1);
                	$this->checkAndUpdateCmd('EVSE_Plug', 'Connectée');
					break;
             	case ($state == 'E'):
					$this->checkAndUpdateCmd('EVSE_State', 'Erreur');
                 	$this->checkAndUpdateCmd('EVSE_Status', 0);
                	$this->checkAndUpdateCmd('EVSE_Plug', '...');
					break;
				case ($state == 'OTA'):
					$this->checkAndUpdateCmd('EVSE_State', 'OTA');
                 	$this->checkAndUpdateCmd('EVSE_Status', 0);
                	$this->checkAndUpdateCmd('EVSE_Plug', '...');
					break;
            	case ($state == 'LOCKED'):
					$this->checkAndUpdateCmd('EVSE_State', 'LOCKED');
                 	$this->checkAndUpdateCmd('EVSE_Status', 0);
                	$this->checkAndUpdateCmd('EVSE_Plug', '...');
					break;
          		case ($state == 'B_PAUSE'):
					$this->checkAndUpdateCmd('EVSE_State', 'Pause');
                 	$this->checkAndUpdateCmd('EVSE_Status', 1);
                	$this->checkAndUpdateCmd('EVSE_Plug', 'Connectée');
					break;
        		case ($state == 'B_SCHEDULER'):
					$this->checkAndUpdateCmd('EVSE_State', 'Pause');
                 	$this->checkAndUpdateCmd('EVSE_Status', 1);
                	$this->checkAndUpdateCmd('EVSE_Plug', 'Connectée');
					break;
			}
              
            // Get LektriCo Temperature
			$temp = $json['temperature'];
			$this->checkAndUpdateCmd('EVSE_Temp', round($temp,0));
              
            // Get LektriCo Actual Volts & Amperes
			$amperes = round($json['pwm_current'],1);
			$volts = round($json['voltage'],0);
			$this->checkAndUpdateCmd('EVSE_Amperes', $amperes);
			$this->checkAndUpdateCmd('EVSE_Volts', $volts);
              
			// Get LektriCo Charge Session in Kwh
			$chargesession = $json['session_energy'];
			$this->checkAndUpdateCmd('EVSE_ChargeSession', round($chargesession/1000,2));
              
			log::add('LektriCo', 'debug','Fonction GetData : Récupération des données LektriCo OK !' );
			return;
		} catch (Exception $e) {
			log::add('LektriCo', 'error', __('Erreur lors de l\'éxecution de GetData ' . ' ' . $e->getMessage()));
		}
	}
  

    
    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
    	$setMode = $this->setConfiguration("Mode",1); //Les nouveaux objets sont de type WIFI API par defaut.
    }

    public function postInsert() {

    }

    public function preSave() {

    }

    public function postSave() {
		$info = $this->getCmd(null, 'EVSE_Volts');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Tension : ', __FILE__));
		}
		$info->setLogicalId('EVSE_Volts');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->setTemplate('dashboard','line');
      	$info->setTemplate('mobile','line');
		$info->setIsHistorized(1);
		$info->setUnite('V');
		$info->setOrder(1);
		$info->save();
		
		$info = $this->getCmd(null, 'EVSE_Amperes');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Intensité : ', __FILE__));
		}
		$info->setLogicalId('EVSE_Amperes');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->setTemplate('dashboard','line');
      	$info->setTemplate('mobile','line');
		$info->setConfiguration('minValue', 0);
		$info->setConfiguration('maxValue', 32);
		$info->setIsHistorized(1);
		$info->setUnite('A');
		$info->setOrder(2);
		$info->save();
		
		$info = $this->getCmd(null, 'EVSE_ChargeSession');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Charge Session : ', __FILE__));
		}
		$info->setLogicalId('EVSE_ChargeSession');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->setTemplate('dashboard','line');
      	$info->setTemplate('mobile','line');
		$info->setIsHistorized(1);
		$info->setUnite('Kwh');
		$info->setOrder(3);
		$info->save();
      
		$info = $this->getCmd(null, 'EVSE_LastSession');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Dernière Session : ', __FILE__));
		}
		$info->setLogicalId('EVSE_LastSession');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->setTemplate('dashboard','line');
      	$info->setTemplate('mobile','line');
		$info->setIsHistorized(1);
		$info->setUnite('Kwh');
		$info->setOrder(4);
		$info->save();
		
		$info = $this->getCmd(null, 'EVSE_Temp');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Température : ', __FILE__));
		}
		$info->setLogicalId('EVSE_Temp');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->setTemplate('dashboard','line');
      	$info->setTemplate('mobile','line');
		$info->setConfiguration('minValue', 0);
		$info->setConfiguration('maxValue', 80);
		$info->setIsHistorized(1);
		$info->setUnite('°C');
		$info->setOrder(5);
		$info->save();
		
		$info = $this->getCmd(null, 'EVSE_Plug');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Prise : ', __FILE__));
		}
		$info->setLogicalId('EVSE_Plug');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setTemplate('dashboard','default');
      	$info->setTemplate('mobile','default');
		$info->setIsHistorized(0);
		$info->setIsVisible(1);
		$info->setOrder(6);
		$info->save();
		
		$AMin = $this->getConfiguration("AMin");
		$AMax = $this->getConfiguration("AMax");
      	if (empty($AMin)) {
			$AMin = 6;
		}  
		if (empty($AMax)) {
			$AMax = 7;
        }
      	if ($AMax<=$AMin) {
         	$AMax = $AMin + 1;
        }
      
		$info = $this->getCmd(null, 'EVSE_AmpSetPointReadBack');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Consigne Demandée : ', __FILE__));
		}
		$info->setLogicalId('EVSE_AmpSetPointReadBack');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->setTemplate('dashboard','line');
      	$info->setTemplate('mobile','line');
		$info->setConfiguration('minValue', $AMin);
		$info->setConfiguration('maxValue', $AMax);
		$info->setIsHistorized(1);
		$info->setUnite('A');
		$info->setOrder(7);
		$info->save();
		
		$action = $this->getCmd(null, 'EVSE_AmpSetPointSlider');
		if (!is_object($action)) {
			$action = new LektriCoCmd();
			$action->setLogicalId('EVSE_AmpSetPointSlider');
			$action->setName(__('Curseur Consigne', __FILE__));
		}
		$action->setType('action');
		$action->setSubType('slider');
	    $action->setConfiguration('stepValue', 1);
      	$action->setValue($this->getCmd(null, 'EVSE_AmpSetPointReadBack')->getId());
      	$action->setTemplate('dashboard','LektriCo::setpoint');
		//$action->setTemplate('mobile','LektriCo::setpoint'); //TEMPLATE SLIDER
		$action->setConfiguration('minValue', $AMin);
		$action->setConfiguration('maxValue', $AMax);
		$action->setEqLogic_id($this->getId());
	    $action->setUnite('A');
		$action->setDisplay("showNameOndashboard",0);
      	$action->setDisplay("showNameOnmobile",0);
		$action->setOrder(8);
		$action->save();    
					
		$info = $this->getCmd(null, 'EVSE_State');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Etat : ', __FILE__));
		}
		$info->setLogicalId('EVSE_State');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setTemplate('dashboard','default');
      	$info->setTemplate('mobile','default');
		$info->setIsHistorized(0);
		$info->setIsVisible(1);
		$info->setOrder(9);
		$info->save();
		
		$info = $this->getCmd(null, 'EVSE_Mode');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Mode : ', __FILE__));
		}
		$info->setLogicalId('EVSE_Mode');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setTemplate('dashboard','default');
      	$info->setTemplate('mobile','default');
		$info->setIsHistorized(0);
		$info->setIsVisible(0);
		$info->setOrder(10);
		$info->save();
		$this->checkAndUpdateCmd('EVSE_Mode', 'Manuel');
      
      	$info = $this->getCmd(null, 'EVSE_Status');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Charge : ', __FILE__));
		}
		$info->setLogicalId('EVSE_Status');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('binary');
		$info->setTemplate('dashboard','default');
      	$info->setTemplate('mobile','default');
		$info->setIsHistorized(0);
		$info->setIsVisible(0);
		$info->setOrder(11);
		$info->save();
      
		$action = $this->getCmd(null, 'EVSE_Start');
		if (!is_object($action)) {
			$action = new LektriCoCmd();
			$action->setLogicalId('EVSE_Start');
			$action->setName(__('Charge_ON', __FILE__));
		}
		$action->setType('action');
		$action->setSubType('other');
      	$action->setValue($this->getCmd(null, 'EVSE_Status')->getId());
      	$action->setTemplate('dashboard','LektriCo::OnOff');
      	$action->setTemplate('mobile','LektriCo::OnOff');
      	$action->setDisplay("showNameOndashboard",0);
      	$action->setDisplay("showNameOnmobile",0);
		$action->setEqLogic_id($this->getId());
		$action->setOrder(12);
		$action->save();
      
     	$action = $this->getCmd(null, 'EVSE_Stop');
		if (!is_object($action)) {
			$action = new LektriCoCmd();
			$action->setLogicalId('EVSE_Stop');
			$action->setName(__('Charge_OFF', __FILE__));
		}
		$action->setType('action');
		$action->setSubType('other');
      	$action->setValue($this->getCmd(null, 'EVSE_Status')->getId());
      	$action->setTemplate('dashboard','LektriCo::OnOff');
      	$action->setTemplate('mobile','LektriCo::OnOff');
      	$action->setDisplay("showNameOndashboard",0);
      	$action->setDisplay("showNameOnmobile",0);
		$action->setEqLogic_id($this->getId());
		$action->setOrder(13);
		$action->save();
      
      	$info = $this->getCmd(null, 'EVSE_ModeBin');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('ModeAuto : ', __FILE__));
		}
		$info->setLogicalId('EVSE_ModeBin');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('binary');
		$info->setTemplate('dashboard','default');
      	$info->setTemplate('mobile','default');
		$info->setIsHistorized(0);
		$info->setIsVisible(0);
		$info->setOrder(21);
		$info->save();
      	$this->checkAndUpdateCmd('EVSE_ModeBin', 0);
		
		$action = $this->getCmd(null, 'EVSE_ModeMan');
		if (!is_object($action)) {
			$action = new LektriCoCmd();
			$action->setLogicalId('EVSE_ModeMan');
			$action->setName(__('ModeAuto_OFF', __FILE__));
		}
		$action->setType('action');
		$action->setSubType('other');
      	$action->setValue($this->getCmd(null, 'EVSE_ModeBin')->getId());
      	$action->setTemplate('dashboard','LektriCo::AutoManu');
      	$action->setTemplate('mobile','LektriCo::AutoManu');
      	$action->setDisplay("showNameOndashboard",0);
      	$action->setDisplay("showNameOnmobile",0);
		$action->setEqLogic_id($this->getId());
      	$action->setIsVisible(1);
		$action->setOrder(22);
		$action->save();
      
		$action = $this->getCmd(null, 'EVSE_ModeAuto');
		if (!is_object($action)) {
			$action = new LektriCoCmd();
			$action->setLogicalId('EVSE_ModeAuto');
			$action->setName(__('ModeAuto_ON', __FILE__));
		}
		$action->setType('action');
		$action->setSubType('other');
      	$action->setValue($this->getCmd(null, 'EVSE_ModeBin')->getId());
      	$action->setTemplate('dashboard','LektriCo::AutoManu');
      	$action->setTemplate('mobile','LektriCo::AutoManu');
      	$action->setDisplay("showNameOndashboard",0);
      	$action->setDisplay("showNameOnmobile",0);
		$action->setEqLogic_id($this->getId());
      	$action->setIsVisible(1);
		$action->setOrder(23);
		$action->save();
		
		$info = $this->getCmd(null, 'EVSE_PersoString');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Perso. Texte', __FILE__));
		}
		$info->setLogicalId('EVSE_PersoString');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setTemplate('dashboard','default');
      	$info->setTemplate('mobile','default');
		$info->setIsHistorized(0);
		$info->setIsVisible(0);
		$info->setOrder(24);
		$info->save();
		
		$info = $this->getCmd(null, 'EVSE_PersoNumeric');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Perso. Num.', __FILE__));
		}
		$info->setLogicalId('EVSE_PersoNumeric');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->setTemplate('dashboard','line');
      	$info->setTemplate('mobile','line');
		$info->setIsHistorized(0);
		$info->setIsVisible(0);
		$info->setOrder(25);
		$info->save();
      
      	$info = $this->getCmd(null, 'EVSE_PersoBinary');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Perso. Bin.', __FILE__));
		}
		$info->setLogicalId('EVSE_PersoBinary');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('binary');
		$info->setTemplate('dashboard','line');
      	$info->setTemplate('mobile','line');
		$info->setIsHistorized(0);
		$info->setIsVisible(0);
		$info->setOrder(26);
		$info->save();

    	$info = $this->getCmd(null, 'EVSE_IndexHC');
		if (!is_object($info)) {
			$info = new LektriCoCmd();
			$info->setName(__('Index HC : ', __FILE__));
		}
		$info->setLogicalId('EVSE_IndexHC');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->setTemplate('dashboard','default');
      	$info->setTemplate('mobile','default');
		$info->setIsHistorized(0);
		$info->setIsVisible(0);
		$info->setOrder(27);
		$info->save();
      
      
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new LektriCoCmd();
			$refresh->setName(__('Rafraîchir', __FILE__));
		}
		$refresh->setEqLogic_id($this->getId());
		$refresh->setLogicalId('refresh');
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->setOrder(50);
		$refresh->save();
      
     	foreach (self::byType('LektriCo') as $LektriCo) {//parcours tous les équipements du plugin LektriCo
			if ($LektriCo->getIsEnable() == 1) {//vérifie que l'équipement est actif
				$cmd = $LektriCo->getCmd(null, 'refresh');//retourne la commande "refresh si elle existe
				if (!is_object($cmd)) {//Si la commande n'existe pas
					continue; //continue la boucle
				}
				$cmd->execCmd(); // la commande existe on la lance
			}
		}
      
    }

    public function preUpdate() {

    }

    public function postUpdate() {

    }

    public function preRemove() {
       
    }

    public function postRemove() {
        
    }
  
  	
	
    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {
      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class LektriCoCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {
		$eqlogic = $this->getEqLogic();
		switch ($this->getLogicalId()) {		
			case 'EVSE_AmpSetPointSlider':
            	$info = $eqlogic->SetSliderSetPoint($_options['slider']/1);
            	$info = $eqlogic->GetData();
				break;
			case 'EVSE_Start':
				$cmd = $eqlogic->SetStartStop('Start');
            	sleep(2);
                $info = $eqlogic->GetData();
				break;
			case 'EVSE_Stop':
				$cmd = $eqlogic->SetStartStop('Stop');
            	sleep(2);
                $info = $eqlogic->GetData();
				break;
			case 'EVSE_ModeMan':
				$cmd = $eqlogic->SetMode('Man');
				$info = $eqlogic->GetData();
				break;
			case 'EVSE_ModeAuto':
				$cmd = $eqlogic->SetMode('Auto');
				$info = $eqlogic->GetData();
				break;
			case 'refresh':
				$info = $eqlogic->GetData();
				break;					
		}
    }
    /*     * **********************Getteur Setteur*************************** */
}
