<?php

namespace MODXDocs;

use ArrayAccess;
use Psr\Container\ContainerInterface;

class Container implements ArrayAccess, ContainerInterface
{
    private $entries = [];
    private $resolved = [];

    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    public function get($id)
    {
        if (!$this->has($id)) {
            throw new \RuntimeException(sprintf('Container entry "%s" was not found.', $id));
        }

        if (!array_key_exists($id, $this->resolved)) {
            $entry = $this->entries[$id];
            $this->resolved[$id] = is_callable($entry) ? $entry($this) : $entry;
        }

        return $this->resolved[$id];
    }

    public function has($id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        $this->entries[$offset] = $value;
        unset($this->resolved[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        unset($this->entries[$offset], $this->resolved[$offset]);
    }
}
