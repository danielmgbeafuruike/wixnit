<?php

namespace Wixnit\App;

use DOMDocument;
use DOMXPath;
use Throwable;
use Wixnit\Exception\ViewNotFoundException;
use Wixnit\Exception\ViewRenderException;
use Wixnit\Interfaces\ITranslator;
use Wixnit\Routing\Request;

/**
 * Renders a single PHP/phtml template, with an optional DOM post-processing pass
 * (translation, route-aware URI rewriting).
 *
 * ## Template helpers
 * Because templates are `require`'d from within an instance method, `$this` inside
 * a template refers to the View instance rendering it - every public method below
 * is callable directly from template markup:
 *
 * ```php
 * <!-- layouts/main.php -->
 * <html>
 *   <head><?php $this->stack('scripts'); ?></head>
 *   <body><?php $this->show('content'); ?></body>
 * </html>
 *
 * <!-- pages/dashboard.php -->
 * <?php $this->extend('layouts.main'); ?>
 * <?php $this->push('scripts'); ?>
 *   <script src="/dashboard.js"></script>
 * <?php $this->endPush(); ?>
 * <?php $this->section('content'); ?>
 *   <h1>Hi <?= $this->e($name) ?></h1>
 *   <?= $this->component('widgets.card', ['title' => 'Sales']) ?>
 * <?php $this->endSection(); ?>
 * ```
 *
 * (Blade users: extend/section/endSection/show map to @extends/@section/@endsection/@yield.)
 */
class View
{
    private string $filePath = '';
    private ?ITranslator $translator = null;
    private array $routeSegments = [];
    private bool $modifyResourcesURI = false;
    private array $data = [];
    private array $extensions = ['php', 'phtml'];
    private bool $debug = false;
    private ?Views $factory = null;

    // Layout/section/stack state - reset at the start of every process() call so a
    // View instance can safely be render()'d more than once.
    private ?string $layout = null;
    private array $sections = [];
    private array $sectionStack = [];
    private array $stacks = [];
    private array $stackStack = [];

    private static array $resolutionCache = [];

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? '';
    }

    /**
     * Render the view directly to output. Accepts, in any order/combination: a
     * Request (made available to the template and via ViewPayload::Init()->request)
     * and/or an array of view data (merged over anything passed to with()).
     * @throws ViewNotFoundException
     * @throws ViewRenderException
     */
    public function render(): void
    {
        echo $this->process(func_get_args());
    }

    /**
     * Same as render(), but returns the rendered HTML instead of echoing it - for
     * embedding one view inside another, returning HTML from an API endpoint,
     * caching a fragment, or asserting against it in a test.
     * @throws ViewNotFoundException
     * @throws ViewRenderException
     */
    public function renderToString(): string
    {
        return $this->process(func_get_args());
    }

    /**
     * Accumulate data to expose to the template as local variables (e.g. `with(["name" => "Ada"])`
     * lets the template use `<?= $name ?>` directly) in addition to via ViewPayload::Init()->payload.
     * Can be called multiple times; later calls win on key conflicts, as does any array
     * passed directly to render()/renderToString().
     * @param array $data
     * @return self
     */
    public function with(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function withTranslator(ITranslator $translator): self
    {
        $this->translator = $translator;
        return $this;
    }

    public function withDataRoutes(array $data, bool $modifyResourcesURI = false): self
    {
        foreach ($data as $value) {
            $this->routeSegments[] = $value;
        }
        $this->modifyResourcesURI = $modifyResourcesURI;
        return $this;
    }

    public function setDataRoutes(array $data, bool $modifyResourcesURI = false): self
    {
        $this->routeSegments = array_values($data);
        $this->modifyResourcesURI = $modifyResourcesURI;
        return $this;
    }

    /**
     * Override which file extensions are tried, in order, when the view's file path
     * has none of its own. Defaults to ['php', 'phtml'].
     * @param array $extensions
     * @return self
     */
    public function withExtensions(array $extensions): self
    {
        $this->extensions = $extensions;
        return $this;
    }

    /**
     * When enabled, malformed-HTML warnings collected while post-processing this view
     * (translation / URI rewriting) are logged instead of being silently discarded.
     * @param bool $debug
     * @return self
     */
    public function withDebug(bool $debug = true): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Attaches the Views factory that produced this View, so extend()/component()
     * can resolve other views by name (respecting namespaces/composers) instead of
     * only by raw filesystem path. Set automatically by Views::get() - you shouldn't
     * normally need to call this yourself.
     * @param Views $factory
     * @return self
     */
    public function withFactory(Views $factory): self
    {
        $this->factory = $factory;
        return $this;
    }



    #region template helpers (called via $this-> from inside a template)

    /**
     * Declares the layout this view's content should be inserted into. The layout is
     * rendered *after* this template finishes running, and only sees whatever was
     * captured via section()/push() - any output outside a section() block is discarded
     * once a layout is set, since the layout is what actually gets echoed/returned.
     * @param string $layout view name (dot or slash notation, optionally "namespace::...")
     * @return void
     */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Starts capturing output into a named section, to be placed wherever the layout
     * calls show($name). Must be paired with a later endSection().
     * @param string $name
     * @return void
     */
    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * Closes the most recently opened section() block.
     * @return void
     * @throws ViewRenderException if there's no open section() to close
     */
    public function endSection(): void
    {
        if (count($this->sectionStack) === 0) {
            throw ViewRenderException::UnmatchedBlock('endSection', $this->filePath);
        }

        $name = array_pop($this->sectionStack);
        $this->sections[$name] = (string) ob_get_clean();
    }

    /**
     * Outputs a named section's captured content - called from within a layout template.
     * @param string $name
     * @param string $default used if that section was never defined
     * @return void
     */
    public function show(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    /**
     * Starts capturing output to append onto a named stack (e.g. "scripts") rather than
     * replace it - multiple templates/components can push() onto the same stack, and all
     * of it is emitted, in order, wherever the layout calls stack($name). Must be paired
     * with a later endPush().
     * @param string $name
     * @return void
     */
    public function push(string $name): void
    {
        $this->stackStack[] = $name;
        ob_start();
    }

    /**
     * Closes the most recently opened push() block.
     * @return void
     * @throws ViewRenderException if there's no open push() to close
     */
    public function endPush(): void
    {
        if (count($this->stackStack) === 0) {
            throw ViewRenderException::UnmatchedBlock('endPush', $this->filePath);
        }

        $name = array_pop($this->stackStack);
        $this->stacks[$name][] = (string) ob_get_clean();
    }

    /**
     * Outputs everything ever pushed onto a named stack, concatenated in push() order -
     * called from within a layout template, typically inside <head> for a "scripts" stack.
     * @param string $name
     * @return void
     */
    public function stack(string $name): void
    {
        echo implode('', $this->stacks[$name] ?? []);
    }

    /**
     * Renders another view as a component/partial and returns its HTML, resolved the
     * same way extend() resolves a layout - by name through the attached Views factory
     * if one is set (respecting namespaces and composers), or relative to this view's
     * own directory otherwise.
     * @param string $name
     * @param array $data
     * @return string
     * @throws ViewNotFoundException
     * @throws ViewRenderException
     */
    public function component(string $name, array $data = []): string
    {
        return $this->resolveRelated($name)->with($data)->renderToString();
    }

    /**
     * HTML-escapes a value for safe output - templates should wrap any non-literal
     * value with this (`<?= $this->e($comment->body) ?>`) unless it's already-trusted
     * HTML (e.g. the output of component()/show(), which are not double-escaped).
     * @param mixed $value
     * @return string
     */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    #endregion



    /**
     * Check whether a given path (with or without an extension already) resolves to
     * an existing file, without constructing or rendering a View for it.
     * @param string $filePath
     * @param array $extensions
     * @return bool
     */
    public static function resolves(string $filePath, array $extensions = ['php', 'phtml']): bool
    {
        return self::locate($filePath, $extensions) !== null;
    }

    /**
     * Clears the static path-resolution cache used by locate()/resolveFilePath().
     * Only relevant on persistent-worker SAPIs (Swoole/RoadRunner/FrankenPHP) where
     * the process - and this cache - can outlive a single request; call this if view
     * files might be added, removed, or renamed without the worker restarting.
     * @return void
     */
    public static function clearResolutionCache(): void
    {
        self::$resolutionCache = [];
    }

    private static function locate(string $filePath, array $extensions): ?string
    {
        if ($filePath === '') {
            return null;
        }

        $cacheKey = $filePath . '|' . implode(',', $extensions);

        if (array_key_exists($cacheKey, self::$resolutionCache)) {
            return self::$resolutionCache[$cacheKey];
        }

        $resolved = file_exists($filePath) ? $filePath : null;

        if ($resolved === null) {
            foreach ($extensions as $extension) {
                $candidate = $filePath . '.' . ltrim($extension, '.');

                if (file_exists($candidate)) {
                    $resolved = $candidate;
                    break;
                }
            }
        }

        return self::$resolutionCache[$cacheKey] = $resolved;
    }

    private function resolveFilePath(): string
    {
        return self::locate($this->filePath, $this->extensions) ?? $this->filePath; // will fail later if still doesn't exist
    }

    /**
     * Resolves a layout/component name to a View - through the attached Views factory
     * if one is set (so namespaces, shared data, and composers all apply consistently),
     * or as a path relative to this view's own directory otherwise.
     * @param string $name
     * @return View
     */
    private function resolveRelated(string $name): View
    {
        $normalized = Views::normalizeName($name);

        if ($this->factory !== null) {
            return $this->factory->get($normalized);
        }

        return (new View(dirname($this->filePath) . '/' . ltrim($normalized, '/')))
            ->withExtensions($this->extensions);
    }

    /**
     * Merges in section content captured by a child view that's extend()-ing this one.
     * @param array $sections
     * @return self
     */
    protected function withSections(array $sections): self
    {
        $this->sections = array_merge($this->sections, $sections);
        return $this;
    }

    /**
     * Merges in stack content pushed by a child view that's extend()-ing this one.
     * @param array $stacks
     * @return self
     */
    protected function withStacks(array $stacks): self
    {
        foreach ($stacks as $name => $items) {
            $this->stacks[$name] = array_merge($this->stacks[$name] ?? [], $items);
        }
        return $this;
    }

    /**
     * Builds the render payload, requires the resolved template, hands off to a layout
     * if extend() was called, and (when a translator or route data is set) runs the DOM
     * post-processing pass over the final result.
     * @param array $args
     * @return string
     * @throws ViewNotFoundException
     * @throws ViewRenderException
     */
    private function process(array $args): string
    {
        // Reset per-render state so this instance can safely be render()'d more than once.
        $this->layout = null;
        $this->sections = [];
        $this->sectionStack = [];
        $this->stacks = [];
        $this->stackStack = [];

        $payload = $this->buildPayload($args);
        Container::set(ViewPayload::CONTAINER_KEY, $payload);

        $resolvedPath = $this->resolveFilePath();

        if (!file_exists($resolvedPath)) {
            Container::remove(ViewPayload::CONTAINER_KEY);
            throw ViewNotFoundException::AtPath($this->filePath);
        }

        ob_start();

        try {
            $this->includeTemplate($resolvedPath, $payload['args'] ?? []);
            $rendered = (string) ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            Container::remove(ViewPayload::CONTAINER_KEY);
            throw ViewRenderException::InView($resolvedPath, $e);
        }

        Container::remove(ViewPayload::CONTAINER_KEY);

        if ($this->layout !== null) {
            return $this->renderLayout($args);
        }

        if ($this->translator !== null || count($this->routeSegments) > 0) {
            $rendered = $this->preProcessContent($rendered);
        }

        return $rendered;
    }

    private function buildPayload(array $args): array
    {
        $payload = ['args' => $this->data];

        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] instanceof Request) {
                $payload['request'] = $args[$i];
            }
            if (is_array($args[$i])) {
                $payload['args'] = array_merge($payload['args'], $args[$i]);
            }
        }

        return $payload;
    }

    /**
     * Requires the resolved template file with $__data extracted into local scope, so
     * templates can use `<?= $name ?>` directly instead of pulling everything out of
     * ViewPayload::Init(). $this is preserved (this is a regular instance method, not
     * static), so templates can also call any of the public template helpers above.
     * Parameters are double-underscore-prefixed to minimize collisions with whatever
     * gets extracted into scope.
     * @param string $__viewPath
     * @param array $__data
     * @return void
     */
    private function includeTemplate(string $__viewPath, array $__data): void
    {
        extract($__data, EXTR_SKIP);
        unset($__data);
        require $__viewPath;
    }

    /**
     * Renders the layout declared via extend(), carrying over this view's captured
     * sections/stacks plus its translator/route/debug settings, and returns the
     * layout's own (already fully DOM-post-processed) output directly - this view's
     * own $rendered content is not used once a layout is set.
     * @param array $originalArgs the same args render()/renderToString() was called with
     * @return string
     * @throws ViewNotFoundException
     * @throws ViewRenderException
     */
    private function renderLayout(array $originalArgs): string
    {
        $layoutView = $this->resolveRelated($this->layout)
            ->withSections($this->sections)
            ->withStacks($this->stacks)
            ->withExtensions($this->extensions)
            ->withDebug($this->debug)
            ->with($this->data);

        if ($this->translator !== null) {
            $layoutView->withTranslator($this->translator);
        }
        if (count($this->routeSegments) > 0) {
            $layoutView->setDataRoutes($this->routeSegments, $this->modifyResourcesURI);
        }

        return $layoutView->renderToString(...$originalArgs);
    }

    private function preProcessContent(string $content): string
    {
        if (trim($content) === '') {
            return $content;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->preserveWhiteSpace = false;

        // Seed an explicit UTF-8 declaration - loadHTML() otherwise assumes ISO-8859-1
        // and mangles any non-ASCII character (accents, curly quotes, non-Latin scripts).
        // LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD stop libxml from silently wrapping
        // a partial-HTML template in an implied <html><body>/DOCTYPE it never had.
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $content,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $parseErrors = libxml_get_errors();
        libxml_clear_errors();

        if ($this->debug && count($parseErrors) > 0) {
            foreach ($parseErrors as $error) {
                error_log(sprintf('[View] %s in "%s" on line %d', trim($error->message), $this->filePath, $error->line));
            }
        }

        $xpath = new DOMXPath($dom);

        // Translate text nodes
        if ($this->translator !== null) {
            // Excludes <script>/<style> content - translating inline JS/CSS text would corrupt it
            $textNodes = $xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]');
            foreach ($textNodes as $node) {
                $node->nodeValue = $this->translator->translate($node->nodeValue);
            }

            // Translate placeholders in inputs and textareas
            $placeholders = $xpath->query('//input[@type="text"] | //textarea');
            foreach ($placeholders as $element) {
                if ($element->hasAttribute('placeholder')) {
                    $element->setAttribute(
                        'placeholder',
                        $this->translator->translate($element->getAttribute('placeholder'))
                    );
                }
            }
        }

        // Adjust URIs if route data or modifyResourcesURI is enabled
        if (!empty($this->routeSegments)) {
            // Always adjust <a> tags
            $this->adjustResourceUris($dom, 'href', ['a'], true);

            // Adjust resources only if enabled
            if ($this->modifyResourcesURI) {
                $this->adjustResourceUris($dom, 'src', ['img', 'script']);
                $this->adjustResourceUris($dom, 'href', ['link']);
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Adjusts resource URIs (e.g. src/href) to include route data
     *
     * @param DOMDocument $dom
     * @param string $attrName
     * @param array $tagNames
     * @param bool $respectIgnoreAttribute Whether to respect data-route="ignore" on <a> tags
     */
    private function adjustResourceUris(DOMDocument $dom, string $attrName, array $tagNames, bool $respectIgnoreAttribute = false): void
    {
        foreach ($tagNames as $tag) {
            foreach ($dom->getElementsByTagName($tag) as $element) {
                if (!$element->hasAttribute($attrName)) {
                    continue;
                }

                $uri = trim($element->getAttribute($attrName));

                if ($uri === '') {
                    continue;
                }

                // Skip fragment-only links ("#" or "#section") - not just the exact "#" case
                if ($uri[0] === '#') {
                    continue;
                }

                // Skip protocol-relative URLs ("//cdn.example.com/asset.js")
                if (str_starts_with($uri, '//')) {
                    continue;
                }

                // Skip any URI with an explicit scheme - http:, https:, mailto:, tel:, sms:,
                // data:, javascript:, etc. - rather than only recognising the two the
                // original check hardcoded, which let e.g. "tel:" fall through and get mangled.
                if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $uri)) {
                    continue;
                }

                // Respect data-route="ignore" if applicable
                if ($respectIgnoreAttribute && $element->hasAttribute('data-route') && $element->getAttribute('data-route') === 'ignore') {
                    continue;
                }

                // Adjust based on path type
                if ($uri[0] === '/') {
                    // Absolute path → prepend route data before it
                    $newUri = '/' . implode('/', $this->routeSegments) . $uri;
                } else {
                    // Relative path → make it relative to the route
                    $newUri = '/' . implode('/', $this->routeSegments) . '/' . ltrim($uri, '/');
                }

                $element->setAttribute($attrName, $newUri);
            }
        }
    }
}
