# Php Cache Warmer

Simple php cache warmer, add urls or parse sitemap and visits all the urls asynchronously.
You need to wait for the promise returned by the `warm` method to finish :

```
$warmer = new Warmer();
$warmer->parseSitemap('http://mysite.com/sitemap.xml');
$warmer->addUrl('http://othersite.com');
$warmer->addUrls([
	'http://othersite.com',
	'http://othersite.com/hello'
]);
$promise = $warmer->warm();
$promise->wait();
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