
# grab-spider

PHP async scrapper used multi curl and reactphp inspired by python grab

## Quick start

```php    
<?php

require __DIR__ . '/../vendor/autoload.php';

class HackerNewCrawler extends \Grab\Spider
{
    public function taskGenerator()
    {
        $range = $this->genRange('https://news.ycombinator.com/news?p=%d', 4);
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
        echo $products[0]->text() . PHP_EOL;
    }
}

$bot = new HackerNewCrawler();
$bot->debug = true;
$bot->setCurlSetting([
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
]);
$bot->run();
```