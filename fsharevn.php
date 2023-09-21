<?php
/* @author: SeCrEt_BoY
 * @version: 2.5 */

class SynoFileHostingFshareVN {
    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;
    private $AppUrl     = 'https://api.fshare.vn/api/';
    private $UserAgent  = 'Enter your app user-agent here';
    private $AppKey     = 'Enter Fshare API Key here';
    private $COOKIE_JAR = '/tmp/fsharevn.cookie';
    private $LOG_FILE   = '/tmp/fsharevn.log';
    private $TOKEN_FILE = '/tmp/fsharevn.token';
    
    public function __construct($Url, $Username, $Password, $HostInfo) {

        if ( strpos($Url,'http') === 0) {
            $Url = 'https' . strstr($Url, '://');
        }
        if ( strpos($Url, '?') ) {
            $Url = strstr($Url, '?' , true);
        }

        $this->Url = $Url;
        $this->Username = $Username;
        $this->Password = $Password;
        $this->HostInfo = $HostInfo;

        ini_set('max_execution_time', 300);
    }
    
    public function Verify($ClearCookie) {
        if ( file_exists($this->COOKIE_JAR) ) unlink($this->COOKIE_JAR);
        if ( file_exists($this->TOKEN_FILE) ) unlink($this->TOKEN_FILE);
            
        return $this->performLogin();
    }
    
    public function GetDownloadInfo() {
        $DownloadInfo = array();

        $this->logInfo("Start getting download info");

        $newLogin = FALSE;
        
        $this->Token = $this->getToken();
        if ( (empty($this->Token)) || (!file_exists($this->COOKIE_JAR)) ) {
            $newLogin = TRUE;
        }

        if($newLogin) {
            $this->logInfo("Cookie/Token file is not existed => need to login to get them");
            // login to get authentication info
            if($this->Verify(FALSE) === LOGIN_FAIL) {
                $DownloadInfo[DOWNLOAD_ERROR] = "Login fail!";
                return $DownloadInfo;
            }    
        }

        $downloadUrl = $this->getLink();
        
        if(empty($downloadUrl) || $downloadUrl === "error") {
            if ($newLogin) {
                $DownloadInfo[DOWNLOAD_ERROR] = "Get link fail"; 
                return $DownloadInfo;
            }

            // get link may fail due to use expired token / cookie
            // => login and retry once
            
            // $this->logInfo("Token/Cookie may be expired. Login and try once");
            if($this->Verify(FALSE) === LOGIN_FAIL) {
                $DownloadInfo[DOWNLOAD_ERROR] = "Login fail!";
                return $DownloadInfo;
            }

            $downloadUrl = $this->getLink();
            if(empty($downloadUrl) || $downloadUrl === "error") {
                $DownloadInfo[DOWNLOAD_ERROR] = "Get link fail"; 
                return $DownloadInfo;
            }
            
        }
        
        $DownloadInfo[DOWNLOAD_URL] = $downloadUrl;

        // $this->logInfo("End getting download info");

        return $DownloadInfo;

    }
    
    private function performLogin() {
        $ret = LOGIN_FAIL;

        $this->logInfo("Start login");

        $service_url = $this->AppUrl . 'user/login';
        $data = array(
            "app_key"       => $this->AppKey,
            "password"      => $this->Password,
            "user_email"    => $this->Username
        );

        $data_string = json_encode($data);

        $curl = curl_init($service_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->COOKIE_JAR);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "User-Agent: " . $this->UserAgent,
            "Content-Type: application/json",
            'Content-Length: ' . strlen($data_string)
        ));
        
        $curl_response = curl_exec($curl);

        if(!$this->isOK($curl, $curl_response)) {
            $this->logError("Login error: " . curl_error($curl));
        } else {
            $this->Token = json_decode($curl_response)->{'token'};
            // save token to disk
            $this->saveToken($this->Token); 
            $this->logInfo("Login ok");
            
            $ret = USER_IS_PREMIUM;
        }

        $this->logInfo("End login");

        curl_close($curl);

        return $ret;
        
    }

    private function getLink() {

        $ret = "error";

        $this->logInfo("Start Get Link: " . $this->Url);

        $service_url = $this->AppUrl . 'session/download';

        $curl = curl_init($service_url);
        $data = array(
            "url"       => $this->Url,
            "password"  => "",
            "token"     => $this->Token,
            "zipflag"   => 0
        );

        $data_string = json_encode($data);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->COOKIE_JAR);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "User-Agent: " . $this->UserAgent,
            "Content-Type: application/json",
            "Content-Length: " . strlen($data_string)
        ));
        
        $curl_response = curl_exec($curl);

        if (!$this->isOK($curl, $curl_response)) {
            $this->logError("Get link error: " . curl_error($curl));
        } else {
            $downloadUrl = json_decode($curl_response)->{'location'};

            // $this->logInfo("Get link ok");

            $ret = $downloadUrl;
        }

        $this->logInfo("End Get Link");

        curl_close($curl);

        return $ret;
    }

    private function logError($msg) {
        $this->log("[ERROR]", $msg);
    }

    private function logInfo($msg) {
        $this->log("[INFO]", $msg);
    }

    private function log($prefix, $msg) {
        error_log($prefix . " - " . date('Y-m-d H:i:s') . " - " . $msg . "\n", 3, $this->LOG_FILE);
    }

    private function saveToken($token) {
        $myfile = fopen($this->TOKEN_FILE, "w");
        fwrite($myfile, $token);
        fclose($myfile);
    }


    private function getToken() {
        if (file_exists($this->TOKEN_FILE)) {
            $myfile = fopen($this->TOKEN_FILE, "r");
            $token = fgets($myfile);
            fclose($myfile);
            return $token;
        } else {
            return "";
        }
    }

    private function isOK($curl, $curl_response) {
        $this->logInfo("HTTP Response: " . $curl_response);
        
        if($curl_response === false) {
            return false;
        }

        $info = curl_getinfo($curl);
        $this->logInfo("HTTP CODE: " . $info['http_code']);
        if($info['http_code'] !== 200) {
            return false;
        }

        $code = json_decode($curl_response)->{'code'};
        $this->logInfo("CODE: " .$code);
        if( !empty($code) && $code !== 200) {
            return false;
        }

        return true;
    }


}

/*$url = "https://www.fshare.vn/file/BA7TDZNZQHUL";
$username = "zang_itu@yahoo.com";
$password = "asd123";

$client = new SynoFileHostingFshareVN($url, $username, $password, NULL);
$client->GetDownloadInfo();
echo "DONE";*/

?>