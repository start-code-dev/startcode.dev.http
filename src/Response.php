<?php

namespace Startcode\Http;

class Response
{
    protected array $_body = array();
    protected array $_exceptions = array();
    protected array $_headers = array();
    protected array $_headersRaw = array();
    protected int $_httpResponseCode = 200;
    protected bool $_isRedirect = false;
    protected bool $_renderExceptions = false;
    protected bool $_headersSentThrowsException = true;

    protected function _normalizeHeader(string $name) : string
    {
        $filtered = str_replace(array('-', '_'), ' ', (string) $name);
        $filtered = ucwords(strtolower($filtered));
        $filtered = str_replace(' ', '-', $filtered);
        return $filtered;
    }

    public function setHeader(string $name, string $value, bool $replace = false) : self
    {
        $this->canSendHeaders(true);
        $name  = $this->_normalizeHeader($name);
        $value = (string) $value;

        if ($replace) {
            foreach ($this->_headers as $key => $header) {
                if ($name == $header['name']) {
                    unset($this->_headers[$key]);
                }
            }
        }

        $this->_headers[] = array(
            'name'        => $name,
            'value'       => $value,
            'replace'     => $replace
        );

        return $this;
    }

    public function setRedirect(string $url, int $code = 302) : self
    {
        $this->canSendHeaders(true);
        $this->setHeader('Location', $url, true)
             ->setHttpResponseCode($code);

        return $this;
    }

    public function isRedirect() : bool
    {
        return $this->_isRedirect;
    }

    public function getHeaders() : array
    {
        return $this->_headers;
    }

    public function clearHeaders() : self
    {
        $this->_headers = array();

        return $this;
    }

    public function setRawHeader(string $value) : self
    {
        $this->canSendHeaders(true);
        if ('Location' == substr($value, 0, 8)) {
            $this->_isRedirect = true;
        }
        $this->_headersRaw[] = (string) $value;
        return $this;
    }

    public function getRawHeaders() : array
    {
        return $this->_headersRaw;
    }

    public function clearRawHeaders() : self
    {
        $this->_headersRaw = array();
        return $this;
    }

    public function clearAllHeaders() : self
    {
        return $this
            ->clearHeaders()
            ->clearRawHeaders();
    }

    public function setHttpResponseCode(int $code) : self
    {
        if (!is_int($code) || (100 > $code) || (599 < $code)) {
            throw new \Exception('Invalid HTTP response code');
        }

        $this->_isRedirect = (300 <= $code) && (307 >= $code);
        $this->_httpResponseCode = $code;
        return $this;
    }

    public function getHttpResponseCode() : int
    {
        return $this->_httpResponseCode;
    }

    /**
     * @throws \Exception
     */
    public function canSendHeaders(bool $throw = false) : bool
    {
        $ok = headers_sent($file, $line);
        if ($ok && $throw && $this->_headersSentThrowsException) {
            throw new \Exception("Cannot send headers; headers already sent in {$file}, line {$line}");
        }

        return !$ok;
    }

    public function sendHeaders() : self
    {
        // Only check if we can send headers if we have headers to send
        if (count($this->_headersRaw) || count($this->_headers) || (200 != $this->_httpResponseCode)) {
            $this->canSendHeaders(true);
        } elseif (200 == $this->_httpResponseCode) {
            // Haven't changed the response code, and we have no headers
            return $this;
        }

        $httpCodeSent = false;

        foreach ($this->_headersRaw as $header) {
            if (!$httpCodeSent && $this->_httpResponseCode) {
                header($header, true, $this->_httpResponseCode);
                $httpCodeSent = true;
            } else {
                header($header);
            }
        }

        foreach ($this->_headers as $header) {
            if (!$httpCodeSent && $this->_httpResponseCode) {
                header($header['name'] . ': ' . $header['value'], $header['replace'], $this->_httpResponseCode);
                $httpCodeSent = true;
            } else {
                header($header['name'] . ': ' . $header['value'], $header['replace']);
            }
        }

        if (!$httpCodeSent) {
            header('HTTP/1.1 ' . $this->_httpResponseCode);
            $httpCodeSent = true;
        }

        return $this;
    }

    public function setBody(string $content, string $name = null) : self
    {
        if ((null === $name) || !is_string($name)) {
            $this->_body = array('default' => $content);
        } else {
            $this->_body[$name] = $content;
        }

        return $this;
    }

    public function appendBody(string $content, string $name = null) : self
    {
        if ((null === $name) || !is_string($name)) {
            if (isset($this->_body['default'])) {
                $this->_body['default'] .= (string) $content;
            } else {
                return $this->append('default', $content);
            }
        } elseif (isset($this->_body[$name])) {
            $this->_body[$name] .= (string) $content;
        } else {
            return $this->append($name, $content);
        }

        return $this;
    }

    public function clearBody(string $name = null) : bool
    {
        if (null !== $name) {
            $name = (string) $name;
            if (isset($this->_body[$name])) {
                unset($this->_body[$name]);
                return true;
            }

            return false;
        }

        $this->_body = array();
        return true;
    }

    /**
     * @return string|array|null
     */
    public function getBody(bool $spec = false)
    {
        if (false === $spec) {
            ob_start();
            $this->outputBody();
            return ob_get_clean();
        } elseif (true === $spec) {
            return $this->_body;
        } elseif (is_string($spec) && isset($this->_body[$spec])) {
            return $this->_body[$spec];
        }

        return null;
    }

    public function append(string $name, string $content) : self
    {
        if (!is_string($name)) {
            throw new \Exception('Invalid body segment key ("' . gettype($name) . '")');
        }

        if (isset($this->_body[$name])) {
            unset($this->_body[$name]);
        }

        $this->_body[$name] = $content;
        return $this;
    }

    public function prepend(string $name, string $content) : self
    {
        if (!is_string($name))     {
            throw new \Exception('Invalid body segment key ("' . gettype($name) . '")');
        }

        if (isset($this->_body[$name])) {
            unset($this->_body[$name]);
        }

        $new = array($name => $content);
        $this->_body = $new + $this->_body;

        return $this;
    }

    public function insert(string $name, string $content, string $parent = null, bool $before = false) : self
    {
        if (!is_string($name)) {
            throw new \Exception('Invalid body segment key ("' . gettype($name) . '")');
        }

        if ((null !== $parent) && !is_string($parent)) {
            throw new \Exception('Invalid body segment parent key ("' . gettype($parent) . '")');
        }

        if (isset($this->_body[$name])) {
            unset($this->_body[$name]);
        }

        if ((null === $parent) || !isset($this->_body[$parent])) {
            return $this->append($name, $content);
        }

        $ins  = array($name => (string) $content);
        $keys = array_keys($this->_body);
        $loc  = array_search($parent, $keys);
        if (!$before) {
            // Increment location if not inserting before
            ++$loc;
        }

        if (0 === $loc) {
            // If location of key is 0, we're prepending
            $this->_body = $ins + $this->_body;
        } elseif ($loc >= (count($this->_body))) {
            // If location of key is maximal, we're appending
            $this->_body = $this->_body + $ins;
        } else {
            // Otherwise, insert at location specified
            $pre  = array_slice($this->_body, 0, $loc);
            $post = array_slice($this->_body, $loc);
            $this->_body = $pre + $ins + $post;
        }

        return $this;
    }

    public function outputBody() : void
    {
        $body = implode('', $this->_body);
        echo $body;
    }

    public function setException(\Exception $e) : self
    {
        $this->_exceptions[] = $e;
        return $this;
    }

    public function getException() : array
    {
        return $this->_exceptions;
    }

    public function isException() : bool
    {
        return !empty($this->_exceptions);
    }

    public function hasExceptionOfType(string $type) : bool
    {
        foreach ($this->_exceptions as $e) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    public function hasExceptionOfMessage(string $message) : bool
    {
        foreach ($this->_exceptions as $e) {
            if ($message == $e->getMessage()) {
                return true;
            }
        }

        return false;
    }

    public function hasExceptionOfCode(int $code) : bool
    {
        $code = (int) $code;
        foreach ($this->_exceptions as $e) {
            if ($code == $e->getCode()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return false|array
     */
    public function getExceptionByType(string $type)
    {
        $exceptions = array();
        foreach ($this->_exceptions as $e) {
            if ($e instanceof $type) {
                $exceptions[] = $e;
            }
        }

        if (empty($exceptions)) {
            $exceptions = false;
        }

        return $exceptions;
    }

    /**
     * @return false|array
     */
    public function getExceptionByMessage(string $message)
    {
        $exceptions = array();
        foreach ($this->_exceptions as $e) {
            if ($message == $e->getMessage()) {
                $exceptions[] = $e;
            }
        }

        if (empty($exceptions)) {
            $exceptions = false;
        }

        return $exceptions;
    }

    /**
     * @param mixed $code
     * @return mixed
     */
    public function getExceptionByCode($code)
    {
        $code       = (int) $code;
        $exceptions = array();
        foreach ($this->_exceptions as $e) {
            if ($code == $e->getCode()) {
                $exceptions[] = $e;
            }
        }

        if (empty($exceptions)) {
            $exceptions = false;
        }

        return $exceptions;
    }

    public function renderExceptions(bool $flag = null) : bool
    {
        if (null !== $flag)
        {
            $this->_renderExceptions = $flag ? true : false;
        }

        return $this->_renderExceptions;
    }

    public function sendResponse() : void
    {
        $this->sendHeaders();

        if ($this->isException() && $this->renderExceptions())
        {
            $exceptions = '';
            foreach ($this->getException() as $e)
            {
                $exceptions .= $e->__toString() . "\n";
            }
            echo $exceptions;
            return;
        }

        $this->outputBody();
    }

    public function __toString() : string
    {
        ob_start();
        $this->sendResponse();
        return ob_get_clean();
    }
}
