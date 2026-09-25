<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Registration\ClassDiscovery;
use NyonCode\WireCore\WireCoreServiceProvider;

/*
 * Finding the resources in a folder instead of listing them.
 *
 * The folder is written per test and autoloaded the way Composer would load an
 * application's namespace, because discovery goes through the autoloader —
 * a class that does not autoload is not one the application has.
 */

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/wire-discovery-*') ?: [] as $dir) {
        (new Filesystem)->deleteDirectory($dir);
    }
});

function cdFolder(): string
{
    $dir = sys_get_temp_dir().'/wire-discovery-'.bin2hex(random_bytes(4));
    $namespace = 'CdApp'.bin2hex(random_bytes(3)).'\\Resources';

    $write = function (string $relative, string $body) use ($dir, $namespace): void {
        $path = $dir.'/'.$relative;
        @mkdir(dirname($path), 0777, true);
        $sub = trim(str_replace('/', '\\', dirname($relative)), '.\\');
        $ns = $sub === '' ? $namespace : $namespace.'\\'.$sub;
        file_put_contents($path, "<?php\n\nnamespace {$ns};\n\n{$body}\n");
    };

    $resource = "use NyonCode\\WireCore\\Core\\Resources\\Concerns\\DescribesRecords;\nuse NyonCode\\WireCore\\Core\\Resources\\Contracts\\DescribesResource;\n\n";
    $write('OrderResource.php', $resource."final class OrderResource implements DescribesResource\n{\n    use DescribesRecords;\n\n    public static function modelClass(): ?string { return null; }\n}");
    $write('Sales/InvoiceResource.php', $resource."final class InvoiceResource implements DescribesResource\n{\n    use DescribesRecords;\n\n    public static function modelClass(): ?string { return null; }\n}");
    $write('BaseResource.php', $resource."abstract class BaseResource implements DescribesResource\n{\n    use DescribesRecords;\n}");
    $write('Helper.php', 'final class Helper {}');
    file_put_contents($dir.'/notes.txt', 'not php');

    spl_autoload_register(function (string $class) use ($dir, $namespace): void {
        if (str_starts_with($class, $namespace.'\\')) {
            $file = $dir.'/'.str_replace('\\', '/', substr($class, strlen($namespace) + 1)).'.php';

            if (is_file($file)) {
                require_once $file;
            }
        }
    });

    return $dir.'|'.$namespace;
}

it('finds the concrete classes of the kind asked for, nested folders included', function () {
    [$dir, $namespace] = explode('|', cdFolder());

    $found = (new ClassDiscovery(new Filesystem))->in($dir, $namespace, DescribesResource::class);

    expect($found)->toBe([$namespace.'\OrderResource', $namespace.'\Sales\InvoiceResource']);
});

it('answers nothing for a folder that is not there', function () {
    expect((new ClassDiscovery(new Filesystem))->in('/nowhere/at/all', 'App\Resources', DescribesResource::class))->toBe([]);
});

it('registers what config says to discover, beside what it lists', function () {
    [$dir, $namespace] = explode('|', cdFolder());

    config()->set('wire-core.discover.resources', [$namespace => $dir]);

    // Boot the registration again, as a provider would with this config.
    (fn () => $this->bootResources())->call(app()->getProvider(WireCoreServiceProvider::class));

    expect(app(ResourceRegistry::class)->all())->toContain($namespace.'\OrderResource', $namespace.'\Sales\InvoiceResource');
});
