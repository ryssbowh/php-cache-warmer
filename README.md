# Php Cache Warmer

Simple php cache warmer, add urls or parse sitemap and visits all the urls asynchronously.
You need to wait for the promise returned by the `warm` method to finish :

```
use Ryssbowh\PhpCacheWarmer\Warmer;

$warmer = new Warmer();
$warmer->parseSitemap('http://mysite.com/sitemap.xml')
	->addUrls('http://othersite.com')
	->addUrls([
		'http://othersite.com/blog',
		'http://othersite.com/hello'
	])
	->ignoreUrls('http://mysite.com/page-503')
	->ignoreUrls([
		'http://mysite.com/page-400',
		'http://mysite.com/page-500'
	])
	->ignoreRegexs('/http:\/\/mysite\.com\/page*/')
	->ignoreRegexs([
		'/http:\/\/mysite\.com\/forum*/',
		'/http:\/\/mysite\.com\/blog*/'
	]);
$warmer->warm()->wait();
```
You can define the amount of concurrent requests (default 25) :
```
$warmer = new Warmer(50);
```
You can pass guzzle options in the constructor :
```
$warmer = new Warmer(25, ['headers' => [
	'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
]]);
```
And subscribe an observer which will be called for every successful and failed requests :
```
use Ryssbowh\PhpCacheWarmer\Warmer;
use Ryssbowh\PhpCacheWarmer\Observer;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

class MyObserver implements Observer
{
	public function onFulfilled(Response $response, string $url)
	{
		echo 'visited '.$url
	}

	public function onRejected(RequestException $reason, string $url)
	{
		echo 'failed '.$url.' with code '.$reason->getResponse()->getStatusCode();
	}
}

$warmer = new Warmer(25, [], new MyObserver);
```

Thanks to the great [vipnytt/sitemapparser](https://github.com/VIPnytt/SitemapParser) sitemap parser :)