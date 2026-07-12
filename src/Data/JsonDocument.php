<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    /**
     * A JSON column with dot-path access instead of manual json_decode()/json_encode()
     * scattered everywhere it's touched.
     *
     * Usage:
     *   class Product extends Model { public JsonDocument $metadata; }
     *
     *   $product->metadata->get('dimensions.width');
     *   $product->metadata->set('dimensions.width', 12);
     *   $product->metadata->has('dimensions.height');
     *
     * Implements ISerializable, the same interface Date/Time/Duration/Color already
     * use - no new framework plumbing needed for this type to work as a model property.
     */
    class JsonDocument implements ISerializable, \JsonSerializable
    {
        private array $data = [];

        function __construct()
        {
        }

        /**
         * @param string $path dot-path, e.g. 'dimensions.width'
         * @param mixed $default
         * @return mixed
         */
        public function get(string $path, mixed $default = null): mixed
        {
            $current = $this->data;

            foreach(explode(".", $path) as $segment)
            {
                if(!is_array($current) || !array_key_exists($segment, $current))
                {
                    return $default;
                }
                $current = $current[$segment];
            }
            return $current;
        }

        /**
         * @param string $path
         * @param mixed $value
         * @return static
         */
        public function set(string $path, mixed $value): static
        {
            $segments = explode(".", $path);
            $current = &$this->data;

            foreach($segments as $i => $segment)
            {
                if($i === count($segments) - 1)
                {
                    $current[$segment] = $value;
                }
                else
                {
                    if(!isset($current[$segment]) || !is_array($current[$segment]))
                    {
                        $current[$segment] = [];
                    }
                    $current = &$current[$segment];
                }
            }
            return $this;
        }

        /**
         * @param string $path
         * @return bool
         */
        public function has(string $path): bool
        {
            $marker = new \stdClass();
            return $this->get($path, $marker) !== $marker;
        }

        /**
         * @param string $path
         * @return static
         */
        public function forget(string $path): static
        {
            $segments = explode(".", $path);
            $last = array_pop($segments);
            $current = &$this->data;

            foreach($segments as $segment)
            {
                if(!isset($current[$segment]) || !is_array($current[$segment]))
                {
                    return $this;
                }
                $current = &$current[$segment];
            }
            unset($current[$last]);
            return $this;
        }

        public function toArray(): array
        {
            return $this->data;
        }

        //#region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::JSON;
        }

        public function _serialize()
        {
            return json_encode($this->data);
        }

        public function _deserialize($data): void
        {
            $decoded = is_string($data) ? json_decode($data, true) : $data;
            $this->data = is_array($decoded) ? $decoded : [];
        }

        //#endregion

        /**
         * @return array the underlying decoded data, not the raw JSON string
         */
        public function jsonSerialize(): mixed
        {
            return $this->data;
        }
    }
