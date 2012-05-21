<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\SymfonyBridgesServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;
use dflydev\markdown\MarkdownParser;

$app = new Silex\Application();

$app['debug'] = $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1';

$app['parameters'] = array(
    'symfttpd'   => array(
        'version' => \Symfttpd\Symfttpd::VERSION,
        'doc_dir' => __DIR__ . '/../vendor/symfttpd/symfttpd/doc'
    ),
    'github_url' => 'https://github.com/benja-M-1/symfttpd',
);

$app->register(new UrlGeneratorServiceProvider());

$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../log/development.log',
));

$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/views',
));

$app->register(new SymfonyBridgesServiceProvider());

$app->register(new TranslationServiceProvider(), array(
    'locale_fallback'     => 'en',
    'translator.messages' => array(
        'en' => __DIR__ . '/locales/en.yml',
        'fr' => __DIR__ . '/locales/fr.yml',
    )
));

$app->register(new HttpCacheServiceProvider(), array(
    'http_cache.cache_dir' => __DIR__.'/cache/',
));

$app['translator.loader'] = $app->share(function () {
    return new YamlFileLoader();
});

$app['markdown'] = $app->share(function () {
    return new MarkdownParser();
});

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig');
})
->bind('homepage');

$app->get('/documentation', function () use ($app) {

    $directory = $app['parameters']['symfttpd']['doc_dir'];

    $content = file_get_contents($directory . '/../README.md');

    return $app['twig']->render(
        'documentation.html.twig',
        array(
            'page' => $app['markdown']->transform($content)
        )
    );
})
->bind('documentation')
->value('current', 'documentation');

$app->get('/download/symfttpd.phar', function () use ($app) {
    $phar = __DIR__.'/../web/symfttpd.phar';

    if (false == file_exists($phar)) {
        $compiler = new \Symfttpd\Compiler();
        $compiler->compile();
    }

    $response = new Response(readfile($phar));
    $response->setStatusCode(200);
    $response->headers->set('Content-Type', 'Content-Type: text/plain; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($phar).'"');
    $response->headers->set('X-LIGHTTPD-send-file', $phar);
    $response->headers->set('X-Sendfile', $phar);
    $response->headers->set('Content-Transfer-Encoding', 'binary');
    $response->headers->set('Content-Length', filesize($phar));
    $response->headers->set('Connection', 'keep-alive');
    $response->sendContent();

    return $response;
})
->bind('download');

return $app;
