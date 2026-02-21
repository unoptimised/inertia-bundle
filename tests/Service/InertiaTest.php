<?php

declare(strict_types=1);

namespace Unoptimised\InertiaBundle\Tests\Service;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Unoptimised\InertiaBundle\Service\Inertia;

class InertiaTest extends TestCase
{
    private RequestStack $requestStack;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->twig = $this->createStub(Environment::class);
    }

    private function makeInertia(?string $version = null, string $rootView = 'inertia.html.twig'): Inertia
    {
        return new Inertia($version, $rootView, $this->requestStack, $this->twig);
    }

    private function pushRequest(array $headers = [], string $uri = '/home'): Request
    {
        $request = Request::create($uri);
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }
        $this->requestStack->push($request);
        return $request;
    }

    public function testFullPageResponseRendersHtml(): void
    {
        $this->pushRequest();

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('inertia.html.twig', $this->callback(function (array $context) {
                $page = $context['page'];
                $this->assertSame('Home', $page['component']);
                return true;
            }))
            ->willReturn('<html></html>');

        $inertia = new Inertia(null, 'inertia.html.twig', $this->requestStack, $twig);
        $response = $inertia->render('Home');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testFullPageResponsePassesPropsToTemplate(): void
    {
        $this->pushRequest();

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (string $view, array $context) {
                $this->assertSame(['name' => 'Alice'], $context['page']['props']);
                return '<html></html>';
            });

        $inertia = new Inertia(null, 'inertia.html.twig', $this->requestStack, $twig);
        $inertia->render('Home', ['name' => 'Alice']);
    }

    public function testInertiaXhrResponseReturnsJson(): void
    {
        $this->pushRequest(['X-Inertia' => 'true']);

        $response = $this->makeInertia('1')->render('Home', ['foo' => 'bar']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-Inertia'));

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Home', $data['component']);
        $this->assertSame(['foo' => 'bar'], $data['props']);
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('version', $data);
    }

    public function testXhrResponseIncludesCorrectUrl(): void
    {
        $this->pushRequest(['X-Inertia' => 'true'], '/dashboard?tab=stats');

        $response = $this->makeInertia()->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame('/dashboard?tab=stats', $data['url']);
    }

    public function testXhrResponseIncludesVersion(): void
    {
        $this->pushRequest(['X-Inertia' => 'true']);

        $response = $this->makeInertia('abc123')->render('Home');
        $data = json_decode($response->getContent(), true);

        $this->assertSame('abc123', $data['version']);
    }

    public function testXhrResponseVersionIsEmptyStringWhenNull(): void
    {
        $this->pushRequest(['X-Inertia' => 'true']);

        $response = $this->makeInertia(null)->render('Home');
        $data = json_decode($response->getContent(), true);

        $this->assertSame('', $data['version']);
    }

    public function testXhrResponseDoesNotCallTwig(): void
    {
        $this->pushRequest(['X-Inertia' => 'true']);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $inertia = new Inertia(null, 'inertia.html.twig', $this->requestStack, $twig);
        $inertia->render('Home');
    }

    public function testSharedPropsAreMergedWithComponentProps(): void
    {
        $this->pushRequest(['X-Inertia' => 'true']);

        $inertia = $this->makeInertia();
        $inertia->share('user', 'Bob');

        $response = $inertia->render('Home', ['title' => 'Hello']);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['user' => 'Bob', 'title' => 'Hello'], $data['props']);
    }

    public function testComponentPropsOverrideSharedProps(): void
    {
        $this->pushRequest(['X-Inertia' => 'true']);

        $inertia = $this->makeInertia();
        $inertia->share('key', 'shared');

        $response = $inertia->render('Home', ['key' => 'component']);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('component', $data['props']['key']);
    }

    public function testShareWithArray(): void
    {
        $inertia = $this->makeInertia();
        $inertia->share(['a' => 1, 'b' => 2]);

        $this->assertSame(['a' => 1, 'b' => 2], $inertia->getSharedProps());
    }

    public function testShareWithKeyValue(): void
    {
        $inertia = $this->makeInertia();
        $inertia->share('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $inertia->getSharedProps());
    }

    public function testShareMergesMultipleCalls(): void
    {
        $inertia = $this->makeInertia();
        $inertia->share('a', 1);
        $inertia->share('b', 2);

        $this->assertSame(['a' => 1, 'b' => 2], $inertia->getSharedProps());
    }

    public function testCallablePropsAreResolvedOnRender(): void
    {
        $this->pushRequest(['X-Inertia' => 'true']);

        $called = false;
        $response = $this->makeInertia()->render('Home', [
            'lazy' => function () use (&$called) {
                $called = true;
                return 'resolved';
            },
        ]);

        $this->assertTrue($called);
        $data = json_decode($response->getContent(), true);
        $this->assertSame('resolved', $data['props']['lazy']);
    }

    public function testPartialReloadOnlyIncludesRequestedProps(): void
    {
        $this->pushRequest([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Home',
            'X-Inertia-Partial-Data' => 'title',
        ]);

        $response = $this->makeInertia()->render('Home', [
            'title' => 'Hello',
            'secret' => 'hidden',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('title', $data['props']);
        $this->assertArrayNotHasKey('secret', $data['props']);
    }

    public function testPartialReloadWithMultipleKeys(): void
    {
        $this->pushRequest([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Home',
            'X-Inertia-Partial-Data' => 'title, user',
        ]);

        $response = $this->makeInertia()->render('Home', [
            'title' => 'Hello',
            'user' => 'Alice',
            'other' => 'nope',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('title', $data['props']);
        $this->assertArrayHasKey('user', $data['props']);
        $this->assertArrayNotHasKey('other', $data['props']);
    }

    public function testPartialReloadIgnoredForDifferentComponent(): void
    {
        $this->pushRequest([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Other',
            'X-Inertia-Partial-Data' => 'title',
        ]);

        $response = $this->makeInertia()->render('Home', [
            'title' => 'Hello',
            'secret' => 'included',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('title', $data['props']);
        $this->assertArrayHasKey('secret', $data['props']);
    }

    public function testPartialReloadResolvesCallableProps(): void
    {
        $this->pushRequest([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Home',
            'X-Inertia-Partial-Data' => 'lazy',
        ]);

        $response = $this->makeInertia()->render('Home', [
            'lazy' => fn() => 'resolved',
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('resolved', $data['props']['lazy']);
    }

    public function testGetVersion(): void
    {
        $inertia = $this->makeInertia('v1.0');
        $this->assertSame('v1.0', $inertia->getVersion());
    }

    public function testSetVersion(): void
    {
        $inertia = $this->makeInertia();
        $inertia->setVersion('v2.0');
        $this->assertSame('v2.0', $inertia->getVersion());
    }

    public function testThrowsWhenNoRequestInStack(): void
    {
        $this->expectException(LogicException::class);

        $this->makeInertia()->render('Home');
    }
}