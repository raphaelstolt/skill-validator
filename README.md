# skill-validator

![Test Status](https://github.com/raphaelstolt/skill-validator/workflows/test/badge.svg)
![Lint Status](https://github.com/raphaelstolt/skill-validator/workflows/lint/badge.svg)
[![Version](http://img.shields.io/packagist/v/stolt/skill-validator.svg?style=flat)](https://packagist.org/packages/stolt/skill-validator)
![Downloads](https://img.shields.io/packagist/dt/stolt/skill-validator)
![PHP Version](https://img.shields.io/badge/php-8.2+-ff69b4.svg)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat)](https://github.com/php-pds/skeleton)
[![Lean dist package](https://img.shields.io/badge/lean-dist%20package-00ffb6.svg?style=flat)](https://github.com/raphaelstolt/lean-package-validator)

A PHP library to parse and validate `SKILL.md` files or raw `SKILL.md` content against the
[SKILL.md format specification](https://www.skillsdirectory.com/docs/skill-md-format).

## Installation and usage

```bash
composer require stolt/skill-validator
```

## Usage

The `SkillMd` class from the [stolt/skill-md](https://github.com/raphaelstolt/skill-md/) package is the primary abstraction for a validated skill. Every valid
result exposes a `SkillMd` instance, and the validator also accepts `SkillMd` instances directly as input.

The validator can validate existing `SKILL.md` files, raw `SKILL.md` content, or the mentioned `SkillMd` instances.

### Validating a `SKILL.md` file

```php
use Stolt\Ai\Skill\Validator;

$validator = new Validator();
$result = $validator->validateFile('/path/to/an-ai-skill/SKILL.md');
```

### Validating all `SKILL.md` files in a directory

```php
use Stolt\Ai\Skill\Validator;

$validator = new Validator();
$results = $validator->validateFromDirectory('/path/to/skills');

foreach ($results as $filePath => $result) {
    if ($result->isInvalid()) {
        echo sprintf('Invalid: %s', $filePath) . PHP_EOL;
        foreach ($result->errors() as $error) {
            echo '  - ' . $error . PHP_EOL;
        }
    }
}
```

The method returns an `array<string, ValidationResult>` keyed by absolute file path, covering all `SKILL.md` files found
recursively under the given directory.

### Validating raw `SKILL.md` content

```php
use Stolt\Ai\Skill\Validator;

$validator = new Validator();
$result = $validator->validateContent('raw-skill-content');
```

### Validating a `SkillMd` instance

```php
use Stolt\Ai\Skill\Validator;
use Stolt\Ai\SkillMd;

$skillMd = SkillMd::create(
    'code-review',
    'Review code changes and provide actionable feedback.',
    "# Code review\n\nReview the changed files and report issues.",
    ['tags' => ['php', 'review'], 'version' => '1.0.0']
);

$validator = new Validator();
$result = $validator->validateSkillMd($skillMd);
```

> [!TIP]
> The `validate` alias method accepts a file path, directory path, raw content, or a `SkillMd` instance and delegates
> to the appropriate method automatically.

### Accessing validation results and metadata

Validation returns a `Stolt\Ai\Skill\ValidationResult` object. When the `SKILL.md` content is valid, a `SkillMd`
instance is available directly. The parsed metadata is also accessible as a `Stolt\Ai\Skill\Metadata` object.

```php
use Stolt\Ai\Skill\Validator;

$validator = new Validator();
$result = $validator->validateContent('raw-skill-content');

if ($result->isInvalid()) {
    foreach ($result->errors() as $error) {
        echo $error . PHP_EOL;
    }
    // Raw metadata can still be inspected when parsing succeeded but validation failed.
    $rawMetadata = $result->rawMetadata();
    exit(1);
}

// Primary SkillMd abstraction — available on every valid result.
$skillMd = $result->skillMd();  // returns ?SkillMd (null when invalid)

// Or assert the SkillMd directly, which throws a LogicException when the result is invalid.
$skillMd = $result->toSkillMd();

// Use the SkillMd instance.
$name        = $skillMd->name();
$description = $skillMd->description();
$body        = $skillMd->body();
$tags        = $skillMd->tags();
$version     = $skillMd->version();

// Metadata object for field access with defaults.
$metadata    = $result->metadata();
$allowedTools = $metadata?->get('allowed-tools', []);
$model       = $metadata?->get('model');
$effort      = $metadata?->get('effort');

// Markdown instructions after the YAML frontmatter.
$instructions = $result->body();

// Array representation for logging, JSON APIs, or integrations.
$arrayResult = $result->toArray();

echo sprintf('Skill "%s" is valid: %s', $name, $description) . PHP_EOL;
```

### Round-tripping between content and `SkillMd`

Because `validateSkillMd()` accepts a `SkillMd` instance and `toSkillMd()` returns one, validation results and
`SkillMd` objects round-trip cleanly:

```php
use Stolt\Ai\Skill\Validator;

$validator = new Validator();

// Parse and validate raw content.
$result = $validator->validateContent($rawContent);

// Get the primary SkillMd abstraction.
$skillMd = $result->toSkillMd();

// Re-validate the SkillMd — e.g. after modifying it.
$revalidated = $validator->validateSkillMd($skillMd);
```

For an actual integration, the project [list-skills-command](https://github.com/raphaelstolt/list-skills-command) can also be consolidated.

## Validation rules

The validator checks that a `SKILL.md` document:

- starts with YAML frontmatter delimited by `---` lines,
- contains the __required__ `name` field,
- contains the __required__ `description` field,
- uses a non-empty, lowercase, hyphenated skill name,
- contains Markdown instructions after the frontmatter,
- only uses supported frontmatter fields,
- uses lists for list-like fields such as `tags`, `paths`, and `allowed-tools`,
- uses a boolean value for `disable-model-invocation`.

Supported frontmatter fields include:

- `name`
- `description`
- `when_to_use`
- `allowed-tools`
- `disable-model-invocation`
- `argument-hint`
- `arguments`
- `paths`
- `model`
- `effort`
- `metadata`
- `compatibility`
- `license`
- `author`
- `version`
- `tags`

### Running tests

```bash
composer test
```

### License

This library is licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

### Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more details.

### Inspiration

This library idea is inspired by the work on [agent-skills-validator](https://github.com/ronaldtebrake/agent-skills-validator)
by [ronaldtebrake](https://github.com/ronaldtebrake).

### Contributing

Please see [CONTRIBUTING.md](.github/CONTRIBUTING.md) for more details.
