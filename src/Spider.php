<?php

namespace Grab;

class Spider
{
    private $loop;
    private $curl;
    private $parser;
    public $debug = false;

    public function __construct()
    {
        $this->loop = \React\EventLoop\Factory::create();
        $this->curl = new \KHR\React\Curl\Curl($this->loop);
        $this->parser = new \DiDom\Document();
    }

    /**
     * Run Spider Loop
     */
    public function run()
    {
        // DEBUG MODE
        if ($this->debug) {
            $start = microtime(true);
        }

        // AUTO RUN FIRST METHOD
        $firstMethod = get_class_methods($this)[0];
        if ($firstMethod === 'run' || strrpos($firstMethod, 'task') !== 0) {
            throw new \InvalidArgumentException('You must define a method whose name begins with task');
        }
        call_user_func([$this, $firstMethod]);

        $this->curl->run();
        $this->loop->run();

        // DEBUG MODE
        if ($this->debug) {
            $memory = round(memory_get_usage(true) / 1024, 2);
            $time = microtime(true) - $start;
            printf('Memory usage: %s, Execution time %.4F sek.' . PHP_EOL, $memory, $time);
        }
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
        $option = array_merge($option, [
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => false,
        ]);
        $this->curl->client->setCurlOption($option);
    }

    /**
     * @param $taskName
     * @param array $params
     * @return mixed
     */
    public function task($taskName, $params = [])
    {
        $taskName = 'task' . ucfirst($taskName);

        // SETTING
        if (isset($params['curl_config'])) {
            $this->setMaxRequest($params['curl_config']);
        }
        if (isset($params['max_request'])) {
            $this->curl->client->setMaxRequest($params['max_request']);
        }
        if (isset($params['sleep'])) {
            if (count($params['sleep']) !== 3) {
                throw new \InvalidArgumentException('Params sleep setting must 3 attribute');
            }
            list($next, $second, $blocking) = $params['sleep'];
            $this->curl->client->setSleep($next, $second, $blocking);
        }

        // CURL
        if (isset($params['url'])) {
            $url = $params['url'];
            return $this->curl->get($url)->then(
                function ($result) use ($taskName, $params) {
                    return $this->$taskName($this->parser->loadHtml((string)$result), (object)$params);
                },
                function ($exception) use ($url) {
                    echo "Throw Exception : {$exception->getMessage()} on url: {$url} \n";
                }
            );
        }

        //CALLBACK
        return $this->$taskName($this->parser, (object)$params);
    }

    /**
     * Generator Url Range
     * @param string $format
     * @param int $deep
     * @param int $start
     * @return \Generator
     */
    public function genRange($format, $deep, $start = 1)
    {
        if ($start < $deep) {
            if ($start <= 0) {
                throw new \LogicException('$start must be > then $deep');
            }
            for ($i = $start; $i <= $deep; $i += $start) {
                yield sprintf($format, $i);
            }
        } else {
            if ($deep >= 0) {
                throw new \LogicException('$deep must be > 0');
            }
            for ($i = $start; $i >= $deep; $i += $start) {
                yield sprintf($format, $i);
            }
        }
    }
}
