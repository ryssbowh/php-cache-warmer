<?php 
namespace Ryssbowh\PhpCacheWarmer;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

interface Observer
{
	/**
	 * Callback for every successful request
	 * 
	 * @param  Response $response
	 * @param  string   $url
	 */
	public function onFulfilled(Response $response, string $url);

	/**
	 * Callback for every failed request
	 * 
	 * @param  RequestException $reason
	 * @param  string           $url
	 */
	public function onRejected(RequestException $reason, string $url);
}