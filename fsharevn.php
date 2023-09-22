<?php
/* @author: SeCrEt_BoY
 * @version: 2.5 */

class DSM7_FshareVN {
    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;
    private $AppUrl         = 'https://api.fshare.vn/api/';
    private $AppKey         = 'Enter AppKey received from Fshare Email';
    private $AppName        = 'Enter AppName received from Fshare Email';
    private $FSHARE_COOKIE  = '/tmp/fsharevn.cookie';
    private $FSHARE_TOKEN   = '/tmp/fsharevn.token';
    private $LOG_FILE       = '/tmp/fsharevn.log';
    
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
        if ( file_exists($this->FSHARE_COOKIE) ) unlink($this->FSHARE_COOKIE);
        if ( file_exists($this->FSHARE_TOKEN) ) unlink($this->FSHARE_TOKEN);
            
        return $this->FshareVNLogin();
    }
    
    public function GetDownloadInfo() {
        $DownloadInfo = array();

        $newLogin = FALSE;
        
        $this->Token = $this->getFshareToken();
        if ( (empty($this->Token)) || (!file_exists($this->FSHARE_COOKIE)) ) {
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

        $downloadUrl = $this->getDownloadLink();
        
        if(empty($downloadUrl) || $downloadUrl === "error") {
            
            // Has just logged in so maybe the file is not existed
            if ($newLogin) {
                $DownloadInfo[DOWNLOAD_ERROR] = "Get link fail"; 
                return $DownloadInfo;
            }

            // get link may fail due to use expired token / cookie
            // => login and retry once
            $this->logInfo("Token/Cookie may be expired. Login and try once");
            
            if($this->Verify(FALSE) === LOGIN_FAIL) {
                $DownloadInfo[DOWNLOAD_ERROR] = "Login fail!";
                return $DownloadInfo;
            }

            $downloadUrl = $this->getDownloadLink();
            if(empty($downloadUrl) || $downloadUrl === "error") {
                $DownloadInfo[DOWNLOAD_ERROR] = "Get link fail"; 
                return $DownloadInfo;
            }
            
        }
        
        $DownloadInfo[DOWNLOAD_URL] = $downloadUrl;

        return $DownloadInfo;

    }
    
    private function FshareVNLogin() {
        $ret = LOGIN_FAIL;

        $service_url = $this->AppUrl . 'user/login';
        $data = array(
            "app_key"       => $this->AppKey,
            "password"      => $this->Password,
            "user_email"    => $this->Username
        );

        $postData = json_encode($data);

        $curl = curl_init($service_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->FSHARE_COOKIE);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "User-Agent: " . $this->AppName,
            "Content-Type: application/json",
            'Content-Length: ' . strlen($postData)
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

        curl_close($curl);

        return $ret;
        
    }

    private function getDownloadLink() {

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

        $postData = json_encode($data);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->FSHARE_COOKIE);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "User-Agent: " . $this->AppName,
            "Content-Type: application/json",
            "Content-Length: " . strlen($postData)
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
        $myfile = fopen($this->FSHARE_TOKEN, "w");
        fwrite($myfile, $token);
        fclose($myfile);
    }


    private function getFshareToken() {
        if (file_exists($this->FSHARE_TOKEN)) {
            $myfile = fopen($this->FSHARE_TOKEN, "r");
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

?>
