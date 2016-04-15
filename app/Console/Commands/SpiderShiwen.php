<?php

namespace App\Console\Commands;

use App\Gunshiwen;
use Illuminate\Console\Command;
//use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\DomCrawler\Crawler;

class SpiderShiwen extends Command
{

    protected $seedUrl = 'http://so.gushiwen.org/type.aspx?c=%E9%AD%8F%E6%99%8B';  //seed
    protected $host = 'http://so.gushiwen.org';
    protected $signature = 'spider';
    protected $description = 'crawler for gushiwen';

    protected $converter;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
//        $this->converter = new CssSelectorConverter();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->comment('gushiwen, Here I Come');
        $crawler = new Crawler(file_get_contents($this->seedUrl));
        $pageText = $crawler->filter('.pages > span')->last()->text();
        //获取类似 "共122篇" 然后通过正则取得122
        preg_match('/[0-9]+/i', $pageText, $match);
        $posts = $match[0];
        $posts%10 == 0 ? $pages = $posts/10 : $pages = $posts/10 + 1;
        for( $page = 1; $page <= $pages; $page++ ) {
            $listUrl = $this->seedUrl.'&p='.$page;
            $this->crawListPage($listUrl);
        }
    }

    //爬取列表页
    protected function crawListPage( $listUrl ) {

        $listCrawler = new Crawler(file_get_contents($listUrl));
        $postsUrls = [];
        $listCrawler->filter('.sons > p:nth-child(2)')->each(function (Crawler $node, $i) use (&$postsUrls){
            !empty($node->filter('a')->nodes) ? $param = $node->filter('a')->attr('href') : $param = $node->previousAll()->filter('a')->attr('href');
            $url = $this->host.$param;
            $postsUrls[] = $url;
        });

        foreach( $postsUrls as $url ) {
            $this->crawDetailPage($url);
        }
    }

    //爬取详细页
    protected function crawDetailPage( $detailUrl ) {
        $detailCrawler = new Crawler(file_get_contents($detailUrl));
        $title = $detailCrawler->filter('.son1')->eq(1)->filter('h1')->text();
        $mainContentDiv = $detailCrawler->filter('.son2')->eq(1);
        $authorMarker = $mainContentDiv->filter('p')->eq(1)->filter('a');
//        $originalTextMarker = $mainContentDiv->filter('p')->eq(2);

        if ( !empty($authorMarker->nodes) ) {
            $author = $authorMarker->text();
            $authorUrl = $authorMarker->attr('href');
        } else {
            $authorUrl = '';
            $author = '佚名';
        }
        $content = $detailCrawler->document->textContent;
        $preg = '/\x{539f}\x{6587}\x{ff1a}([\s\S]+)\x{5199}\x{7ffb}\x{8bd1}/uU';
        preg_match_all($preg, $content, $match);
        $originalText = trim($match[1][0]);
        $fanyiUrls = [];
        $shangxiUrls = [];
        $detailCrawler->filter('.son5')->each(function (Crawler $node, $i) use (&$fanyiUrls, &$shangxiUrls){
            $url = $this->host.$node->filter('a')->attr('href');
            if ( str_contains($url, 'fanyi') ) {
                $fanyiUrls[] = $url;
            } elseif( str_contains($url, 'shangxi') ) {
                $shangxiUrls[] = $url;
            }
        });

        $crawlResult['url'] = $detailUrl;
        $crawlResult['title'] = $title;
        $crawlResult['author'] = $author;
        $crawlResult['authorUrl'] = $authorUrl;
        $crawlResult['originalText'] = $originalText;
        $crawlResult['fanyiUrls'] = $fanyiUrls;
        $crawlResult['shangxiUrls'] = $shangxiUrls;
        //存入数据库
        $this->storeToGunshiwen(json_encode($crawlResult));

    }

    protected function storeToGunshiwen( $result ) {
        $recode = new Gunshiwen;
        $recode->result = $result;
        $recode->save();
    }

}
