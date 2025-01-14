<?php
require_once "../../config.php";
require_once "../../app/src/helper/Database.php";
require_once "../../app/api/ScriptCalculation.php";

date_default_timezone_set("Europe/Bratislava");
header('Content-Type: application/json; charset=utf-8');

switch ($_SERVER['REQUEST_METHOD']) {

    case "GET":
        //UKLADANIE LOGOV DO DB
        $db = (new App\Helper\Database)->getConnection();
        $stmt0 = $db->prepare("CREATE TABLE  IF NOT EXISTS`requirements` (
                                    `id` int NOT NULL AUTO_INCREMENT,
                                    `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                    `command` varchar(535) CHARACTER SET utf8 COLLATE utf8_slovak_ci DEFAULT NULL,
                                    `info` varchar(535) CHARACTER SET utf8 COLLATE utf8_slovak_ci NOT NULL,
                                    `mistake_info` varchar(535) CHARACTER SET utf8 COLLATE utf8_slovak_ci DEFAULT NULL,
                                    primary key (id)
                                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");
        $stmt0->execute();
        $stmt = $db->prepare("INSERT INTO requirements(command,info,mistake_info)VALUES(:command,:info,:mistake_info)");
        $info = "OK";
        $mistake_info = null;
        $com=null;
        if (isset($_GET['acces_token']) && !empty($_GET['r'])) {//kontrola tokenu a hodnoty r
            if ($_GET['acces_token'] == $acces_token && is_float(floatval($_GET['r']))) {
                $r = $_GET['r'];
                $com = "Vypocet tlmenia";
                $script_runner = new ScriptCalculation();
                $dataT = $script_runner->handleOutputT($r);
                $dataY = $script_runner->handleOutput_Y($r);
                $dataX = $script_runner->handleOutput_X($r);
                header("HTTP/1.1 200 OK");
                unset($_POST['r']);
                $json_all = array("dataT" => $dataT, "dataX" => $dataX, "dataY" => $dataY);
                $json_all = json_encode($json_all);
                echo($json_all);
            } else {
                $stmt->bindParam(":command", $com);
                if ($_GET['acces_token'] != $acces_token) {            //ak je chyba na strane tokenu pri zadavani hodnoty r
                    $err = array("err" => json_encode("Wrong access token!"));
                    $err = json_encode($err);
                    $info = "ERROR";
                    $mistake_info = $err;
                    echo $err;
                } else {                          //ak je chybny input
                    $err = array("err" => json_encode("Wrong input!"));
                    $err = json_encode($err);
                    $info = "ERROR";
                    $mistake_info = $err;
                    echo $err;
                }
            }
        }

        // KOD PRE HOCIJAKY PRIKAZ Z OCTAVE
        if (isset($_GET['acces_token']) && !empty($_GET['prikaz'])) {
            //kontrola tokenu a ci bol prikaz zadany
            if ($_GET['acces_token'] == $acces_token) {
                $command = urldecode($_GET['prikaz']);
                $command = str_replace(" ", "+", $command);
                header("HTTP/1.1 200 OK");
                //echo $command;
                $com=$command;
                $scriptRunner = new ScriptCalculation();
                $o = $scriptRunner->runOctaveCommand($command);
                $check = implode($o[0]);
                if (str_contains($check, "err")) {
                    $json_cmd_err = json_encode($check);
                    $json_error = array("err" => $json_cmd_err);//ak nastane nejaky error pri zadani octave commandu pouzivatelom padne to sem a v $check
                    $json_error = json_encode($json_error);
                    $info = "ERROR";
                    $mistake_info = "Wrong command input";
                    echo $json_error;                                                   //je popis erroru ktory treba ulozit do DB
                } else {
                    $json_message = json_encode($check);        //ak je vsetko OK padne to sem a vypíše vysledok prikazu toto asi este prerobim nejako aby to pekne vypisalo niekde na stranku
                    $json_ans = array("ans" => $json_message);
                    $json_ans = json_encode($json_ans);
                    $info = "OK";
                    echo $json_ans;
                }
                unset($_GET['prikaz']);
            } else {
                if (isset($_GET['acces_token'])) {
                    if ($_GET['acces_token'] != $acces_token) {        //ak je chyba na strane tokenu pri zadavani hodnoty r
                        $err = array("err" => json_encode("Wrong access token!"));
                        $err = json_encode($err);
                        $info = "ERROR";
                        $mistake_info = "Wrong access token";
                        echo $err;
                    } else {                                          //ak je problem s inputom
                        $err = array("err" => json_encode("Wrong input!"));
                        $err = json_encode($err);
                        $info = "ERROR";
                        $mistake_info = "Input error";
                        echo $err;
                    }
                }
            }
        }
        $stmt->bindParam(":command", $com);
        $stmt->bindParam(":info", $info);
        $stmt->bindParam(":mistake_info", $mistake_info);
        $stmt->execute();
}
?>
