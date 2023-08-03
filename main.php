<?php
// Router host & data fetch URL
$host = '192.168.1.1';
$url = "http://{$host}/cgi-bin-igd/netcore_get.cgi";

// Router Admin login
$auth = [
    'username' => 'admin',
    'password' => ''
];

// Alert Sound Notification
$play_sound = [
    'internet_access' => 'C:\Windows\Media\Alarm01.wav',
    'no_internet_access' => 'C:\Windows\Media\Alarm10.wav',
    'router_login_error' => 'C:\Windows\Media\Windows Background.wav'
];

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Log file name
$logFile = __FILE__ . '.log';

if (!file_exists($logFile)) touch($logFile);
if (filesize($logFile) > 1024 * 1024 * 1) {     // 1 MB
    $i = 1;
    do {
        $logFile_new = __FILE__ . '(' . $i . ').log';
        if (file_exists($logFile_new)) {
            $i++;
            continue;
        } else {
            rename($logFile, $logFile_new);
            touch($logFile);
            break;
        }
    } while (true);
}

function prependFileLog($fname, $msg) {
    $file = fopen($fname, 'r+');
    $msg = $msg . file_get_contents($fname);
    fwrite($file, $msg, strlen($msg));
    fclose($file);
}

function playSound($wav) {
    exec('powershell -c $PlayWav=New-Object System.Media.SoundPlayer;$PlayWav.SoundLocation=\''.$wav.'\';$PlayWav.playsync();');
}

prependFileLog($logFile, "\t************************************************************************\n\n\n");
prependFileLog($logFile, "\t*************** Log Started at ".date('d/m/Y H:i:s')." *********************\n");
prependFileLog($logFile, "\n\t************************************************************************\n");

$i = 1;
$last_access = 0;
$internet = false;
$is_pppoe_connected = 0;

while (true) {

    if (!$internet) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, 
            [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Accept-Encoding: gzip',
                'Accept-Language: en-GB,en;q=0.9',
                'Authorization: Basic ' . base64_encode($auth['username'] . ':' . $auth['password']),
                'Cache-Control: max-age=0',
                'Connection: keep-alive',
                'Host: ' . $host,
                'Upgrade-Insecure-Requests: 1',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.75 Safari/537.36'     
            ]
        );
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        $resp = curl_exec($curl);
        curl_close($curl);

        $resp = preg_replace('/\'/', '"', $resp);
        $resp = json_decode($resp, true);
        if (!isset($resp['connected'])) {
            echo "\n\nError Router Login... Check if you are connected with your Wi-fi... and make sure that you have entered correct username and password! Retrying... \n\n";
            if (!empty($play_sound['router_login_error'])) 
                playSound($play_sound['router_login_error']);
            sleep(1);
            continue;
        }
        $is_pppoe_connected = $resp['connected'];
    }

    $logMsg = '';

    if ($is_pppoe_connected == 1) {
        if (!$internet) {
            $logMsg .= "\n" . $i . ". PPPOE connected! " . date('d/m/Y H:i:s') . "\n";
            $logMsg .= "\tPrimary DNS: " . $resp['dns_a'] . "\n";
            $logMsg .= "\tSecondary DNS: " . $resp['dns_b'] . "\n\n";
            echo $logMsg;
        }

        $internet = false;
        if (exec('ping 8.8.8.8 -n 1 2>&1', $output)) {
            foreach ($output as $out) {
                if (preg_match('/Reply from 8\.8\.8\.8: bytes=(\d+) time=(\d+)(ms|s) TTL=(\d+)/i', $out)) {
                    $logMsg .= $i . ".\tInternet Access. " . date('d/m/Y H:i:s') . "\n\n";
                    echo $i . ".\tInternet Access. " . date('d/m/Y H:i:s') . "\n\n";
                    if ($last_access <= $i - 5 && $i > 10) {
                        $msg = "\n\n\t****************************************************************************\n";
                        $msg .= "\t********************** Now you can access the Internet! ********************\n";
                        $msg .= "\t****************************************************************************\n\n";
                        $logMsg .= $msg;
                        echo $msg;
                        if (!empty($play_sound['internet_access'])) 
                            playSound($play_sound['internet_access']);
                    }

                    $last_access = $i;
                    $internet = true;
                    break;
                }
            }
        }
        unset($output);

        if (!$internet) {  
            if ($last_access == $i - 5 && $i > 10) {
                $msg = "\n\n\t****************************************************************************\n";
                $msg .= "\t************* You are no longer connected with the internet! ***************\n";
                $msg .= "\t****************************************************************************\n\n";
                $logMsg .= $msg;
                echo $msg;
                if (!empty($play_sound['no_internet_access'])) 
                    playSound($play_sound['no_internet_access']);
            }
            $logMsg .= $i . ". No internet Access " . date('d/m/Y H:i:s') . "!\n";
            echo $i .". No internet Access! " . date('d/m/Y H:i:s') . "\n";
        }

    
    } else {
        $msg = $i . ". PPPOE not connected " . date('d/m/Y H:i:s') . "\n";
        $logMsg .= $msg;
        echo $msg;
    }

    // Add log
    prependFileLog($logFile, $logMsg);

    $i++;
    sleep(1);

}
