<?php

namespace Ryssbowh\PhpCacheWarmer;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use vipnytt\SitemapParser;

class Warmer
{
	protected $observer;
	protected $concurrentRequests;
	protected $guzzleConfig;
	protected $urls = [];

	public function __construct(int $concurrentRequests = 25, array $guzzleConfig = [], ?Observer $observer = null)
	{
		$this->observer = $observer;
		$this->concurrentRequests = $concurrentRequests;
		$this->guzzleConfig = $guzzleConfig;
	}

	/**
	 * Parses a sitemap and add the urls to the queue
	 * 
	 * @param  string $sitemap sitemap's url
	 * @param  array  $options sitemap parser options
	 * @return array urls parsed
	 */
	public function parseSitemap(string $sitemap, $options = []): array
	{
		$options['guzzle'] = array_merge($this->guzzleConfig, $options['guzzle'] ?? []);
		$parser = new SitemapParser($options);
	    $parser->parseRecursive($sitemap);
	    $urls = array_keys($parser->getURLs());
	    $this->addUrls($urls);
	    return $urls;
	}

	/**
	 * Add urls to be warmed
	 * 
	 * @param array $urls
	 */
	public function addUrls(array $urls)
	{
		$this->urls = array_merge($this->urls, $urls);
	}

	/**
	 * Add url to be warmed
	 * 
	 * @param string $url
	 */
	public function addUrl(string $url)
	{
		$this->urls[] = $url;
	}

	/**
	 * Get urls to be warmed
	 * 
	 * @return array
	 */
	public function getUrls(): array
	{
		return $this->urls;
	}

	/**
	 * Asynchronously visit all the urls using guzzle
	 * 
	 * @return Promise
	 */
	public function warm(): Promise
	{
		$_this = $this;
		$client = new Client($this->guzzleConfig);
		$requests = function () use ($client) {
		    foreach ($this->urls as $url) {
		    	yield function() use ($url, $client, $_this) {
		    		return $client->getAsync($url)->then(
		            	function (Response $response) use ($url, $_this) {
		            		if ($_this->observer) {
		            			$_this->observer->onFulfilled($response, $url);
		            		}
		            		return $response;
		            	},
		            	function (RequestException $reason) use ($url, $_this) {
		            		if ($_this->observer) {
		            			$_this->observer->onRejected($reason, $url);
		            		}
		            		return $reason;
		            	}
		        	);
		    	};
			}
		};
		$pool = new Pool($this->client, $requests(), [
    		'concurrency' => $this->concurrentRequests
		]);
		return $pool->promise();
	}
}