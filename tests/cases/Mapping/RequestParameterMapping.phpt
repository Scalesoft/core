<?php

/**
 * Test: Mapping\RequestParameterMapping
 */

require_once __DIR__ . '/../../bootstrap.php';

use Apitte\Core\Exception\Logical\InvalidStateException;
use Apitte\Core\Http\ApiRequest;
use Apitte\Core\Http\ApiResponse;
use Apitte\Core\Http\RequestAttributes;
use Apitte\Core\Mapping\Parameter\FloatTypeMapper;
use Apitte\Core\Mapping\Parameter\IntegerTypeMapper;
use Apitte\Core\Mapping\Parameter\StringTypeMapper;
use Apitte\Core\Mapping\RequestParameterMapping;
use Apitte\Core\Schema\Endpoint;
use Apitte\Core\Schema\EndpointParameter;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Tester\Assert;
use Tester\TestCase;

final class TestRequestParameterMapping extends TestCase
{

	/**
	 * @var RequestParameterMapping
	 */
	private $requestParameterMapping;

	/**
	 * @var ApiRequest
	 */
	private $request;

	/**
	 * @var ApiResponse
	 */
	private $response;

	protected function setUp()
	{
		$this->requestParameterMapping = new RequestParameterMapping;

		$this->requestParameterMapping->addMapper('string', new StringTypeMapper);
		$this->requestParameterMapping->addMapper('int', new IntegerTypeMapper);
		$this->requestParameterMapping->addMapper('float', new FloatTypeMapper);

		$this->request = new ApiRequest(
			new ServerRequest(
				'GET',
				'/'
			)
		);
		$this->response = new ApiResponse(new Response);
	}

	public function testIntInPath()
	{
		$endpoint = new Endpoint;

		$idEndpointParameter = new EndpointParameter;
		$idEndpointParameter->setName('id');
		$idEndpointParameter->setType('int');
		$idEndpointParameter->setIn($idEndpointParameter::IN_PATH);
		$idEndpointParameter->setRequired(FALSE);
		$idEndpointParameter->setAllowEmpty(TRUE);

		$endpoint->addParameter($idEndpointParameter);

		$request = $this->request
			->withAttribute(RequestAttributes::ATTR_ENDPOINT, $endpoint)
			->withAttribute(RequestAttributes::ATTR_PARAMETERS, [
				'id' => NULL,
			]);

		// ---- test optional parameter

		$notRequiredIdResponse = $this->requestParameterMapping->map(
			$request,
            $this->response
		);

		Assert::null($notRequiredIdResponse->getAttribute(RequestAttributes::ATTR_PARAMETERS)['id']);

		// ---- test throw missing parameter

		$idEndpointParameter->setRequired(TRUE);

		Assert::throws(
            function () use ($request) {
                $this->requestParameterMapping->map($request, $this->response);
            },
            InvalidStateException::class,
            'Parameter "id" should be provided in request attributes'
		);

		// ---- test correct map int parameter

		$requestWithId = $request->withAttribute(RequestAttributes::ATTR_PARAMETERS, [
			'id' => '10',
		]);

		$requiredIdResponse = $this->requestParameterMapping->map($requestWithId, $this->response);

		Assert::true(
			array_key_exists(
				'id',
				$requiredIdResponse->getAttribute(RequestAttributes::ATTR_PARAMETERS)
			)
		);
		Assert::same(10, $requiredIdResponse->getAttribute(RequestAttributes::ATTR_PARAMETERS)['id']);

		// ---- test throw empty parameter

		$requestWithEmptyId = $request->withAttribute(RequestAttributes::ATTR_PARAMETERS, [
			'id' => '',
		]);
		$idEndpointParameter->setAllowEmpty(FALSE);

		Assert::throws(
            function () use ($requestWithEmptyId) {
                $this->requestParameterMapping->map($requestWithEmptyId, $this->response);
            },
            InvalidStateException::class,
            'Parameter "id" should not be empty'
		);
	}

	public function testFloatInQuery()
	{
		$endpoint = new Endpoint;

		$scoreEndpointParameter = new EndpointParameter;
		$scoreEndpointParameter->setName('score');
		$scoreEndpointParameter->setType('float');
		$scoreEndpointParameter->setIn($scoreEndpointParameter::IN_QUERY);
		$scoreEndpointParameter->setRequired(FALSE);
		$scoreEndpointParameter->setAllowEmpty(TRUE);

		$endpoint->addParameter($scoreEndpointParameter);

		$request = $this->request
			->withAttribute(RequestAttributes::ATTR_ENDPOINT, $endpoint)
			->withAttribute(RequestAttributes::ATTR_PARAMETERS, [
				'score' => NULL,
			]);

		$noScoreResponse = $this->requestParameterMapping->map($request, $this->response);

		Assert::equal(['score' => NULL], $noScoreResponse->getAttribute(RequestAttributes::ATTR_PARAMETERS));

		$requestWithIdAndScore = $request->withAttribute(
			RequestAttributes::ATTR_PARAMETERS,
			[
				'score' => '3.33',
			]
		);

		$scoreResponse = $this->requestParameterMapping->map(
			$requestWithIdAndScore,
			$this->response
		);

		Assert::equal(
			[
				'score' => 3.33,
			],
			$scoreResponse->getAttribute(RequestAttributes::ATTR_PARAMETERS)
		);

		$scoreEndpointParameter->setRequired(TRUE);

		Assert::throws(
            function () use ($request) {
                $this->requestParameterMapping->map($request, $this->response);
            },
            InvalidStateException::class,
            'Parameter "score" should be provided in request attributes'
		);
	}

	public function testStringInCookie()
	{
		$endpoint = new Endpoint;

		$sessionEndpointParameter = new EndpointParameter;
		$sessionEndpointParameter->setName('session');
		$sessionEndpointParameter->setType('string');
		$sessionEndpointParameter->setIn($sessionEndpointParameter::IN_COOKIE);
		$sessionEndpointParameter->setRequired(FALSE);
		$sessionEndpointParameter->setAllowEmpty(FALSE);

		$endpoint->addParameter($sessionEndpointParameter);

		$request = $this->request
			->withAttribute(RequestAttributes::ATTR_ENDPOINT, $endpoint)
			->withAttribute(RequestAttributes::ATTR_PARAMETERS, [])
			->withCookieParams(
				[
					'session' => NULL,
				]
			);

		$responseWithoutCookie = $this->requestParameterMapping->map($request, $this->response);
		Assert::equal(['session' => NULL], $responseWithoutCookie->getCookieParams());

		$requestWithEmptyCookie = $request->withCookieParams(
			[
				'session' => '',
			]
		);

		Assert::throws(
            function () use ($requestWithEmptyCookie) {
                $this->requestParameterMapping->map($requestWithEmptyCookie, $this->response);
            },
            InvalidStateException::class,
            'Parameter "session" should not be empty'
		);

		$requestWithCookie = $request->withCookieParams(
			[
				'session' => 'bar-baz-key',
			]
		);

		$cookieResponse = $this->requestParameterMapping->map(
			$requestWithCookie,
			$this->response
		);

		Assert::equal(['session' => 'bar-baz-key'], $cookieResponse->getCookieParams());
	}

	public function testStringInHeader()
	{
		$endpoint = new Endpoint;

		$authEndpointParameter = new EndpointParameter;
		$authEndpointParameter->setName('auth');
		$authEndpointParameter->setType('string');
		$authEndpointParameter->setIn($authEndpointParameter::IN_HEADER);
		$authEndpointParameter->setRequired(TRUE);
		$authEndpointParameter->setAllowEmpty(FALSE);

		$endpoint->addParameter($authEndpointParameter);

		$request = $this->request
			->withAttribute(RequestAttributes::ATTR_ENDPOINT, $endpoint)
			->withAttribute(RequestAttributes::ATTR_PARAMETERS, []);

		$requestWithEmptyHeader = $request->withHeader(
			'auth',
			[
				'some',
				'',
			]
		);

		Assert::throws(
            function () use ($requestWithEmptyHeader) {
                $this->requestParameterMapping->map($requestWithEmptyHeader, $this->response);
            },
            InvalidStateException::class,
            'Parameter "auth" should not be empty'
		);

		$requestWithHeader = $request->withHeader(
			'auth',
			[
				'some',
				'other',
			]
		);

		$headerResponse = $this->requestParameterMapping->map($requestWithHeader, $this->response);

		Assert::equal(
			[
				'some',
				'other',
			],
			$headerResponse->getHeader('auth')
		);
	}

}

(new TestRequestParameterMapping)->run();
