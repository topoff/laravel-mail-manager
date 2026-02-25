# laravel-mail-manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/topoff/laravel-mail-manager.svg?style=flat-square)](https://packagist.org/packages/topoff/laravel-mail-manager)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/topoff/laravel-mail-manager/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/topoff/laravel-mail-manager/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/topoff/laravel-mail-manager/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/topoff/laravel-mail-manager/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/topoff/laravel-mail-manager.svg?style=flat-square)](https://packagist.org/packages/topoff/laravel-mail-manager)

This package provides a comprehensive solution for managing mail templates and mail sending in Laravel applications.

## Installation

You can install the package via composer:

```bash
composer require topoff/laravel-mail-manager
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="mail-manager-config"
```

## Usage

Documentation coming soon.

### SES/SNS Auto Setup (SES v2 Configuration Sets)

The package can provision SES/SNS tracking resources via AWS API:

- SES Configuration Set
- SES Event Destination (SNS)
- SNS Topic policy for SES publish
- SNS HTTPS subscription to `mail-manager.tracking.sns`

Enable it in config:

```php
'ses_sns' => [
    'enabled' => true,
],
```

Then run:

```bash
php artisan mail-manager:ses-sns:setup
php artisan mail-manager:ses-sns:check
php artisan mail-manager:ses-sns:teardown --force
```

In Nova (`Message Types` resource), use action `Setup SES/SNS Tracking` to run setup and open the status/check page.

### Nova Integration

If Laravel Nova is installed, the package can auto-register a tracked messages resource with preview and resend actions.

Configuration keys:

- `mail-manager.tracking.nova.enabled`
- `mail-manager.tracking.nova.register_resource`
- `mail-manager.tracking.nova.resource`
- `mail-manager.tracking.nova.preview_route`

The preview action uses a temporary signed URL and the package route `mail-manager.tracking.nova.preview`.

## Development

### Code Quality Tools

This package uses several tools to maintain code quality:

#### Laravel Pint (Code Formatting)

Format code according to Laravel standards:

```bash
composer format
```

#### Rector (Automated Refactoring)

Preview potential code improvements:

```bash
composer rector-dry
```

Apply automated refactorings:

```bash
composer rector
```

#### PHPStan (Static Analysis)

Run static analysis:

```bash
composer analyse
```

#### Run All Quality Checks

```bash
composer lint
```

This runs both Pint and PHPStan.

### Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Andreas Berger](https://github.com/andreasberger83)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
