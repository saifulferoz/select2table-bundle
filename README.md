# Select2TableBundle

[![CI](https://github.com/saifuleroz/select2table-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/saifuleroz/select2table-bundle/actions)
[![Latest Stable Version](https://poser.pugx.org/saifuleroz/select2table-bundle/v/stable)](https://packagist.org/packages/saifuleroz/select2table-bundle)
[![Total Downloads](https://poser.pugx.org/saifuleroz/select2table-bundle/downloads)](https://packagist.org/packages/saifuleroz/select2table-bundle)
[![License](https://poser.pugx.org/saifuleroz/select2table-bundle/license)](https://packagist.org/packages/saifuleroz/select2table-bundle)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-8892BF.svg)](https://php.net)
[![Symfony Version](https://img.shields.io/badge/Symfony-6.4%20%7C%207.4%20%7C%208.x-black.svg)](https://symfony.com)

A high-performance Symfony bundle that integrates [Select2](https://select2.org/) as an asynchronous, remote-AJAX autocomplete replacement for standard table/entity select fields in Symfony forms.

> **Inspiration:** Inspired by [`tetranz/select2entity-bundle`](https://github.com/tetranz/select2entity-bundle), optimized with direct Doctrine DBAL performance, typed properties, strict typing, modern assets, and full support for **Symfony 7.4+** and **Symfony 8.x** (PHP 8.2+).

---

## Key Features

- ⚡ **High Performance:** Queries large database tables effortlessly with lightweight Doctrine DBAL pagination and count queries.
- 🔄 **Single & Multi-Select:** Full support for `multiple: false` (single selection) and `multiple: true` (multi-selection).
- 📜 **Infinite Scrolling:** Server-side pagination with seamless incremental scrolling.
- 🔗 **Cascading / Dependent Dropdowns:** Reacts dynamically to changes in parent form fields (`req_params`).
- 🏷️ **Dynamic Tag Creation:** Allows users to create new tags on-the-fly (`allow_add`).
- 🎨 **HTML & Custom Templating:** Render rich option templates (images, avatars, badges, icons).
- 🛠️ **Modern Asset Integration:** Compatible with **Symfony UX / AssetMapper**, **Webpack Encore**, **Vite**, and traditional CDN/Script setups.
- 🛡️ **SQL Injection Protection:** Validates identifiers and safely binds parameters across DBAL 3 & DBAL 4.

---

## Requirements

- **PHP:** `^8.2 || ^8.3 || ^8.4 || ^8.5`
- **Symfony:** `^6.4 || ^7.4 || ^8.0`
- **Doctrine DBAL:** `^3.6 || ^4.0`
- **Twig:** `^3.0`
- **jQuery & Select2 4.x**

---

## Installation

### 1. Install via Composer

```bash
composer require saifuleroz/select2table-bundle
```

### 2. Enable the Bundle (if not using Symfony Flex)

If you are not using Symfony Flex, add the bundle to `config/bundles.php`:

```php
return [
    // ...
    SaifulFeroz\Select2TableBundle\SaifulFerozSelect2TableBundle::class => ['all' => true],
];
```

### 3. Register the Twig Form Theme

Add the bundle's form theme in `config/packages/twig.yaml`:

```yaml
twig:
    form_themes:
        - '@SaifulFerozSelect2Table/form/fields.html.twig'
```

---

## Asset Setup

### Option A: Traditional / CDN Setup

Ensure **jQuery** and **Select2** (CSS & JS) are loaded on your page, then include the bundle's JavaScript:

```html
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- jQuery & Select2 JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Select2Table Script -->
<script src="{{ asset('bundles/saifulferozselect2table/js/select2table.js') }}"></script>
```

Install the public bundle assets with:

```bash
php bin/console assets:install --symlink
```

### Option B: Symfony UX / AssetMapper (Stimulus)

Import the script in your `assets/app.js`:

```javascript
import 'select2/dist/css/select2.min.css';
import 'select2';
import './vendor/saifuleroz/select2table-bundle/select2table.js';
```

---

## Usage

### 1. Form Type Configuration

In your Symfony form class, use `Select2TableType::class`:

```php
namespace App\Form;

use SaifulFeroz\Select2TableBundle\Form\Type\Select2TableType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', Select2TableType::class, [
                'table_name'           => 'tbl_customers',
                'primary_key'          => 'id',
                'text_property'        => 'name',
                'property'             => ['name', 'email', 'phone'], // Searchable columns
                'remote_route'         => 'app_customer_autocomplete',
                'minimum_input_length' => 2,
                'page_limit'           => 10,
                'scroll'               => true,
                'allow_clear'          => true,
                'placeholder'          => 'Select a customer...',
            ]);
    }
}
```

### 2. Autocomplete Controller Endpoint

Create an autocomplete controller action and use the provided `AutocompleteService`:

```php
namespace App\Controller;

use App\Form\OrderType;
use SaifulFeroz\Select2TableBundle\Service\AutocompleteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AutocompleteController extends AbstractController
{
    #[Route('/autocomplete/customers', name: 'app_customer_autocomplete', methods: ['GET'])]
    public function customerAutocomplete(Request $request, AutocompleteService $autocompleteService): JsonResponse
    {
        $results = $autocompleteService->getAutocompleteResults($request, OrderType::class);

        return new JsonResponse($results);
    }
}
```

---

## Configuration Reference

You can customize global defaults in `config/packages/saifulferoz_select2_table.yaml`:

```yaml
saifulferoz_select2_table:
    minimum_input_length: 1       # Characters before triggering search
    page_limit: 10                # Number of records per page
    scroll: false                 # Enable infinite scrolling
    allow_clear: false            # Show clear ('x') button
    delay: 250                    # Debounce delay in milliseconds
    language: 'en'                # Select2 language code
    theme: 'default'              # Select2 theme (e.g. 'default', 'bootstrap-5')
    cache: true                   # Client-side AJAX cache
    cache_timeout: 60000          # Cache lifetime in ms (0 = indefinite)
    table_name: null              # Default table name
    text_property: null           # Default column for text label
    primary_key: 'id'             # Default primary key column
    width: null                   # CSS width (e.g. '100%', 'resolve')
    render_html: false            # Allow HTML rendering in results
    allow_add:
        enabled: false            # Enable new tag creation
        new_tag_text: ' (NEW)'    # Text appended to new tags
        new_tag_prefix: '__'      # Prefix added to submitted value
        tag_separators: '[",", " "]'
```

---

## Advanced Recipes

### 1. Cascading / Dependent Dropdowns (`req_params`)

When selecting a City that depends on the chosen Country, specify `req_params`:

```php
$builder
    ->add('country', Select2TableType::class, [
        'table_name'   => 'tbl_countries',
        'remote_route' => 'app_autocomplete',
        'property'     => 'name',
    ])
    ->add('city', Select2TableType::class, [
        'table_name'   => 'tbl_cities',
        'remote_route' => 'app_autocomplete',
        'property'     => 'name',
        'req_params'   => ['country_id' => 'parent.children[country]'],
        'callback'     => function (\Doctrine\DBAL\Query\QueryBuilder $qb, $request): void {
            if ($countryId = $request->get('country_id')) {
                $qb->andWhere('country_id = :country_id')
                   ->setParameter('country_id', $countryId);
            }
        },
    ]);
```

### 2. Multi-Column Search & Custom Filtering Callbacks

Search across multiple columns (e.g. `first_name`, `last_name`, `email`) and filter active rows:

```php
$builder->add('agent', Select2TableType::class, [
    'table_name'    => 'tbl_agents',
    'primary_key'   => 'id',
    'text_property' => 'full_name',
    'property'      => ['first_name', 'last_name', 'email'],
    'remote_route'  => 'app_agent_autocomplete',
    'callback'      => function (\Doctrine\DBAL\Query\QueryBuilder $qb, $request): void {
        $qb->andWhere('is_active = :active')
           ->setParameter('active', 1);
    },
]);
```

### 3. Rich HTML Results & Avatar Icons

Set `render_html: true` on your form field:

```php
$builder->add('member', Select2TableType::class, [
    'table_name'   => 'tbl_members',
    'remote_route' => 'app_member_autocomplete',
    'property'     => 'username',
    'render_html'  => true,
]);
```

In your controller, return an `html` property in the row array:

```php
// In custom repository or callback query:
$results = [
    'results' => [
        [
            'id' => 1,
            'text' => 'Jane Doe',
            'html' => '<img src="/avatars/jane.png" class="rounded-circle me-2" width="24" /> <strong>Jane Doe</strong> <span class="badge bg-primary">Admin</span>'
        ]
    ],
    'more' => false,
];
```

### 4. Embedded Collection Form Support

If using Symfony Form Collections with `data-prototype` or dynamic additions, `select2table.js` automatically detects newly added elements and initializes them without extra code.

---

## Backward Compatibility

For smooth upgrades from legacy versions:
- Legacy namespace `Feroz\Select2TableBundle\` classes and service aliases remain intact.
- Legacy form theme block `{% block feroz_select2table_widget %}` delegates automatically to `saifulferoz_select2table_widget`.
- Legacy configuration key `feroz_select2_table` is fully supported.

---

## Testing

Run the PHPUnit test suite:

```bash
composer test
```

---

## License

This bundle is open-sourced software licensed under the [MIT License](LICENSE).
