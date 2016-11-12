
# grab-spider

PHP async scrapper used multi curl and reactphp inspired by python grab


## Installation

To install grab-spider run the command:

```bash

composer require grab/spider "dev-master" 
    
```

## Quick start

```php    
<?php

require __DIR__ . '/../vendor/autoload.php';

class HackerNewCrawler extends \Grab\Spider
{
    public function taskGenerator()
    {
        $range = array_map(function($item) {
            return sprintf('https://news.ycombinator.com/news?p=%d', $item);
        }, range(1, 4)) ;

        foreach ($range as $url) {
            $this->task('page', [
                'url' => $url,
                'max_request' => 10,
            ]);
        }
    }

    public function taskPage($parser, $task)
    {
        $links = $parser->find('.storylink');
        foreach ($links as $link) {
            $this->task('topic', [
                'url' => $link->getAttribute('href'),
                'curl_config' => [
                    CURLOPT_TIMEOUT => 60,
                ],
                'max_request' => 10,
            ]);
        }
    }

    public function taskTopic($parser, $task)
    {
        $products = $parser->find('title');
        echo trim($products[0]->text()) . PHP_EOL;
    }
}

$bot = new HackerNewCrawler();
$bot->debug = true;
$bot->setCurlSetting([
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
]);
//$bot->loadProxy(__DIR__ . '/proxy_list.txt');
$bot->run();
```
