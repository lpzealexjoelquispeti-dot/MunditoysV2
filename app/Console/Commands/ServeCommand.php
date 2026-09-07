<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as LaravelServeCommand;

class ServeCommand extends LaravelServeCommand
{
    /** @return array<int, string> */
    protected function serverCommand(): array
    {
        $comando = parent::serverCommand();
        array_splice($comando, 1, 0, [
            '-d', 'upload_max_filesize=50M',
            '-d', 'post_max_size=64M',
        ]);

        return $comando;
    }
}
