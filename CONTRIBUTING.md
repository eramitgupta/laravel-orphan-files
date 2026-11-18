# Contributing

Bug reports, doc fixes, tests and features are all welcome. Since this package deletes files, one rule beats everything else: a file that is still referenced must never be cleaned.

The repository is [eramitgupta/laravel-orphan-files](https://github.com/eramitgupta/laravel-orphan-files).

## Ways to help

Found a bug? [Open an issue](https://github.com/eramitgupta/laravel-orphan-files/issues/new/choose) with the bug report form, and include the output of the command run without `--force` first. You can see what's already reported on the [Issues](https://github.com/eramitgupta/laravel-orphan-files/issues) page.

For a feature, use the feature request form and describe the problem before the solution you have in mind. Questions and ideas that aren't bugs go to [Discussions](https://github.com/eramitgupta/laravel-orphan-files/discussions). Open and merged changes are on the [Pull requests](https://github.com/eramitgupta/laravel-orphan-files/pulls) page.

Please don't report security problems in a public issue. That includes any bug that could delete a file that's still in use. See [SECURITY.md](SECURITY.md).

For anything else, email Er Amit Gupta at [info.eramitgupta@gmail.com](mailto:info.eramitgupta@gmail.com).

## Local Development

Requirements: PHP 8.2+ (8.3+ for Laravel 13), Composer, and the SQLite PDO extension for the test suite.

1. Fork and clone the repository. With the [GitHub CLI](https://cli.github.com/) this forks it to your account, clones your fork and adds this repository as the `upstream` remote:

   ```bash
   gh repo fork eramitgupta/laravel-orphan-files --clone
   cd laravel-orphan-files
   ```

   Without the CLI, click "Fork" on [the repository page](https://github.com/eramitgupta/laravel-orphan-files), clone your fork, and add the upstream remote yourself:

   ```bash
   git remote add upstream https://github.com/eramitgupta/laravel-orphan-files.git
   ```

2. Install the dependencies:

   ```bash
   composer install
   ```

   On PHP 8.4 this installs Laravel 13 with Pest 5. Laravel 12 can't use Pest 5 (it needs Symfony 8), so it gets Pest 3 or 4. To work against Laravel 12:

   ```bash
   composer require --dev "laravel/framework:12.*" "orchestra/testbench:^10.0" "pestphp/pest:^3.0|^4.0" --no-update
   composer update
   ```

   Don't commit the `composer.json` changes these commands make.

3. Create a branch from the latest `main`:

   ```bash
   git fetch upstream
   git checkout -b fix/trash-collision upstream/main
   ```

4. Commit your change, push the branch to your fork and open a pull request against `main`:

   ```bash
   git push -u origin fix/trash-collision
   gh pr create --repo eramitgupta/laravel-orphan-files
   ```

   Or open it from the [Pull requests](https://github.com/eramitgupta/laravel-orphan-files/pulls) page on GitHub. The pull request template asks for a summary and a short checklist.

## Running the Checks

| Command | What it does |
| --- | --- |
| `composer test` | Runs the Pint style check, then the full Pest suite. CI runs the same checks. |
| `composer test:unit` | Runs only the Pest suite, in random order. |
| `composer test:lint` | Checks code style with Pint without changing files. |
| `composer lint` | Fixes code style with Pint (Laravel preset). |

Run a single test file or test while you work:

```bash
vendor/bin/pest tests/Feature/CleanCommandTest.php
vendor/bin/pest --filter="keeps files modified within the grace period"
```

There's no PHPStan setup yet. Open an issue first if you want to add one.

The README GIF (`art/demo.gif`) is a [VHS](https://github.com/charmbracelet/vhs) recording of the real commands. `composer demo` records it again; you need `vhs`, `ttyd` and `ffmpeg` installed.

## Branches and Commits

- Branch from `main`, using a short prefix: `fix/…`, `feat/…`, `docs/…`, `test/…` or `chore/…`.
- Write commit messages in the imperative mood with a [Conventional Commits](https://www.conventionalcommits.org/) prefix, for example `fix: keep trashed copies when a path is trashed twice` or `feat: add markdown column type`.
- Squash fix-up commits before you ask for a review.

## Pull Request Rules

- One change per pull request. Link the issue it closes.
- New features and bug fixes need tests. A bug fix should come with a test that fails without it.
- If you touch scanning, reference collection, normalisation, filters, trash, restore or delete, add a test that shows referenced files are still kept.
- `composer test` must pass. CI runs it on PHP 8.2–8.4 with Laravel 12 and 13.
- Update the README when behaviour, options or commands change, and add an entry under "Unreleased" in [CHANGELOG.md](CHANGELOG.md).
- Avoid breaking changes to commands, config keys, facade methods and events. If one is unavoidable, mark it clearly in the pull request and the changelog.
- Don't add dependencies without discussing it in an issue first.

By contributing, you agree that your contributions are licensed under the [MIT License](LICENSE.md) and that you will follow the [Code of Conduct](CODE_OF_CONDUCT.md).
