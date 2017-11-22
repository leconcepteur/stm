<?php

error_reporting(E_ALL);
ini_set('display_error', 'On');

class CurlApiCaller {

    const VERSION = '1.0';

    private $cookies = array();
    private $headers = array();
    private $options = array();
    private $debug = false;
    private $verbose;

    public $curl;
    public $apiBaseURL;

    public $curl_error = false;
    public $curl_error_code = 0;
    public $curl_error_message = null;

    public $response_headers = null;
    public $response = null;
    public $raw_response = null;

    public $json_decode_assoc = true;
    public $last_url = null;

    public $timeout = 30;

    public function __construct($debug = false)
    {
        if (!extension_loaded('curl')) {
            throw new ErrorException('cURL library is not loaded');
        }

        $this->debug = (bool)$debug;

        $this->curl = curl_init();

        if ($this->debug) {
            $this->setOpt(CURLOPT_VERBOSE, true);
            $this->verbose = fopen('php://temp', 'w+');
            $this->setOpt(CURLOPT_STDERR, $this->verbose);
        }

        $this->setDefaultUserAgent();
        $this->setOpt(CURLINFO_HEADER_OUT, true);
        $this->setOpt(CURLOPT_HEADER, true);
        $this->setOpt(CURLOPT_RETURNTRANSFER, true);

        $this->setOpt(CURLOPT_CONNECTTIMEOUT, 5); // 5 seconds
        $this->setOpt(CURLOPT_TIMEOUT, $this->timeout);
    }

    public function get($service, $data = array())
    {
        $this->setopt(CURLOPT_URL, $this->buildURL($service, $data));
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'GET');
        $this->setopt(CURLOPT_HTTPGET, true);

        return $this->exec();
    }

    public function post($service, $data_mixed)
    {
        if (is_array($data_mixed)) {
            if(empty($data_mixed)) {
                $this->unsetHeader('Content-Length');
            }
        } elseif(is_string($data_mixed)) {
            /*$this->setOpt(CURLOPT_HTTPHEADER, array(  
                'Content-Type: application/json',  
                'Content-Length: ' . strlen($data_mixed)
                )  
            );*/
            $this->setHeader('Content-Type', 'application/json');
            $this->setHeader('Content-Length', strlen($data_mixed));
        }

        $this->setOpt(CURLOPT_URL, $this->buildURL($service));
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'POST');
        $this->setOpt(CURLOPT_POST, true);
        $this->setOpt(CURLOPT_POSTFIELDS, $data_mixed);

        return $this->exec();
    }

    public function put($service, $data_mixed)
    {
        if (is_array($data_mixed)) {
            if(empty($data_mixed)) {
                $this->unsetHeader('Content-Length');
            }
        } elseif(is_string($data_mixed)) {
            /*$this->setOpt(CURLOPT_HTTPHEADER, array(  
                'Content-Type: application/json',  
                'Content-Length: ' . strlen($data_mixed)
                )  
            );*/
            $this->setHeader('Content-Type', 'application/json');
            $this->setHeader('Content-Length', strlen($data_mixed));
        }

        $this->setOpt(CURLOPT_URL, $this->buildURL($service));
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'PUT');
        $this->setOpt(CURLOPT_POSTFIELDS, $data_mixed);

        return $this->exec();
    }

    public function patch($service, $data_mixed)
    {
        $this->unsetHeader('Content-Length');
        $this->setOpt(CURLOPT_URL, $this->buildURL($service));
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'PATCH');
        $this->setOpt(CURLOPT_POSTFIELDS, $data_mixed);
        return $this->exec();
    }

    public function delete($url, $data = array())
    {
        $this->unsetHeader('Content-Length');
        $this->setOpt(CURLOPT_URL, $this->buildURL($url, $data));
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'DELETE');
        return $this->exec();
    }

    public function head($service, $data = array())
    {
        $this->setOpt(CURLOPT_URL, $this->buildURL($service, $data));
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'HEAD');
        $this->setOpt(CURLOPT_NOBODY, true);
        return $this->exec();
    }

    public function options($service, $data = array())
    {
        $this->unsetHeader('Content-Length');
        $this->setOpt(CURLOPT_URL, $this->buildURL($service, $data));
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        return $this->exec();
    }

    public function setApiBaseURL($apiBaseURL)
    {
        $this->apiBaseURL = preg_replace("/(\/+)$/", "", $apiBaseURL);
    }

    public function setBasicAuthentication($username, $password = '')
    {
        $this->setOpt(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $userpwd = $username;
        if(strlen($password) > 0) {
            $userpwd.= ':' . $password;
        }

        $this->setOpt(CURLOPT_USERPWD, $userpwd);
    }

    public function setHeader($key, $value)
    {
        $this->headers[$key] = $key . ': ' . $value;
        $this->setOpt(CURLOPT_HTTPHEADER, array_values($this->headers));
    }

    public function unsetHeader($key)
    {
        $this->setHeader($key, '');
        unset($this->headers[$key]);
    }

    public function setDefaultUserAgent()
    {
        $user_agent = 'HE/Gateway CurlApiCaller/' . self::VERSION;
        $user_agent .= ' PHP/' . PHP_VERSION;
        $curl_version = curl_version();
        $user_agent .= ' curl/' . $curl_version['version'];
        $this->setUserAgent($user_agent);
    }

    public function setUserAgent($user_agent)
    {
        $this->setOpt(CURLOPT_USERAGENT, $user_agent);
    }

    public function setReferrer($referrer)
    {
        $this->setOpt(CURLOPT_REFERER, $referrer);
    }

    public function setCookie($key, $value)
    {
        $this->cookies[$key] = $value;
        $this->setOpt(CURLOPT_COOKIE, http_build_query($this->cookies, '', '; ', PHP_QUERY_RFC3986));
    }

    public function setCookieFile($cookie_file)
    {
        $this->setOpt(CURLOPT_COOKIEFILE, $cookie_file);
    }

    public function setCookieJar($cookie_jar)
    {
        $this->setOpt(CURLOPT_COOKIEJAR, $cookie_jar);
    }

    public function setOpt($option, $value, $_ch = null)
    {
        $ch = is_null($_ch) ? $this->curl : $_ch;

        $required_options = array(
            CURLINFO_HEADER_OUT    => 'CURLINFO_HEADER_OUT',
            CURLOPT_HEADER         => 'CURLOPT_HEADER',
            CURLOPT_RETURNTRANSFER => 'CURLOPT_RETURNTRANSFER',
        );

        if (in_array($option, array_keys($required_options), true) && !($value === true)) {
            trigger_error($required_options[$option] . ' is a required option', E_USER_WARNING);
        }

        $this->options[$option] = $value;
        return curl_setopt($ch, $option, $value);
    }

    public function close()
    {
        if (is_resource($this->curl)) {
            if ($this->debug) {
                if ($this->raw_response === FALSE) {
                    printf("cUrl error (#%d): %s<br>\n", curl_errno($this->curl),
                           htmlspecialchars(curl_error($this->curl)));
                }

                rewind($this->verbose);
                $verboseLog = stream_get_contents($this->verbose);

                echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
                curl_close($this->curl);
                die();
            }

            curl_close($this->curl);
        }
    }

    protected function exec($_ch = null)
    {
        $ch = is_null($_ch) ? $this : $_ch;

        $ch->raw_response = curl_exec($ch->curl);

        if ($this->debug) {
            var_dump($ch->raw_response);
            echo "<br /><br /><pre>";
            print_r($ch->raw_response);
            echo "</pre><br /><br />";
        }

        $ch->curl_error_code = curl_errno($ch->curl);

        $ch->curl_error_message = curl_error($ch->curl);
        $ch->curl_error = !($ch->curl_error_code === 0);
        $ch->http_status_code = curl_getinfo($ch->curl, CURLINFO_HTTP_CODE);
        $ch->http_error = in_array(floor($ch->http_status_code / 100), array(4, 5));
        $ch->error = $ch->curl_error || $ch->http_error;
        $ch->error_code = $ch->error ? ($ch->curl_error ? $ch->curl_error_code : $ch->http_status_code) : 0;

        list($ch->response_headers, $ch->response, $ch->raw_response) = $this->parseResponse($ch->raw_response);

        $ch->http_error_message = '';
        if ($ch->error) {
            if (isset($ch->response_headers['Status-Line'])) {
                $ch->http_error_message = $ch->response_headers['Status-Line'];
            }
        }
        $ch->error_message = $ch->curl_error ? $ch->curl_error_message : $ch->http_error_message;

        return $ch->response;
    }

    private function buildURL($service, $data = array())
    {
        // Remove / in front of service if any
        $service = preg_replace("/^(\/+)/", "", $service);
        $this->last_url = $this->apiBaseURL . '/' . $service . (empty($data) ? '' : '?' . http_build_query($data));

        return $this->last_url;
    }

    private function parseResponse($response)
    {
        $response_headers = '';
        $raw_response = $response;
        if (!(strpos($response, "\r\n\r\n") === false)) {
            $response_array = explode("\r\n\r\n", $response);
            for ($i = count($response_array) - 1; $i >= 0; $i--) {
                if (stripos($response_array[$i], 'HTTP/') === 0) {
                    $response_header = $response_array[$i];
                    $response = implode("\r\n\r\n", array_splice($response_array, $i + 1));
                    break;
                }
            }
            $response_headers = explode("\r\n", $response_header);
            if (in_array('HTTP/1.1 100 Continue', $response_headers)) {
                list($response_header, $response) = explode("\r\n\r\n", $response, 2);
            }
            $response_headers = $this->parseResponseHeaders($response_header);
            $raw_response = $response;

            if (isset($response_headers['Content-Type'])) {
                if (preg_match('/^application\/json/i', $response_headers['Content-Type'])) {
                    $json_obj = json_decode($response, $this->json_decode_assoc);
                    if (!is_null($json_obj)) {
                        $response = $json_obj;
                    }
                } elseif (preg_match('/^application\/atom\+xml/i', $response_headers['Content-Type']) ||
                          preg_match('/^application\/rss\+xml/i', $response_headers['Content-Type']) ||
                          preg_match('/^application\/xml/i', $response_headers['Content-Type']) ||
                          preg_match('/^text\/xml/i', $response_headers['Content-Type'])) {
                    $xml_obj = @simplexml_load_string($response);
                    if (!($xml_obj === false)) {
                        $response = $xml_obj;
                    }
                }
            }
        }

        return array($response_headers, $response, $raw_response);
    }

    private function parseHeaders($raw_headers)
    {
        $raw_headers = preg_split('/\r\n/', $raw_headers, null, PREG_SPLIT_NO_EMPTY);
        $http_headers = new CaseInsensitiveArray();

        for ($i = 1; $i < count($raw_headers); $i++) {
            list($key, $value) = explode(':', $raw_headers[$i], 2);
            $key = trim($key);
            $value = trim($value);
            // Use isset() as array_key_exists() and ArrayAccess are not compatible.
            if (isset($http_headers[$key])) {
                $http_headers[$key] .= ',' . $value;
            } else {
                $http_headers[$key] = $value;
            }
        }

        return array(isset($raw_headers['0']) ? $raw_headers['0'] : '', $http_headers);
    }

    private function parseResponseHeaders($raw_headers)
    {
        $response_headers = new CaseInsensitiveArray();
        list($first_line, $headers) = $this->parseHeaders($raw_headers);
        $response_headers['Status-Line'] = $first_line;
        foreach ($headers as $key => $value) {
            $response_headers[$key] = $value;
        }
        return $response_headers;
    }
}

class CaseInsensitiveArray implements ArrayAccess, Countable, Iterator
{
    private $container = array();

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $index = array_search(strtolower($offset), array_keys(array_change_key_case($this->container, CASE_LOWER)));
            if (!($index === false)) {
                $keys = array_keys($this->container);
                unset($this->container[$keys[$index]]);
            }
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return array_key_exists(strtolower($offset), array_change_key_case($this->container, CASE_LOWER));
    }

    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    public function offsetGet($offset)
    {
        $index = array_search(strtolower($offset), array_keys(array_change_key_case($this->container, CASE_LOWER)));
        if ($index === false) {
            return null;
        }

        $values = array_values($this->container);
        return $values[$index];
    }

    public function count()
    {
        return count($this->container);
    }

    public function current()
    {
        return current($this->container);
    }

    public function next()
    {
        return next($this->container);
    }

    public function key()
    {
        return key($this->container);
    }

    public function valid()
    {
        return !($this->current() === false);
    }

    public function rewind()
    {
        reset($this->container);
    }
}

$settings = array();
$settings['base_url'] = 'https://api.stm.info/pub/i3/v1c/api/fr/';
// $settings['base_url'] = 'https://mapi.uatsimons.ca/MobileStoreService/rest/v1';
// $settings['username'] = 'mobileclient';
// $settings['password'] = 'g00dRe5t';

$apiCaller = new CurlApiCaller(true);
$apiCaller->setApiBaseURL($settings['base_url']);
// $apiCaller->setBasicAuthentication($settings['username'], $settings['password']);
// $apiCaller->setHeader('Accept-Language', 'en');
$apiCaller->setHeader('Host', 'api.stm.info');
$apiCaller->setHeader('Origin', 'http://beta.stm.info');
$apiCaller->setHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
$apiCaller->setHeader('Accept-Encoding', 'gzip, deflate, br');
$apiCaller->setHeader('Accept-Language', 'en-US,en;q=0.9,fr;q=0.8');
$apiCaller->setHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.94 Safari/537.36');

$apiCaller->setOpt(CURLOPT_ENCODING, "gzip");

// Disable SSL verification
$apiCaller->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
$apiCaller->setOpt(CURLOPT_SSL_VERIFYPEER, false);

$response = $apiCaller->get('lines/search', array('q' => 36, 'o' => 'web', '_' => round(microtime(true) * 1000)));
$apiCaller->close();
