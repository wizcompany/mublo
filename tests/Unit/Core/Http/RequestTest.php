<?php
/**
 * tests/Unit/Core/Http/RequestTest.php
 *
 * Request 클래스 단위 테스트
 */

namespace Tests\Unit\Core\Http;

use Tests\TestCase;
use Mublo\Core\Http\Request;

class RequestTest extends TestCase
{
    public function testRequestMethodDetection(): void
    {
        $request = new Request('GET', '/test/path');
        $this->assertEquals('GET', $request->getMethod());

        $postRequest = new Request('POST', '/submit');
        $this->assertEquals('POST', $postRequest->getMethod());
    }

    public function testUriExtraction(): void
    {
        $request = new Request('GET', '/test/path');
        $this->assertEquals('/test/path', $request->getUri());
        $this->assertEquals('/test/path', $request->getPath());
    }

    public function testIsJsonRequest(): void
    {
        // JSON 요청이 아님
        $request = new Request('GET', '/');
        $this->assertFalse($request->isJson());

        // JSON 요청
        $jsonRequest = new Request('POST', '/api/data', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ]);
        $this->assertTrue($jsonRequest->isJson());
    }

    public function testIsAjaxRequest(): void
    {
        // AJAX 요청이 아님
        $request = new Request('GET', '/');
        $this->assertFalse($request->isAjax());

        // AJAX 요청
        $ajaxRequest = new Request('GET', '/data', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $this->assertTrue($ajaxRequest->isAjax());
    }

    public function testQueryParameters(): void
    {
        $request = new Request('GET', '/search', ['q' => 'test', 'page' => 1]);

        $this->assertEquals('test', $request->get('q'));
        $this->assertEquals(1, $request->get('page'));
        $this->assertNull($request->get('missing'));
        $this->assertEquals('default', $request->get('missing', 'default'));
    }

    public function testBodyParameters(): void
    {
        $request = new Request('POST', '/submit', [], [
            'name' => 'John',
            'email' => 'john@example.com'
        ]);

        $this->assertEquals('John', $request->input('name'));
        $this->assertEquals('john@example.com', $request->input('email'));
    }

    public function testServerInfo(): void
    {
        $request = new Request('GET', '/', [], [], [
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0'
        ]);

        $this->assertEquals('example.com', $request->server('HTTP_HOST'));
        $this->assertEquals('Mozilla/5.0', $request->server('HTTP_USER_AGENT'));
    }

    public function testHeader(): void
    {
        $request = new Request('GET', '/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer token123',
            'HTTP_ACCEPT' => 'application/json'
        ]);

        $this->assertEquals('Bearer token123', $request->header('Authorization'));
        $this->assertEquals('application/json', $request->header('Accept'));
    }

    public function testBearerToken(): void
    {
        $request = new Request('GET', '/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer my_secret_token'
        ]);

        $this->assertEquals('my_secret_token', $request->bearerToken());
    }

    /**
     * all() 메서드는 PayloadType에 따라 반환:
     * - FORM (POST): body 반환
     * - GET: query 반환
     */
    public function testAllData(): void
    {
        // POST 요청: body 반환
        $postRequest = new Request('POST', '/data', [], [
            'name' => 'Test',
            'email' => 'test@example.com'
        ]);
        $postAll = $postRequest->all();
        $this->assertEquals('Test', $postAll['name']);
        $this->assertEquals('test@example.com', $postAll['email']);

        // GET 요청: query 반환
        $getRequest = new Request('GET', '/search', [
            'page' => 1,
            'limit' => 10
        ]);
        $getAll = $getRequest->all();
        $this->assertEquals(1, $getAll['page']);
        $this->assertEquals(10, $getAll['limit']);
    }
}
