<?php
declare(strict_types=1);

namespace Database\Seeders;

use Quenza\Core\Database\Seeder;

final class OptionSeeder extends Seeder
{
    public function run(): void
    {
        $options = [
            'site_name' => [(string) $this->app->config('app.name', 'Quenza CMS'), 1],
            'site_url' => [(string) $this->app->config('app.url', 'http://localhost'), 1],
            'site_locale' => [(string) $this->app->config('app.locale', 'id'), 1],
            'site_timezone' => [(string) $this->app->config('app.timezone', 'Asia/Jakarta'), 1],
            'active_theme' => [(string) $this->app->config('app.active_theme', 'default'), 1],
        ];

        foreach ($options as $optionName => [$optionValue, $autoload]) {
            $this->db()->updateOrInsert(
                'options',
                ['option_name' => $optionName],
                [
                    'option_value' => $optionValue,
                    'autoload' => $autoload,
                ],
            );
        }
    }
}
