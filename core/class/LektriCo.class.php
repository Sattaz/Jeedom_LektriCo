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
require_once __DIR__ . "/../../../../core/php/core.inc.php";

class LektriCo extends eqLogic
{
    /*     * *************************Attributs****************************** */

    /*     * ***********************Methode static*************************** */

    public static function deamon_info()
    {
        $return = [];
        $return["log"] = "";
        $return["state"] = "nok";
        $cron = cron::byClassAndFunction(__CLASS__, "daemon");
        if (is_object($cron) && $cron->running()) {
            $return["state"] = "ok";
        }
        $return["launchable"] = "ok";
        return $return;
    }

    public static function deamon_start()
    {
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info["launchable"] != "ok") {
            throw new Exception(
                __("Veuillez vérifier la configuration", __FILE__)
            );
        }
        $cron = cron::byClassAndFunction(__CLASS__, "daemon");
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass(__CLASS__);
            $cron->setFunction("daemon");
            $cron->setEnable(1);
            $cron->setDeamon(1);
            $cron->setTimeout(1440);
            $cron->setSchedule("* * * * *");
            $cron->save();
        }
        $cron->run();
    }

    public static function deamon_stop()
    {
        $cron = cron::byClassAndFunction(__CLASS__, "daemon");
        if (is_object($cron)) {
            $cron->halt();
        }
    }

    public static function daemon()
    {
        $starttime = microtime(true);
        foreach (self::byType(__CLASS__, true) as $eqLogic) {
            $cmd = $eqLogic->getCmd(null, "update"); //retourne la commande 'update' si elle existe
            if (is_object($cmd)) {
                //si la comande existe
                $cmd->execCmd(); //alors on l'execute
            }
        }
        $endtime = microtime(true);
        if (
            $endtime - $starttime <
            config::byKey("pollInterval", __CLASS__, 60, true)
        ) {
            usleep(
                floor(
                    (config::byKey("pollInterval", __CLASS__) +
                        $starttime -
                        $endtime) *
                        1000000
                )
            );
        }
    }

    public static function templateWidget()
    {
        $return = ["info" => ["string" => []]];
        $return["action"]["other"]["OnOff"] = [
            "template" => "tmplicon",
            "replace" => [
                "#_icon_on_#" =>
                    '<img class="img-responsive" src="plugins/LektriCo/core/img/on.jpg" width="70" style="border-radius:10px; border:2px solid grey;margin:2px 2px" title="Désactiver la charge">',
                "#_icon_off_#" =>
                    '<img class="img-responsive" src="plugins/LektriCo/core/img/off.jpg" width="70" style="border-radius:10px; border:2px solid grey;margin:2px 2px" title="Activer la charge">',
            ],
        ];
        $return["action"]["other"]["AutoManu"] = [
            "template" => "tmplicon",
            "replace" => [
                "#_icon_on_#" =>
                    '<img class="img-responsive" src="plugins/LektriCo/core/img/auto.jpg" width="70" style="border-radius:10px; border:2px solid grey;margin:2px 2px" title="Mode Auto">',
                "#_icon_off_#" =>
                    '<img class="img-responsive" src="plugins/LektriCo/core/img/manu.jpg" width="70" style="border-radius:10px; border:2px solid grey;margin:2px 2px" title="Mode Manuel">',
            ],
        ];
        $return["action"]["slider"]["setpoint"] = [
            "template" => "nooSliderLektriCo", //'SliderButton'
        ];
        return $return;
    }

    public function SetSliderSetPoint($valueSlider)
    {
        try {
            if ($valueSlider == 0) {
                return;
            }

            $DeviceType = $this->getConfiguration("DeviceType");
            $LektriCo_IP = $this->getConfiguration("IP");
            $LektriCo_User = $this->getConfiguration("User");
            $LektriCo_Password = $this->getConfiguration("Password");
            $ch = curl_init();

            if ($LektriCo_User != "" && $LektriCo_Password != "") {
                curl_setopt(
                    $ch,
                    CURLOPT_USERPWD,
                    $LektriCo_User . ":" . $LektriCo_Password
                );
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            //Set State $ setpoint
            //curl_setopt_array($ch, [
            // 	CURLOPT_URL => 'http://'.$LektriCo_IP.'/rpc/app_config.Set',
            //	CURLOPT_RETURNTRANSFER => true,
            //	CURLOPT_ENCODING => "",
            //	CURLOPT_MAXREDIRS => 10,
            //	CURLOPT_TIMEOUT => 10,
            //	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //	CURLOPT_CUSTOMREQUEST => 'POST',
            // 	CURLOPT_POSTFIELDS => '{config_key:"user_current", config_value:'.$valueSlider.'}',
            //	CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            //]);

            $relayMode = 3;
            if ($DeviceType == 10) {
                //1P7K
                $postfields = "{dynamic_current:" . $valueSlider . "}";
            } else {
                //3P22K
                $postfields =
                    "{dynamic_current:" .
                    $valueSlider .
                    ", relay_mode:" .
                    $relayMode .
                    "}";
            }

            curl_setopt_array($ch, [
                CURLOPT_URL =>
                    "http://" . $LektriCo_IP . "/rpc/Dynamic_current.set",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postfields,
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);

            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetSliderSetPoint : Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
            } else {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetSliderSetPoint : Changement référence intensité à " .
                        $valueSlider .
                        " ampères -> " .
                        $response .
                        " " .
                        $this->getHumanName()
                );
                return $valueSlider;
            }
        } catch (Exception $e) {
            log::add(
                "LektriCo",
                "error",
                __(
                    'Erreur lors de l\'éxecution de SetSliderSetPoint ' .
                        " " .
                        $e->getMessage() .
                        " " .
                        $this->getHumanName()
                )
            );
        }
    }

    public function SetStartStop($StartStop)
    {
        try {
            $DeviceType = $this->getConfiguration("DeviceType");
            $LektriCo_IP = $this->getConfiguration("IP");
            $LektriCo_User = $this->getConfiguration("User");
            $LektriCo_Password = $this->getConfiguration("Password");
            $ch = curl_init();

            if ($LektriCo_User != "" && $LektriCo_Password != "") {
                curl_setopt(
                    $ch,
                    CURLOPT_USERPWD,
                    $LektriCo_User . ":" . $LektriCo_Password
                );
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            //Get all other data
            curl_setopt_array($ch, [
                CURLOPT_URL =>
                    "http://" . $LektriCo_IP . "/rpc/Charger_info.Get",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);

            if ($response == "") {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : Erreur de connexion / authentification " .
                        $this->getHumanName()
                );
                curl_close($ch);
                return;
            }

            $err = curl_error($ch);

            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetStartStop : Charger_info - Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
                return;
            }
            $json = json_decode($response, true);

            // Get LektriCo State
            $plug = 0;
            $state = $json["extended_charger_state"];
            switch (true) {
                case $state == "B":
                    $plug = 1;
                    break;
                case $state == "B_AUTH":
                    $plug = 1;
                    break;
                case $state == "C":
                    $plug = 1;
                    break;
                case $state == "D":
                    $plug = 1;
                    break;
                case $state == "B_PAUSE":
                    $plug = 1;
                    break;
                case $state == "B_SCHEDULER":
                    $plug = 1;
                    break;
            }

            if ($plug == 0 && $StartStop == "Start") {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetStartStop : Prise non connectée, la charge ne peut pas démarrer " .
                        $this->getHumanName()
                );
                return;
            }

            $state = "Charge.Stop";
            switch ($StartStop) {
                case "Start":
                    $state = "Charge.Start";
                    break;
                case "Stop":
                    $state = "Charge.Stop";
                    break;
            }

            curl_setopt_array($ch, [
                CURLOPT_URL =>
                    "http://" .
                    $LektriCo_IP .
                    "/rpc/Charging_schedule.Override",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);

            //Check if user_current value matches the AMAX defined in settings, if not then set it
            if ($state == "Charge.Start") {
                $AMax = $this->getConfiguration("AMax");

                log::add(
                    "LektriCo",
                    "debug",
                    'Fonction SetStartStop : $AMax = ' . $AMax
                );

                curl_setopt_array($ch, [
                    CURLOPT_URL =>
                        "http://" . $LektriCo_IP . "/rpc/System_currents.Get",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                ]);
                $response = curl_exec($ch);
                if ($response == "") {
                    log::add(
                        "LektriCo",
                        "debug",
                        "Fonction SetStartStop : System_currents - Erreur de connexion / authentification " .
                            $this->getHumanName()
                    );
                    curl_close($ch);
                    return;
                }
                $err = curl_error($ch);
                if ($err) {
                    log::add(
                        "LektriCo",
                        "debug",
                        "Fonction SetStartStop : System_currents - Erreur CURL -> "
                    ) .
                        $err .
                        " " .
                        $this->getHumanName();
                    return;
                }
                $json = json_decode($response, true);

                // Get LektriCo user_current Set Point
                $user_current = $json["user_current"];

                log::add(
                    "LektriCo",
                    "debug",
                    'Fonction SetStartStop : $user_current = ' . $user_current
                );

                if ($user_current != $AMax) {
                    curl_setopt_array($ch, [
                        CURLOPT_URL =>
                            "http://" . $LektriCo_IP . "/rpc/app_config.Set",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS =>
                            '{config_key:"user_current", config_value:' .
                            $AMax .
                            "}",
                        CURLOPT_HTTPHEADER => [
                            "Content-Type: application/json",
                        ],
                    ]);
                    $response = curl_exec($ch);
                    if ($response == "") {
                        log::add(
                            "LektriCo",
                            "debug",
                            "Fonction SetStartStop : App_config - Erreur de connexion / authentification " .
                                $this->getHumanName()
                        );
                        curl_close($ch);
                        return;
                    }
                    $err = curl_error($ch);
                    if ($err) {
                        log::add(
                            "LektriCo",
                            "debug",
                            "Fonction SetStartStop : App_config - Erreur CURL -> "
                        ) .
                            $err .
                            " " .
                            $this->getHumanName();
                        return;
                    }
                }
              
                $cmd = $this->getCmd(null, "EVSE_AmpSetPointReadBack");
                $setPointCMD = $cmd->execCmd();
          
                $relayMode = 3;
                if ($DeviceType == 10) {
                    //1P7K
                    $postfields = "{dynamic_current:" . $setPointCMD . "}";
                } else {
                    //3P22K
                    $postfields =
                        "{dynamic_current:" .
                        $setPointCMD .
                        ", relay_mode:" .
                        $relayMode .
                        "}";
                }
          
                curl_setopt_array($ch, [
                    CURLOPT_URL =>
                        "http://" . $LektriCo_IP . "/rpc/Dynamic_current.set",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => $postfields,
                    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                ]);

                $response = curl_exec($ch);
                if ($response == "") {
                    log::add(
                        "LektriCo",
                        "debug",
                        "Fonction SetStartStop : Dynamic_current - Erreur de connexion / authentification " .
                            $this->getHumanName()
                    );
                    curl_close($ch);
                    return;
                }
                $err = curl_error($ch);
                if ($err) {
                    log::add(
                        "LektriCo",
                        "debug",
                        "Fonction SetStartStop : Dynamic_current - Erreur CURL -> "
                    ) .
                        $err .
                        " " .
                        $this->getHumanName();
                    return;
                }
              
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => "http://" . $LektriCo_IP . "/rpc/" . $state,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);
            $err = curl_error($ch);

            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetStartStop : State - Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
                curl_close($ch);
                return;
            }

            curl_close($ch);
            if (curl_errno($ch)) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetStartStop : Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
            } else {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetStartStop : Changement valeur à " .
                        $StartStop .
                        " -> " .
                        $response .
                        " " .
                        $this->getHumanName()
                );
            }
        } catch (Exception $e) {
            log::add(
                "LektriCo",
                "error",
                __(
                    'Erreur lors de l\'éxecution de SetStop ' .
                        " " .
                        $e->getMessage() .
                        " " .
                        $this->getHumanName()
                )
            );
        }
    }

    public function SetMode($SelMode)
    {
        try {
            switch ($SelMode) {
                case "Man":
                    $this->checkAndUpdateCmd("EVSE_Mode", "Manuel");
                    $this->checkAndUpdateCmd("EVSE_ModeBin", 0);
                    break;
                case "Auto":
                    $this->checkAndUpdateCmd("EVSE_Mode", "Automatique");
                    $this->checkAndUpdateCmd("EVSE_ModeBin", 1);
                    break;
            }
            log::add(
                "LektriCo",
                "debug",
                "Fonction SetMode : Changement valeur à " . $SelMode
            );
            return;
        } catch (Exception $e) {
            log::add(
                "LektriCo",
                "error",
                __(
                    'Erreur lors de l\'éxecution de SetMode ' .
                        " " .
                        $e->getMessage() .
                        " " .
                        $this->getHumanName()
                )
            );
        }
    }

    public function get_string_between($string, $start, $end)
    {
        $string = " " . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) {
            return "";
        }
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public function CheckHPHC()
    {
        $sendHPHCCmd = $this->getConfiguration("sendHPHCCmd", "");
        if (strlen($sendHPHCCmd) > 0) {
            $cmdHPHC = cmd::byId(str_replace("#", "", $sendHPHCCmd));
            if (!is_object($cmdHPHC)) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction CheckHC : Commande '{$cmdHPHC->getName()}' non trouvée -> vérifiez la configuration pour " .
                        $this->getHumanName()
                );
            } else {
                $valCmdHPHC = $cmdHPHC->execCmd();
                $valCmdHPHC = strtoupper($valCmdHPHC);
                $valCmdIndexHC = $this->getConfiguration("indexHCCmd", "");
                $valCmdIndexHC = strtoupper($valCmdIndexHC);
                $cmd = $this->getCmd(null, "EVSE_IndexHC");
                $valIndexHC = $cmd->execCmd();
                $valIndexHC = strtoupper($valIndexHC);
                $cmd = $this->getCmd(null, "EVSE_ModeBin");
                $valModeBin = $cmd->execCmd();
                if ($valCmdHPHC != $valIndexHC) {
                    $cmdName = $cmdHPHC->getName();
                    $this->checkAndUpdateCmd("EVSE_IndexHC", $valCmdHPHC);
                    log::add(
                        "LektriCo",
                        "debug",
                        "Fonction CheckHC : La commande '{$cmdName}' retourne une nouvelle valeur -> " .
                            $valCmdHPHC .
                            " " .
                            $this->getHumanName()
                    );
                    if ($valModeBin == 1) {
                        if ($valCmdHPHC == $valCmdIndexHC) {
                            log::add(
                                "LektriCo",
                                "debug",
                                "Fonction CheckHC : Mode Start/Stop automatique actif -> " .
                                    $valCmdHPHC .
                                    " -> activation charge " .
                                    $this->getHumanName()
                            );
                            $this->SetStartStop("Start");
                        } else {
                            log::add(
                                "LektriCo",
                                "debug",
                                "Fonction CheckHC : Mode Start/Stop automatique actif -> " .
                                    $valCmdHPHC .
                                    " -> désactivation charge " .
                                    $this->getHumanName()
                            );
                            $this->SetStartStop("Stop");
                        }
                        sleep(2);
                    }
                } else {
                    if ($valModeBin == 1) {
                        $cmd = $this->getCmd(null, "EVSE_Status");
                        $valEVSEStatus = $cmd->execCmd();
                        if (
                            $valCmdHPHC == $valCmdIndexHC &&
                            $valEVSEStatus != 1
                        ) {
                            log::add(
                                "LektriCo",
                                "debug",
                                "Fonction CheckHC : Mode Start/Stop automatique actif -> " .
                                    $valCmdHPHC .
                                    " -> activation charge " .
                                    $this->getHumanName()
                            );
                            $this->SetStartStop("Start");
                            sleep(2);
                        }
                        if (
                            $valCmdHPHC != $valCmdIndexHC &&
                            $valEVSEStatus != 0
                        ) {
                            log::add(
                                "LektriCo",
                                "debug",
                                "Fonction CheckHC : Mode Start/Stop automatique actif -> " .
                                    $valCmdHPHC .
                                    " -> désactivation charge " .
                                    $this->getHumanName()
                            );
                            $this->SetStartStop("Stop");
                            sleep(2);
                        }
                    }
                }
            }
        }
    }

    public function GetData($DeviceType)
    {
        try {
            //$DeviceType 10 = 1P7K
            //$DeviceType 20 = 3p22K

            $this->CheckHPHC();

            $LektriCo_IP = $this->getConfiguration("IP");
            $LektriCo_User = $this->getConfiguration("User");
            $LektriCo_Password = $this->getConfiguration("Password");
            $ch = curl_init();

            if ($LektriCo_User != "" && $LektriCo_Password != "") {
                curl_setopt(
                    $ch,
                    CURLOPT_USERPWD,
                    $LektriCo_User . ":" . $LektriCo_Password
                );
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            //Get all other data
            curl_setopt_array($ch, [
                CURLOPT_URL =>
                    "http://" . $LektriCo_IP . "/rpc/Dynamic_current.Get",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);

            if ($response == "") {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : Erreur de connexion / authentification " .
                        $this->getHumanName()
                );
                curl_close($ch);
                return;
            }

            $err = curl_error($ch);
            //curl_close($ch);
            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : State - Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
                return;
            }
            $json = json_decode($response, true);

            // Get LektriCo Amperes Set Point
            $setPointEVSE = $json["dynamic_current"];
            $cmd = $this->getCmd(null, "EVSE_AmpSetPointReadBack");
            $setPointCMD = $cmd->execCmd();
            if ($setPointEVSE != $setPointCMD) {
                // Set AmpSetPointReadBack value
                $this->checkAndUpdateCmd(
                    "EVSE_AmpSetPointReadBack",
                    $setPointEVSE
                );
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : Amperes Set Point -> Rafraîchissement valeur set point intensité à " .
                        $setPointEVSE .
                        " ampères " .
                        $this->getHumanName()
                );
            } else {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : Amperes Set Point -> Check valeur set point EVSE vs Plugin OK (" .
                        $setPointEVSE .
                        " ampères) " .
                        $this->getHumanName()
                );
            }

            //Get last charge session data
            curl_setopt_array($ch, [
                CURLOPT_URL =>
                    "http://" . $LektriCo_IP . "/rpc/Counters_config.Get",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);

            if ($response == "") {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : Erreur de connexion / authentification " .
                        $this->getHumanName()
                );
                curl_close($ch);
                return;
            }

            $err = curl_error($ch);

            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : State - Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
                return;
            }
            $json = json_decode($response, true);

            // Get LektriCo last session energy
            $LastSessionEnergy = $json["last_session_energy"];
            $this->checkAndUpdateCmd(
                "EVSE_LastSession",
                round($LastSessionEnergy / 1000, 2)
            );

            //Get all other data
            curl_setopt_array($ch, [
                CURLOPT_URL =>
                    "http://" . $LektriCo_IP . "/rpc/Charger_info.Get",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);

            if ($response == "") {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : Erreur de connexion / authentification " .
                        $this->getHumanName()
                );
                curl_close($ch);
                return;
            }

            $err = curl_error($ch);
            curl_close($ch);
            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetData : State - Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
                return;
            }
            $json = json_decode($response, true);

            // Get LektriCo State
            $state = $json["extended_charger_state"];
            switch (true) {
                case $state == "A":
                    $this->checkAndUpdateCmd("EVSE_State", "OFF");
                    $this->checkAndUpdateCmd("EVSE_Status", 0);
                    $this->checkAndUpdateCmd("EVSE_Plug", "Déconnectée");
                    break;
                case $state == "B":
                    $this->checkAndUpdateCmd("EVSE_State", "OFF");
                    $this->checkAndUpdateCmd("EVSE_Status", 0);
                    $this->checkAndUpdateCmd("EVSE_Plug", "Connectée");
                    break;
                case $state == "B_AUTH":
                    $this->checkAndUpdateCmd("EVSE_State", "OFF");
                    $this->checkAndUpdateCmd("EVSE_Status", 0);
                    $this->checkAndUpdateCmd("EVSE_Plug", "Connectée");
                    break;
                case $state == "C":
                    $this->checkAndUpdateCmd("EVSE_State", "En Charge");
                    $this->checkAndUpdateCmd("EVSE_Status", 1);
                    $this->checkAndUpdateCmd("EVSE_Plug", "Connectée");
                    break;
                case $state == "D":
                    $this->checkAndUpdateCmd("EVSE_State", "En Charge");
                    $this->checkAndUpdateCmd("EVSE_Status", 1);
                    $this->checkAndUpdateCmd("EVSE_Plug", "Connectée");
                    break;
                case $state == "E":
                    $this->checkAndUpdateCmd("EVSE_State", "Erreur");
                    $this->checkAndUpdateCmd("EVSE_Status", 0);
                    $this->checkAndUpdateCmd("EVSE_Plug", "...");
                    break;
                case $state == "OTA":
                    $this->checkAndUpdateCmd("EVSE_State", "OTA");
                    $this->checkAndUpdateCmd("EVSE_Status", 0);
                    $this->checkAndUpdateCmd("EVSE_Plug", "...");
                    break;
                case $state == "LOCKED":
                    $this->checkAndUpdateCmd("EVSE_State", "LOCKED");
                    $this->checkAndUpdateCmd("EVSE_Status", 0);
                    $this->checkAndUpdateCmd("EVSE_Plug", "...");
                    break;
                case $state == "B_PAUSE":
                    $this->checkAndUpdateCmd("EVSE_State", "Pause");
                    $this->checkAndUpdateCmd("EVSE_Status", 1);
                    $this->checkAndUpdateCmd("EVSE_Plug", "Connectée");
                    break;
                case $state == "B_SCHEDULER":
                    $this->checkAndUpdateCmd("EVSE_State", "Pause");
                    $this->checkAndUpdateCmd("EVSE_Status", 1);
                    $this->checkAndUpdateCmd("EVSE_Plug", "Connectée");
                    break;
            }

            // Get LektriCo Temperature
            $temp = $json["temperature"];
            $this->checkAndUpdateCmd("EVSE_Temp", round($temp, 0));

            // Get LektriCo Actual Volts & Amperes
            $amperes = round($json["pwm_current"], 1);
            $volts = round($json["voltage"], 0);
            if ($volts == 0) {
                $volts = round(
                    ($json["voltages"]["0"] +
                        $json["voltages"]["1"] +
                        $json["voltages"]["2"]) /
                        3,
                    0
                );
            }
            $puissance = round($json["instant_power"], 0);
            $totalenergy = round($json["total_charged_energy"], 2);
            $this->checkAndUpdateCmd("EVSE_Amperes", $amperes);
            $this->checkAndUpdateCmd("EVSE_Volts", $volts);
            $this->checkAndUpdateCmd("EVSE_Puissance", $puissance);
            $this->checkAndUpdateCmd("EVSE_TotalEnergy", $totalenergy);

            // Get LektriCo Charge Session in Kwh
            $chargesession = $json["session_energy"];
            $this->checkAndUpdateCmd(
                "EVSE_ChargeSession",
                round($chargesession / 1000, 2)
            );

            log::add(
                "LektriCo",
                "debug",
                "Fonction GetData : Récupération des données LektriCo OK ! " .
                    $this->getHumanName()
            );
            return;
        } catch (Exception $e) {
            log::add(
                "LektriCo",
                "error",
                __(
                    'Erreur lors de l\'éxecution de GetData ' .
                        " " .
                        $e->getMessage() .
                        " " .
                        $this->getHumanName()
                )
            );
        }
    }

    public function SetLoadBalancingMode($Mode)
    {
        try {
            $LektriCo_IP = $this->getConfiguration("IP");
            $LektriCo_User = $this->getConfiguration("User");
            $LektriCo_Password = $this->getConfiguration("Password");
            $ch = curl_init();

            if ($LektriCo_User != "" && $LektriCo_Password != "") {
                curl_setopt(
                    $ch,
                    CURLOPT_USERPWD,
                    $LektriCo_User . ":" . $LektriCo_Password
                );
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            curl_setopt_array($ch, [
                CURLOPT_URL =>
                    "http://" . $LektriCo_IP . "/rpc/Load_balancing_mode.Set",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "{load_balancing_mode:" . $Mode . "}",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetLoadBalancingMode : Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
            } else {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction SetLoadBalancingMode : Changement mode équilibrage à " .
                        $Mode .
                        " -> " .
                        $response .
                        " " .
                        $this->getHumanName()
                );
            }
        } catch (Exception $e) {
            log::add(
                "LektriCo",
                "error",
                __(
                    'Erreur lors de l\'éxecution de SetLoadBalancingMode ' .
                        " " .
                        $e->getMessage() .
                        " " .
                        $this->getHumanName()
                )
            );
        }
    }

    public function GetEM($DeviceType)
    {
        try {
            $LektriCo_IP = $this->getConfiguration("IP");
            $LektriCo_User = $this->getConfiguration("User");
            $LektriCo_Password = $this->getConfiguration("Password");
            $ch = curl_init();

            if ($LektriCo_User != "" && $LektriCo_Password != "") {
                curl_setopt(
                    $ch,
                    CURLOPT_USERPWD,
                    $LektriCo_User . ":" . $LektriCo_Password
                );
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            //Get all Meter data
            curl_setopt_array($ch, [
                CURLOPT_URL => "http://" . $LektriCo_IP . "/rpc/Meter_info.Get",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);

            if ($response == "") {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetEM : Erreur de connexion / authentification " .
                        $this->getHumanName()
                );
                curl_close($ch);
                return;
            }

            $err = curl_error($ch);
            //curl_close($ch);
            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetEM : Meter data - Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
                return;
            }
            $json = json_decode($response, true);

            // Get LektriCo Actual Volts & Amperes
            $voltage = $json["voltage"];
            $voltageL1 = round($voltage[0], 3);
            $voltageL2 = round($voltage[1], 3);
            $voltageL3 = round($voltage[2], 3);

            $current = $json["current"];
            $currentL1 = round($current[0], 3);
            $currentL2 = round($current[1], 3);
            $currentL3 = round($current[2], 3);

            $power = $json["active_p"];
            $powerL1 = round($power[0], 3);
            $powerL2 = round($power[1], 3);
            $powerL3 = round($power[2], 3);

            $this->checkAndUpdateCmd("EVSE_VoltsL1", $voltageL1);
            $this->checkAndUpdateCmd("EVSE_AmperesL1", $currentL1);
            $this->checkAndUpdateCmd("EVSE_WattsL1", $powerL1);

            $InstallationType = $this->getConfiguration("InstallationType");
            if ($InstallationType == 20) {
                $this->checkAndUpdateCmd("EVSE_VoltsL2", $voltageL2);
                $this->checkAndUpdateCmd("EVSE_AmperesL2", $currentL2);
                $this->checkAndUpdateCmd("EVSE_WattsL2", $powerL2);
                $this->checkAndUpdateCmd("EVSE_VoltsL3", $voltageL3);
                $this->checkAndUpdateCmd("EVSE_AmperesL3", $currentL3);
                $this->checkAndUpdateCmd("EVSE_WattsL3", $powerL3);
            }

            //Get all Load Balancing Mode
            curl_setopt_array($ch, [
                CURLOPT_URL =>
                    "http://" . $LektriCo_IP . "/rpc/Load_balancing_mode.Get",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            $response = curl_exec($ch);

            if ($response == "") {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetEM : Erreur de connexion / authentification " .
                        $this->getHumanName()
                );
                curl_close($ch);
                return;
            }

            $err = curl_error($ch);
            curl_close($ch);
            if ($err) {
                log::add(
                    "LektriCo",
                    "debug",
                    "Fonction GetEM : Get load balancing mode - Erreur CURL -> "
                ) .
                    $err .
                    " " .
                    $this->getHumanName();
                return;
            }
            $json = json_decode($response, true);

            $mode = $json["load_balancing_mode"];

            switch ($mode) {
                case 0:
                    $this->checkAndUpdateCmd("EVSE_LoadBalancingMode", "Off");
                    break;
                case 1:
                    $this->checkAndUpdateCmd("EVSE_LoadBalancingMode", "Power");
                    break;
                case 2:
                    $this->checkAndUpdateCmd(
                        "EVSE_LoadBalancingMode",
                        "Hybrid"
                    );
                    break;
                case 3:
                    $this->checkAndUpdateCmd("EVSE_LoadBalancingMode", "Green");
                    break;
            }

            log::add(
                "LektriCo",
                "debug",
                "Fonction GetEM : Récupération des données Energy Management OK ! " .
                    $this->getHumanName()
            );
            return;
        } catch (Exception $e) {
            log::add(
                "LektriCo",
                "error",
                __(
                    'Erreur lors de l\'éxecution de GetEM ' .
                        " " .
                        $e->getMessage() .
                        " " .
                        $this->getHumanName()
                )
            );
        }
    }

    /*     * *********************Méthodes d'instance************************* */

    public function preInsert()
    {
        $DeviceType = $this->getConfiguration("DeviceType");
        if ($DeviceType < 30) {
            $setMode = $this->setConfiguration("Mode", 1); //Les nouveaux objets sont de type WIFI API par defaut.
        }
    }

    public function postInsert()
    {
    }

    public function preSave()
    {
    }

    public function postSave()
    {
        $action = $this->getCmd(null, "refresh");
        if (is_object($action)) {
            $action->remove();
        }

        $DeviceType = $this->getConfiguration("DeviceType");
        if ($DeviceType < 30) {
            // Device 10 = 1P7K, Device 20 = 3P22K
            // Cleanup any object not related to Chargers
            $action = $this->getCmd(null, "EVSE_VoltsL1");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_VoltsL2");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_VoltsL3");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_AmperesL1");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_AmperesL2");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_AmperesL3");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_WattsL1");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_WattsL2");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_WattsL3");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_LoadBalancingMode");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_LBM_Off");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_LBM_Power");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_LBM_Hybrid");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_LBM_Green");
            if (is_object($action)) {
                $action->remove();
            }

            $info = $this->getCmd(null, "EVSE_Volts");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Tension : ", __FILE__));
                $info->setLogicalId("EVSE_Volts");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
              	$info->setConfiguration("historizeRound", 0);
                $info->setIsHistorized(1);
                $info->setUnite("V");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(1);
            $info->save();

            $info = $this->getCmd(null, "EVSE_Amperes");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Intensité : ", __FILE__));

                $info->setLogicalId("EVSE_Amperes");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("minValue", 0);
                $info->setConfiguration("maxValue", 32);
                $info->setConfiguration("historizeRound", 0);
                $info->setIsHistorized(1);
                $info->setUnite("A");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(2);
            $info->save();
          
            $info = $this->getCmd(null, "EVSE_Puissance");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Puissance : ", __FILE__));

                $info->setLogicalId("EVSE_Puissance");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("historizeRound", 0);
                $info->setIsHistorized(1);
                $info->setUnite("W");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(3);
            $info->save();
          
            $info = $this->getCmd(null, "EVSE_TotalEnergy");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Energie Totale : ", __FILE__));

                $info->setLogicalId("EVSE_TotalEnergy");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("historizeRound", 2);
                $info->setIsHistorized(1);
                $info->setUnite("kWh");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(4);
            $info->save();

            $info = $this->getCmd(null, "EVSE_ChargeSession");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Charge Session : ", __FILE__));

                $info->setLogicalId("EVSE_ChargeSession");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("historizeRound", 2);
                $info->setIsHistorized(1);
                $info->setUnite("Kwh");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(5);
            $info->save();

            $info = $this->getCmd(null, "EVSE_LastSession");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Dernière Session : ", __FILE__));
                $info->setLogicalId("EVSE_LastSession");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("historizeRound", 2);
                $info->setIsHistorized(1);
                $info->setUnite("Kwh");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(6);
            $info->save();

            $info = $this->getCmd(null, "EVSE_Temp");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Température : ", __FILE__));
                $info->setLogicalId("EVSE_Temp");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("minValue", 0);
                $info->setConfiguration("maxValue", 80);
                $info->setConfiguration("historizeRound", 1);
                $info->setIsHistorized(1);
                $info->setUnite("°C");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(7);
            $info->save();

            $info = $this->getCmd(null, "EVSE_Plug");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Prise : ", __FILE__));
                $info->setLogicalId("EVSE_Plug");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("string");
                $info->setTemplate("dashboard", "default");
                $info->setTemplate("mobile", "default");
                $info->setIsHistorized(0);
                $info->setIsVisible(1);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(8);
            $info->save();

            $AMin = $this->getConfiguration("AMin");
            $AMax = $this->getConfiguration("AMax");
            if (empty($AMin)) {
                $AMin = 6;
            }
            if (empty($AMax)) {
                $AMax = 7;
            }
            if ($AMax <= $AMin) {
                $AMax = $AMin + 1;
            }

            $info = $this->getCmd(null, "EVSE_AmpSetPointReadBack");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Consigne Demandée : ", __FILE__));
                $info->setLogicalId("EVSE_AmpSetPointReadBack");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("historizeRound", 0);
                $info->setIsHistorized(1);
                $info->setUnite("A");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setConfiguration("minValue", $AMin);
            $info->setConfiguration("maxValue", $AMax);
            $info->setOrder(9);
            $info->save();

            $action = $this->getCmd(null, "EVSE_AmpSetPointSlider");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_AmpSetPointSlider");
                $action->setName(__("Curseur Consigne", __FILE__));
                $action->setType("action");
                $action->setSubType("slider");
                $action->setConfiguration("stepValue", 1);
                $action->setValue(
                    $this->getCmd(null, "EVSE_AmpSetPointReadBack")->getId()
                );
                $action->setTemplate("dashboard", "LektriCo::setpoint");
                //$action->setTemplate('mobile','LektriCo::setpoint'); //TEMPLATE SLIDER
                $action->setEqLogic_id($this->getId());
                $action->setUnite("A");
                $action->setDisplay("showNameOndashboard", 0);
                $action->setDisplay("showNameOnmobile", 0);
                $action->setDisplay("forceReturnLineAfter", 1);
            }
            $action->setConfiguration("minValue", $AMin);
            $action->setConfiguration("maxValue", $AMax);
            $action->setOrder(10);
            $action->save();

            $info = $this->getCmd(null, "EVSE_State");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Etat : ", __FILE__));
                $info->setLogicalId("EVSE_State");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("string");
                $info->setTemplate("dashboard", "default");
                $info->setTemplate("mobile", "default");
                $info->setIsHistorized(0);
                $info->setIsVisible(1);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(11);
            $info->save();

            $info = $this->getCmd(null, "EVSE_Mode");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Mode : ", __FILE__));
                $info->setLogicalId("EVSE_Mode");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("string");
                $info->setTemplate("dashboard", "default");
                $info->setTemplate("mobile", "default");
                $info->setIsHistorized(0);
                $info->setIsVisible(0);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(12);
            $info->save();
            $this->checkAndUpdateCmd("EVSE_Mode", "Manuel");

            $info = $this->getCmd(null, "EVSE_Status");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Charge : ", __FILE__));
                $info->setLogicalId("EVSE_Status");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("binary");
                $info->setTemplate("dashboard", "default");
                $info->setTemplate("mobile", "default");
                $info->setIsHistorized(0);
                $info->setIsVisible(0);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(13);
            $info->save();

            $action = $this->getCmd(null, "EVSE_Start");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_Start");
                $action->setName(__("Charge_ON", __FILE__));
                $action->setType("action");
                $action->setSubType("other");
                $action->setValue($this->getCmd(null, "EVSE_Status")->getId());
                $action->setTemplate("dashboard", "LektriCo::OnOff");
                $action->setTemplate("mobile", "LektriCo::OnOff");
                $action->setDisplay("showNameOndashboard", 0);
                $action->setDisplay("showNameOnmobile", 0);
                $action->setEqLogic_id($this->getId());
            }
            $action->setOrder(14);
            $action->save();

            $action = $this->getCmd(null, "EVSE_Stop");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_Stop");
                $action->setName(__("Charge_OFF", __FILE__));
                $action->setType("action");
                $action->setSubType("other");
                $action->setValue($this->getCmd(null, "EVSE_Status")->getId());
                $action->setTemplate("dashboard", "LektriCo::OnOff");
                $action->setTemplate("mobile", "LektriCo::OnOff");
                $action->setDisplay("showNameOndashboard", 0);
                $action->setDisplay("showNameOnmobile", 0);
                $action->setEqLogic_id($this->getId());
            }
            $action->setOrder(15);
            $action->save();

            $info = $this->getCmd(null, "EVSE_ModeBin");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("ModeAuto : ", __FILE__));
                $info->setLogicalId("EVSE_ModeBin");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("binary");
                $info->setTemplate("dashboard", "default");
                $info->setTemplate("mobile", "default");
                $info->setIsHistorized(0);
                $info->setIsVisible(0);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(21);
            $info->save();
            $this->checkAndUpdateCmd("EVSE_ModeBin", 0);

            $action = $this->getCmd(null, "EVSE_ModeMan");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_ModeMan");
                $action->setName(__("ModeAuto_OFF", __FILE__));
                $action->setType("action");
                $action->setSubType("other");
                $action->setValue($this->getCmd(null, "EVSE_ModeBin")->getId());
                $action->setTemplate("dashboard", "LektriCo::AutoManu");
                $action->setTemplate("mobile", "LektriCo::AutoManu");
                $action->setDisplay("showNameOndashboard", 0);
                $action->setDisplay("showNameOnmobile", 0);
                $action->setEqLogic_id($this->getId());
                $action->setIsVisible(1);
                $action->setDisplay("forceReturnLineAfter", 1);
            }
            $action->setOrder(22);
            $action->save();

            $action = $this->getCmd(null, "EVSE_ModeAuto");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_ModeAuto");
                $action->setName(__("ModeAuto_ON", __FILE__));
                $action->setType("action");
                $action->setSubType("other");
                $action->setValue($this->getCmd(null, "EVSE_ModeBin")->getId());
                $action->setTemplate("dashboard", "LektriCo::AutoManu");
                $action->setTemplate("mobile", "LektriCo::AutoManu");
                $action->setDisplay("showNameOndashboard", 0);
                $action->setDisplay("showNameOnmobile", 0);
                $action->setEqLogic_id($this->getId());
                $action->setIsVisible(1);
                $action->setDisplay("forceReturnLineAfter", 1);
            }
            $action->setOrder(23);
            $action->save();

            $info = $this->getCmd(null, "EVSE_PersoString");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Perso. Texte", __FILE__));
                $info->setLogicalId("EVSE_PersoString");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("string");
                $info->setTemplate("dashboard", "default");
                $info->setTemplate("mobile", "default");
                $info->setIsHistorized(0);
                $info->setIsVisible(0);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(24);
            $info->save();

            $info = $this->getCmd(null, "EVSE_PersoNumeric");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Perso. Num.", __FILE__));
                $info->setLogicalId("EVSE_PersoNumeric");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setIsHistorized(0);
                $info->setIsVisible(0);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(25);
            $info->save();

            $info = $this->getCmd(null, "EVSE_PersoBinary");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Perso. Bin.", __FILE__));
                $info->setLogicalId("EVSE_PersoBinary");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("binary");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setIsHistorized(0);
                $info->setIsVisible(0);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(26);
            $info->save();

            $info = $this->getCmd(null, "EVSE_IndexHC");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Index HC : ", __FILE__));
                $info->setLogicalId("EVSE_IndexHC");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("string");
                $info->setTemplate("dashboard", "default");
                $info->setTemplate("mobile", "default");
                $info->setIsHistorized(0);
                $info->setIsVisible(0);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(27);
            $info->save();
        } else {
            // Device 30 = Energy Management module M2W
            // Cleanup any object not related to EM
            $action = $this->getCmd(null, "EVSE_Volts");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_Amperes");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_Puissance");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_TotalEnergy");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_ChargeSession");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_LastSession");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_Temp");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_Plug");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_AmpSetPointReadBack");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_AmpSetPointSlider");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_State");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_Mode");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_Status");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_Start");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_Stop");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_ModeBin");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_ModeMan");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_ModeAuto");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_PersoString");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_PersoNumeric");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_PersoBinary");
            if (is_object($action)) {
                $action->remove();
            }
            $action = $this->getCmd(null, "EVSE_IndexHC");
            if (is_object($action)) {
                $action->remove();
            }

            $InstallationType = $this->getConfiguration("InstallationType");

            $info = $this->getCmd(null, "EVSE_VoltsL1");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Tension L1 : ", __FILE__));
                $info->setLogicalId("EVSE_VoltsL1");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("historizeRound", 0);
                $info->setIsHistorized(1);
                $info->setUnite("V");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(10);
            $info->save();

            if ($InstallationType == 10) {
                //10 = single phase, 20 = 3 phased
                $action = $this->getCmd(null, "EVSE_VoltsL2");
                if (is_object($action)) {
                    $action->remove();
                }
                $action = $this->getCmd(null, "EVSE_VoltsL3");
                if (is_object($action)) {
                    $action->remove();
                }
            } else {
                $info = $this->getCmd(null, "EVSE_VoltsL2");
                if (!is_object($info)) {
                    $info = new LektriCoCmd();
                    $info->setName(__("Tension L2 : ", __FILE__));
                    $info->setLogicalId("EVSE_VoltsL2");
                    $info->setEqLogic_id($this->getId());
                    $info->setType("info");
                    $info->setSubType("numeric");
                    $info->setTemplate("dashboard", "line");
                    $info->setTemplate("mobile", "line");
                    $info->setConfiguration("historizeRound", 0);
                    $info->setIsHistorized(1);
                    $info->setUnite("V");
                    $info->setDisplay("forceReturnLineAfter", 1);
                }
                $info->setOrder(11);
                $info->save();

                $info = $this->getCmd(null, "EVSE_VoltsL3");
                if (!is_object($info)) {
                    $info = new LektriCoCmd();
                    $info->setName(__("Tension L3 : ", __FILE__));
                    $info->setLogicalId("EVSE_VoltsL3");
                    $info->setEqLogic_id($this->getId());
                    $info->setType("info");
                    $info->setSubType("numeric");
                    $info->setTemplate("dashboard", "line");
                    $info->setTemplate("mobile", "line");
                    $info->setConfiguration("historizeRound", 0);
                    $info->setIsHistorized(1);
                    $info->setUnite("V");
                    $info->setDisplay("forceReturnLineAfter", 1);
                }
                $info->setOrder(12);
                $info->save();
            }

            $info = $this->getCmd(null, "EVSE_AmperesL1");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Intensité L1 : ", __FILE__));
                $info->setLogicalId("EVSE_AmperesL1");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("minValue", 0);
                $info->setConfiguration("maxValue", 32);
                $info->setConfiguration("historizeRound", 0);
                $info->setIsHistorized(1);
                $info->setUnite("A");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(21);
            $info->save();

            if ($InstallationType == 10) {
                //10 = single phase, 20 = 3 phased
                $action = $this->getCmd(null, "EVSE_AmperesL2");
                if (is_object($action)) {
                    $action->remove();
                }
                $action = $this->getCmd(null, "EVSE_AmperesL3");
                if (is_object($action)) {
                    $action->remove();
                }
            } else {
                $info = $this->getCmd(null, "EVSE_AmperesL2");
                if (!is_object($info)) {
                    $info = new LektriCoCmd();
                    $info->setName(__("Intensité L2 : ", __FILE__));
                    $info->setLogicalId("EVSE_AmperesL2");
                    $info->setEqLogic_id($this->getId());
                    $info->setType("info");
                    $info->setSubType("numeric");
                    $info->setTemplate("dashboard", "line");
                    $info->setTemplate("mobile", "line");
                    $info->setConfiguration("minValue", 0);
                    $info->setConfiguration("maxValue", 32);
                    $info->setConfiguration("historizeRound", 0);
                    $info->setIsHistorized(1);
                    $info->setUnite("A");
                    $info->setDisplay("forceReturnLineAfter", 1);
                }
                $info->setOrder(22);
                $info->save();

                $info = $this->getCmd(null, "EVSE_AmperesL3");
                if (!is_object($info)) {
                    $info = new LektriCoCmd();
                    $info->setName(__("Intensité L3 : ", __FILE__));
                    $info->setLogicalId("EVSE_AmperesL3");
                    $info->setEqLogic_id($this->getId());
                    $info->setType("info");
                    $info->setSubType("numeric");
                    $info->setTemplate("dashboard", "line");
                    $info->setTemplate("mobile", "line");
                    $info->setConfiguration("minValue", 0);
                    $info->setConfiguration("maxValue", 32);
                    $info->setConfiguration("historizeRound", 0);
                    $info->setIsHistorized(1);
                    $info->setUnite("A");
                    $info->setDisplay("forceReturnLineAfter", 1);
                }
                $info->setOrder(23);
                $info->save();
            }

            $info = $this->getCmd(null, "EVSE_WattsL1");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Puissance L1: ", __FILE__));
                $info->setLogicalId("EVSE_WattsL1");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("numeric");
                $info->setTemplate("dashboard", "line");
                $info->setTemplate("mobile", "line");
                $info->setConfiguration("historizeRound", 0);
                $info->setIsHistorized(1);
                $info->setUnite("W");
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(31);
            $info->save();

            if ($InstallationType == 10) {
                //10 = single phase, 20 = 3 phased
                $action = $this->getCmd(null, "EVSE_WattsL2");
                if (is_object($action)) {
                    $action->remove();
                }
                $action = $this->getCmd(null, "EVSE_WattsL3");
                if (is_object($action)) {
                    $action->remove();
                }
            } else {
                $info = $this->getCmd(null, "EVSE_WattsL2");
                if (!is_object($info)) {
                    $info = new LektriCoCmd();
                    $info->setName(__("Puissance L2 : ", __FILE__));
                    $info->setLogicalId("EVSE_WattsL2");
                    $info->setEqLogic_id($this->getId());
                    $info->setType("info");
                    $info->setSubType("numeric");
                    $info->setTemplate("dashboard", "line");
                    $info->setTemplate("mobile", "line");
                    $info->setConfiguration("historizeRound", 0);
                    $info->setIsHistorized(1);
                    $info->setUnite("W");
                    $info->setDisplay("forceReturnLineAfter", 1);
                }
                $info->setOrder(32);
                $info->save();

                $info = $this->getCmd(null, "EVSE_WattsL3");
                if (!is_object($info)) {
                    $info = new LektriCoCmd();
                    $info->setName(__("Puissance L3 : ", __FILE__));
                    $info->setLogicalId("EVSE_WattsL3");
                    $info->setEqLogic_id($this->getId());
                    $info->setType("info");
                    $info->setSubType("numeric");
                    $info->setTemplate("dashboard", "line");
                    $info->setTemplate("mobile", "line");
                    $info->setConfiguration("historizeRound", 0);
                    $info->setIsHistorized(1);
                    $info->setUnite("W");
                    $info->setDisplay("forceReturnLineAfter", 1);
                }
                $info->setOrder(33);
                $info->save();
            }

            $info = $this->getCmd(null, "EVSE_LoadBalancingMode");
            if (!is_object($info)) {
                $info = new LektriCoCmd();
                $info->setName(__("Mode Equilibrage : ", __FILE__));
                $info->setLogicalId("EVSE_LoadBalancingMode");
                $info->setEqLogic_id($this->getId());
                $info->setType("info");
                $info->setSubType("string");
                $info->setTemplate("dashboard", "default");
                $info->setTemplate("mobile", "default");
                $info->setIsHistorized(0);
                $info->setIsVisible(1);
                $info->setDisplay("forceReturnLineAfter", 1);
            }
            $info->setOrder(40);
            $info->save();

            $action = $this->getCmd(null, "EVSE_LBM_Off");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_LBM_Off");
                $action->setName(__("Off", __FILE__));
                $action->setType("action");
                $action->setSubType("other");
                $action->setTemplate("dashboard", "default");
                $action->setTemplate("mobile", "default");
                $action->setEqLogic_id($this->getId());
                //$action->setDisplay('forceReturnLineAfter',1);
            }
            $action->setOrder(50);
            $action->save();

            $action = $this->getCmd(null, "EVSE_LBM_Power");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_LBM_Power");
                $action->setName(__("Power", __FILE__));
                $action->setType("action");
                $action->setSubType("other");
                $action->setTemplate("dashboard", "default");
                $action->setTemplate("mobile", "default");
                $action->setEqLogic_id($this->getId());
                //$action->setDisplay('forceReturnLineAfter',1);
            }
            $action->setOrder(51);
            $action->save();

            $action = $this->getCmd(null, "EVSE_LBM_Hybrid");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_LBM_Hybrid");
                $action->setName(__("Hybrid", __FILE__));
                $action->setType("action");
                $action->setSubType("other");
                $action->setTemplate("dashboard", "default");
                $action->setTemplate("mobile", "default");
                $action->setEqLogic_id($this->getId());
                //$action->setDisplay('forceReturnLineAfter',1);
            }
            $action->setOrder(52);
            $action->save();

            $action = $this->getCmd(null, "EVSE_LBM_Green");
            if (!is_object($action)) {
                $action = new LektriCoCmd();
                $action->setLogicalId("EVSE_LBM_Green");
                $action->setName(__("Green", __FILE__));
                $action->setType("action");
                $action->setSubType("other");
                $action->setTemplate("dashboard", "default");
                $action->setTemplate("mobile", "default");
                $action->setEqLogic_id($this->getId());
                //$action->setDisplay('forceReturnLineAfter',1);
            }
            $action->setOrder(53);
            $action->save();
        }

        $refresh = $this->getCmd(null, "update");
        if (!is_object($refresh)) {
            $refresh = new LektriCoCmd();
            $refresh->setName(__("Rafraîchir", __FILE__));
            $refresh->setEqLogic_id($this->getId());
            $refresh->setLogicalId("update");
            $refresh->setType("action");
            $refresh->setSubType("other");
            $refresh->setIsVisible(0);
        }
        $refresh->setOrder(99);
        $refresh->save();

        self::deamon_start();
    }

    public function preUpdate()
    {
    }

    public function postUpdate()
    {
    }

    public function preRemove()
    {
    }

    public function postRemove()
    {
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

class LektriCoCmd extends cmd
{
    /*     * *************************Attributs****************************** */

    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = [])
    {
        $eqlogic = $this->getEqLogic();
        $DeviceType = $eqlogic->getConfiguration("DeviceType");
        switch ($this->getLogicalId()) {
            case "EVSE_AmpSetPointSlider":
                $info = $eqlogic->SetSliderSetPoint($_options["slider"] / 1);
                $info = $eqlogic->GetData($DeviceType);
                break;
            case "EVSE_Start":
                $cmd = $eqlogic->SetStartStop("Start");
                sleep(2);
                $info = $eqlogic->GetData($DeviceType);
                break;
            case "EVSE_Stop":
                $cmd = $eqlogic->SetStartStop("Stop");
                sleep(2);
                $info = $eqlogic->GetData($DeviceType);
                break;
            case "EVSE_ModeMan":
                $cmd = $eqlogic->SetMode("Man");
                $info = $eqlogic->GetData($DeviceType);
                break;
            case "EVSE_ModeAuto":
                $cmd = $eqlogic->SetMode("Auto");
                $info = $eqlogic->GetData($DeviceType);
                break;
            case "EVSE_LBM_Off":
                $cmd = $eqlogic->SetLoadBalancingMode(0);
                $info = $eqlogic->GetEM($DeviceType);
                break;
            case "EVSE_LBM_Power":
                $cmd = $eqlogic->SetLoadBalancingMode(1);
                $info = $eqlogic->GetEM($DeviceType);
                break;
            case "EVSE_LBM_Hybrid":
                $cmd = $eqlogic->SetLoadBalancingMode(2);
                $info = $eqlogic->GetEM($DeviceType);
                break;
            case "EVSE_LBM_Green":
                $cmd = $eqlogic->SetLoadBalancingMode(3);
                $info = $eqlogic->GetEM($DeviceType);
                break;
            case "update":
                if ($DeviceType < 30) {
                    $info = $eqlogic->GetData($DeviceType);
                } else {
                    $info = $eqlogic->GetEM($DeviceType);
                }
                break;
        }
    }
    /*     * **********************Getteur Setteur*************************** */
}
