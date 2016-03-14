<?php namespace Box\Content;

class Client
{
    public $clientId = '';
    public $clientSecret = '';
    public $redirectUri = '';
    public $accessToken = '';
    public $refreshToken = '';
    public $authorizeUrl = 'https://www.box.com/api/oauth2/authorize';
    public $tokenUrl = 'https://www.box.com/api/oauth2/token';
    public $apiUrl = 'https://api.box.com/2.0';
    public $uploadUrl = 'https://upload.box.com/api/2.0';

    public $tokenStoragePath = './';

    public function __construct($clientId = '', $clientSecret = '', $redirectUri = '')
    {
        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('Invalid CLIENT_ID or CLIENT_SECRET or REDIRECT_URL. Please provide CLIENT_ID, CLIENT_SECRET and REDIRECT_URL when creating an instance of the class.');
        } else {
            $this->clientId = $clientId;
            $this->clientSecret = $clientSecret;
            $this->redirectUri = $redirectUri;
        }
    }

    /* First step for authentication [Gets the code] */
    public function getCode()
    {
        if (array_key_exists('refresh_token', $_REQUEST)) {
            $this->refreshToken = $_REQUEST['refresh_token'];
        } else {
            $url = $this->authorizeUrl . '?' . http_build_query(array('response_type' => 'code', 'client_id' => $this->clientId, 'redirect_uri' => $this->redirectUri));
            header('location: ' . $url);
            exit();
        }
    }

    /* Second step for authentication [Gets the access_token and the refresh_token] */
    public function getUser()
    {
        $url = $this->buildUrl('/users/me');
        return json_decode($this->get($url), true);
    }

    /* Gets the current user details */
    private function buildUrl($api_func, array $opts = array(), $url = null)
    {
        $opts = $this->setOptions($opts);
        if (!is_null($url)) {
            $base = $url . $api_func . '?';
        } else {
            $base = $this->apiUrl . $api_func . '?';
        }
        $query_string = http_build_query($opts);
        $base = $base . $query_string;
        return $base;
    }

    /* Get the details of the mentioned folder */
    private function setOptions(array $opts)
    {
        if (!array_key_exists('access_token', $opts)) {
            $opts['access_token'] = $this->accessToken;
        }
        return $opts;
    }

    /* Get the list of items in the mentioned folder */
    private static function get($url, $followRedirects = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($followRedirects) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        }

        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /* Get the list of collaborators in the mentioned folder */
    public function getFolderDetails($folder, $json = false)
    {
        $url = $this->buildUrl("/folders/$folder");
        if ($json) {
            return $this->get($url);
        } else {
            return json_decode($this->get($url), true);
        }
    }

    /* Lists the folders in the mentioned folder */
    public function getFolderCollaborators($folder, $json = false)
    {
        $url = $this->buildUrl("/folders/$folder/collaborations");
        if ($json) {
            return $this->get($url);
        } else {
            return json_decode($this->get($url), true);
        }
    }

    /* Lists the files in the mentioned folder */
    public function getFolders($folder)
    {
        $data = $this->getFolderItems($folder);
        $return = array();
        foreach ($data['entries'] as $item) {
            $array = '';
            if ($item['type'] == 'folder') {
                $array = $item;
            }
            $return[] = $array;
        }
        return array_filter($return);
    }

    /* Lists the files in the mentioned folder */
    public function getFolderItems($folder, $json = false)
    {
        $url = $this->buildUrl("/folders/$folder/items");
        if ($json) {
            return $this->get($url);
        } else {
            return json_decode($this->get($url), true);
        }
    }

    public function getFiles($folder)
    {
        $data = $this->getFolderItems($folder);
        $return = array();
        foreach ($data['entries'] as $item) {
            $array = '';
            if ($item['type'] == 'file') {
                $array = $item;
            }
            $return[] = $array;
        }
        return array_filter($return);
    }

    /* Modifies the folder details as per the api */
    public function getLinks($folder)
    {
        $data = $this->getFolderItems($folder);
        $return = array();
        foreach ($data['entries'] as $item) {
            $array = '';
            if ($item['type'] == 'web_link') {
                $array = $item;
            }
            $return[] = $array;
        }
        return array_filter($return);
    }

    /* Deletes a folder */
    public function createFolder($name, $parent_id = '0')
    {
        $url = $this->buildUrl("/folders");
        $params = array('name' => $name, 'parent' => array('id' => $parent_id));
        return json_decode($this->post($url, json_encode($params)), true);
    }

    /* Shares a folder */
    private static function post($url, $params, $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /* Shares a file */
    public function updateFolder($folder, array $params)
    {
        $url = $this->buildUrl("/folders/$folder");
        return json_decode($this->put($url, $params), true);
    }

    /* Get the details of the mentioned file */
    private static function put($url, array $params = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /* Uploads a file */
    public function deleteFolder($folder, array $opts)
    {
        $url = $this->buildUrl("/folders/$folder", $opts);
        $return = json_decode($this->delete($url), true);
        if (empty($return)) {
            return true;
        } else {
            return false;
        }
    }

    /* Modifies the file details as per the api */
    private static function delete($url, $params = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /* Deletes a file */
    public function shareFolder($folder, array $params)
    {
        $url = $this->buildUrl("/folders/$folder");
        return json_decode($this->put($url, $params), true);
    }

    /* Saves the token */
    public function shareFile($file, array $params)
    {
        $url = $this->buildUrl("/files/$file");
        return json_decode($this->put($url, $params), true);
    }

    /* Reads the token */
    public function getFileDetails($file, $json = false)
    {
        $url = $this->buildUrl("/files/$file");
        if ($json) {
            return $this->get($url);
        } else {
            return json_decode($this->get($url), true);
        }
    }

    /* Loads the token */
    public function putFile($filePath, $name, $parent_id = '0')
    {
        $url = $this->buildUrl('/files/content', array(), $this->uploadUrl);

        if (!isset($name) || empty($name)) {
            $name = basename($filePath);
        }

        $attributes = [
            'name' => $name,
            'parent' => [
                'id' => $parent_id
            ]
        ];

        $params = [
            'attributes' => json_encode($attributes),
            'file' => new \CURLFile($filePath, "", 'file'),
        ];

        return json_decode($this->post($url, $params), true);
    }

    /* Builds the URL for the call */
    public function updateFile($file, array $params)
    {
        $url = $this->buildUrl("/files/$file");
        return json_decode($this->put($url, $params), true);
    }

    /* Sets the required before building the query */
    public function deleteFile($file)
    {
        $url = $this->buildUrl("/files/$file");
        $return = json_decode($this->delete($url), true);
        if (empty($return)) {
            return 'The file has been deleted.';
        } else {
            return $return;
        }
    }

    public function loadToken()
    {
        $array = $this->readToken('file');
        if (!$array) {
            return false;
        } else {
            if (isset($array['error'])) {
                $this->error = $array['error_description'];
                return false;
            } elseif ($this->expired($array['expires_in'], $array['timestamp'])) {
                $this->refreshToken = $array['refresh_token'];
                $token = $this->getToken(NULL, true);
                if ($this->writeToken($token, 'file')) {
                    $array = json_decode($token, true);
                    $this->refreshToken = $array['refresh_token'];
                    $this->accessToken = $array['access_token'];
                    return true;
                }
            } else {
                $this->refreshToken = $array['refresh_token'];
                $this->accessToken = $array['access_token'];
                return true;
            }
        }
    }

    public function readToken($type = 'file', $json = false)
    {
        if ($type == 'file' && file_exists($this->tokenStoragePath . 'token.box')) {
            $fp = fopen($this->tokenStoragePath . 'token.box', 'r');
            $content = fread($fp, filesize($this->tokenStoragePath . 'token.box'));
            fclose($fp);
        } else {
            return false;
        }
        if ($json) {
            return $content;
        } else {
            return json_decode($content, true);
        }
    }

    private static function expired($expires_in, $timestamp)
    {
        $ctimestamp = time();
        if (($ctimestamp - $timestamp) >= $expires_in) {
            return true;
        } else {
            return false;
        }
    }

    public function getToken($code = '', $json = false)
    {
        $url = $this->tokenUrl;
        if (!empty($this->refreshToken)) {
            $params = array('grant_type' => 'refresh_token', 'refresh_token' => $this->refreshToken, 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret);
        } else {
            $params = array('grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret);
        }
        if ($json) {
            return $this->post($url, $params);
        } else {
            return json_decode($this->post($url, $params), true);
        }
    }

    public function writeToken($token, $type = 'file')
    {
        $array = json_decode($token, true);
        if (isset($array['error'])) {
            $this->error = $array['error_description'];
            return false;
        } else {
            $array['timestamp'] = time();
            if ($type == 'file') {
                $fp = fopen($this->tokenStoragePath . 'token.box', 'w');
                fwrite($fp, json_encode($array));
                fclose($fp);
            }
            return true;
        }
    }

    public function getPreviewLink($fileId)
    {
        $url = $this->buildUrl('/files/' . $fileId, ['fields' => 'expiring_embed_link']);

        $result = json_decode($this->get($url), true);

        if (array_key_exists('expiring_embed_link', $result) && array_key_exists('url', $result['expiring_embed_link'])) {
            return $result['expiring_embed_link']['url'];
        } else {
            return '';
        }
    }

    /**
     * @param $fileId
     * @param string $extension
     * @param int $minHeight
     * @param int $maxHeight
     * @param int $minWidth
     * @param int $maxWidth
     * @return mixed
     */
    public function getThumbnail($fileId, $extension = 'png', $minHeight = null, $maxHeight = null, $minWidth = null, $maxWidth = null)
    {
        $urlParams = [];
        if ($minHeight !== null) {
            $urlParams['min_height'] = $minHeight;
        }

        if ($maxHeight !== null) {
            $urlParams['max_height'] = $maxHeight;
        }

        if ($minWidth !== null) {
            $urlParams['min_width'] = $minWidth;
        }

        if ($maxWidth !== null) {
            $urlParams['max_width'] = $maxWidth;
        }

        $url = $this->buildUrl('/files/' . $fileId . '/thumbnail.' . $extension, $urlParams);

        /**
         * - thumbnail is not yet available -> status code 202 and placeholder in Location-header
         * - can't generate thumbnail for this file type -> status code 302 and redirection to the placeholder
         * - thumbnail is available -> status 200
         */

        return $this->get($url, true);
    }

    public function downloadFile($fileId, $directData = true)
    {
        $url = $this->buildUrl('/files/' . $fileId . '/content');

        /**
         * - if everything is valid, box redirects to the actual file
         * - 202 code in case of the file not being available for now, but later on
         */

        if ($directData) {
            return $this->get($url, true);
        } else {
            $ch = curl_init();
            $downloadLink = null;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curlRes, $headerData) use (&$downloadLink) {
                if (substr($headerData, 0, 9) == 'Location:') {
                    $downloadLink = substr($headerData, 10);
                }

                return strlen($headerData);
            });

            curl_exec($ch);
            curl_close($ch);

            return $downloadLink;
        }
    }
    
    /* Create a Collaboration */
    public function createCollaboration($params)
    {
        $url = $this->buildUrl("/collaborations");
        return json_decode($this->post($url, json_encode($params)), true);
    }

    /* Get Shared Items */
    public function getSharedItems($link)
    {
        $url = $this->buildUrl('/shared_items');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["BoxApi: shared_link=".$link]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        $data = curl_exec($ch);
        curl_close($ch);

        return json_decode($data);
    }
    /*Copy File */
    public function copyFile($file,$params,$link)
    {
        $url = $this->buildUrl("/files/".$file."/copy");
        return json_decode($this->post($url, json_encode($params),["BoxApi: shared_link=".$link]), true);
    }

}
