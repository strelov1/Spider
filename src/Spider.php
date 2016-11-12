<?php

namespace Grab;

class Spider
{
    private $loop;
    private $curl;
    private $parser;
    private $running = false;
    private $cookieFile;

    public $proxies = [];
    private $validProxies = [];
    private $proxyTimeout;
    private $proxyMaxRequest;

    public $debug = false;
    public $saveCookie = false;
    public $baseUrl;

    const GET = 'get';
    const POST = 'post';

    /**
     * Run Spider Loop
     */
    public function run()
    {
        // DEBUG MODE
        if ($this->debug) {
            $start = microtime(true);
        }

        if ($this->saveCookie) {
            $this->cookieFile = tempnam('/tmp', 'CURLCOOKIE');
        }

        if ($this->proxies) {
            $this->proxyCheck();
        } else {
            $this->callFirstTask();
        }

        $this->curl->run();
        $this->loop->run();

        // DEBUG MODE
        if ($this->debug) {
            $memory = round(memory_get_usage(true) / 1024, 2);
            $time = microtime(true) - $start;
            printf('Memory usage: %s, Execution time %.4F sek.' . PHP_EOL, $memory, $time);
        }
    }

    public function __construct()
    {
        $this->loop = \React\EventLoop\Factory::create();
        $this->curl = new \KHR\React\Curl\Curl($this->loop);
        $this->parser = new \DiDom\Document();
    }

    /**
     * Run First Method
     */
    public function callFirstTask()
    {
        $firstMethod = get_class_methods($this)[0];
        if (strrpos($firstMethod, 'task') !== 0) {
            throw new \InvalidArgumentException('You must define a method whose name begins with task');
        }
        $this->running = true;
        call_user_func([$this, $firstMethod]);
    }

    /**
     * @param $taskName
     * @param array $params
     * @return mixed
     */
    public function task($taskName, array $params = [])
    {
        $taskName = 'task' . ucfirst($taskName);

        if (isset($params['url'])) {
            $opts = [];

            //OPTS
            if (isset($params['curl_config'])) {
                $opts = array_merge($params['curl_config'], $opts);
            }

            //URL
            $opts[CURLOPT_URL] = $params['url'];

            //COOKIES
            if ($this->saveCookie) {
                $opts[CURLOPT_COOKIEJAR] = $this->cookieFile;
                $opts[CURLOPT_COOKIEFILE] = $this->cookieFile;
            }

            //MAX_REQUEST
            if (isset($params['max_request'])) {
                $this->curl->client->setMaxRequest($params['max_request']);
            }

            //SLEEP
            if (isset($params['sleep'])) {
                if (count($params['sleep']) !== 3) {
                    throw new \InvalidArgumentException('Params sleep setting must 3 attribute');
                }
                list($next, $second, $blocking) = $params['sleep'];
                $this->curl->client->setSleep($next, $second, $blocking);
            }

            //PROXY
            if ($this->proxies) {
                if (!$this->validProxies) {
                    exit('Not valid proxy' . PHP_EOL);
                }
                if ($this->debug) {
                    echo 'Use proxy ' . $this->getCurrentProxy() . PHP_EOL;
                }
                $opts[CURLOPT_PROXY] = $this->getCurrentProxy();
            }

            //METHOD
            if (isset($params['method'])) {
                if (!in_array($params['method'], [self::GET, self::POST], true)) {
                    throw new \InvalidArgumentException('Not allow metod');
                }
                if ($params['method'] === self::POST) {
                    $opts[CURLOPT_POST] = true;
                    if ($params['post_data']) {
                        if (is_array($params['post_data'])) {
                            $opts[CURLOPT_POSTFIELDS] = http_build_query($params['post_data']);
                        }
                        $opts[CURLOPT_POSTFIELDS] = $params['post_data'];
                    }
                }
            }

            //CURL
            return $this->curl->add($opts)->then(
                function ($result) use ($taskName, $params, $opts) {
                    return $this->taskSuccess($result, $opts, $taskName, $params);

                },
                function($exception) use ($taskName, $params, $opts) {
                    return $this->taskException($exception, $opts, $taskName, $params);
                }
            );
        }

        //CALLBACK
        return $this->$taskName($this->parser, $params);
    }

    /**
     * @param $result
     * @param $taskName
     * @param $opts
     * @param $params
     * @return mixed
     */
    public function taskSuccess($result, $opts, $taskName, $params)
    {
        $content = $result->getBody();
        if (!$content) {
            if ($this->debug) {
                echo 'No content from url ' . $result->getOptions()[CURLOPT_URL] . PHP_EOL;
            }
            return false;
        }
        $parser = $this->parser->load($content);
        return $this->$taskName($parser, $params, $content);
    }

    /**
     * @param $exception
     * @param $opts
     * @param $taskName
     * @param $params
     * @return mixed
     */
    public function taskException($exception, $opts, $taskName, $params)
    {
        if ($this->debug) {
            echo 'Throw Exception: ' . $exception->getMessage() . ' on url: ' . $opts[CURLOPT_URL] . PHP_EOL;
            if ($this->proxies) {
                echo 'Exception use proxy: ' . $opts[CURLOPT_PROXY] . PHP_EOL;
                array_shift($this->validProxies);
                $this->returnFailedProxyTask($taskName, $params, $opts);
            }
        }
    }

    /**
     * @param $taskName
     * @param $params
     * @param $opts
     */
    public function returnFailedProxyTask($taskName, $params, $opts)
    {
        if (!$this->validProxies) {
            exit('Not valid proxy' . PHP_EOL);
        }
        if ($this->debug) {
            echo 'Change proxy ' . $this->getCurrentProxy() . PHP_EOL;
        }
        $opts[CURLOPT_PROXY] = $this->getCurrentProxy();
        $this->curl->add($opts)->then(
            function ($result) use ($taskName, $params, $opts) {
                return $this->taskSuccess($result, $opts, $taskName, $params);

            },
            function($exception) use ($taskName, $params, $opts) {
                return $this->taskException($exception, $opts, $taskName, $params);
            }
        );
    }

    /**
     * Upload proxy list
     * @param $proxyList
     * @return bool
     */
    public function loadProxy($proxyList)
    {
        if (is_array($proxyList)) {
            $this->proxies = $proxyList;
            return true;
        }
        if (file_exists($proxyList)) {
            return $this->loadProxyFile($proxyList);
        }
        if (filter_var($proxyList, FILTER_VALIDATE_URL)) {
            return $this->loadProxyUrl($proxyList);
        }
    }

    /**
     * @param $file
     * @return bool
     */
    public function loadProxyFile($file)
    {
        if (file_exists($file)) {
            $proxyFile = file_get_contents($file);
            return $this->loadProxyString($proxyFile);
        }
    }

    /**
     * @param $url
     * @return bool
     */
    public function loadProxyUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $proxyFile = file_get_contents($url);
            return $this->loadProxyString($proxyFile);
        }
    }

    /**
     * @param $proxyString
     * @return bool
     */
    public function loadProxyString($proxyString)
    {
        $this->proxies = explode("\n", $proxyString);
        return true;
    }

    /**
     * @return mixed
     */
    public function getCurrentProxy()
    {
        return $this->validProxies[0];
    }

    /**
     * Check proxy before run
     */
    public function proxyCheck()
    {
        if (!$this->proxies) {
            echo 'Not proxy' . PHP_EOL;
        }
        $this->curl->client->setMaxRequest($this->proxyMaxRequest ?: 10);
        foreach ($this->proxies as $proxy) {
            $opts = [
                CURLOPT_URL => $this->baseUrl ?: 'http://google.com/',
                CURLOPT_PROXY => $proxy,
                CURLOPT_CONNECTTIMEOUT => $this->proxyTimeout ?: 10,
                CURLOPT_TIMEOUT => $this->proxyTimeout ?: 10,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
            ];
            $this->curl->add($opts)->then(
                function($result) use ($opts) {
                    return $this->proxyCheckSuccess($result, $opts);
                },
                function($exception) use ($opts) {
                    return $this->proxyCheckException($exception, $opts);
                }
            );
        }
    }

    /**
     * Success Callback from proxyCheck
     * @param $result
     * @param $opts
     */
    public function proxyCheckSuccess($result, $opts)
    {
        $this->validProxies[] = $opts[CURLOPT_PROXY];
        if ($this->debug) {
            echo 'Add valid proxy ' . $opts[CURLOPT_PROXY] . PHP_EOL;
        }
        if (!$this->running) {
            if ($this->debug) {
                echo 'Running first task' . PHP_EOL;
            }
            //Run first task
            $this->callFirstTask();
        }
    }

    /**
     * Exception Callback from proxyCheck
     * @param $exception
     * @param $opts
     */
    public function proxyCheckException($exception, $opts)
    {
        if ($this->debug) {
            echo 'Throw Exception: ' . $exception->getMessage() . ' on check proxy ' . $opts[CURLOPT_PROXY] . PHP_EOL;
        }
    }

    /**
     * @param $time
     */
    public function setProxyTimeout($time)
    {
        $this->proxyTimeout = $time;
    }

    /**
     * @param $max
     */
    public function setProxyMaxRequest($max)
    {
        $this->proxyMaxRequest = $max;
    }

    /**
     * @param int $max
     */
    public function setMaxRequest($max)
    {
        $this->curl->client->setMaxRequest($max);
    }

    /**
     * @param int $next
     * @param float $second
     * @param bool $blocking
     */
    public function setSleep($next, $second = 1.0, $blocking = true)
    {
        $this->curl->client->setSleep($next, $second, $blocking);
    }

    /**
     * @param array $option
     */
    public function setCurlSetting(array $option = [])
    {
        $this->curl->client->setCurlOption($option);
    }
}
