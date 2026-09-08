<?php

declare(strict_types=1);

namespace Unoptimised\InertiaBundle\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Unoptimised\InertiaBundle\Service\InertiaInterface;
use Unoptimised\InertiaBundle\Tests\Functional\Fixtures\TestKernel;

/**
 * Boots a real kernel with the bundle registered. This is what catches DI wiring
 * regressions, missing runtime dependencies and cross-version container breakage —
 * none of which the unit tests can see.
 */
class InertiaKernelTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        if (null !== $this->kernel) {
            $cacheDir = $this->kernel->getCacheDir();
            $this->kernel->shutdown();
            (new Filesystem())->remove($cacheDir);
            $this->kernel = null;
        }

        // Booting in debug mode installs Symfony's ErrorHandler, which never
        // unregisters itself. PHPUnit flags the leftover as a risky test.
        restore_exception_handler();
    }

    private function boot(array $inertiaConfig = []): TestKernel
    {
        $this->kernel = new TestKernel($inertiaConfig);
        $this->kernel->boot();

        return $this->kernel;
    }

    private function inertia(TestKernel $kernel): InertiaInterface
    {
        return $kernel->getContainer()->get('test.inertia');
    }

    private function pushRequest(TestKernel $kernel, Request $request): void
    {
        $kernel->getContainer()->get('test.request_stack')->push($request);
    }

    public function testContainerCompilesAndBundleBoots(): void
    {
        $kernel = $this->boot();

        $this->assertInstanceOf(InertiaInterface::class, $this->inertia($kernel));
    }

    public function testServiceIsAvailableUnderTheLegacyId(): void
    {
        $kernel = $this->boot();

        $this->assertSame(
            $this->inertia($kernel),
            $kernel->getContainer()->get('test.inertia_legacy_id'),
        );
    }

    public function testFullPageRenderUsesTheShippedRootTemplate(): void
    {
        $kernel = $this->boot();
        $this->pushRequest($kernel, Request::create('/home'));

        $response = $this->inertia($kernel)->render('Home', ['name' => 'Alice']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<div id="app" data-page="', $response->getContent());
        $this->assertStringContainsString('&quot;component&quot;:&quot;Home&quot;', $response->getContent());
    }

    public function testXhrRenderReturnsThePageObject(): void
    {
        $kernel = $this->boot(['version' => 'v1']);
        $request = Request::create('/home');
        $request->headers->set('X-Inertia', 'true');
        $this->pushRequest($kernel, $request);

        $response = $this->inertia($kernel)->render('Home', ['name' => 'Alice']);

        $this->assertSame('true', $response->headers->get('X-Inertia'));
        $this->assertSame('X-Inertia', $response->headers->get('Vary'));
        $this->assertSame(
            ['component' => 'Home', 'props' => ['name' => 'Alice'], 'url' => '/home', 'version' => 'v1'],
            json_decode($response->getContent(), true),
        );
    }

    public function testUnicodePropsAreNotEscapedInTheJsonResponse(): void
    {
        $kernel = $this->boot();
        $request = Request::create('/home');
        $request->headers->set('X-Inertia', 'true');
        $this->pushRequest($kernel, $request);

        $response = $this->inertia($kernel)->render('Home', ['name' => 'Zoë']);

        // JSON_UNESCAPED_UNICODE must survive as far as the wire, rather than being
        // dropped by a re-encode inside JsonResponse.
        $escaped = trim(json_encode('Zoë'), '"'); // Zo\u00eb

        $this->assertStringContainsString('Zoë', $response->getContent());
        $this->assertStringNotContainsString($escaped, $response->getContent());
    }

    public function testSerializerNormalisesObjectProps(): void
    {
        $kernel = $this->boot();
        $request = Request::create('/home');
        $request->headers->set('X-Inertia', 'true');
        $this->pushRequest($kernel, $request);

        $response = $this->inertia($kernel)->render('Home', ['dto' => new Fixtures\TestDto('Zoë', 3)]);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(['name' => 'Zoë', 'count' => 3], $data['props']['dto']);
    }

    public function testStaleAssetVersionYields409WithLocationHeader(): void
    {
        $kernel = $this->boot(['version' => 'v1']);

        $request = Request::create('/page');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'stale');

        $response = $kernel->handle($request);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('http://localhost/page', $response->headers->get('X-Inertia-Location'));
    }

    public function testMatchingAssetVersionIsNotIntercepted(): void
    {
        $kernel = $this->boot(['version' => 'v1']);

        $request = Request::create('/page');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'v1');

        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Page', json_decode($response->getContent(), true)['component']);
    }

    public function testRedirectAfterDeleteIsConvertedTo303(): void
    {
        $kernel = $this->boot(['version' => 'v1']);

        $request = Request::create('/delete', 'DELETE');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'v1');

        $response = $kernel->handle($request);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/page', $response->headers->get('Location'));
    }

    public function testRedirectAfterGetIsLeftAlone(): void
    {
        $kernel = $this->boot(['version' => 'v1']);

        $request = Request::create('/redirect');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'v1');

        $response = $kernel->handle($request);

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testServiceIsAutowirableAsAControllerArgument(): void
    {
        $kernel = $this->boot(['version' => 'v1']);

        $request = Request::create('/autowired');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'v1');

        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Autowired', json_decode($response->getContent(), true)['component']);
    }
}