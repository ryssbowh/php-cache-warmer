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
	/**
	 * @var null|Observer
	 */
	protected $observer;
	/**
	 * @var int
	 */
	protected $concurrentRequests;
	/**
	 * @var array
	 */
	protected $guzzleConfig;
	/**
	 * @var array
	 */
	protected $urls = [];
	/**
	 * @var array
	 */
	protected $ignoreUrls = [];
	/**
	 * @var array
	 */
	protected $ignoreRegex = [];

	/**
	 * Constructor
	 * 
	 * @param int|integer   $concurrentRequests
	 * @param array         $guzzleConfig
	 * @param Observer|null $observer
	 */
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
	public function parseSitemap(string $sitemap, $options = []): Warmer
	{
		$options['guzzle'] = array_merge($this->guzzleConfig, $options['guzzle'] ?? []);
		$parser = new SitemapParser(SitemapParser::DEFAULT_USER_AGENT, $options);
	    $parser->parseRecursive($sitemap);
	    $urls = array_keys($parser->getURLs());
	    return $this->addUrls($urls);
	}

	/**
	 * Add urls to be warmed
	 * 
	 * @param array $urls
	 * @return Warmer
	 */
	public function addUrls(array $urls): Warmer
	{
		foreach ($urls as $url) {
			$this->addUrl($url);
		}
		return $this;
	}

	/**
	 * Add url to be warmed
	 * 
	 * @param string $url
	 * @return Warmer
	 */
	public function addUrl(string $url): Warmer
	{
		if (!$this->checkIgnoreUrls($url) and !$this->checkIgnoreRegex($url)) {
			$this->urls[] = rtrim($url, '/');
		}
		return $this;
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
	 * Ignore exact url(s)
	 * 
	 * @param  string|array $url
	 * @return Warmer
	 */
	public function ignoreUrls($urls): Warmer
	{
		$urls = is_array($urls) ? $urls : [$urls];
		foreach ($urls as $url) {
			$url = rtrim($url, '/');
			$this->ignoreUrls[] = $url;
			$this->filterIgnoreUrls($url);
		}
		
		return $this;
	}

	/**
	 * Ignore by regex
	 * 
	 * @param  string|array $regexs
	 * @return Warmer
	 */
	public function ignoreRegexs($regexs): Warmer
	{
		$regexs = is_array($regexs) ? $regexs : [$regexs];
		foreach ($regexs as $regex) {
			$this->ignoreRegex[] = $regex;
			$this->filterIgnoreRegex($regex);
		}
		return $this;
	}

	/**
	 * How many urls are registered
	 * 
	 * @return int
	 */
	public function size(): int
	{
		return sizeof($this->urls);
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
		$requests = function () use ($client, $_this) {
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
		$pool = new Pool($client, $requests(), [
    		'concurrency' => $this->concurrentRequests
		]);
		return $pool->promise();
	}

	/**
	 * Check one url against all ignored urls
	 * 
	 * @param  string $url
	 * @return bool is there a match
	 */
	protected function checkIgnoreUrls(string $url): bool
	{
		foreach ($this->ignoreUrls as $toIgnore) {
			if ($toIgnore == $url) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check one url against all ignored regex
	 * 
	 * @param  string $url
	 * @return bool is there a match
	 */
	protected function checkIgnoreRegex(string $url): bool
	{
		foreach ($this->ignoreRegex as $toIgnore) {
			if (preg_match($toIgnore, $url)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Filter out all urls that match a url to ignore
	 * 
	 * @param  string $toIgnore
	 */
	protected function filterIgnoreUrls(string $toIgnore)
	{
		foreach ($this->urls as $index => $url) {
			if ($toIgnore == $url) {
				unset($this->urls[$index]);
			}
		}
	}

	/**
	 * Filter out all urls that match a regex to ignore
	 * 
	 * @param  string $toIgnore
	 */
	protected function filterIgnoreRegex(string $toIgnore)
	{
		foreach ($this->urls as $index => $url) {
			if (preg_match($toIgnore, $url)) {
				unset($this->urls[$index]);
			}
		}
	}
}