# skill-validator

PHP library for parsing and validating `SKILL.md` files against the [SKILL.md format specification](https://www.skillsdirectory.com/docs/skill-md-format).

## Project Info

- **Language:** PHP >= 8.2
- **Namespace:** `Stolt\Ai\Skill\`
- **Package:** `stolt/skill-validator`

## Common Commands

```bash
composer test                   # Run PHPUnit tests
composer test-agentic           # Run PHPUnit tests with agentic output
composer test-with-coverage     # Run tests with coverage report
composer cs-fix                 # Auto-fix coding style (PSR-2)
composer cs-lint                # Check coding style compliance
composer static-analyse         # Run PHPStan (level 5)
composer spell-check            # Check for spelling errors
composer validate-gitattributes # Validate .gitattributes
composer pre-commit-check       # Run all checks before committing
```

## Code Conventions

- **PSR-2** coding standard enforced via php-cs-fixer
- **Strict types** required: `declare(strict_types=1);` in every file
- **Final classes** by default
- **Readonly** properties and constructor property promotion preferred
- **Static factory methods** with private constructors for immutable objects
- **PHPStan level 5** — all types must be explicit
- Follow **Conventional Commits** for commit messages

## Architecture

- `src/Validator.php` — main validator; `validate()`, `validateFile()`, `validateContent()`
- `src/ValidationResult.php` — immutable result with `isValid()`, `errors()`, `metadata()`, `body()`
- `src/Metadata.php` — immutable metadata container

## Testing

- Framework: PHPUnit 11 with `#[Test]` attributes
- `DG\BypassFinals` is enabled in bootstrap to allow mocking final classes
- Base `TestCase` provides `setUpTemporaryDirectory()` and `removeDirectory()` helpers
- Tests live in `tests/` under namespace `Stolt\Ai\Skill\Tests`
