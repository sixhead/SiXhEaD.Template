# SiXhEaD Template

A simple, single-file PHP template engine with no dependencies. A designer
writes plain HTML with `$variables` and block markers, and the PHP code fills
them in. Requires PHP 8.5.

## Usage

`_tp_list.html`:

```html
<h3>$title</h3>
<ul>
<!--template:row-->
    <li>$no</li>
<!--/template:row-->
</ul>
```

`list.php`:

```php
require __DIR__ . '/SiXhEaD.Template.php';

$tp = new Template(__DIR__ . '/_tp_list.html');
$title = 'Numbers';

$tp->block('row');
for ($no = 1; $no <= 3; $no++) {
    $tp->apply();
}

$tp->display();
```

By default `$name` reads global variables. Call `$tp->setGlobal(false)` and
`$tp->assign('name', $value)` to pass values explicitly. See `examples/` for
blocks, sub parts, nested blocks, custom keys and `ignore()`.

## Security: block web access to template files

Template files are plain HTML. If they are inside the document root, anyone
can open them directly in a browser, for example
`https://example.com/_tp_list.html`, and read the raw template.

The safest setup is to keep templates outside the document root. If they must
stay inside it, deny direct access in the web server.

### nginx

Put this `location` before other regex locations that can match `.html`
files (for example a static file cache rule), because nginx uses the first
regex location that matches:

```nginx
location ~* (?:^/_templates/|/_tp_[^/]*\.html?$) {
    deny all;
}
```

This blocks every file named `_tp_*.html` or `_tp_*.htm`, and everything in a
top-level `_templates/` directory.

### Other web servers

Apache, Caddy, LiteSpeed and other servers need the same protection. We use
nginx only, so this README has no tested example for them. Add an equivalent
deny rule for the same paths in your server config.

### Using a `.tp` extension

You can name templates `_tp_list.tp` instead of `_tp_list.html`. The engine
reads any file name. This does not protect the files by itself: the web
server still serves unknown extensions, usually as a download. Add `tp` to
the deny rule:

```nginx
location ~* (?:^/_templates/|/_tp_[^/]*\.(?:html?|tp)$) {
    deny all;
}
```

Editors also lose HTML syntax highlighting for `.tp` files unless you map the
extension to HTML.

Check the rule after you add it: a request for a template file must return
`403 Forbidden`.

```bash
curl -I https://example.com/_tp_list.html
```

## Development

Composer runs the tests and checks:

```bash
composer test
composer analyse
composer lint
```

The Memcached and Redis example tests need the `memcached` / `redis` PHP
extension and a running server. Set `MEMCACHED_HOST` and `REDIS_HOST` when the
servers are not reachable as `memcached` and `redis`. Without them the tests
are skipped.

## License

MIT. See `LICENSE`.
