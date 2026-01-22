<?php

namespace Wixnit\App;

use DOMDocument;
use DOMXPath;
use Exception;
use Wixnit\Interfaces\ITranslator;
use Wixnit\Routing\Request;

class View
{
    private string $filePath = '';
    private ?ITranslator $translator = null;
    private array $routeData = [];
    private bool $modifyResourcesURI = false;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? '';
    }

    /**
     * @throws Exception
     */
    public function render(): void
    {
        $args = func_get_args();
        $payload = [];

        for($i = 0; $i < count($args); $i++)
        {
            if($args[$i] instanceof Request)
            {
                $payload['request'] = $args[$i];
            }
            if(is_array($args[$i]))
            {
                $payload['args'] =  $args[$i];
            }
        }
        $GLOBALS['WIXNIT_VIEW_PAYLOAD'] = $payload;
        

        $resolvedPath = $this->resolveFilePath();

        if (!file_exists($resolvedPath)) {
            throw new Exception(
                sprintf(
                    'The view "%s" was not found',
                    basename($this->filePath)
                )
            );
        }

        if ($this->translator === null && count($this->routeData) === 0) {
            require_once $resolvedPath;
        } else {
            ob_start();
            require_once $resolvedPath;
            echo $this->preProcessContent((string) ob_get_clean());
            ob_end_flush();
        }
    }

    public function withTranslator(ITranslator $translator): self
    {
        $this->translator = $translator;
        return $this;
    }

    public function withDataRoutes(array $data, bool $modifyResourcesURI = false): self
    {
        foreach ($data as $value) {
            $this->routeData[] = $value;
        }
        $this->modifyResourcesURI = $modifyResourcesURI;
        return $this;
    }

    public function setDataRoutes(array $data, bool $modifyResourcesURI = false): self
    {
        $this->routeData = array_values($data);
        $this->modifyResourcesURI = $modifyResourcesURI;
        return $this;
    }

    private function resolveFilePath(): string
    {
        if ($this->filePath === '') {
            return '';
        }

        if (file_exists($this->filePath)) {
            return $this->filePath;
        }

        if (file_exists($this->filePath . '.php')) {
            return $this->filePath . '.php';
        }

        if (file_exists($this->filePath . '.phtml')) {
            return $this->filePath . '.phtml';
        }

        return $this->filePath; // will fail later if still doesn't exist
    }

    private function preProcessContent(string $content): string
    {
        if (trim($content) === '') {
            return $content;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->preserveWhiteSpace = false;
        $dom->loadHTML($content);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Translate text nodes
        if ($this->translator !== null) {
            $textNodes = $xpath->query('//text()');
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
        if (!empty($this->routeData)) {
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

                // Skip external URLs and special schemes
                if ($uri === '' || preg_match('#^(https?:)?//#', $uri) || stripos($uri, 'mailto:') === 0) {
                    continue;
                }

                // Skip "#" only hrefs
                if ($uri === '#') {
                    continue;
                }

                // Respect data-route="ignore" if applicable
                if ($respectIgnoreAttribute && $element->hasAttribute('data-route') && $element->getAttribute('data-route') === 'ignore') {
                    continue;
                }

                // Adjust based on path type
                if ($uri[0] === '/') {
                    // Absolute path → prepend route data before it
                    $newUri = '/' . implode('/', $this->routeData) . $uri;
                } else {
                    // Relative path → make it relative to the route
                    $newUri = '/' . implode('/', $this->routeData) . '/' . ltrim($uri, '/');
                }

                $element->setAttribute($attrName, $newUri);
            }
        }
    }
}
