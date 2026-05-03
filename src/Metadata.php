<?php

declare(strict_types=1);

namespace Stolt\Ai\Skill;

final readonly class Metadata
{
    /**
     * @param array<string, mixed> $additionalFields
     */
    private function __construct(
        private string $name,
        private string $description,
        private array $additionalFields = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function fromArray(array $metadata): self
    {
        return new self(
            (string) $metadata['name'],
            (string) $metadata['description'],
            \array_diff_key($metadata, \array_flip(['name', 'description']))
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function has(string $key): bool
    {
        return $key === 'name'
            || $key === 'description'
            || \array_key_exists($key, $this->additionalFields);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'name' => $this->name,
            'description' => $this->description,
            default => $this->additionalFields[$key] ?? $default,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function additionalFields(): array
    {
        return $this->additionalFields;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        $tags = $this->get('tags', []);

        if (\is_array($tags) === false) {
            return [];
        }

        return \array_values($tags);
    }

    public function version(): ?string
    {
        $version = $this->get('version');

        if (\is_string($version) === false || $version === '') {
            return null;
        }

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            ...$this->additionalFields,
        ];
    }
}
