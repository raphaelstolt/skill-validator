<?php

declare(strict_types=1);

namespace Stolt\Ai\Skill;

final class Validator
{
    private const MAX_NAME_LENGTH = 64;

    private const MAX_DESCRIPTION_LENGTH = 1024;

    /**
     * @var list<string>
     */
    private const ALLOWED_FIELDS = [
        'allowed-tools',
        'argument-hint',
        'arguments',
        'author',
        'compatibility',
        'description',
        'disable-model-invocation',
        'effort',
        'license',
        'metadata',
        'model',
        'name',
        'paths',
        'tags',
        'version',
        'when_to_use',
    ];

    /**
     * @return array<string, ValidationResult>
     */
    public function validateFromDirectory(string $directory): array
    {
        if (\is_dir($directory) === false) {
            throw new \RuntimeException(
                \sprintf('Directory does not exist: %s', $directory)
            );
        }

        if (\is_readable($directory) === false) {
            throw new \RuntimeException(
                \sprintf('Directory is not readable: %s', $directory)
            );
        }

        $results = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() === false || $file->getFilename() !== 'SKILL.md') {
                continue;
            }

            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            $results[$filePath] = $this->validateFile($filePath);
        }

        return $results;
    }

    /**
     * @return ValidationResult|array<string, ValidationResult>
     */
    public function validate(string $input): ValidationResult|array
    {
        if (\is_dir($input)) {
            return $this->validateFromDirectory($input);
        }

        if (\is_file($input)) {
            return $this->validateFile($input);
        }

        return $this->validateContent($input);
    }

    public function validateFile(string $skillFile): ValidationResult
    {
        if (\is_file($skillFile) === false) {
            return ValidationResult::invalid([
                \sprintf('SKILL.md file does not exist: %s', $skillFile),
            ]);
        }

        if (\is_readable($skillFile) === false) {
            return ValidationResult::invalid([
                \sprintf('SKILL.md file is not readable: %s', $skillFile),
            ]);
        }

        $content = \file_get_contents($skillFile);

        if ($content === false) {
            return ValidationResult::invalid([
                \sprintf('Unable to read SKILL.md file: %s', $skillFile),
            ]);
        }

        $parentDirName = \basename(\dirname(\realpath($skillFile)));

        return $this->validateContent($content, $parentDirName);
    }

    public function validateContent(string $content, ?string $parentDirName = null): ValidationResult
    {
        $parsed = $this->parseContent($content);

        if ($parsed->hasErrors()) {
            return $parsed;
        }

        $rawMetadata = $parsed->rawMetadata();
        $errors = $this->validateMetadata($rawMetadata, $parentDirName);

        if (\trim($parsed->body()) === '') {
            $errors[] = 'SKILL.md must contain Markdown instructions after the frontmatter.';
        }

        if ($errors !== []) {
            return ValidationResult::invalid(
                $errors,
                $rawMetadata,
                $parsed->body()
            );
        }

        return ValidationResult::valid(
            Metadata::fromArray($rawMetadata),
            $parsed->body()
        );
    }

    public function parseContent(string $content): ValidationResult
    {
        $content = \str_replace(["\r\n", "\r"], "\n", $content);

        if (\preg_match('/\A---[ \t]*\n(?<frontmatter>.*?)\n---[ \t]*(?:\n(?<body>.*)|\z)/s', $content, $matches) !== 1) {
            return ValidationResult::invalid([
                'SKILL.md must start with YAML frontmatter delimited by --- lines.',
            ]);
        }

        $metadata = $this->parseYamlSubset($matches['frontmatter']);
        $body = $matches['body'] ?? '';

        return ValidationResult::invalid([], $metadata, $body);
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return list<string>
     */
    private function validateMetadata(array $metadata, ?string $parentDirName = null): array
    {
        $errors = [];

        $unexpectedFields = \array_values(\array_diff(\array_keys($metadata), self::ALLOWED_FIELDS));

        if ($unexpectedFields !== []) {
            \sort($unexpectedFields);

            $errors[] = \sprintf(
                'Unexpected frontmatter field(s): %s.',
                \implode(', ', $unexpectedFields)
            );
        }

        if (\array_key_exists('name', $metadata) === false) {
            $errors[] = 'Missing required frontmatter field: name.';
        } else {
            $errors = [
                ...$errors,
                ...$this->validateName($metadata['name'], $parentDirName),
            ];
        }

        if (\array_key_exists('description', $metadata) === false) {
            $errors[] = 'Missing required frontmatter field: description.';
        } else {
            $errors = [
                ...$errors,
                ...$this->validateDescription($metadata['description']),
            ];
        }

        if (isset($metadata['tags']) && \is_array($metadata['tags']) === false) {
            $errors[] = 'Frontmatter field tags must be a list.';
        }

        if (isset($metadata['paths']) && \is_array($metadata['paths']) === false) {
            $errors[] = 'Frontmatter field paths must be a list.';
        }

        if (isset($metadata['allowed-tools']) && \is_array($metadata['allowed-tools']) === false) {
            $errors[] = 'Frontmatter field allowed-tools must be a list.';
        }

        if (isset($metadata['arguments']) && \is_array($metadata['arguments']) === false) {
            $errors[] = 'Frontmatter field arguments must be a list or map.';
        }

        if (isset($metadata['disable-model-invocation']) && \is_bool($metadata['disable-model-invocation']) === false) {
            $errors[] = 'Frontmatter field disable-model-invocation must be a boolean.';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateName(mixed $name, ?string $parentDirName = null): array
    {
        if (\is_string($name) === false || \trim($name) === '') {
            return ['Frontmatter field name must be a non-empty string.'];
        }

        $name = \trim($name);
        $errors = [];

        if (\mb_strlen($name, 'UTF-8') > self::MAX_NAME_LENGTH) {
            $errors[] = \sprintf(
                'Frontmatter field name must not exceed %d characters.',
                self::MAX_NAME_LENGTH
            );
        }

        if (\preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $name) !== 1) {
            $errors[] = 'Frontmatter field name must use lowercase letters, numbers, and hyphens, and must not start or end with a hyphen.';
        }

        if (\str_contains($name, '--')) {
            $errors[] = 'Frontmatter field name must not contain consecutive hyphens.';
        }

        if ($parentDirName !== null && $errors === [] && $name !== $parentDirName) {
            $errors[] = \sprintf(
                'Frontmatter field name must match the parent directory name "%s".',
                $parentDirName
            );
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateDescription(mixed $description): array
    {
        if (\is_string($description) === false || \trim($description) === '') {
            return ['Frontmatter field description must be a non-empty string.'];
        }

        if (\mb_strlen($description, 'UTF-8') > self::MAX_DESCRIPTION_LENGTH) {
            return [
                \sprintf(
                    'Frontmatter field description must not exceed %d characters.',
                    self::MAX_DESCRIPTION_LENGTH
                ),
            ];
        }

        return [];
    }

    /**
     * Parses the subset of YAML commonly used in SKILL.md frontmatter:
     * scalars, booleans, inline lists, block lists, and one-level maps.
     *
     * @return array<string, mixed>
     */
    private function parseYamlSubset(string $yaml): array
    {
        $metadata = [];
        $currentKey = null;

        foreach (\explode("\n", $yaml) as $line) {
            $line = \rtrim($line);

            if (\trim($line) === '' || \str_starts_with(\ltrim($line), '#')) {
                continue;
            }

            if (\preg_match('/^([A-Za-z0-9_-]+):(?:[ \t]*(.*))?$/', $line, $matches) === 1) {
                $currentKey = $matches[1];
                $rawValue = $matches[2] ?? '';

                if ($rawValue === '') {
                    $metadata[$currentKey] = [];
                    continue;
                }

                $metadata[$currentKey] = $this->normalizeValue($rawValue);
                continue;
            }

            if ($currentKey === null) {
                continue;
            }

            if (\preg_match('/^[ \t]+-[ \t]+(.+)$/', $line, $matches) === 1) {
                if (\is_array($metadata[$currentKey]) === false) {
                    $metadata[$currentKey] = [];
                }

                $metadata[$currentKey][] = $this->normalizeValue($matches[1]);
                continue;
            }

            if (\preg_match('/^[ \t]+([A-Za-z0-9_-]+):(?:[ \t]*(.*))?$/', $line, $matches) === 1) {
                if (\is_array($metadata[$currentKey]) === false) {
                    $metadata[$currentKey] = [];
                }

                $metadata[$currentKey][$matches[1]] = $this->normalizeValue($matches[2] ?? '');
            }
        }

        return $metadata;
    }

    private function normalizeValue(string $value): mixed
    {
        $value = \trim($value);

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (\preg_match('/^\[(.*)]$/', $value, $matches) === 1) {
            return $this->parseInlineList($matches[1]);
        }

        if (
            (\str_starts_with($value, '"') && \str_ends_with($value, '"'))
            || (\str_starts_with($value, "'") && \str_ends_with($value, "'"))
        ) {
            return \substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function parseInlineList(string $value): array
    {
        return \array_values(\array_filter(
            \array_map(
                fn (string $item): string => \trim($item, " \t\n\r\0\x0B'\""),
                \explode(',', $value)
            ),
            fn (string $item): bool => $item !== ''
        ));
    }
}
