# Tornado Engine Project Reference

This document provides essential style guidelines and library documentation for the Tornado Engine project. Claude can access full file contents directly, so this document focuses on patterns and conventions rather than complete code listings.

---

## Important: Run Commands in the Harness

**CRITICAL:** AI agents work inside the harness container (`docker compose exec harness bash`), never on the host. Run `php artisan`, `composer` and `npm` commands inside the harness, from the `web/` directory.

### Examples:
```bash
# Inside the harness (docker compose exec harness bash), in /workspace/web:
php artisan make:migration create_notes_table
php artisan migrate
php artisan make:controller NoteController
composer require package/name
npm run build
npm install
```

---

## Style Guide Overview

This is the style guide for creating new projects in Laravel 13. The goal is to maintain consistent organization and style across all projects. Each project is designed to be "Drop-in Ready" as a standalone lightweight component. Some projects DO have dependencies on other components, which will be mentioned in that project's documentation.

In short, it is designed to be used by others in their own Laravel 13 projects. Because of this, we do our best to keep the installation process simple.

### Vite Configuration

To keep the installation process simple, we use a single `{project}-css-loader.css` file and `{project}-modules-loader.js` to load CSS and JavaScript into our Vite config.

**Example Vite config snippet:**
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // {project} Styles
                'resources/css/{project}-css-loader.css',
                // {project} JavaScript
                'resources/js/{project}-modules-loader.js',
            ],
            // ...
        }),
    ],
});
```

They can then be included in the `<x-app-layout>` with:
```php
@vite(['resources/css/{project}-css-loader.css'])
@vite(['resources/js/{project}-modules-loader.js'])
```

---

## Style Guide

### Directories and Files

We create directories per project to keep projects organized. When creating a new directory or file, make sure that it is needed and does not meaningfully fit into an existing file or directory.

#### Laravel Application Components

Laravel Application Components are things like Controllers, Models, Migrations, etc., typically created and modified with `php artisan`.

When creating a new controller, model, migration, etc., always use the proper commands to create the directories and files as needed.

**Example (as opposed to creating the files manually):**
```bash
php artisan make:controller ExampleController
```
When creating new Laravel application components, make sure that it is needed and does not meaningfully fit into an existing component. Do not duplicate functions. Each project can create Laravel application components as needed. When creating a component, it should be named in a way that makes its purpose obvious.

#### Resources

##### CSS

First, create the project directory:
- `web/resources/css/{project}`
- `web/resources/css/{project}-css-loader.css`

Then create sub-directories (as needed):
- `core`
- `components`
- `layout`
- `mobile`

**Note:** We do not create a separate folder for dark mode!

###### Dark Mode

We use Tailwind's `dark:` class and create themes right next to their light mode counterpart:
Example:
```css
#info-panel {
  width: 350px;
  min-width: 250px;
  max-width: 800px;
  display: flex;
  flex-direction: column;
  padding: 20px;
  border-left: 1px solid #ccc;
  background: linear-gradient(180deg, #ffffff 0%, #f9f9f9 100%);
  overflow: hidden;
  box-shadow: -2px 0 10px rgba(0,0,0,0.1);
  transition: all 0.2s ease;
  color: #333;
}

/* Dark mode info panel */
@media (prefers-color-scheme: dark) {
  #info-panel {
    background: linear-gradient(180deg, #374151 0%, #1f2937 100%);
    border-left-color: #4b5563;
    color: #f3f4f6;
    box-shadow: -2px 0 10px rgba(0,0,0,0.3);
  }
}

.dark #info-panel {
  background: linear-gradient(180deg, #374151 0%, #1f2937 100%);
  border-left-color: #4b5563;
  color: #f3f4f6;
  box-shadow: -2px 0 10px rgba(0,0,0,0.3);
}
```

###### {project}-css-loader.css

Because we create our projects as standalone lightweight components, we use a single `{project}-css-loader.css` file to load into our Vite config. This way there is only a single update to the Vite config for the entire component to function as expected.

**Example: `example-css-loader.css`**
```css
/* resources/css/example-css-loader.css */

/* === Core Variables (Must be first) === */
/* Every project has a variables file which is used for all other css files */
@import './example/core/variables.css';

/* === Components (Specific UI elements) === */
/* @import './{project}/components/animations.css';
   @import './{project}/components/buttons.css';
   @import './{project}/components/context-menu.css';
   @import './{project}/components/forms.css';
   @import './{project}/components/modals.css';
   @import './{project}/components/grid.css';
   @import './{project}/components/tabs.css';
   @import './{project}/components/storage.css';
   @import './{project}/components/notifications.css'; */

/* === Layout Styles === */
/* @import './{project}/layout/sidebar.css'; */

/* === Mobile Responsive Styles === */
/* @import './{project}/mobile/responsive.css'; */
```

##### JavaScript

First, create the project directory:
- `web/resources/js/{project}`

Then create sub-directories (only as needed):
- `controllers`
- `core`
- `data`
- `interaction`
- `services`
- `utils`

When creating a new sub-directory in the js directory, make sure that it is needed and does not meaningfully fit into an existing sub-category. For example, the timeline project has `editor` and `rendering`, and the icon browser has `modal`, because they were needed and meaningfully different from the existing sub-categories.

###### {project}-modules-loader.js

**Example: `example-modules-loader.js`**
```javascript
// Import centralized icon system
import { ExampleManager } from './example/core/example-manager.js';

// Make globally available
window.ExampleManager = ExampleManager;

// Initialize global icon cache manager
window.globalExampleManager = ExampleManager.getInstance();

// Export everything for use in other modules
export {
    ExampleManager
};

console.log('Example modules loaded and made globally available');
```

##### Views

When creating views, we create a new directory for the project. Unless specified, we do not use partials. Every project will have an index blade as its base:
- `web/resources/views/{project}/index.blade.php`

Some projects will create multiple pages. Unless the scale grows quite large, the extra pages should simply be placed next to the `index.blade.php` file.

**Examples:**
- `web/resources/views/{project}/analytics.blade.php`
- `web/resources/views/{project}/export.blade.php`
- `web/resources/views/{project}/index.blade.php`
- `web/resources/views/{project}/manage.blade.php`

#### Routes

Each project will use its own `routes/{project}.php` file. We avoid using `web.php`.

**Example: `routes/icons.php`**
```php
<?php
use App\Http\Controllers\IconController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Icon Browser Routes
|--------------------------------------------------------------------------
|
| All routes related to icon browsing, uploading, and management
|
*/

Route::middleware('auth')->group(function () {

    // Icon browsing interface
    Route::prefix('icons')->name('icons.')->group(function () {
        Route::get('/browse', [IconController::class, 'browse'])->name('browse');
    });

    // Icon API endpoints (using session auth for CSRF protection)
    Route::prefix('api/icons')->name('api.icons.')->group(function () {
        Route::get('/', [IconController::class, 'index'])->name('index');
        Route::post('/', [IconController::class, 'store'])->name('store');
        Route::delete('/', [IconController::class, 'destroy'])->name('destroy');
        Route::post('/rename', [IconController::class, 'rename'])->name('rename');
        Route::get('/folders', [IconController::class, 'folders'])->name('folders');
    });

    // Serve user icons (protected route)
    Route::get('/user-icons/{userId}/{filename}', [IconController::class, 'show'])
        ->name('icons.show')
        ->where('userId', '[0-9]+')
        ->where('filename', '[^/]+\.svg');
});
```

Then include route files in `web/bootstrap/app.php`:

```php
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));

            Route::middleware('web')
                ->group(base_path('routes/{project}.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

##### Layouts

We are using the `x-app-layout` configured to use Tailwind's `dark:` tags for consistency across the website. In general, we try to match the style of the default Laravel 13 dashboard for basic components and simple pages.

**Example: `views/dashboard.blade.php`**
```php
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    {{ __("You're logged in!") }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

More complex pages may create new or custom layouts, but that is out of the scope of this style guide.

---

## Modules

Tools are built as modules, never as changes to Core. Each module is its own git repo and Composer package, named `tornado-engine/{name}` for official modules.

- Develop it in `web/modules/{name}` (cloned there; Core's git ignores the folder). Composer links it into `vendor/`.
- Its `composer.json` has a `tornado` section under `extra`:
  ```json
  "extra": {
      "tornado": {
          "name": "Notes",
          "core": "^0.1",
          "core-module": false,
          "provider": "TornadoEngine\\Notes\\NotesServiceProvider"
      },
      "branch-alias": { "dev-main": "0.1.x-dev" }
  }
  ```
  `core` is the range of Core versions it works with; Core only starts modules that fit.
- Its service provider loads the module's routes, views (`loadViewsFrom(..., '{name}')`) and migrations, and adds its menu items through Nav.
- Tests go in the module's `tests/` folder and extend Core's `Tests\TestCase`; they run with Core's tests.
- If something a module needs isn't possible without changing Core, add a general extension point to Core instead of a module-specific change.
- `core` is open-ended (`>=0.3`). When Core changes in a way that breaks a module, release a new version of that module with an updated `core` range, and make Core require that new version in its `composer.json`.
- Release by tagging a version (`v0.1.0`) and pushing; Packagist publishes it.

When `web/composer.local.json` exists, run Composer with `COMPOSER=composer.local.json`, for example `COMPOSER=composer.local.json composer update`.

---

## Phones and Screen Sizes

Every page must work on a phone as well as a desktop. Write the phone layout first, then add styles for larger screens.

### Breakpoints

Use Tailwind's breakpoints only. Styles without a prefix are for phones; each prefix applies from that width up.

| Prefix | From | Typical screen |
|---|---|---|
| (none) | 0px | Phone |
| `sm:` | 640px | Large phone sideways, small tablet |
| `md:` | 768px | Tablet |
| `lg:` | 1024px | Laptop and desktop |

In custom CSS (the `mobile` folder), use `min-width` media queries at these same widths.

### Layout

- Side padding is `px-4` on phones, then `sm:px-6 lg:px-8`, as in the default dashboard.
- The page never scrolls sideways. Wide content, such as tables and code, scrolls inside its own box with `overflow-x-auto`.
- Columns stack on phones: `grid grid-cols-1 md:grid-cols-2`.
- Cards are rounded from `sm:` up (`sm:rounded-lg`).
- Images and diagrams use `max-w-full h-auto`, so they shrink to fit.

### Touch

- Buttons and links people tap are at least 44px tall.
- Nothing works only on hover. Hover effects are extras.
- Form fields use at least 16px text (`text-base`), so phones don't zoom in when they're tapped.

### Navigation

Modules don't build their own menus. They add their items through the Nav module, which shows them in the top bar on larger screens and in the menu button on phones.

### Before a page is done

Check it at 375px wide (a phone) and 1280px wide (a desktop), in light and dark mode.

---