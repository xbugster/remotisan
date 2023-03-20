<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {09/03/2023}
 * Time: {12:29}
 */

namespace PayMe\Remotisan\Console\Commands;

use Illuminate\Console\Command;
use PayMe\Remotisan\Models\Execution;

class CompletionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "remotisan:complete {uuid}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Marks a remotisan job as completed.";

    /**
     * The command execution handler.
     *
     * @return void
     */
    public function handle()
    {
        $executionRecord = Execution::getByJobUuid($this->argument("uuid"));

        if ($executionRecord) {
            $executionRecord->markCompleted();
        }
    }
}