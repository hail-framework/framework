<?php

namespace Hail\Database\Console;

use Hail\Console\Command;

class Migration extends Command
{
    public function brief(): string
    {
        return 'Database migration manage';
    }
}
