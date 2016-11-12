<?php

require __DIR__ . '/../vendor/autoload.php';

class HabrCrawler extends \Grab\Spider
{
    public function taskGenerator()
    {
        $range = array_map(function($item) {
            return sprintf('https://habrahabr.ru/page%d', $item);
        }, range(1, 10)) ;

        foreach ($range as $url) {
            $this->task('page', [
                'url' => $url,
                'max_request' => 10,
                'sleep' => [1, 0.1, true],
            ]);
        }
    }

    public function taskPage($parser, $task)
    {
        //echo $task['url'] . PHP_EOL;
        $pages = $parser->find('.post__title_link');
        foreach ($pages as $page) {
            $this->task('post', [
                'url' => $page->getAttribute('href'),
                'curl_config' => [
                    CURLOPT_TIMEOUT => 30,
                ],
                'max_request' => 20,
                'sleep' => [1, 0.1],
            ]);
        }
    }

    public function taskPost($parser, $task, $content)
    {
        //var_dump($content);
        $title = $parser->find('.post__title');
        $time = $parser->find('.post__time_published');
        echo $title[0]->text() . PHP_EOL;
        echo $time[0]->text() . PHP_EOL;
    }
}

$bot = new HabrCrawler();
$bot->debug = true;
$bot->setCurlSetting([
    CURLOPT_TIMEOUT => 30,
]);
$bot->run();