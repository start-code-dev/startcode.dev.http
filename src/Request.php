<?php

namespace Startcode\Http;

use Startcode\ValueObject\Uuid;

class Request
{
    const HTTP_X_ND_UUID = 'HTTP_X_ND_UUID';
    const SCHEME_HTTP = 'http';
    const SCHEME_HTTPS = 'https';

    protected array $_paramSources = array('_GET', '_POST');
    protected array $_params = array();
    protected ?string $_rawBody;

    public function __construct()
    {
        $this->parseRequestRawBody();
        $this->setRequestUuid();
        $this->appendParamsFromHeader();
    }

    private function parseRequestRawBody() : self
    {
        if ($this->isPut() || $this->isDelete()) {
            $raw = array();
            parse_str($this->getRawBody(), $raw);
            $this->setParams($raw);
        }
        return $this;
    }

    private function appendParamsFromHeader() : self
    {
        foreach ($this->getListOfHeaderKeys() as $key) {
            $this->setParam($key, $this->getHeader($key));
        }
        return $this;
    }

    private function setRequestUuid() : self
    {
        if (!$this->getHeader('X-ND-UUID')) {
            $_SERVER[self::HTTP_X_ND_UUID] = (string) Uuid::generate();
        }

        return $this;
    }

    private function getListOfHeaderKeys() : array
    {
        return [
            'X-ND-Authentication',
            'X-ND-AppKey',
            'X-ND-AppToken',
            'X-ND-UUID'
        ];
    }

    /**
     * @return mixed
     */
    public function __get(string $key)
    {
        switch (true) {
            case isset($this->_params[$key]):
                return $this->_params[$key];
            case isset($_GET[$key]):
                return $_GET[$key];
            case isset($_POST[$key]):
                return $_POST[$key];
            case isset($_COOKIE[$key]):
                return $_COOKIE[$key];
            case isset($_SERVER[$key]):
                return $_SERVER[$key];
            case isset($_ENV[$key]):
                return $_ENV[$key];
            default:
                return null;
        }
    }

    /**
     * @return mixed
     */
    public function get(string $key)
    {
        return $this->__get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @throws \Exception
     */
    public function __set(string $key, $value) : void
    {
        throw new \Exception('Setting values in superglobals not allowed; please use setParam()');
    }

    /**
     * @param string $key
     * @param mixed $value
     * @throws \Exception
     */
    public function set(string $key, $value)
    {
        return $this->__set($key, $value);
    }

    public function __isset(string $key) : bool
    {
        switch (true) {
            case isset($this->_params[$key]):
                return true;
            case isset($_GET[$key]):
                return true;
            case isset($_POST[$key]):
                return true;
            case isset($_COOKIE[$key]):
                return true;
            case isset($_SERVER[$key]):
                return true;
            case isset($_ENV[$key]):
                return true;
            default:
                return false;
        }
    }

    public function has(string $key) : bool
    {
        return $this->__isset($key);
    }

    /**
     * @param  string|array $spec
     * @param  null|mixed $value
     */
    public function setQuery($spec, $value = null) : self
    {
        if ((null === $value) && !is_array($spec)) {
            throw new \Exception('Invalid value passed to setQuery(); must be either array of values or key/value pair');
        }

        if ((null === $value) && is_array($spec)) {
            foreach ($spec as $key => $value) {
                $this->setQuery($key, $value);
            }
            return $this;
        }

        $_GET[(string) $spec] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getQuery(string $key = null, $default = null)
    {
        if (null === $key) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }

    /**
     * @param  string|array $spec
     * @param  null|mixed $value
     * @throws  \Exception
     */
    public function setPost($spec, $value = null) : self
    {
        if ((null === $value) && !is_array($spec)) {
            throw new \Exception('Invalid value passed to setPost(); must be either array of values or key/value pair');
        }

        if ((null === $value) && is_array($spec)) {
            foreach ($spec as $key => $value) {
                $this->setPost($key, $value);
            }
            return $this;
        }

        $_POST[(string) $spec] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getPost(string $key = null, $default = null)
    {
        if (null === $key) {
            return $_POST;
        }

        return $_POST[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getCookie(string $key = null, $default = null)
    {
        if (null === $key) {
            return $_COOKIE;
        }

        return $_COOKIE[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getServer(string $key = null, $default = null)
    {
        if (null === $key) {
            return $_SERVER;
        }

        return $_SERVER[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getEnv(string $key = null, $default = null)
    {
        if (null === $key) {
            return $_ENV;
        }

        return $_ENV[$key] ?? $default;
    }

    public function setParamSources(array $paramSources = array()) : self
    {
        $this->_paramSources = $paramSources;
        return $this;
    }

    public function getParamSources() : array
    {
        return $this->_paramSources;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function setParam($key, $value) : self
    {
        $key = (string) $key;

        if ((null === $value) && isset($this->_params[$key])) {
            unset($this->_params[$key]);
        } elseif (null !== $value) {
            $this->_params[$key] = $value;
        }

        return $this;
    }

    /**
     * @param mixed $key
     * @param mixed $default Default value to use if key not found
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        $paramSources = $this->getParamSources();

        if (isset($this->_params[$key])) {
            return $this->_params[$key];
        } elseif (in_array('_GET', $paramSources) && (isset($_GET[$key]))) {
            return $_GET[$key];
        } elseif (in_array('_POST', $paramSources) && (isset($_POST[$key]))) {
            return $_POST[$key];
        }

        return $default;
    }

    public function getParams() : array
    {
        $return    = $this->_params;
        $paramSources = $this->getParamSources();

        if (in_array('_GET', $paramSources)
            && isset($_GET)
            && is_array($_GET)
        ) {
            $return += $_GET;
        }

        if (in_array('_POST', $paramSources)
            && isset($_POST)
            && is_array($_POST)
        ) {
            $return += $_POST;
        }

        return $return;
    }

    public function setParams(array $params) : self
    {
        foreach ($params as $key => $value) {
            $this->setParam($key, $value);
        }
        return $this;
    }

    public function getMethod() : string
    {
        return $this->getServer('REQUEST_METHOD');
    }

    protected function isMethod($method) : bool
    {
        if(!is_scalar($method)) {
            // throw new InvalidArgumentException
            return false;
        }

        $allowed_methods = array('POST', 'GET', 'PUT', 'DELETE', 'HEAD', 'OPTIONS');

        // normalize argument name
        $method = strtoupper($method);

        return in_array($method, $allowed_methods)
            ? strtoupper($this->getMethod()) == $method
            : false;
    }

    public function isPost() : bool
    {
        return $this->isMethod('POST');
    }

    public function isGet() : bool
    {
        return $this->isMethod('GET');
    }

    public function isPut() : bool
    {
        return $this->isMethod('PUT');
    }

    public function isDelete() : bool
    {
        return $this->isMethod('DELETE');
    }

    public function isHead() : bool
    {
        return $this->isMethod('HEAD');
    }

    public function isOptions() : bool
    {
        return $this->isMethod('OPTIONS');
    }

    public function isCli() : bool
    {
        static $isCli = null;
        if(null !== $isCli) { return $isCli; }

        return $isCli = (php_sapi_name() === 'cli' && empty($_SERVER['REMOTE_ADDR']));
    }

    public function isAjax() : bool
    {
        static $isAjax = null;
        if(null !== $isAjax) { return $isAjax; }

        return $isAjax = ($this->getHeader('X_REQUESTED_WITH') === 'XMLHttpRequest');
    }

    public function isSecure() : bool
    {
        return ($this->getScheme() === self::SCHEME_HTTPS);
    }

    public function getRawBody() : ?string
    {
        if (null === $this->_rawBody) {
            $body = file_get_contents('php://input');

            if (strlen(trim($body)) > 0) {
                $this->_rawBody = $body;
            } else {
                $this->_rawBody = false;
            }
        }
        return $this->_rawBody;
    }

    /**
     * @param string $header HTTP header name
     * @return string|false HTTP header value, or false if not found
     * @throws \Exception
     */
    public function getHeader($header)
    {
        if (empty($header)) {
            throw new \Exception('An HTTP header name is required');
        }

        // Try to get it from the $_SERVER array first
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (!empty($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }

        // This seems to be the only way to get the Authorization header on Apache
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers[$header])) {
                return $headers[$header];
            }
        }

        return false;
    }

    public function getScheme() : string
    {
        return ($this->getServer('HTTPS') === 'on') ? self::SCHEME_HTTPS : self::SCHEME_HTTP;
    }

    public function getHttpHost() : string
    {
        $host = $this->getServer('HTTP_HOST');
        if (!empty($host)) {
            return $host;
        }

        $scheme = $this->getScheme();
        $name   = $this->getServer('SERVER_NAME');
        $port   = $this->getServer('SERVER_PORT');

        return ($scheme == self::SCHEME_HTTP && $port == 80) || ($scheme == self::SCHEME_HTTPS && $port == 443) ? $name : $name . ':' . $port;
    }

    public function clearParams() : self
    {
        $this->_params = array();
        return $this;
    }

}
