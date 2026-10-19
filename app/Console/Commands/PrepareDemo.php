<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

class PrepareDemo extends Command
{
    protected $signature = 'demo:prepare';

    protected $description = 'Safely migrate and seed the synthetic FHIR demo';

    public function handle(): int
    {
        try {
            if (! config('app.key')) {
                $path = $this->laravel->environmentFilePath();
                $contents = is_file($path) ? file_get_contents($path) : '';
                if ($contents === false) {
                    throw new RuntimeException('Environment file could not be read.');
                }
                $environment = Dotenv::parse($contents);
                if (! empty($environment['APP_KEY'])) {
                    config(['app.key' => $environment['APP_KEY']]);
                } else {
                    $this->callSilently('key:generate', ['--force' => true]);
                }

                if (! config()->get('app.key')) {
                    throw new RuntimeException('Application key is unavailable.');
                }

                $this->laravel->forgetInstance('encrypter');
                Crypt::clearResolvedInstance('encrypter');
            }

            if ($this->callSilently('migrate', ['--force' => true]) !== self::SUCCESS
                || $this->callSilently('db:seed', ['--class' => DemoSeeder::class, '--force' => true]) !== self::SUCCESS) {
                throw new RuntimeException('Database preparation failed.');
            }
        } catch (Throwable) {
            $this->error('Demo preparation failed. Check APP_KEY, MySQL DB_* settings, and database permissions.');

            return self::FAILURE;
        }

        $this->info('Synthetic demo ready. Existing records preserved.');

        return self::SUCCESS;
    }
}
